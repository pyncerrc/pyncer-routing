<?php
namespace Pyncer\Routing;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Pyncer\Routing\AbstractComponentRouter;
use Pyncer\Source\SourceMapInterface;

class PageRouter extends AbstractComponentRouter
{
    public function __construct(
        SourceMapInterface $sourceMap,
        PsrServerRequestInterface $request
    ) {
        parent::__construct($sourceMap, $request);

        $this->setPathQueryName('page');
    }
}
