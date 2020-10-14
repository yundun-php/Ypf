<?php

namespace Ypf\Core;

use Ypf\Ypf;

abstract class Core
{
    public static $container = null;

    public function __construct()
    {
        if (is_null(self::$container)) {
            self::$container = Ypf::getContainer();
        }
    }

    public function __set($name, $value)
    {
        self::$container[$name] = $value;
    }

    public function __get($name)
    {
        return isset(self::$container[$name]) ? self::$container[$name] : null;
    }
}
