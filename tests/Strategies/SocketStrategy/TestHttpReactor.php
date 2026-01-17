<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy;

use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use IfCastle\AmpPool\Worker\WorkerEntryPointInterface;
use IfCastle\AmpPool\Worker\WorkerInterface;
use Revolt\EventLoop;

final class TestHttpReactor implements WorkerEntryPointInterface
{
    public const string ADDRESS     = '127.0.0.1:9999';

    public static function getFile(): string
    {
        return \sys_get_temp_dir() . '/worker-pool-test-window.text';
    }

    public static function removeFile(): void
    {
        $file                       = self::getFile();

        if (\file_exists($file)) {
            \unlink($file);
        }

        if (\file_exists($file)) {
            throw new \RuntimeException('Could not remove file: ' . $file);
        }
    }

    private ?\WeakReference $worker = null;

    public function initialize(WorkerInterface $worker): void
    {
        $this->worker               = \WeakReference::create($worker);
    }

    public function run(): void
    {
        echo "TestHttpReactor::run() - START\n";

        $worker                     = $this->worker->get();

        if ($worker instanceof WorkerInterface === false) {
            throw new \RuntimeException('The worker is not available!');
        }

        echo "TestHttpReactor::run() - Worker obtained\n";

        echo "TestHttpReactor::run() - Getting worker group...\n";
        $workerGroup = $worker->getWorkerGroup();
        echo "TestHttpReactor::run() - Worker group obtained\n";

        echo "TestHttpReactor::run() - Getting socket strategy...\n";
        $socketStrategy = $workerGroup->getSocketStrategy();
        echo "TestHttpReactor::run() - Socket strategy: " . ($socketStrategy ? get_class($socketStrategy) : 'NULL') . "\n";

        if ($socketStrategy === null) {
            throw new \RuntimeException('The socket strategy is not available!');
        }

        echo "TestHttpReactor::run() - Getting server socket factory...\n";
        $socketFactory = $socketStrategy->getServerSocketFactory();
        echo "TestHttpReactor::run() - Server socket factory obtained\n";

        if ($socketFactory === null) {
            throw new \RuntimeException('The socket factory is not available!');
        }

        echo "TestHttpReactor::run() - Socket factory obtained\n";

        $clientFactory              = new SocketClientFactory($worker->getLogger());
        $httpServer                 = new SocketHttpServer($worker->getLogger(), $socketFactory, $clientFactory);

        echo "TestHttpReactor::run() - HTTP server created\n";

        // 2. Expose the server to the network
        $httpServer->expose(self::ADDRESS);

        echo "TestHttpReactor::run() - Server exposed on " . self::ADDRESS . "\n";

        // 3. Handle incoming connections and start the server
        $httpServer->start(
            new ClosureRequestHandler(static function () use ($worker): Response {

                echo "TestHttpReactor - Request received!\n";

                \file_put_contents(self::getFile(), self::class);

                EventLoop::delay(2, static function () use ($worker) {
                    $worker->stop();
                });

                return new Response(
                    HttpStatus::OK,
                    [
                        'content-type' => 'text/plain; charset=utf-8',
                    ],
                    self::class
                );
            }),
            new DefaultErrorHandler(),
        );

        echo "TestHttpReactor::run() - Server started, awaiting requests\n";

        // 4. Await termination of the worker
        $worker->awaitTermination();

        echo "TestHttpReactor::run() - Worker terminated\n";

        // 5. Stop the HTTP server
        $httpServer->stop();
    }
}
