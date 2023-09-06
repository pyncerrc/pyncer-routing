<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;
use Pyncer\Validation\Rule\Base64IdRule;
use Pyncer\Validation\ValueValidator;

class Base64IdRoutingPath extends AbstractRoutingPath
{
    protected ValueValidator $validator;

    public function __construct(
        ?string $queryName = 'id64',
        ?string $routeDirPath = '@id64'
    ) {
        parent::__construct($queryName, $routeDirPath);

        $this->validator = new ValueValidator();
        $this->validator->AddRules(
            new Base64IdRule()
        );
    }

    public function isValidPath(string $path): bool
    {
        return $this->validator->isValidAndClean($path);
    }
}
