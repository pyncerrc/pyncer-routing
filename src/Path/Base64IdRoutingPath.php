<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;

use function preg_match;

class Base64IdRoutingPath extends AbstractRoutingPath
{
    public function __construct(
        ?string $queryName = 'id64',
        ?string $routeDirPath = '@id64'
    ) {
        parent::__construct($queryName, $routeDirPath);
    }

    public function isValidPath(string $path): bool
    {
         $pattern = '/^[0-9a-zA-Z-_]+$/';

         if (preg_match($pattern, $path)) {
            return true;
         }

         return false;
    }
}
