<?php
namespace Pyncer\Routing\Path;

use Pyncer\Exception\InvalidArgumentException;

use function Pyncer\IO\is_valid_path as pyncer_io_is_valid_path;
use function Pyncer\IO\clean_path as pyncer_io_clean_path;

abstract class AbstractRoutingPath
{
    protected ?string $queryName;
    protected ?string $routeDirPath;
    protected bool $continuous;

    public function __construct(
        ?string $queryName,
        ?string $routeDirPath,
        bool $continuous = false
    ) {
        $this->setQueryName($queryName);
        $this->setRouteDirPath($routeDirPath);
        $this->setContinuous($continuous);
    }

    public function getQueryName(): ?string
    {
        return $this->queryName;
    }

    public function setQueryName(?string $value): static
    {
        $this->queryName = $value;
        return $this;
    }

    public function getRouteDirPath(): ?string
    {
        return $this->routeDirPath;
    }

    public function setRouteDirPath(?string $value): static
    {
        if ($value === null) {
            $this->routeDirPath = null;
            return $this;
        }

        if (!pyncer_io_is_valid_path($value)) {
            throw new InvalidArgumentException('Dir path is invalid.');
        }

        $this->routeDirPath = pyncer_io_clean_path($value);

        return $this;
    }

    public function getContinuous(): bool
    {
        return $this->continuous;
    }

    public function setContinuous(bool $value): static
    {
        $this->continuous = $value;
        return $this;
    }

    public function isValidPath(string $path): bool
    {
        return true;
    }

    public function getQueryValue(string $path): string
    {
        return $path;
    }
}
