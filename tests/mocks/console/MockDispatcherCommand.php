<?php

namespace arthur\tests\mocks\console;

class MockDispatcherCommand extends \arthur\console\Command 
{
	protected $_classes = array(
		'response' => '\arthur\tests\mocks\console\MockResponse'
	);

	public function testRun() 
	{
		$this->response->testAction = __FUNCTION__;
	}

	public function run($param = null)
	{
		$this->response->testAction = __FUNCTION__;
		$this->response->testParam  = $param;
	}

	public function testAction() 
	{
		$this->response->testAction = __FUNCTION__;
	}
}