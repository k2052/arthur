<?php

namespace arthur\tests\mocks\console\command;

class MockCommandHelp extends \arthur\console\Command 
{
	public $long = 'default';
	public $blong = true;
	public $s = true;
	
	protected $_classes = array(
		'response' => '\arthur\tests\mocks\console\MockResponse'
	);

	public function run()
	{
		return true;
	}

	public function sampleTaskWithRequiredArgs($arg1, $arg2) 
	{
		return true;
	}

	public function sampleTaskWithOptionalArgs($arg1 = null, $arg2 = null) 
	{
		return true;
	}

	protected function _sampleHelper() { }
}