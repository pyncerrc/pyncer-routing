<?php
namespace Pyncer\Routing;

use Pyncer\Utility\InitializeInterface;

interface RedirectorInterface extends InitializeInterface
{
    public function getRoutePaths(array $urlPaths): array;

    public function getUrlPaths(array $routePaths): array;
}
