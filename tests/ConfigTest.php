<?php

namespace Tests;

use Ypf\Lib\Config;

define( "__CONF__", __DIR__ . '/Conf' );


class ConfigTest extends TestCase {

	public function testLoad() {
		$confObj = new Config();
		$confObj->load( __CONF__ );
		$confObj->loadRecursive( __CONF__ . '/lang/en' );
		$confObj->loadRecursive( __CONF__ . '/lang/zh_CN' );

		var_dump( Config::$config );
	}
}