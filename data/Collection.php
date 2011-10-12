<?php

namespace lithium\data;

abstract class Collection extends \lithium\util\Collection 
{
	protected $_parent = null;
	protected $_pathKey = null;
	protected $_model = null;
	protected $_query = null;
	protected $_result = null;
	protected $_valid = true;
	protected $_stats = array();
	protected $_hasInitialized = false;
	protected $_schema = array();

	protected $_autoConfig = array(
		'data', 'model', 'result', 'query', 'parent', 'stats', 'pathKey', 'schema'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('data' => array(), 'model' => null);
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();

		foreach(array('data', 'classes', 'model', 'result', 'query') as $key) {
			unset($this->_config[$key]);
		}
		if($model = $this->_model) 
		{
			$options = array(
				'pathKey' => $this->_pathKey,
				'schema'  => $model::schema(),
				'exists'  => isset($this->_config['exists']) ? $this->_config['exists'] : null
			);
			$this->_data = $model::connection()->cast($this, $this->_data, $options);
		}
	}

	public function assignTo($parent, array $config = array()) 
	{
		foreach($config as $key => $val) {
			$this->{'_' . $key} = $val;
		}
		$this->_parent =& $parent;
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
			return isset($self->_schema[$field]) ? $self->_schema[$field] : null;

		return $schema;
	}

	public function offsetExists($offset) 
	{
		return ($this->offsetGet($offset) !== null);
	}

	public function rewind() 
	{
		$this->_valid = (reset($this->_data) || count($this->_data));

		if(!$this->_valid && !$this->_hasInitialized) 
		{
			$this->_hasInitialized = true;

			if($entity = $this->_populate()) {
				$this->_valid = true;
				return $entity;
			}
		}   
		
		return current($this->_data);
	}

	public function meta() 
	{
		return array('model' => $this->_model);
	}

	public function each($filter) 
	{
		if(!$this->closed())
			while ($this->next()) {}

		return parent::each($filter);
	}

	public function map($filter, array $options = array()) 
	{
		$defaults = array('collect' => true);
		$options += $defaults;

		if(!$this->closed())
			while ($this->next()) {}

		$data = parent::map($filter, $options);

		if($options['collect']) 
		{
			foreach(array('_model', '_schema', '_pathKey') as $key) {
				$data->{$key} = $this->{$key};
			}
		}      
		
		return $data;
	}

	public function sort($field = 'id', array $options = array()) 
	{
		$this->offsetGet(null);

		if(is_string($field)) 
		{
			$sorter = function ($a, $b) use ($field) 
			{
				if(is_array($a))
					$a = (object) $a;
				if(is_array($b)) 
					$b = (object) $b;

				return strcmp($a->$field, $b->$field);
			};
		} 
		else if(is_callable($field))
			$sorter = $field;

		return parent::sort($sorter, $options);
	}

	public function data() {
		return $this->to('array');
	}

	public function offsetSet($offset, $data) 
	{
		if(is_array($data) && ($model = $this->_model))
			$data = $model::connection()->cast($this, $data);
		elseif(is_object($data))
			$data->assignTo($this);
		
		return $this->_data[] = $data;
	}

	public function stats($name = null) 
	{
		if($name)
			return isset($this->_stats[$name]) ? $this->_stats[$name] : null;

		return $this->_stats;
	}

	public function close() 
	{
		if(!empty($this->_result)) 
			$this->_result = null;
	}

	public function closed() 
	{
		return empty($this->_result);
	}

	public function __destruct() 
	{
		$this->close();
	}

	abstract protected function _populate($data = null, $key = null);
}