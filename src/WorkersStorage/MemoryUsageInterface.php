<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

interface MemoryUsageInterface
{
    public static function instanciate(WorkersStorageInterface $workersStorage, int $workersCount = 0, bool $isReadOnly = true): static;

    public function getStructureSize(): int;

    /**
     * @return array<int, int>
     */
    public function getWorkersMemoryUsageStat(): array;

    public function getWorkersMemoryUsage(int $workerId): int;

    public function update(): void;

    public function read(): void;

    /**
     * @return array<int, int>
     */
    public function getStats(): array;

    /**
     * @param array<int, int> $stats
     */
    public function setStats(array $stats): static;
}
