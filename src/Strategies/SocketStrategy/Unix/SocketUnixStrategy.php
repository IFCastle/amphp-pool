<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\DeferredFuture;
use Amp\Parallel\Ipc;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocketFactory;
use Amp\TimeoutCancellation;
use IfCastle\AmpPool\EventWeakHandler;
use IfCastle\AmpPool\Strategies\SocketStrategy\SocketStrategyInterface;
use IfCastle\AmpPool\Strategies\SocketStrategy\Unix\Messages\InitiateSocketTransfer;
use IfCastle\AmpPool\Strategies\SocketStrategy\Unix\Messages\SocketTransferInfo;
use IfCastle\AmpPool\Strategies\WorkerStrategyAbstract;

use function Amp\Future\await;

final class SocketUnixStrategy extends WorkerStrategyAbstract implements SocketStrategyInterface
{
    private ServerSocketPipeFactory|null $socketPipeFactory = null;

    private string              $uri                = '';

    private string              $key                = '';

    private DeferredFuture|null $deferredFuture     = null;

    private EventWeakHandler|null $workerEventHandler = null;

    /** @var SocketProvider[] */
    private array $workerSocketProviders = [];

    public function __construct(private readonly int $ipcTimeout = 5) {}

    public function onStarted(): void
    {
        echo "[SocketUnixStrategy] onStarted() called\n";

        $workerPool                 = $this->getWorkerPool();

        if ($workerPool !== null) {
            echo "[SocketUnixStrategy] Running in PARENT/WATCHER mode\n";

            $self                   = \WeakReference::create($this);

            $workerPool->getWorkerEventEmitter()
                       ->addWorkerEventListener(static function (mixed $message, int $workerId = 0) use ($self) {
                           $self->get()?->handleMessage($message, $workerId);
                       });

            return;
        }

        echo "[SocketUnixStrategy] Running in WORKER mode\n";

        $worker                     = $this->getSelfWorker();

        if ($worker === null) {
            echo "[SocketUnixStrategy] ERROR: Worker is null!\n";
            return;
        }

        echo "[SocketUnixStrategy] Worker ID: " . $worker->getWorkerId() . "\n";

        $this->deferredFuture       = new DeferredFuture();

        $self                       = \WeakReference::create($this);
        $this->workerEventHandler   = new EventWeakHandler(
            $this,
            static function (mixed $message, int $workerId = 0) use ($self) {
                $self->get()?->handleMessage($message, $workerId);
            }
        );

        $worker->getWorkerEventEmitter()->addWorkerEventListener($this->workerEventHandler);

        echo "[SocketUnixStrategy] Sending InitiateSocketTransfer message...\n";
        $worker->sendMessageToWatcher(
            new InitiateSocketTransfer($worker->getWorkerId(), $worker->getWorkerGroup()->getWorkerGroupId())
        );
        echo "[SocketUnixStrategy] InitiateSocketTransfer sent\n";
    }

    public function onStopped(): void
    {
        if (false === $this->deferredFuture?->isComplete()) {
            $this->deferredFuture->complete();
            $this->deferredFuture   = null;
        }

        if ($this->workerEventHandler !== null) {
            $this->getSelfWorker()?->getWorkerEventEmitter()->removeWorkerEventListener($this->workerEventHandler);
            $this->workerEventHandler = null;
        }

        $this->socketPipeFactory    = null;

        $providers                  = $this->workerSocketProviders;
        $this->workerSocketProviders = [];

        foreach ($providers as $socketProvider) {
            $socketProvider->stop();
        }
    }

    /**
     * Calling this method pauses the Worker’s execution thread until the Watcher returns data for socket
     * initialization.
     *
     */
    public function getServerSocketFactory(): ServerSocketFactory|null
    {
        echo "[SocketUnixStrategy] getServerSocketFactory() called\n";

        if ($this->socketPipeFactory !== null) {
            echo "[SocketUnixStrategy] Returning cached socketPipeFactory\n";
            return $this->socketPipeFactory;
        }

        if ($this->deferredFuture === null) {
            throw new \Error('Wrong usage of the method getServerSocketFactory(). The deferredFuture undefined.');
        }

        echo "[SocketUnixStrategy] Waiting for SocketTransferInfo from parent (timeout: {$this->ipcTimeout}s)...\n";
        await([$this->deferredFuture->getFuture()], new TimeoutCancellation($this->ipcTimeout, 'Timeout waiting for socketPipeFactory from the parent process'));
        echo "[SocketUnixStrategy] Received SocketTransferInfo from parent\n";

        return $this->socketPipeFactory;
    }

    private function createIpcForTransferSocket(): ResourceSocket
    {
        $worker                     = $this->getSelfWorker();

        if ($worker === null) {
            throw new \Error('Wrong usage of the method getServerSocketFactory(). This method can be used only inside the worker!');
        }

        try {
            $socket                 = Ipc\connect($this->uri, $this->key, new TimeoutCancellation($this->ipcTimeout));

            if ($socket instanceof ResourceSocket) {
                return $socket;
            }

            throw new \RuntimeException('Type of socket is not ResourceSocket');

        } catch (\Throwable $exception) {
            throw new \RuntimeException('Could not connect to IPC socket', 0, $exception);
        }
    }

    private function handleMessage(mixed $message): void
    {
        if ($this->isSelfWorker()) {
            $this->workerHandler($message);
        } elseif ($this->getWorkerPool() !== null) {
            $this->watcherHandler($message);
        }
    }

    private function workerHandler(mixed $message): void
    {
        echo "[SocketUnixStrategy] workerHandler() received message: " . get_class($message) . "\n";

        if (false === $message instanceof SocketTransferInfo) {
            echo "[SocketUnixStrategy] Message is not SocketTransferInfo, ignoring\n";
            return;
        }

        echo "[SocketUnixStrategy] SocketTransferInfo received! uri={$message->uri}, key={$message->key}\n";

        if ($this->workerEventHandler !== null) {
            $this->getSelfWorker()?->getWorkerEventEmitter()->removeWorkerEventListener($this->workerEventHandler);
            $this->workerEventHandler = null;
        }

        if ($this->deferredFuture === null || $this->deferredFuture->isComplete()) {
            echo "[SocketUnixStrategy] DeferredFuture is null or already complete, ignoring\n";
            return;
        }

        $this->uri              = $message->uri;
        $this->key              = $message->key;

        echo "[SocketUnixStrategy] Creating ServerSocketPipeFactory...\n";
        $this->socketPipeFactory = new ServerSocketPipeFactory($this->createIpcForTransferSocket());
        echo "[SocketUnixStrategy] Completing DeferredFuture\n";
        $this->deferredFuture->complete();
        echo "[SocketUnixStrategy] DeferredFuture completed!\n";
    }

    private function watcherHandler(mixed $message): void
    {
        echo "[SocketUnixStrategy] watcherHandler() received message: " . get_class($message) . "\n";

        $workerPool             = $this->getWorkerPool();

        if ($workerPool === null) {
            echo "[SocketUnixStrategy] WorkerPool is null, ignoring\n";
            return;
        }

        if (false === $message instanceof InitiateSocketTransfer) {
            echo "[SocketUnixStrategy] Message is not InitiateSocketTransfer, ignoring\n";
            return;
        }

        echo "[SocketUnixStrategy] InitiateSocketTransfer received from worker {$message->workerId}, group {$message->groupId}\n";

        if ($message->groupId !== $this->getWorkerGroup()?->getWorkerGroupId()) {
            echo "[SocketUnixStrategy] Group ID mismatch, ignoring\n";
            return;
        }

        $workerContext              = $workerPool->findWorkerContext($message->workerId);

        if ($workerContext === null) {
            echo "[SocketUnixStrategy] WorkerContext not found for worker {$message->workerId}\n";
            return;
        }

        echo "[SocketUnixStrategy] WorkerContext found, preparing SocketTransferInfo\n";

        $workerCancellation         = $workerPool->findWorkerCancellation($message->workerId);

        try {

            $ipcHub                 = $workerPool->getIpcHub();
            $ipcKey                 = $ipcHub->generateKey();
            $socketPipeProvider     = new SocketProvider($message->workerId, $ipcHub, $ipcKey, $workerCancellation, $this->ipcTimeout);

            echo "[SocketUnixStrategy] Sending SocketTransferInfo to worker {$message->workerId}...\n";
            $workerContext->send(new SocketTransferInfo($ipcKey, $ipcHub->getUri()));
            echo "[SocketUnixStrategy] SocketTransferInfo sent successfully\n";

            if (\array_key_exists($message->workerId, $this->workerSocketProviders)) {
                $this->workerSocketProviders[$message->workerId]->stop();
            }

            $this->workerSocketProviders[$message->workerId] = $socketPipeProvider;

            echo "[SocketUnixStrategy] Starting SocketProvider...\n";
            $socketPipeProvider->start();
            echo "[SocketUnixStrategy] SocketProvider started\n";

        } catch (\Throwable $exception) {
            echo "[SocketUnixStrategy] ERROR in watcherHandler: " . $exception->getMessage() . "\n";
            if (\array_key_exists($message->workerId, $this->workerSocketProviders)) {
                $this->workerSocketProviders[$message->workerId]->stop();
                unset($this->workerSocketProviders[$message->workerId]);
            }

            $workerPool->getLogger()?->error('Could not send socket transfer info to worker', ['exception' => $exception]);
        }
    }

    #[\Override]
    public function __serialize(): array
    {
        return ['ipcTimeout' => $this->ipcTimeout];
    }

    public function __unserialize(array $data): void
    {
        $this->ipcTimeout           = $data['ipcTimeout'] ?? 5;
    }
}
