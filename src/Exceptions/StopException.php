<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

final class StopException extends \RuntimeException
{
    public function __construct(
        string     $message = 'The operation was stopped',
        int        $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
