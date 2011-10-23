<?php

namespace arthur\tests\mocks\core;

class MockStaticInstantiator extends \arthur\core\StaticObject 
{
	protected static $_classes = array('request' => '\arthur\tests\mocks\core\MockRequest');

	public static function instance($name, array $config = array()) 
	{
		return static::_instance($name, $config);
	}
}