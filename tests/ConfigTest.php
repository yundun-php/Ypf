<?php

namespace Tests;

use Ypf\Lib\Config;

define( "__CONF__", __DIR__ . '/Conf' );


class ConfigTest extends TestCase {

	public function testLoad() {
		$confObj = new Config();
		$confObj->load( __CONF__ );
		$confObj->loadLang( __CONF__ . '/lang/servicecommon' );
		$confObj->loadLang( __CONF__ . '/lang/service1' );
		$confObj->loadLang( __CONF__ . '/lang/service2' );

		var_dump( Config::$config );
	}
}