<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Utility\InitializeInterface;

interface RewriterInterface extends InitializeInterface
{
    /**
     * @param string $path
     * @param string|iterable<int|string, mixed> $query
     * @return \Psr\Http\Message\UriInterface
     */
    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface;

    /**
     * @return array<string>
     */
    public function getRoutePaths(): array;

    /**
     * @return \Psr\Http\Message\UriInterface
     */
    public function getRedirectUrl(): ?PsrUriInterface;
}
