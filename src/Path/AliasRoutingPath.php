<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;

use function preg_match;

class AliasRoutingPath extends AbstractRoutingPath
{
    public function __construct(
        ?string $queryName = 'alias',
        ?string $routeDirPath = '@alias'
    ) {
        parent::__construct($queryName, $routeDirPath);
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
