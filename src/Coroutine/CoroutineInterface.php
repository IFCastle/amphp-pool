<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Coroutine;

use Amp\Future;
use Revolt\EventLoop\Suspension;

interface CoroutineInterface
{
    public function execute(): mixed;

    public function resolve(mixed $data = null): void;

    public function fail(\Throwable $exception): void;

    /**
     * @return Suspension<mixed>|null
     */
    public function getSuspension(): ?Suspension;

    /**
     * @param Suspension<mixed> $suspension
     */
    public function defineSuspension(Suspension $suspension): void;

    /**
     * @param Suspension<mixed> $schedulerSuspension
     */
    public function defineSchedulerSuspension(Suspension $schedulerSuspension): void;

    /**
     * @return Future<mixed>
     */
    public function getFuture(): Future;

    public function getPriority(): int;
}
