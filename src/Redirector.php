<?php
namespace Pyncer\Routing;

use Psr\Http\Message\UriInterface;
use Pyncer\Exception\UnexpectedValueException;
use Pyncer\Source\SourceMap;
use Pyncer\Utility\InitializeTrait;

use const DIRECTORY_SEPARATOR as DS;

class Redirector implements RedirectorInterface
{
    use InitializeTrait;

    /**
     * @var array<string, string>
     */
    protected array $redirects = [];

    public function __construct(
        protected SourceMap $sourceMap,
    ) {}

    public function initialize(): static
    {
        $this->setInitialized(true);

        $this->initializeRedirects();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutePaths(array $urlPaths): array
    {
        return $this->getRoutePathsFromRedirects($urlPaths, $this->redirects);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrlPaths(array $routePaths): array
    {
        return $this->getUrlPathsFromRedirects($routePaths, $this->redirects);
    }

    /**
     * @param array<string> $urlPaths
     * @param array<string, string> $redirects
     */
    protected function getRoutePathsFromRedirects(
        array $urlPaths,
        array $redirects,
    ): array
    {
        if (!$urlPaths) {
            return $urlPaths;
        }

        $permutations = $this->getPathPermutations($urlPaths);

        $path = '/' . implode('/', $urlPaths) . '/';

        foreach ($permutations as $permutation) {
            $search = array_search($permutation, $redirects);
            if ($search === false) {
                continue;
            }

            $parts = explode($permutation . '/', $path);

            $newPaths = [];

            if ($parts[0] !== '') {
                $before = explode('/', substr($parts[0], 1));
                $before = $this->getRoutePathsFromRedirects(
                    $before,
                    $redirects
                );

                $newPaths = [...$newPaths, ...$before];
            }

            $newPaths = [
                ...$newPaths,
                ...explode('/', substr($search, 1))
            ];

            if ($parts[1] !== '') {
                $after = explode('/', substr($parts[1], 0, -1));
                $after = $this->getRoutePathsFromRedirects(
                    $after,
                    $redirects
                );

                $newPaths = [...$newPaths, ...$after];
            }

            return $newPaths;
        }

        return $urlPaths;
    }

    protected function getUrlPathsFromRedirects(
        array $routePaths,
        array $redirects,
    ): array
    {
        if (!$routePaths) {
            return $routePaths;
        }

        $permutations = $this->getPathPermutations($routePaths);

        $path = '/' . implode('/', $routePaths) . '/';

        foreach ($permutations as $permutation) {
            if (!array_key_exists($permutation, $redirects)) {
                continue;
            }

            $parts = explode($permutation . '/', $path);

            $newPaths = [];

            if ($parts[0] !== '') {
                $before = explode('/', substr($parts[0], 1));
                $before = $this->getUrlPathsFromRedirects(
                    $before,
                    $redirects
                );

                $newPaths = [...$newPaths, ...$before];
            }

            $newPaths = [
                ...$newPaths,
                ...explode('/', substr($redirects[$permutation], 1))
            ];

            if ($parts[1] !== '') {
                $after = explode('/', substr($parts[1], 0, -1));
                $after = $this->getUrlPathsFromRedirects(
                    $after,
                    $redirects
                );

                $newPaths = [...$newPaths, ...$after];
            }

            return $newPaths;
        }

        return $routePaths;
    }

    /**
     * @param array<string> $paths
     * @return array<string>
     */
    protected function getPathPermutations(array $paths): array
    {
        $permutations = [];
        $currentPaths = $paths;

        while (count($currentPaths)) {
            $permutations[] = '/' . implode('/', $currentPaths);
            array_pop($currentPaths);
        }

        array_shift($paths);

        if ($paths) {
            $permutations = [
                ...$permutations,
                ...$this->getPathPermutations($paths)
            ];
        }

        return $permutations;
    }

    protected function initializeRedirects(): void
    {
        foreach ($this->getSourceDirs() as $dir) {
            $file = $dir . DS . 'redirects.php';

            if (!file_exists($file)) {
                continue;
            }

            $redirects = include $file;

            if (!is_array($redirects)) {
                throw new UnexpectedValueException(
                    'Redirects file is invalid.'
                );
            }

            $this->redirects = [
                ...$this->redirects,
                ...$redirects
            ];
        }
    }

    protected function getSourceDirs(): array
    {
        $sources = array_reverse($this->sourceMap->getKeys());

        $sourceDirs = [];

        foreach ($sources as $source) {
            $dirs = $this->sourceMap->get($source);
            if (is_array($dirs)) {
                $dirs = array_reverse($dirs);
            } elseif (is_string($dirs)) {
                $dirs = [$dirs];
            } else {
                throw new UnexpectedValueException(
                    'Invalid source map value.'
                );
            }

            $sourceDirs = array_merge($sourceDirs, $dirs);
        }

        return $sourceDirs;
    }
}
