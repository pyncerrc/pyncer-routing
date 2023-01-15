<?php
namespace Pyncer\Routing;

use Pyncer\Utility\InitializeInterface;

interface RedirectorInterface extends InitializeInterface
{
    /**
     * @param array<string> $urlPaths
     */
    public function getRoutePaths(array $urlPaths): array;

    /**
     * @param array<string> $routePaths
     */
    public function getUrlPaths(array $routePaths): array;
}
