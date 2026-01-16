<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Exceptions;

class RemoteException extends \RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    protected array $remoteException = [];

    /**
     * @return array<string, mixed>|null
     */
    public function getRemoteException(): ?array
    {
        return $this->remoteException;
    }

    public function getRemoteMessage(): string
    {
        return $this->remoteException['message'] ?? $this->getMessage();
    }

    public function getRemoteCode(): int
    {
        return $this->remoteException['code'] ?? $this->getCode();
    }

    public function getRemoteClass(): string
    {
        return $this->remoteException['class'] ?? '';
    }

    public function getRemoteFile(): string
    {
        return $this->remoteException['file'] ?? $this->getFile();
    }

    public function getRemoteLine(): int
    {
        return $this->remoteException['line'] ?? $this->getLine();
    }

    /**
     * @return array<mixed>
     */
    public function getRemoteTrace(): array
    {
        return $this->remoteException['trace'] ?? $this->getTrace();
    }

    public function __serialize(): array
    {
        /**
         * AM PHP issue:
         * Since exceptions contain traces, they cannot be serialized directly because they include objects that prevent serialization.
         * That's why we use this class.
         */

        return static::_toArray($this);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function _toArray(?\Throwable $exception = null, int $recursion = 0): ?array
    {
        if (null === $exception) {
            return null;
        }

        if ($recursion >= 10) {
            return null;
        }

        // Ignore self and unwrap previous exceptions
        if ($exception instanceof self && $exception->getPrevious() !== null) {
            $exception              = $exception->getPrevious();
        }

        return [
            'message'               => $exception->getMessage(),
            'class'                 => $exception::class,
            'code'                  => $exception->getCode(),
            'file'                  => $exception->getFile(),
            'line'                  => $exception->getLine(),
            'trace'                 => self::_getTrace($exception),
            'previous'              => self::_toArray($exception->getPrevious(), $recursion + 1),
        ];
    }

    /**
     * @return array<mixed>
     */
    protected static function _getTrace(\Throwable $exception): array
    {
        $trace                      = $exception->getTrace();

        foreach ($trace as $key => $item) {

            if (empty($item['args'])) {
                continue;
            }

            foreach ($item['args'] as $k => $arg) {
                if (\is_string($arg)) {
                    $trace[$key]['args'][$k] = \strlen($arg) <= 64 ? \substr($arg, 0, 61) . '...' : $arg;
                } elseif (\is_scalar($arg) || $arg === null) {
                    $trace[$key]['args'][$k] = $arg;
                } else {
                    $trace[$key]['args'][$k] = \get_debug_type($arg);
                }
            }
        }

        return $trace;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->message = \array_key_exists('message', $data) ? 'Remote exception: ' . $data['message'] : 'Remote exception';

        if (\array_key_exists('code', $data)) {
            $this->code             = $data['code'];
        }

        if (\array_key_exists('file', $data)) {
            $this->file             = $data['file'];
        }

        if (\array_key_exists('line', $data)) {
            $this->line             = $data['line'];
        }

        $this->remoteException      = $data;
    }
}
