<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Utility\InitializeInterface;

interface RewriterInterface extends InitializeInterface
{
    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface;

    public function getRoutePaths(): array;

    public function getRedirectUrl(): ?PsrUriInterface;
}
