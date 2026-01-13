<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\WorkersStorage;

use PHPUnit\Framework\TestCase;

class WorkersStorageTest extends TestCase
{
    public function testWriteRead(): void
    {
        $workerStorage              = WorkersStorage::instanciate(10);
        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();
        $workerStorage->getApplicationState()->update();

        $workerState2               = $workerStorage->getWorkerState(2);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testOnlyRead(): void
    {
        $workerStorage              = WorkersStorage::instanciate(10);
        $workerStorageReadOnly      = WorkersStorage::instanciate();

        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();
        $workerStorage->getApplicationState()->update();

        $workerState2               = $workerStorageReadOnly->getWorkerState(2);
        $workerState2->read();

        $this->assertEquals($workerState, $workerState2);
    }

    public function testReview(): void
    {
        $workerStorage              = WorkersStorage::instanciate(10);
        $workerStorageReadOnly      = WorkersStorage::instanciate();

        $workerState                = $workerStorage->getWorkerState(2);
        $this->fillWorkerState($workerState);
        $workerState->update();
        $workerStorage->getApplicationState()->update();

        $workerState                = $workerStorage->reviewWorkerState(2);

        $workerState2               = $workerStorageReadOnly->reviewWorkerState(2);
        $this->assertEquals($workerState, $workerState2);
    }

    public function testForeachWorkers(): void
    {
        $workerStorage              = WorkersStorage::instanciate(10);
        $workerStorageReadOnly      = WorkersStorage::instanciate();

        $workerStorage->getApplicationState()->update();

        $workerStates               = [];

        for ($i = 1; $i <= 10; $i++) {
            $workerState            = $workerStorage->getWorkerState($i);
            $this->fillWorkerState($workerState);
            $workerState->update();

            $workerStates[$i - 1]     = $workerStorage->reviewWorkerState($i);
        }

        $workerStatesReadOnly       = [];

        foreach ($workerStorageReadOnly->foreachWorkers() as $workerState) {
            $workerStatesReadOnly[] = $workerState;
        }

        $this->assertEquals($workerStates, $workerStatesReadOnly);
    }

    public function testWriteException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This instance WorkersStorage is read-only');

        $workerStorageReadOnly      = WorkersStorage::instanciate();

        $workerState2               = $workerStorageReadOnly->getWorkerState(2);
        $workerState2->update();
    }

    public function testWrongWorkerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid worker id provided');

        $workerStorage              = WorkersStorage::instanciate(10);
        $workerStorage->getWorkerState(0);
    }

    public function testWorkerIdOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Worker id is out of range');

        $workerStorage              = WorkersStorage::instanciate(10);
        $workerStorage->getWorkerState(11);
    }

    private function fillWorkerState(WorkerState $workerState): void
    {
        $workerState->groupId        = \random_int(1, 1000000);
        $workerState->shouldBeStarted = \random_int(0, 1) === 1;
        $workerState->isReady        = \random_int(0, 1) === 1;
        $workerState->totalReloaded  = \random_int(1, 1000000);
        $workerState->weight         = \random_int(1, 1000000);
        $workerState->firstStartedAt = \random_int(1, 1000000);
        $workerState->startedAt      = \random_int(1, 1000000);
        $workerState->finishedAt     = \random_int(1, 1000000);
        $workerState->updatedAt      = \random_int(1, 1000000);
        $workerState->phpMemoryUsage = \random_int(1, 1000000);
        $workerState->phpMemoryPeakUsage = \random_int(1, 1000000);
        $workerState->connectionsAccepted = \random_int(1, 1000000);
        $workerState->connectionsProcessed = \random_int(1, 1000000);
        $workerState->connectionsErrors = \random_int(1, 1000000);
        $workerState->connectionsRejected = \random_int(1, 1000000);
        $workerState->connectionsProcessing = \random_int(1, 1000000);
        $workerState->jobAccepted = \random_int(1, 1000000);
        $workerState->jobProcessed = \random_int(1, 1000000);
        $workerState->jobProcessing = \random_int(1, 1000000);
        $workerState->jobErrors = \random_int(1, 1000000);
        $workerState->jobRejected = \random_int(1, 1000000);
    }
}
