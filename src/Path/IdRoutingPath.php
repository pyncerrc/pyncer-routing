<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;
use Pyncer\Validation\Rule\IntRule;
use Pyncer\Validation\ValueValidator;

class IdRoutingPath extends AbstractRoutingPath
{
    protected ValueValidator $validator;

    public function __construct(
        ?string $queryName = 'id',
        ?string $routeDirPath = '@id'
    ) {
        parent::__construct($queryName, $routeDirPath);

        $this->validator = new ValueValidator();
        $this->validator->AddRules(
            new IntRule(
                minValue: 1,
            )
        );
    }

    public function isValidPath(string $path): bool
    {
        return $this->validator->isValidAndClean(intval($path));
    }
}
