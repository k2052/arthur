<?php

namespace arthur\tests\mocks\data\source;

use MongoId;
use arthur\tests\mocks\data\source\mongo_db\MockResult;

class MockMongoSource extends \arthur\core\Object 
{
	public $resultSets = array();
	public $queries = array();

	public function __get($name) 
	{
		return $this;
	}

	public function insert(&$data, $options)
	{
		$this->queries[] = compact('data', 'options');
		$result          = current($this->resultSets);  
		
		next($this->resultSets);
		$data['_id'] = new MongoId();      
		
		return $result;
	}

	public function find($conditions, $fields) 
	{
		$this->queries[] = compact('conditions', 'fields');
		$result          = new MockResult(array('data' => current($this->resultSets)));
		next($this->resultSets); 
		
		return $result;
	}
}