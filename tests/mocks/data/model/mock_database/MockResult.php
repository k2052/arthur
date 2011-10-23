<?php

namespace arthur\tests\mocks\data\model\mock_database;

class MockResult extends \arthur\data\source\database\Result 
{
	public $records = array();

	public function __construct(array $config = array()) 
	{
		$defaults = array('resource' => true);
		parent::__construct($config + $defaults);
	}

	protected function _close() 
	{
	}

	protected function _prev() 
	{
		return prev($this->records);
	}

	protected function _next() 
	{
		return next($this->records);
	}
}