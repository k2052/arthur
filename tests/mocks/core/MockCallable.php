<?php

namespace arthur\tests\mocks\core;

class MockCallable extends \arthur\core\Object 
{
	public function __call($method, $params = array()) 
	{
		return $params;
	}
}