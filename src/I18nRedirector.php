<?php
namespace Pyncer\Routing;

use Pyncer\Exception\UnexpectedValueException;
use Pyncer\I18n\I18n;
use Pyncer\Source\SourceMap;

use const DIRECTORY_SEPARATOR as DS;

class I18nRedirector extends Redirector
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $i18nRedirects= [];

    public function __construct(
        SourceMap $sourceMap,
        protected I18n $i18n
    ) {
        parent::__construct($sourceMap);
    }

    /**
     * @inheritdoc
     */
    public function getRoutePaths(array $urlPaths): array
    {
        return $this->getLocaleRoutePaths(null, $urlPaths);
    }

    /**
     * @inheritdoc
     */
    public function getUrlPaths(array $routePaths): array
    {
        return $this->getLocaleUrlPaths(null, $routePaths);
    }

    /**
     * @param null|string $localeCode,
     * @param array<string> $urlPaths
     */
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

    /**
     * @param null|string $localeCode,
     * @param array<string> $routePaths
     */
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

                /**
                 * @var array<string, string>
                 */
                $redirects = include $file;

                if (!is_array($redirects)) {
                    throw new UnexpectedValueException(
                        'Redirects file is invalid.'
                    );
                }

                $this->i18nRedirects[$locale->getCode()] = [
                    ...$this->i18nRedirects[$locale->getCode()],
                    ...$redirects
                ];
            }
        }
    }
}
