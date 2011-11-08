<?php

namespace arthur\tests\mocks\console;

class MockDispatcherRequest extends \arthur\console\Request 
{

	public $params = array(
		'command' => '\arthur\tests\mocks\console\MockDispatcherCommand'
	);
}