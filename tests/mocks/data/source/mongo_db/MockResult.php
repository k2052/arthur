<?php

namespace arthur\tests\mocks\data\source\mongo_db;

class MockResult extends \arthur\data\source\mongo_db\Result 
{
	protected $_autoConfig = array('data');

	protected $_data = array(
		array('_id' => '4c8f86167675abfabdbf0300', 'title' => 'bar'),
		array('_id' => '5c8f86167675abfabdbf0301', 'title' => 'foo'),
		array('_id' => '6c8f86167675abfabdbf0302', 'title' => 'dib')
	);

	public function hasNext() 
	{
		if(!is_array($this->_data))
			return false;

		return key($this->_data) !== null && key($this->_data) < count($this->_data);
	}

	public function getNext() 
	{
		$result = current($this->_data);
		next($this->_data);
		return $result;
	}

	public function next() 
	{
		return $this->_next();
	}

	public function __call($method, array $params) 
	{
		return $this;
	}

	protected function _close() { }

	protected function _next() 
	{
		$result = current($this->_data) ?: null;
		next($this->_data);   
		
		return $result;
	}
}