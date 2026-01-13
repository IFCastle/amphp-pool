<?php

declare(strict_types=1);

namespace IfCastle\AmpPool\Strategies\SocketStrategy\Unix;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;

/**
 * @template-covariant T
 */
final readonly class TransferredResource
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param resource $resource Stream-socket resource.
     * @param T $data
     */
    public function __construct(
        private mixed $resource,
        private mixed $data,
    ) {}

    /**
     * @return resource Stream-socket resource.
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * @return T
     */
    public function getData(): mixed
    {
        return $this->data;
    }
}
