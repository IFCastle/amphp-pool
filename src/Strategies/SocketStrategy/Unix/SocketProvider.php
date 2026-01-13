<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Socket\SocketException;
use Revolt\EventLoop;

use const Amp\Process\IS_WINDOWS;

final class SocketProvider
{
    private ServerSocketPipeProvider|null $provider = null;

    private readonly Cancellation $cancellation;

    private readonly DeferredCancellation $deferredCancellation;

    public function __construct(
        private readonly int    $workerId,
        Cancellation            $cancellation
    ) {
        if (IS_WINDOWS) {
            throw new \Error(self::class . ' can\'t be used under Windows OS');
        }

        $this->provider             = new ServerSocketPipeProvider($this->workerId);
        $this->deferredCancellation = new DeferredCancellation();
        $this->cancellation         = new CompositeCancellation($cancellation, $this->deferredCancellation->getCancellation());
    }

    public function start(): void
    {
        $self                       = \WeakReference::create($this);

        EventLoop::queue(static function () use ($self) {

            $provider               = $self->get()?->provider;

            if ($provider === null) {
                return;
            }

            try {
                $provider->provideFor($self->get()->createSocketTransport(), $self->get()->cancellation);
            } catch (SocketException $exception) {

                $deferredCancellation = $self->get()?->deferredCancellation;

                // Stop the service
                if ($deferredCancellation instanceof DeferredCancellation && false === $deferredCancellation->isCancelled()) {
                    $deferredCancellation->cancel($exception);
                }

            } catch (CancelledException) {
                // Ignore
            }
        });
    }

    public function stop(): void
    {
        if (false === $this->deferredCancellation->isCancelled()) {
            $this->deferredCancellation->cancel();
        }

        $this->provider             = null;
    }
}
