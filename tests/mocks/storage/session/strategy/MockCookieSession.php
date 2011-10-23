<?php

namespace arthur\tests\mocks\storage\session\strategy;

class MockCookieSession extends \arthur\core\Object 
{
	protected static $_secret = 'foobar';

	protected static $_data = array('one' => 'foo', 'two' => 'bar');

	public static function read($key = null, array $options = array())
	{
		if(isset(static::$_data[$key]))
			return static::$_data[$key];

		return static::$_data;
	}

	public static function write($key, $value = null, array $options = array()) 
	{
		static::$_data[$key] = $value;
		return $value;
	}

	public static function reset() 
	{
		return static::$_data = array('one' => 'foo', 'two' => 'bar');
	}

	public static function data() 
	{
		return static::$_data;
	}
}