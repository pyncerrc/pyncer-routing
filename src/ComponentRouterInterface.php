<?php
namespace Pyncer\Routing;

use Pyncer\Routing\RouterInterface;

interface ComponentRouterInterface extends RouterInterface
{
    public function getEnableRewriting(): bool;
    public function getEnableRedirects(): bool;

    public function getPathQueryName(): string;

    public function getRoutePaths(): array;
    public function getComponentPaths(): array;
}
