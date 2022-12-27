<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Component\ComponentDecoratorAwareInterface;
use Pyncer\Component\ComponentDecoratorAwareTrait;
use Pyncer\Component\ComponentInterface;
use Pyncer\Exception\InvalidArgumentException;
use Pyncer\Exception\UnexpectedValueException;
use Pyncer\Http\Message\Response;
use Pyncer\Http\Message\JsonResponse;
use Pyncer\Http\Message\Status;
use Pyncer\Http\Server\RequestHandlerInterface;
use Pyncer\Iterable\Set;
use Pyncer\Iterable\SetInterface;
use Pyncer\Routing\AbstractRouter;
use Pyncer\Routing\ComponentRouterInterface;
use Pyncer\Routing\RedirectorInterface;
use Pyncer\Routing\RewriterInterface;
use Pyncer\Routing\Path\NullRoutingPath;
use Pyncer\Source\SourceMap;
use Pyncer\Utility\InitializeInterface;
use Pyncer\Utility\InitializeTrait;

use function array_reverse;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function implode;
use function is_string;
use function preg_quote;
use function preg_replace;
use function Pyncer\IO\is_valid_path as pyncer_io_is_valid_path;
use function Pyncer\IO\clean_path as pyncer_io_clean_path;
use function Pyncer\Http\encode_url_path as pyncer_http_encode_url_path;
use function Pyncer\String\ltrim_string as pyncer_ltrim_string;
use function Pyncer\String\to_lower as pyncer_str_to_lower;
use function strval;

use const DIRECTORY_SEPARATOR as DS;

abstract class AbstractComponentRouter extends AbstractRouter implements
    ComponentDecoratorAwareInterface,
    ComponentRouterInterface,
    InitializeInterface
{
    use ComponentDecoratorAwareTrait;
    use InitializeTrait;

    protected SourceMap $sourceMap;
    protected Set $routingPaths;

    private bool $enableRewriting = false;

    private bool $enableRedirects = false;

    /**
     * Query param name of for the component path when rewriter is disabld
     */
    private string $pathQueryName = 'path';

    /**
     * The path directory path of http status components
     */
    private string $httpStatusRouteDirPath = DS . '@http-status';

    /**
     * List of characters to allow in paths outside of alphanumeric ones
     */
    private string $allowedPathCharacters = '-';

    /**
     * File of found component
     */
    protected ?string $routeFile = null;

    /**
     * Array of URL paths from source dir up to matched component
     */
    protected ?array $routePaths = null;

    /**
     * Array of URL paths to be handled by the component
     */
    protected ?array $componentPaths = null;

    /**
     * Array of query values generated from the current URL's path to be sent
     * in the components request.
     */
    protected ?array $componentQuery = null;

    /**
     * Whether or not the current URL matches a component
     */
    protected bool $componentFound = false;

    protected RewriterInterface $rewriter;

    public function __construct(
        SourceMap $sourceMap,
        PsrServerRequestInterface $request
    ) {
        parent::__construct($request);

        $this->sourceMap = $sourceMap;

        $this->routingPaths = new Set();
        $this->routingPaths->add(new NullRoutingPath());
    }

    protected function getSourceDirs(): array
    {
        $sources = array_reverse($this->sourceMap->getKeys());

        $sourceDirs = [];

        foreach ($sources as $source) {
            $dirs = $this->sourceMap->get($source);
            $dirs = array_reverse($dirs);

            $sourceDirs = array_merge($sourceDirs, $dirs);
        }

        return $sourceDirs;
    }

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
        if (isset($this->response)) {
            throw new UnexpectedValueException('Response already requested.');
        }

        $this->enableRewriting = $value;
        return $this;
    }

    public function getEnableRedirects(): bool
    {
        return $this->enableRedirects;
    }
    public function setEnableRedirects(bool $value): static
    {
        if (isset($this->response)) {
            throw new UnexpectedValueException('Response already requested.');
        }

        $this->enableRedirects = $value;
        return $this;
    }

    public function getPathQueryName(): string
    {
        return $this->pathQueryName;
    }
    public function setPathQueryName(string $value): static
    {
        if ($this->response !== null) {
            throw new UnexpectedValueException('Response already requested.');
        }

        $this->pathQueryName = $value;
        return $this;
    }

    public function getHttpStatusDirPath(): string
    {
        return $this->httpStatusRouteDirPath;
    }
    public function setHttpStatusDirPath(string $value): static
    {
        if (!pyncer_io_is_valid_path($value)) {
            throw new InvalidArgumentException('Http status dir path is invalid.');
        }

        $this->httpStatusRouteDirPath = pyncer_io_clean_path($value);
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

    /**
     * Gets the URL path of the matching component
     */
    public function getRoutePaths(): array
    {
        return $this->routePaths;
    }

    /**
     * Gets the remaining URL path not used matching a component
     */
    public function getComponentPaths(): array
    {
        return $this->componentPaths;
    }

    public function initialize(): static
    {
        $this->rewriter = $this->initializeRewriter();

        $url = $this->rewriter->getRedirectUrl();
        if ($url !== null) {
            $response = new Response(Status::REDIRECTION_302_FOUND);
            $this->response = $response->withHeader('Location', $url);
            return $this;
        }

        $paths = $this->rewriter->getRoutePaths();

        $matchRouteDir = null;
        $matchRouteDirPaths = null;
        $matchRouteUrlPaths = null;

        $matchComponentUrlPaths = null;
        $matchComponentQuery = null;
        $matchComponentFound = false;

        foreach ($this->getSourceDirs() as $routeDir) {
            if (!file_exists($routeDir)) {
                continue;
            }

            $routeDirPaths = []; // Dir paths from base up to matched component
            $routeUrlPaths = []; // Url paths from base up to matched component
            $componentUrlPaths = []; // Unhandled trailing paths to be passed to component
            $componentQuery = []; // Routing paths converted to query to be passed to component

            $temporaryMatchRouteDir = null;
            $temporaryMatchRouteDirPaths = null;
            $temporaryMatchRouteUrlPaths = null;
            $temporaryMatchComponentFound = false;

            $currentDirPath = '';

            $continuous = false;
            $continuousQueryKey = null;
            $last = false;
            foreach ($paths as $path) {
                // If no matching dir previously found, pass along
                // the remaining paths to the component
                if ($last) {
                    $componentUrlPaths[] = $path;
                    continue;
                }

                foreach ($this->getRoutingPaths() as $routingPath) {
                    if (!$routingPath->isValidPath($path)) {
                        continue;
                    }

                    $currentRouteDirPath = $routingPath->getRouteDirPath() ?? DS . $path;

                    if (!$this->getRouteDir(
                        $routeDir,
                        $currentDirPath . $currentRouteDirPath
                    )) {
                        continue;
                    }

                    $currentDirPath .= $currentRouteDirPath;
                    $componentQueryKey = $routingPath->getQueryName();

                    // Check query key override
                    $file = $this->getQueryFile($routeDir, $currentDirPath);
                    if ($file !== null && file_exists($file)) {
                        $componentQueryKey = include $file;

                        if (!is_string($componentQueryKey)) {
                            throw new UnexpectedValueException('Id query key file returned an invalid value.');
                        }

                        if ($componentQueryKey === '') {
                            throw new UnexpectedValueException('Id query key file returned a falsey value.');
                        }
                    }

                    if ($componentQueryKey !== null) {
                        if ($routingPath->getContinuous()) {
                            $componentQuery[$componentQueryKey] = [$routingPath->getQueryValue($path)];
                        } else {
                            $componentQuery[$componentQueryKey] = $routingPath->getQueryValue($path);
                        }
                    }

                    $routeUrlPaths[] = $path;
                    $routeDirPaths[] = pyncer_ltrim_string($currentRouteDirPath, DS);

                    // Continuous path ie. /a/b/c
                    if ($routingPath->getContinuous()) {
                        $continuous = true;
                        $continuousQueryKey = $componentQueryKey;
                    } else {
                        $continuous = false;
                        $continuousQueryKey = null;
                    }

                    if ($this->getRouteFile(
                        $routeDir,
                        $currentDirPath
                    )) {
                        $temporaryMatchRouteDir = $routeDir;
                        $temporaryMatchRouteDirPaths = $routeDirPaths;
                        $temporaryMatchRouteUrlPaths = $routeUrlPaths;
                        $temporaryMatchComponentFound = true;
                        $componentUrlPaths = [];
                    } elseif ($temporaryMatchComponentFound) {
                        $componentUrlPaths[] = $path;
                    }

                    continue 2; // Next url path
                }

                // Append glob paths until a new matching dir is found
                if ($continuous) {
                    $routeUrlPaths[] = $path;

                    if ($continuousQueryKey !== null) {
                        $componentQuery[$continuousQueryKey][] =  $path;
                    }

                    continue;
                }

                // Dir not found, rest of paths get sent to component
                $last = true;
                $componentUrlPaths[] = $path;
            }

            // If no paths or matching route, set route to index
            if (!$temporaryMatchComponentFound) {
                $temporaryMatchRouteDir = $routeDir;

                // Should directories exist, but with no index.php
                $componentUrlPaths = [
                    ...$routeUrlPaths,
                    ...$componentUrlPaths
                ];

                // Reset values to match no paths
                $routeUrlPaths = [];
                $routeDirPaths = [];

                $temporaryMatchRouteDirPaths = $routeDirPaths;
                $temporaryMatchRouteUrlPaths = $routeUrlPaths;

                $temporaryMatchComponentFound = ($this->getRouteFile(
                    $routeDir,
                    ''
                ) ? true : false);
            }

            if ($matchRouteUrlPaths === null ||
                ($temporaryMatchComponentFound && !$matchComponentFound) ||
                count($routeUrlPaths) > count($matchRouteUrlPaths)
            ) {
                $matchRouteDir = $temporaryMatchRouteDir;
                $matchRouteDirPaths = $temporaryMatchRouteDirPaths;
                $matchRouteUrlPaths = $temporaryMatchRouteUrlPaths;

                $matchComponentUrlPaths = $componentUrlPaths;
                $matchComponentQuery = $componentQuery;
                $matchComponentFound = $temporaryMatchComponentFound;
            }
        }

        if ($matchComponentFound) {
            $this->routeFile = $this->getRouteFile(
                $matchRouteDir, // Shouldn't be required
                (
                    $matchRouteDirPaths ?
                    DS . implode(DS, $matchRouteDirPaths) :
                    ''
                )
            );
        }

        $this->routePaths = $matchRouteUrlPaths;

        $this->componentPaths = $matchComponentUrlPaths;
        $this->componentQuery = $matchComponentQuery;
        $this->componentFound = $matchComponentFound;

        $this->setInitialized(true);

        return $this;
    }

    protected function initializeRewriter(): RewriterInterface
    {
        $rewriter = new Rewriter(
            $this->request,
            $this->getBaseUrl(),
        );

        $redirector = $this->initializeRedirector();
        $rewriter->setRedirector($redirector);

        $rewriter->setPathQueryName($this->getPathQueryName());
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

        $redirector = new Redirector(
            $this->sourceMap
        );

        $redirector->initialize();

        return $redirector;
    }

    protected function getRouteFile(string $routeDir, string $routeDirPath): ?string
    {
        $file = $this->getRouteDir($routeDir, $routeDirPath) .
            DS . 'index.php';

        if (file_exists($file)) {
            return $file;
        }

        return null;
    }

    protected function getRouteDir(string $routeDir, string $routeDirPath): ?string
    {
        $file = $routeDir . $routeDirPath;

        if (file_exists($file)) {
            return $file;
        }

        return null;
    }

    protected function getQueryFile(string $routeDir, string $routeDirPath): ?string
    {
        $file = $this->getRouteDir($routeDir, $routeDirPath) .
            DS . 'query.php';

        if (file_exists($file)) {
            return $file;
        }

        return null;
    }

    /**
    * @return \Psr\Http\Message\UriInterface
    */
    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        return $this->rewriter->getUrl($path, $query);
    }

    final public function getResponse(
        RequestHandlerInterface $handler
    ): ?PsrResponseInterface
    {
        if ($this->response !== null) {
            return $this->response;
        }

        if (!$this->getInitialized()) {
            $this->initialize();
        }

        $response = null;

        if ($this->componentFound) {
            $response = $this->includeComponent(
                $handler,
                $this->routeFile,
                $this->componentPaths,
                $this->componentQuery,
                $this->request
            );
        }

        if ($response !== null) {
            $status = Status::from($response->getStatusCode());

            if (!$status->isSuccess() && $this->isEmptyResponse($response)) {
                $httpStatusResponse = $this->includeHttpStatusComponent(
                    $handler,
                    Status::from($response->getStatusCode())
                );

                if ($httpStatusResponse) {
                    $response = $httpStatusResponse;
                }
            }
        }

        if ($response === null) {
            $httpStatusResponse = $this->includeHttpStatusComponent(
                $handler,
                Status::CLIENT_ERROR_404_NOT_FOUND
            );

            if ($httpStatusResponse) {
                $response = $httpStatusResponse;
            } else {
                $response = new Response(
                    Status::CLIENT_ERROR_404_NOT_FOUND
                );
            }
        }

        $this->response = $response;

        return $this->response;
    }

    private function isEmptyResponse(
        PsrResponseInterface $response
    ): bool
    {
        if ($response instanceof JsonResponse) {
            $parsedBody = $response->getParsedBody();
            if ($parsedBody === '' || $parsedBody === []) {
                return true;
            }

            return false;
        }

        return !$response->getBody()->getSize();
    }

    /**
     * Include the component file and get a response.
     *
     * @param $handler The remaining sub paths
     * @param string $file The component file to include
     * @param array $paths The remaining sub paths
     * @param $request The request
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function includeComponent(
        RequestHandlerInterface $handler,
        string $file,
        array $paths,
        array $query,
        PsrServerRequestInterface $request,
        ?Status $httpStatus = null
    ): ?PsrResponseInterface
    {
        $request = $request->withQueryParams(array_merge(
            $request->getQueryParams(),
            $query
        ));

        $response = include $file;

        if ($response instanceof ComponentInterface) {
            $response = $this->getComponentResponse($handler, $response);
        }

        if (!$response instanceof PsrResponseInterface) {
            return null;
        }

        if ($httpStatus !== null &&
            $response->getStatusCode() !== $httpStatus->getStatusCode()
        ) {
            $response = $response->withStatus($httpStatus->getStatusCode());
        }

        return $response;
    }

    private function includeHttpStatusComponent(
        RequestHandlerInterface $handler,
        Status $httpStatus
    ): ?PsrResponseInterface
    {
        $response = null;

        foreach ($this->getSourceDirs() as $dir) {
            $file = $this->getRouteFile(
                $dir,
                $this->getHttpStatusDirPath() . DS . $httpStatus->getStatusCode()
            );

            if ($file) {
                if ($this->routePaths) {
                    $paths = array_merge($this->routePaths, $this->componentPaths);
                } else {
                    $paths = $this->componentPaths;
                }

                $response = $this->includeComponent(
                    $handler,
                    $file,
                    $paths,
                    $this->componentQuery,
                    $this->request,
                    $httpStatus
                );
                break;
            }
        }

        return $response;
    }

    private function getComponentResponse(
        RequestHandlerInterface $handler,
        ComponentInterface $component
    ): ?PsrResponseInterface
    {
        if ($component instanceof PsrLoggerAwareInterface &&
            !$component->getLogger() &&
            $this->logger
        ) {
            $component->setLogger($this->logger);
        }

        $response = $component->getResponse($handler);

        $status = Status::from($response->getStatusCode());
        if (!$status->isSuccess()) {
            return $response;
        }

        $decorator = $this->getComponentDecorator();

        if ($decorator) {
            $component = $decorator->apply($component);
            if ($component !== null) {
                $response = $component->getResponse($handler);
            }
        }

        return $response;
    }
}
