<?php

namespace arthur\tests\mocks\test;

class MockFilterClass extends \arthur\core\Object
{
	public function __construct($all = false) 
	{
		if($all)
			return true;

		return false;
	}

	public function testFunction() 
	{
		$test = true;

		return $test;
	}
}