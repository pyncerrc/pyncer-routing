<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Routing\RedirectorInterface;
use Pyncer\Routing\RewriterInterface;
use Pyncer\Utility\InitializeTrait;

use function Pyncer\Http\build_uri_query as pyncer_http_build_uri_query;
use function Pyncer\Http\clean_path as pyncer_http_clean_path;
use function Pyncer\Http\decode_uri as pyncer_http_decode_uri;
use function Pyncer\Http\ltrim_path as pyncer_http_ltrim_path;
use function Pyncer\Http\parse_uri_query as pyncer_http_parse_uri_query;
use function trim;

class Rewriter implements RewriterInterface
{
    use InitializeTrait;

    private string $pathQueryName = 'path';
    private bool $enableRewriting = false;
    private ?RedirectorInterface $redirector = null;
    private string $allowedPathCharacters = '-';
    /**
     * @var array<string>
     */
    protected array $routePaths;
    protected ?PsrUriInterface $redirectUrl = null;

    public function __construct(
        protected PsrServerRequestInterface $request,
        protected PsrUriInterface $baseUrl,
    ) {}

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

    /**
     * @return array<string>
     */
    protected function initializePaths(): array
    {
        if ($this->getEnableRewriting()) {
            $path = $this->request->getUri()->getPath();

            $path = pyncer_http_decode_uri(pyncer_http_ltrim_path(
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

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): ?PsrUriInterface
    {
        return $this->redirectUrl;
    }

    /**
     * @inheritdoc
     */
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
                if (is_iterable($query)) {
                    $query = pyncer_http_build_uri_query($query);
                }
                $url = $url->withQuery($query);
            }

            return $url;
        }

        if (is_string($query)) {
            $query = pyncer_http_parse_uri_query($query);
        }

        $path = explode('/', substr($path, 1));
        $path = implode('__', $path);

        $query = array_merge([
            $query,
            [
                $this->getPathQueryName() => $path
            ]
        ]);

        $url = $url->withQuery(pyncer_http_build_uri_query($query));

        return $url;
    }

    /**
     * @inheritdoc
     */
    public function getRoutePaths(): array
    {
        return $this->routePaths;
    }
}
