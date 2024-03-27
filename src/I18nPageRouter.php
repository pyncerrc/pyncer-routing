<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Pyncer\I18n\I18n;
use Pyncer\Routing\PageRouter;
use Pyncer\Routing\I18nComponentRouterInterface;
use Pyncer\Routing\I18nComponentRouterTrait;
use Pyncer\Source\SourceMapInterface;

class I18nPageRouter extends PageRouter implements
    I18nComponentRouterInterface
{
    use I18nComponentRouterTrait;

    public function __construct(
        SourceMapInterface $sourceMap,
        PsrServerRequestInterface $request,
        protected I18n $i18n,
    ) {
        parent::__construct($sourceMap, $request);
    }
}
