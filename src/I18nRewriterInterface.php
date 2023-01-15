<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Routing\RewriterInterface;

interface I18nRewriterInterface extends RewriterInterface
{
    public function getLocaleCode(): ?string;

    /**
     * @param null|string $localeCode
     * @param string $path
     * @param string|iterable<int|string, mixed> $query
     * @return \Psr\Http\Message\UriInterface
     */
    public function getLocaleUrl(
        ?string $localeCode,
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface;
}
