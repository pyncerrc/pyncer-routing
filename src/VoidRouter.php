<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Pyncer\Exception\LogicException;
use Pyncer\Http\Server\RequestHandlerInterface;
use Pyncer\Routing\AbstractRouter;

class VoidRouter extends AbstractRouter
{
    /**
     * @inheritdoc
     */
    final public function getResponse(
        RequestHandlerInterface $handler
    ): ?PsrResponseInterface
    {
        throw new LogicException('The getResponse function is not used as there are no responses.');
    }

    /**
     * @inheritdoc
     */
    public function getUrl(
        string $path = '',
        string|iterable $query = []
    ): PsrUriInterface
    {
        throw new LogicException('The getUrl function is not used as there are no urls.');
    }
}
