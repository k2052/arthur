<?php

namespace arthur\tests\mocks\storage\cache\strategy;

class MockConfigurizer extends \arthur\core\Object 
{
	public static $parameters = array();
	
	public function __construct(array $config = array()) 
	{
		static::$parameters = $config;
	}

	public static function write($data) 
	{
		return static::$parameters;
	}

	public static function read($data) 
	{
		return static::$parameters;
	}
}