<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;
use Pyncer\Validation\Rule\UidRule;
use Pyncer\Validation\ValueValidator;

class UidRoutingPath extends AbstractRoutingPath
{
    protected ValueValidator $validator;

    public function __construct(
        ?string $queryName = 'uid',
        ?string $routeDirPath = '@uid'
    ) {
        parent::__construct($queryName, $routeDirPath);

        $this->validator = new ValueValidator();
        $this->validator->addRules(
            new UidRule()
        );
    }

    public function isValidPath(string $path): bool
    {
        return $this->validator->isValidAndClean($path);
    }
}
