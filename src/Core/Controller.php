<?php

namespace Ypf\Core;

use Ypf\Ypf;

class Controller extends Core
{
    protected function action($action, $args = [])
    {
        return Ypf::getInstance()->execute($action, $args);
    }
}
