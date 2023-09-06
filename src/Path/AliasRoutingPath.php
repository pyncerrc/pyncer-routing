<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;
use Pyncer\Validation\Rule\AliasRule;
use Pyncer\Validation\ValueValidator;

class AliasRoutingPath extends AbstractRoutingPath
{
    protected ValueValidator $validator;

    public function __construct(
        ?string $queryName = 'alias',
        ?string $routeDirPath = '@alias'
    ) {
        parent::__construct($queryName, $routeDirPath);

        $this->validator = new ValueValidator();
        $this->validator->AddRules(
            new AliasRule(
                allowNumericCharacters: true,
                allowLowerCaseCharacters: true,
                allowUpperCaseCharacters: true,
                separatorCharacters: '-_',
            )
        );
    }

    public function isValidPath(string $path): bool
    {
        return $this->validator->isValidAndClean($path);
    }
}
