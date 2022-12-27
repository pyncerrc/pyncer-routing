<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Http\Server\RequestResponseInterface;

interface RouterInterface extends RequestResponseInterface
{
    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface;

    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getCurrentUrl(): PsrUriInterface;

    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getBaseUrl(): PsrUriInterface;

    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getIndexUrl(): PsrUriInterface;
}
