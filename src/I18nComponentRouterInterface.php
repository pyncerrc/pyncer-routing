<?php
namespace Pyncer\Routing;

use Pyncer\Routing\ComponentRouterInterface;
use Pyncer\Routing\I18nRouterInterface;

interface I18nComponentRouterInterface extends
    ComponentRouterInterface,
    I18nRouterInterface
{
    public function getLocaleQueryName(): string;
}
