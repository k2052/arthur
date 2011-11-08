<?php

namespace arthur\tests\mocks\analysis;

class MockLoggerAdapter extends \arthur\core\Object 
{
	public function write($name, $value) 
	{
		return function($self, $params, $chain) 
		{
			return true;
		};
	}
}