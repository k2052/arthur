<?php

namespace arthur\tests\mocks\data\source\database\adapter;

class MockAdapter extends \arthur\data\source\Database 
{
	protected $_records = array();
	protected $_columns = array();
	protected $_autoConfig = array('records', 'columns');
	protected $_pointer = 0;

	public function __construct(array $config = array()) 
	{
		$defaults =  array('records' => array(), 'columns' => array());
		$config['autoConnect'] = false;
		parent::__construct((array) $config + $defaults);
	}

	public function connect() 
	{
		return true;
	}

	public function disconnect() 
	{
		return true;
	}

	public function sources($class = null) { }

	public function encoding($encoding = null) 
	{
		return $encoding ?: '';
	}

	public function describe($entity, array $meta = array()) 
	{
		return array();
	}

	public function create($record, array $options = array()) 
	{
		return true;
	}

	public function read($query, array $options = array()) {
		return true;
	}

	public function update($query, array $options = array()) 
	{
		return true;
	}

	public function delete($query, array $options = array()) 
	{
		return true;
	}

	public function result($type, $resource, $context) 
	{
		$return = null;
		if(array_key_exists($this->_pointer, $this->_records))
			$return = $this->_records[$this->_pointer++];

		return $return;
	}

	public function error() 
	{
		return false;
	}

	public function name($name) 
	{
		return $name;
	}

	public function value($value, array $schema = array()) 
	{
		if(is_array($value))
			return parent::value($value, $schema);

		return $value;
	}

	public function schema($query, $resource = null, $context = null) 
	{
		return $this->_columns;
	}

	public function conditions($conditions, $context, array $options = array()) 
	{
		return $conditions;
	}

	public function fields($fields, $context) 
	{
		if(empty($fields))
			return $context->fields();

		return $fields;
	}

	public function limit($limit, $context) 
	{
		if(empty($limit)) 
			return ''; 
			
		return $limit;
	}

	public function order($order, $context) 
	{
		if(empty($order))
			return '';

		return $order;
	}

	public function renderCommand($type, $data = null, $context = null) 
	{
		return '';
	}

	public function key() { }

	protected function _execute($sql) 
	{
		return $sql;
	}

	protected function _insertId($query) { }
}