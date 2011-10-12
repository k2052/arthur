<?php

namespace lithium\data;

use BadMethodCallException;
use UnexpectedValueException;
use lithium\data\Collection;

class Entity extends \lithium\core\Object 
{
	protected $_model = null;
	protected $_data = array();
	protected $_relationships = array();
	protected $_parent = null;
	protected $_errors = array();
	protected $_updated = array();
	protected $_increment = array();
	protected $_exists = false;
	protected $_schema = array();
	protected $_autoConfig = array(
		'classes' => 'merge', 'parent', 'schema', 'data',
		'model', 'exists', 'pathKey', 'relationships'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('model' => null, 'data' => array(), 'relationships' => array());
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();
		$this->_updated = $this->_data;
	}

	public function &__get($name)
	{
		if(isset($this->_relationships[$name])) 
			return $this->_relationships[$name];
		if(isset($this->_updated[$name]))
			return $this->_updated[$name];         
			
		$null = null;
		return $null;
	}

	public function __set($name, $value = null) 
	{
		if (is_array($name) && !$value) {
			return array_map(array(&$this, '__set'), array_keys($name), array_values($name));
		}
		$this->_updated[$name] = $value;
	}

	public function __isset($name) 
	{
		return isset($this->_updated[$name]);
	}

	public function __call($method, $params) 
	{
		if(!($model = $this->_model) || !method_exists($model, $method)) {
			$message = "No model bound or unhandled method call `{$method}`.";
			throw new BadMethodCallException($message);
		}
		array_unshift($params, $this);
		$class = $model::invokeMethod('_object');   
		
		return call_user_func_array(array(&$class, $method), $params);
	}

	public function set(array $data) 
	{
		foreach($data as $name => $value) {
			$this->__set($name, $value);
		}
	}

	public function data($name = null) 
	{
		if($name) 
			return $this->__get($name);

		return $this->to('array');
	}

	public function model() 
	{
		return $this->_model;
	}

	public function schema($field = null) 
	{
		$schema = array();

		switch(true) 
		{
			case ($this->_schema):
				$schema = $this->_schema;
			break;
			case ($model = $this->_model):
				$schema = $model::schema();
			break;
		}
		if($field) 
			return isset($schema[$field]) ? $schema[$field] : null; 
			
		return $schema;
	}

	public function errors($field = null, $value = null) 
	{
		if($field === null)
			return $this->_errors;
		if(is_array($field))
			return ($this->_errors = $field);  
			
		if($value === null && isset($this->_errors[$field]))
			return $this->_errors[$field];
		if($value !== null)
			return $this->_errors[$field] = $value;

		return $value;
	}

	public function exists() 
	{
		return $this->_exists;
	}

	public function sync($id = null, array $data = array(), array $options = array()) 
	{
		$defaults = array('materialize' => true);
		$options += $defaults;
		$model    = $this->_model;
		$key      = array();

		if($options['materialize'])
			$this->_exists = true;
		if($id && $model) {
			$key = $model::meta('key');
			$key = is_array($key) ? array_combine($key, $id) : array($key => $id);
		}
		$this->_data = $this->_updated = ($key + $data + $this->_updated);
	}

	public function increment($field, $value = 1) 
	{
		if(!isset($this->_updated[$field]))
			return $this->_updated[$field] = $value;
		if(!is_numeric($this->_updated[$field]))
			throw new UnexpectedValueException("Field '{$field}' cannot be incremented.");

		return $this->_updated[$field] += $value;
	}
	
	public function decrement($field, $value = 1) 
	{
		return $this->increment($field, $value * -1);
	}

	public function modified() 
	{
		return array_fill_keys(array_keys($this->_updated), true);
	}

	public function export() {
		return array(
			'exists'    => $this->_exists,
			'data'      => $this->_data,
			'update'    => $this->_updated,
			'increment' => $this->_increment
		);
	}

	public function to($format, array $options = array()) 
	{
		switch($format) 
		{
			case 'array':
				$data = $this->_updated;
				$rel = array_map(function($obj) { return $obj->data(); }, $this->_relationships);
				$data = array_merge($data, $rel);
				$result = Collection::toArray($data, $options);
			break;
			default:
				$result = $this;
			break;
		}  
		
		return $result;
	}
}