<?php

namespace arthur\tests\mocks\core;

class MockObjectForParents extends \arthur\core\Object 
{
	public static function parents() 
	{
		return static::_parents();
	}
}