<?php

namespace arthur\tests\mocks\console;

class MockCommand extends \arthur\console\Command 
{
	public $case = null;
	public $face = true;
	public $mace = 'test';
	public $race;
	public $lace = true;

	protected $_dontShow = null;

	protected $_classes = array(
		'response' => '\arthur\tests\mocks\console\MockResponse'
	);

	public function testRun() 
	{
		$this->response->testAction = __FUNCTION__;
	}

	public function clear() { }

	public function testReturnNull() 
	{
		return null;
	}

	public function testReturnTrue() 
	{
		return true;
	}

	public function testReturnFalse() 
	{
		return false;
	}

	public function testReturnNegative1() 
	{
		return -1;
	}

	public function testReturn1() 
	{
		return 1;
	}

	public function testReturn3() 
	{
		return 3;
	}

	public function testReturnString() 
	{
		return 'this is a string';
	}

	public function testReturnEmptyArray() 
	{
		return array();
	}

	public function testReturnArray() 
	{
		return array('a' => 'b');
	}
}