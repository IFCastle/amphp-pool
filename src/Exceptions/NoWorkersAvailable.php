<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

final class NoWorkersAvailable extends \RuntimeException
{
    /**
     * @param array<string> $groups
     */
    public function __construct(array $groups)
    {
        parent::__construct('No available workers in groups: ' . \implode(', ', $groups) . '.');
    }
}
