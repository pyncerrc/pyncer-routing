<?php
namespace Pyncer\Routing\Path;

use Pyncer\Routing\Path\AbstractRoutingPath;

class NullRoutingPath extends AbstractRoutingPath
{
    public function __construct()
    {
        parent::__construct(null, null);
    }
}
