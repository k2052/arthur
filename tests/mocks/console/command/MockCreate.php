<?php

namespace arthur\tests\mocks\console\command;

class MockCreate extends \arthur\console\command\Create 
{
	protected $_classes = array(
		'response' => '\arthur\tests\mocks\console\MockResponse'
	);

	public function save($template, $params = array()) 
	{
		return $this->_save($template, $params);
	}
}