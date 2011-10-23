<?php

namespace arthur\tests\mocks\security\auth\adapter;

class MockHttp extends \arthur\security\auth\adapter\Http 
{
	public $headers = array();

	protected function _writeHeader($string) 
	{
		$this->headers[] = $string;
	}
}