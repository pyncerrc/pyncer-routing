<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Http\Message\Response;
use Pyncer\Http\Message\Status;
use Pyncer\Exception\InvalidArgumentException;
use Pyncer\Exception\LogicException;
use Pyncer\Routing\AbstractComponentRouter;
use Pyncer\Source\SourceMap;

use function file_exists;
use function in_array;
use function strtolower;

use const DIRECTORY_SEPARATOR as DS;

class ModuleRouter extends AbstractComponentRouter
{
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private bool $otherComponentsFound = false;

    public function __construct(
        SourceMap $sourceMap,
        PsrServerRequestInterface $request
    ) {
        parent::__construct($sourceMap, $request);

        if (!in_array($this->request->getMethod(), $this->getAllowedMethods())) {
            throw new InvalidArgumentException('Invalid module HTTP method specified.');
        }

        $this->setPathQueryName('module');
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
    public function setAllowedMethods(array $value): static
    {
        $this->allowedMethods = $value;
        return $this;
    }

    public function initialize(): static
    {
        parent::initialize();

        if (!$this->componentFound) {
            if ($this->otherComponentsFound) {
                $this->response = new Response(
                    Status::CLIENT_ERROR_405_METHOD_NOT_ALLOWED
                );
            } else {
                $this->response = new Response(
                    Status::CLIENT_ERROR_404_NOT_FOUND
                );
            }
        }

        return $this;
    }

    protected function getRouteFile(
        string $routeDir,
        string $routeDirPath
    ): ?string
    {
        $method = strtolower($this->request->getMethod());

        $dir = $this->getRouteDir($routeDir, $routeDirPath);

        $file = $dir . DS . '@' . $method . DS . 'index.php';

        if (file_exists($file)) {
            return $file;
        }

        $file = $dir . DS . '@method' . DS . 'index.php';

        if (file_exists($file)) {
            return $file;
        }

        // Check if other http methods exist to handle 405 error
        foreach ($this->getAllowedMethods() as $otherMethod) {
            $otherMethod = strtolower($otherMethod);

            if ($otherMethod === $method) {
                continue;
            }

            $file = $dir . DS . $otherMethod . DS . 'index.php';

            if (file_exists($file)) {
                $this->otherComponentsFound = true;
                break;
            }
        }

        return null;
    }

    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        throw new LogicException('The getUrl function is not used as there are no urls.');
    }
}
