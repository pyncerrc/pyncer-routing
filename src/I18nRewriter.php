<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\I18n\I18n;
use Pyncer\Routing\Rewriter;
use Pyncer\Routing\I18nRedirecor;

use function Pyncer\Http\build_url_query as pyncer_http_build_url_query;
use function Pyncer\Http\clean_path as pyncer_http_clean_path;
use function Pyncer\Http\parse_url_query as pyncer_http_parse_url_query;

class I18nRewriter extends Rewriter
{
    private string $localeQueryName = 'locale';
    private ?string $localeCode = null;
    private ?string $defaultLocaleCode;

    public function __construct(
        PsrServerRequestInterface $request,
        PsrUriInterface $baseUrl,
        protected I18n $i18n,
    ) {
        parent::__construct(
            $request,
            $baseUrl,
        );

        // Store
        $this->defaultLocaleCode = $i18n->getDefaultLocaleCode();
    }

    public function getLocaleQueryName(): string
    {
        return $this->localeQueryName;
    }
    public function setLocaleQueryName(string $value): static
    {
        $this->localeQueryName = $value;
        return $this;
    }

    public function getLocaleCode(): ?string
    {
        return $this->localeCode;
    }
    protected function setLocaleCode(?string $value): static
    {
        // Get normalized code
        $value = $this->getOptimizedLocaleCode($value);
        $this->localeCode = $this->cleanLocaleCode($value);
        return $this;
    }

    protected function initializePaths(): array
    {
        $paths = parent::initializePaths();

        if ($this->getEnableRewriting()) {
            if ($paths && $this->isValidLocaleCode($paths[0])) {
                $this->setLocaleCode($paths[0]);
                unset($paths[0]);
                $paths = array_values($paths);
            }
        } else {
            $localeCode = $this->request->getQueryParams();
            $localeCode = $localeCode[$this->getLocaleQueryName()] ?? '';
            if ($this->isValidLocaleCode($localeCode)) {
                $this->setLocaleCode($localeCode);
            }
        }

        // Update i18n's default locale
        if ($this->getLocaleCode() !== null) {
            $this->i18n->setDefaultLocaleCode($this->getLocaleCode());
        }

        return $paths;
    }

    private function isValidLocaleCode(string $localeCode): bool
    {
        if ($localeCode === '') {
            return false;
        }

        if (strtolower($localeCode) !== $localeCode) {
            return false;
        }

        // Only allow _ in locale code if - is not present
        if (str_contains($this->getAllowedPathCharacters(), '-') &&
            str_contains($localeCode, '_')
        ) {
            return false;
        }

        if (!$this->i18n->hasLocale($localeCode)) {
            return false;
        }

        // If locale can be optimized, than it's invalid
        $localeCode = $this->i18n->getLocale($localeCode)->getCode();
        $optimizedLocaleCode = $this->getOptimizedLocaleCode($localeCode);

        if ($localeCode !== $optimizedLocaleCode) {
            return false;
        }

        return true;
    }

    private function cleanLocaleCode(string $localeCode): string
    {
        $localeCode = strtolower($localeCode);

        if (str_contains($this->getAllowedPathCharacters(), '-')) {
            return str_replace('_', '-', $localeCode);
        }

        if (str_contains($this->getAllowedPathCharacters(), '_')) {
            return str_replace('-', '_', $localeCode);
        }

        return str_replace('_', '-', $localeCode);
    }

    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        $url = parent::getUrl($path, $query);

        if ($this->getLocaleCode() === null) {
            return $url;
        }

        if ($this->getEnableRewriting()) {
            $path = '/' . $this->getLocaleCode() . $url->getPath();

            $url = $url->withPath($path);

            return $url;
        }

        $query = $url->getQuery();
        $query = pyncer_http_parse_url_query($query);
        $query[$this->getLocaleQueryName()] = $this->getLocaleCode();

        $url = $url->withQuery(pyncer_http_build_url_query($query));

        return $url;
    }

    public function getLocaleUrl(
        ?string $localeCode,
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        if ($this->getRedirector() instanceof I18nRedirector) {
            // Disable redirector to manually process it on i18n redirector
            $redirector = $this->getRedirector();
            $this->setRedirector(null);

            $path = pyncer_http_clean_path($path);
            if ($path !== '') {
                $paths = $redirector->getLocaleUrlPaths(
                    $localeCode,
                    explode('/', substr($path, 1))
                );
                $path = '/' . implode('/', $paths);
            }

            $url = parent::getUrl($path, $query);
            $this->setRedirector($redirector);
        } else {
            $url = parent::getUrl($path, $query);
        }

        if ($localeCode === null) {
            return $url;
        }

        // Normalize code and compare with default
        $localeCode = $this->i18n->getLocale($localeCode)->getCode();

        if ($localeCode === $this->defaultLocaleCode) {
            return $url;
        }

        $localeCode = $this->getOptimizedLocaleCode($localeCode);
        $localeCode = $this->cleanLocaleCode($localeCode);

        if ($this->getEnableRewriting()) {
            $path = '/' . $localeCode . $url->getPath();

            $url = $url->withPath($path);

            return $url;
        }

        $query = $url->getQuery();
        $query = pyncer_http_parse_url_query($query);
        $query[$this->getLocaleQueryName()] = $localeCode;

        $url = $url->withQuery(pyncer_http_build_url_query($query));

        return $url;
    }

    private function getOptimizedLocaleCode(string $localeCode): string
    {
        $locale = $this->i18n->getLocale($localeCode);

        if ($locale->getShortCode() === $locale->getCode()) {
            return $locale->getCode();
        }

        foreach ($this->i18n->getLocaleCodes() as $value) {
            $value = $this->i18n->getLocale($value);

            if ($value->getCode() === $locale->getCode()) {
                continue;
            }

            if ($value->getShortCode() === $value->getCode()) {
                continue;
            }

            if ($value->getShortCode() == $locale->getShortCode()) {
                return $locale->getCode();
            }
        }

        return $locale->getShortCode();
    }
}
