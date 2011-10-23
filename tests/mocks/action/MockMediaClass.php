<?php

namespace arthur\tests\mocks\action;

class MockMediaClass extends \arthur\net\http\Media 
{
	public static function render(&$response, $data = null, array $options = array()) 
	{
		$response->options = $options;
		$response->data    = $data;
	}
}