<?php

namespace arthur\tests\mocks\net\http;

class MockSocket extends \arthur\net\Socket 
{
	public $data = null;
	public $configs = array();

	public function __construct(array $config = array()) 
	{
		parent::__construct((array) $config);
	}

	public function open(array $options = array()) 
	{
		parent::open($options);
		return true;
	}

	public function close() 
	{
		return true;
	}

	public function eof() 
	{
		return true;
	}

	public function read() 
	{
		return $this->data;
	}

	public function write($data) 
	{
		if(!is_object($data))
			$data = $this->_instance($this->_classes['request'], (array) $data + $this->_config);

		$this->data = $data;
		return true;
	}

	public function timeout($time) 
	{
		return true;
	}

	public function encoding($charset) 
	{
		return true;
	}

	public function config() 
	{
		return $this->_config;
	}
}