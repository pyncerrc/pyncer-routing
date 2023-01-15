<?php
namespace Pyncer\Routing\Path;

interface RoutingPathInterface
{
    public function getQueryName(): ?string;
    public function getRouteDirPath(): ?string;
    public function getContinuous(): bool;
    public function isValidPath(string $path): bool;
    public function getQueryValue(string $path): string;
}
