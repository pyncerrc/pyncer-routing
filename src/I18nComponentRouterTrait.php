<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Exception\InvalidArgumentException;
use Pyncer\Exception\UnexpectedValueException;
use Pyncer\I18n\LocaleInterface;
use Pyncer\Http\Message\Response;
use Pyncer\Routing\I18nRewriteMap;
use Pyncer\Routing\RewriterInterface;

use function array_key_exists;
use function array_search;
use function array_values;
use function count;
use function file_exists;
use function implode;
use function is_array;
use function Pyncer\Http\clean_path as pyncer_http_clean_path;
use function Pyncer\Http\merge_url_queries as pyncer_http_merge_url_queries;
use function str_replace;

use const DIRECTORY_SEPARATOR as DS;

trait I18nComponentRouterTrait
{
    private string $localeQueryName = 'locale';

    public function getLocaleQueryName(): string
    {
        return $this->localeQueryName;
    }
    public function setLocaleQueryName(string $value): static
    {
        if (isset($this->response)) {
            throw new UnexpectedValueException('Response already requested.');
        }

        $this->localeQueryName = $value;
        return $this;
    }

    protected function initializeRewriter(): RewriterInterface
    {
        $rewriter = new I18nRewriter(
            $this->request,
            $this->getBaseUrl(),
            $this->i18n,
        );

        $redirector = $this->initializeRedirector();
        $rewriter->setRedirector($redirector);

        $rewriter->setPathQueryName($this->getPathQueryName());
        $rewriter->setLocaleQueryName($this->getLocaleQueryName());
        $rewriter->setEnableRewriting($this->getEnableRewriting());
        $rewriter->setAllowedPathCharacters($this->getAllowedPathCharacters());

        $rewriter->initialize();

        return $rewriter;
    }

    protected function initializeRedirector(): ?RedirectorInterface
    {
        if (!$this->getEnableRedirects()) {
            return null;
        }

        $redirector = new I18nRedirector(
            $this->sourceMap,
            $this->i18n,
        );

        $redirector->initialize();

        return $redirector;
    }

    public function getLocale(): LocaleInterface
    {
        $localeCode = $this->rewriter->getLocaleCode();

        if ($localeCode !== null) {
            return $this->i18n->getLocale($localeCode);
        }

        return $this->i18n->getDefaultLocale();
    }

    protected function getLocaleCodeUrlPath(): string
    {
        if (!$this->getEnableRewriting()) {
            return '';
        }

        $localeCode = $this->rewriter->getLocaleCode();

        if ($localeCode === null) {
            return '';
        }

        return '/' . $localeCode;
    }
    protected function getLocaleCodeUrlQuery(): string
    {
        if ($this->getEnableRewriting()) {
            return '';
        }

        $localeCode = $this->rewriter->getLocaleCode();

        $query = [
            $this->getLocaleQueryName() => $localeCode
        ];

        return pyncer_http_build_url_query($query);
    }

    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getIndexUrl(): PsrUriInterface
    {
        $url = parent::getIndexUrl();

        $path = $this->getLocaleCodeUrlPath();

        if ($path !== '') {
            $url = $url->withPath($url->getPath() . $path);
        }

        $query = $this->getLocaleCodeUrlQuery();
        if ($query !== '') {
            $query = pyncer_http_merge_url_queries($url->getQuery(), $query);
            $url = $url->withQuery(pyncer_http_build_url_query($query));
        }

        return  $url;
    }

    public function getLocaleUrl(
        ?string $localeCode,
        string $path,
        string|iterable $query = []
    ): PsrUriInterface
    {
        return $this->rewriter->getLocaleUrl($localeCode, $path, $query);
    }

    public function getCurrentLocaleUrl(?string $localeCode): PsrUriInterface
    {
        $url = $this->getCurrentUrl();

        if ($this->getEnableRewriting()) {
            $indexPath = $this->getUrl()->getPath();

            $path = $url->getPath();
            $path = substr($path, strlen($indexPath));

            $query = $url->getQuery();
        } else {
            $path = $url->getPath();

            $query = $url->getQuery();
            $query = pyncer_http_parse_url_query($query);

            unset($query[$this->getLocaleQueryName()]);
        }

        $redirector = $this->rewriter->getRedirector();
        if ($redirector instanceof I18nRedirector) {
            $path = pyncer_http_clean_path($path);
            if ($path !== '') {
                $paths = $redirector->getLocaleRoutePaths(
                    null,
                    explode('/', substr($path, 1))
                );

                $path = '/' . implode('/', $paths);
            }
        }

        return $this->getLocaleUrl(
            $localeCode,
            $path,
            $query
        );
    }
}
