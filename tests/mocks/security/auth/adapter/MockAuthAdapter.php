<?php

namespace arthur\tests\mocks\security\auth\adapter;

class MockAuthAdapter extends \arthur\core\Object 
{
	public function check($credentials, array $options = array()) 
	{
		return isset($options['success']) ? $credentials : false;
	}

	public function set($data, array $options = array()) 
	{
		if(isset($options['fail']))
			return false;

		return $data;
	}

	public function clear(array $options = array()) { }
}