<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Routing\RedirectorInterface;
use Pyncer\Routing\RewriterInterface;
use Pyncer\Utility\InitializeTrait;

use function Pyncer\Http\build_url_query as pyncer_http_build_url_query;
use function Pyncer\Http\clean_path as pyncer_http_clean_path;
use function Pyncer\Http\decode_url as pyncer_http_decode_url;
use function Pyncer\Http\ltrim_path as pyncer_http_ltrim_path;
use function Pyncer\Http\build_url_query as pyncer_http_parse_url_query;
use function trim;

class Rewriter implements RewriterInterface
{
    use InitializeTrait;

    private string $pathQueryName = 'path';
    private bool $enableRewriting = false;
    private ?RedirectorInterface $redirector = null;
    private string $allowedPathCharacters = '-';
    protected array $routePaths;
    protected ?PsrUriInterface $redirectUrl = null;

    public function __construct(
        protected PsrServerRequestInterface $request,
        protected PsrUriInterface $baseUrl,
    ) {}

    /**
     * Gets a set of routing paths used to handle specialized pathing situations.
     */
    public function getRoutingPaths(): SetInterface
    {
        return $this->routingPaths;
    }

    public function getEnableRewriting(): bool
    {
        return $this->enableRewriting;
    }
    public function setEnableRewriting(bool $value): static
    {
        $this->enableRewriting = $value;
        return $this;
    }

    public function getRedirector(): ?RedirectorInterface
    {
        return $this->redirector;
    }
    public function setRedirector(?RedirectorInterface $value): static
    {
        $this->redirector = $value;
        return $this;
    }

    public function getPathQueryName(): string
    {
        return $this->pathQueryName;
    }
    public function setPathQueryName(string $value): static
    {
        $this->pathQueryName = $value;
        return $this;
    }

    public function getAllowedPathCharacters(): string
    {
        return $this->allowedPathCharacters;
    }
    public function setAllowedPathCharacters(string $value): static
    {
        $this->allowedPathCharacters = $value;
        return $this;
    }

    public function initialize(): static
    {
        $this->setInitialized(true);

        $paths = $this->initializePaths();

        $redirector = $this->getRedirector();
        if ($redirector !== null) {
            $redirectPaths = $redirector->getUrlPaths($paths);
            if ($redirectPaths !== $paths) {
                $path = '/' . implode('/', $redirectPaths);
                $query = $this->request->getQueryParams();

                $this->redirectUrl = $this->getUrl($path, $query);

                $paths = $redirectPaths;
            }

            $paths = $redirector->getRoutePaths($paths);
        }

        $this->routePaths = $paths;
        return $this;
    }

    protected function initializePaths(): array
    {
        if ($this->getEnableRewriting()) {
            $path = $this->request->getUri()->getPath();

            $path = pyncer_http_decode_url(pyncer_http_ltrim_path(
                $path,
                $this->baseUrl->getPath()
            ));

            $path = trim($path, '/');

            if ($path !== '') {
                $paths = explode('/', $path);
            } else {
                $paths = [];
            }
        } else {
            $paths = $this->request->getQueryParams();
            $paths = $paths[$this->getPathQueryName()] ?? '';
            if ($paths === '') {
                $paths = [];
            } else {
                $paths = explode('__', $paths);
            }
        }

        foreach ($paths as $key => $value) {
            if ($this->isValidPath($value)) {
                $paths[$key] = $value;
            } else {
                $paths[$key] = '@void';
            }
        }

        return $paths;
    }

    /**
     * Ensures a single path value does not contain malicious characters.
     *
     * @param string $path
     */
    private function isValidPath(string $path): bool
    {
        $cleanPath = trim($path);

        $characters = $this->getAllowedPathCharacters();
        if ($characters !== '') {
            $pattern = '/[^\p{N}\p{L}' . preg_quote($characters, '/') . ']$/';
        } else {
            $pattern = '/[^\p{N}\p{L}]$/';
        }
        $cleanPath = preg_replace($pattern, '', $cleanPath);

        return ($cleanPath === $path);
    }

    public function getRedirectUrl(): ?PsrUriInterface
    {
        return $this->redirectUrl;
    }

    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        $url = $this->baseUrl;

        $path = pyncer_http_clean_path($path);

        if ($path !== '' && $this->getRedirector() !== null) {
            $paths = $this->getRedirector()->getUrlPaths(explode('/', substr($path, 1)));
            $path = '/' . implode('/', $paths);
        }

        if ($this->getEnableRewriting()) {
            if ($path !== '') {
                $url = $url->withPath($url->getPath() . $path);
            }

            if ($query) {
                if (is_array($query)) {
                    $query = pyncer_http_build_url_query($query);
                }
                $url = $url->withQuery($query);
            }

            return $url;
        }

        if (is_string($query)) {
            $query = pyncer_http_parse_url_query($query);
        }

        $query = array_merge([
            $query,
            [
                $this->getPathQueryName() => implode('__', trim($path, '/'))
            ]
        ]);

        $url = $url->withQuery(pyncer_http_build_url_query($query));

        return $url;
    }

    public function getRoutePaths(): array
    {
        return $this->routePaths;
    }
}
