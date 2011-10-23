<?php

namespace arthur\tests\mocks\action;

class MockRequestType extends \arthur\action\Request 
{
	public function type($raw = false) 
	{
		return 'foo';
	}

	public function accepts($type = null) 
	{
		return 'foo';
	}
}