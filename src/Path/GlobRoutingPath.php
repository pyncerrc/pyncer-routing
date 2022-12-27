<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;

use function preg_match;

class GlobRoutingPath extends AbstractRoutingPath
{
    public function __construct(
        ?string $queryName = 'glob',
        ?string $routeDirPath = '@glob'
    ) {
        parent::__construct($queryName, $routeDirPath, true);
    }

    public function isValidPath(string $path): bool
    {
         $pattern = '/^[0-9a-z-_]+$/';

         if (preg_match($pattern, $path)) {
            return true;
         }

         return false;
    }
}
