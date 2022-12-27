<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Psr\Log\LoggerAwareInterface as PsrLoggerAwareInterface;
use Psr\Log\LoggerAwareTrait as PsrLoggerAwareTrait;
use Pyncer\Exception\UnexpectedValueException;
use Pyncer\Http\Message\Uri;
use Pyncer\Routing\RouterInterface;

use array_keys;
use explode;
use function Pyncer\Http\clean_path as pyncer_http_clean_path;
use function Pyncer\Http\encode_url_path as pyncer_http_encode_url_path;

abstract class AbstractRouter implements
    RouterInterface,
    PsrLoggerAwareInterface
{
    use PsrLoggerAwareTrait;

    protected PsrServerRequestInterface $request;
    protected ?PsrResponseInterface $response = null;

    private string $baseUrlPath = '';

    public function __construct(PsrServerRequestInterface $request)
    {
        $url = $request->getUri();
        $path = pyncer_http_clean_path($url->getPath());
        if ($url->getPath() !== $path) {
            $url = $url->withPath($path);
            $request = $request->withUri($url);
        }
        $this->request = $request;
    }

    /**
     * @return \Psr\Http\Message\UriInterface
     */
    public function getCurrentUrl(): PsrUriInterface
    {
        return $this->request->getUri();
    }

    /**
     * Gets the url of the site's base url.
     *
     * @return \Psr\Http\Message\UriInterface
     */
    public function getBaseUrl(): PsrUriInterface
    {
        $url = $this->request->getUri()->getScheme() . '://' .
            $this->request->getUri()->getAuthority() .
            $this->getBaseUrlPath();

        return new Uri($url);
    }

    public function getBaseUrlPath(): string
    {
        return $this->baseUrlPath;
    }
    public function setBaseUrlPath(string $value): static
    {
        if (isset($this->response)) {
            throw new UnexpectedValueException('Response already requested.');
        }

        $this->baseUrlPath = pyncer_http_encode_url_path(
            pyncer_http_clean_path($value)
        );

        return $this;
    }

    /**
     * Gets the site's index URL which is the same as the base url pluse
     * any modifiers such as locale
     *
     * @return \Psr\Http\Message\UriInterface
     */
    public function getIndexUrl(): PsrUriInterface
    {
        return $this->getBaseUrl();
    }
}
