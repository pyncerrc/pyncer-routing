<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\I18n\LocaleInterface;
use Pyncer\Routing\RouterInterface;

interface I18nRouterInterface extends RouterInterface
{
    public function getLocale(): LocaleInterface;

    public function getLocaleUrl(
        ?string $localeCode,
        string $path,
        string|iterable $query = []
    ): PsrUriInterface;

    public function getCurrentLocaleUrl(?string $localeCode): PsrUriInterface;
}
