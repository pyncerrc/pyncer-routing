<?php
namespace Pyncer\Routing;

use Pyncer\Exception\UnexpectedValueException;
use Pyncer\I18n\I18n;
use Pyncer\Source\SourceMap;

use const DIRECTORY_SEPARATOR as DS;

class I18nRedirector extends Redirector
{
    protected array $i18nRedirects= [];

    public function __construct(
        SourceMap $sourceMap,
        protected I18n $i18n
    ) {
        parent::__construct($sourceMap);
    }

    public function getRoutePaths(array $urlPaths): array
    {
        return $this->getLocaleRoutePaths(null, $urlPaths);
    }

    public function getUrlPaths(array $routePaths): array
    {
        return $this->getLocaleUrlPaths(null, $routePaths);
    }

    public function getLocaleRoutePaths(
        ?string $localeCode,
        array $urlPaths
    ): array
    {
        if ($localeCode === null) {
            $locale = $this->i18n->getDefaultLocale();
        } else {
            $locale = $this->i18n->getLocale($localeCode);
        }

        if ($locale !== null) {
            if ($locale->getShortCode() !== $locale->getCode()) {
                $urlPaths = $this->getRoutePathsFromRedirects(
                    $urlPaths,
                    [
                        ...$this->i18nRedirects[$locale->getShortCode()],
                        ...$this->i18nRedirects[$locale->getCode()],
                    ]
                );
            } else {
                $urlPaths = $this->getRoutePathsFromRedirects(
                    $urlPaths,
                    $this->i18nRedirects[$locale->getCode()],
                );
            }
        }

        return parent::getRoutePaths($urlPaths);
    }

    public function getLocaleUrlPaths(
        ?string $localeCode,
        array $routePaths
    ): array
    {
        $routePaths = parent::getUrlPaths($routePaths);

        if ($localeCode === null) {
            $locale = $this->i18n->getDefaultLocale();
        } else {
            $locale = $this->i18n->getLocale($localeCode);
        }

        if ($locale !== null) {
            if ($locale->getShortCode() !== $locale->getCode()) {
                $routePaths = $this->getUrlPathsFromRedirects(
                    $routePaths,
                    [
                        ...$this->i18nRedirects[$locale->getShortCode()],
                        ...$this->i18nRedirects[$locale->getCode()],
                    ]
                );
            } else {
                $routePaths = $this->getUrlPathsFromRedirects(
                    $routePaths,
                    $this->i18nRedirects[$locale->getCode()],
                );
            }
        }

        return $routePaths;
    }

    protected function initializeRedirects(): void
    {
        parent::initializeRedirects();

        foreach ($this->i18n->getLocales() as $locale) {
            $this->i18nRedirects[$locale->getCode()] = [];

            $localeCode = str_replace('-', '_', $locale->getCode());

            foreach ($this->getSourceDirs() as $dir) {
                $file = $dir . DS . 'redirects_' . $localeCode . '.php';

                if (!file_exists($file)) {
                    continue;
                }

                $redirects = include $file;

                if (!is_array($redirects)) {
                    throw new UnexpectedValueException(
                        'Redirects file is invalid.'
                    );
                }

                $this->i18nRedirects[$locale->getCode()] = [
                    ...$this->i18nRedirects,
                    ...$redirects
                ];
            }
        }
    }
}
