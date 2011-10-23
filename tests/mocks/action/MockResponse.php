<?php

namespace arthur\tests\mocks\action;

class MockResponse extends \arthur\action\Response 
{
	public $testHeaders = array();

	public function render() 
	{
		$this->testHeaders = array();
		parent::render();
		$this->headers     = array();
	}

	protected function _writeHeader($header, $code = null) 
	{
		$this->testHeaders[] = $header;
	}
}