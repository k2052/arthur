<?php

namespace lithium\data\collection;

use lithium\util\Collection;

class DocumentArray extends \lithium\data\Collection 
{
	protected $_exists = false;
	protected $_original = array();
	protected $_autoConfig = array(
		'data', 'model', 'result', 'query', 'parent', 'stats', 'pathKey', 'exists'
	);

	protected function _init() 
	{
		parent::_init();
		$this->_original = $this->_data;
	}

	public function exists() 
	{
		return $this->_exists;
	}

	public function sync($id = null, array $data = array()) 
	{
		$this->_exists = true;
		$this->_original = $this->_data;
	}

	public function to($format, array $options = array()) 
	{
		$defaults = array('handlers' => array(
			'MongoId' => function($value) { return (string) $value; },
			'MongoDate' => function($value) { return $value->sec; }
		));

		if($format == 'array') {
			$options += $defaults;
			return Collection::toArray($this->_data, $options);
		}    
		
		return parent::to($format, $options);
	}

	public function __isset($name) 
	{
		return isset($this->_data[$name]);
	}

	public function __unset($name) 
	{
		unset($this->_data[$name]);
	}

	public function offsetGet($offset) 
	{
		return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
	}

	public function offsetSet($offset, $data) 
	{
		if($model = $this->_model) {
			$options = array('first' => true, 'schema' => $model::schema());
			$data    = $model::connection()->cast($this, array($this->_pathKey => $data), $options);
		}
		if($offset)
			return $this->_data[$offset] = $data;     
			
		return $this->_data[] = $data;
	}

	public function rewind() 
	{
		$data = parent::rewind();
		$key  = key($this->_data);
		return $this->offsetGet($key);
	}

	public function export() 
	{
		return array(
			'exists' => $this->_exists,
			'key'    => $this->_pathKey,
			'data'   => $this->_original,
			'update' => $this->_data
		);
	}

	protected function _populate($data = null, $key = null) { }
}