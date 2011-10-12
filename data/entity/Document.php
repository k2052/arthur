<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\data\entity;

use UnexpectedValueException;

class Document extends \lithium\data\Entity implements \Iterator, \ArrayAccess 
{
	protected $_pathKey = null;
	protected $_stats = array();
	protected $_valid = false;

	protected function _init() 
	{
		parent::_init();

		$data           = (array) $this->_data;
		$this->_data    = array();
		$this->_updated = array();

		$this->set($data, array('init' => true));
		$this->sync(null, array(), array('materialize' => false));
		unset($this->_autoConfig);
	}

	public function &__get($name) 
	{
		if(strpos($name, '.'))
			return $this->_getNested($name);

		if(isset($this->_embedded[$name]) && !isset($this->_relationships[$name])) {
			$item = isset($this->_data[$name]) ? $this->_data[$name] : array();
			var_dump($this->_relationships[$name]);
			die('#WINNING');
		}
		$result = parent::__get($name);

		if($result !== null || array_key_exists($name, $this->_updated)) 
			return $result;

		if($field = $this->schema($name)) 
		{
			if(isset($field['default'])) {
				$this->set(array($name => $field['default']));
				return $this->_updated[$name];
			}
			if(isset($field['array']) && $field['array'] && ($model = $this->_model)) 
			{
				$this->_updated[$name] = $model::connection()->item($model, array(), array(
					'class' => 'array'
				));
				return $this->_updated[$name];
			}
		}          
		
		$null = null;
		return $null;
	}

	public function export() 
	{
		foreach($this->_updated as $key => $val) 
		{
			if(is_a($val, __CLASS__)) {
				$path = $this->_pathKey ? "{$this->_pathKey}." : '';
				$this->_updated[$key]->_pathKey = "{$path}{$key}";
			}
		}        
		
		return parent::export() + array('key' => $this->_pathKey);
	}

	public function sync($id = null, array $data = array(), array $options = array()) 
	{
		$defaults = array('recursive' => true);
		$options += $defaults;

		if(!$options['recursive'])
			return parent::sync($id, $data, $options);

		foreach($this->_updated as $key => $val) 
		{
			if(is_object($val) && method_exists($val, 'sync')) {
				$nested = isset($data[$key]) ? $data[$key] : array();
				$this->_updated[$key]->sync(null, $nested, $options);
			}
		}
		parent::sync($id, $data, $options);
	}

	protected function _relation($classType, $key, $data, $options = array()) 
	{
		return parent::_relation($classType, $key, $data, array('exists' => false) + $options);
	}

	protected function &_getNested($name) 
	{
		$current = $this;
		$null    = null;
		$path    = explode('.', $name);
		$length  = count($path) - 1;

		foreach($path as $i => $key) 
		{
			if(!isset($current[$key]))
				return $null;
			$current = $current[$key];

			if(is_scalar($current) && $i < $length)
				return $null;
		}     
		
		return $current;
	}

	public function __set($name, $value = null) 
	{
		$this->set(array($name => $value));
	}

	protected function _setNested($name, $value) 
	{
		$current =& $this;
		$path    = explode('.', $name);
		$length  = count($path) - 1;

		for($i = 0; $i < $length; $i++) 
		{
			$key = $path[$i];

			if(isset($current[$key]))
				$next =& $current[$key];
			else {
				unset($next);
				$next = null;
			}

			if($next === null && ($model = $this->_model)) {
				$current->set(array($key => $model::connection()->item($model)));
				$next =& $current->{$key};
			}
			$current =& $next;
		}

		if(is_object($current))
			$current->set(array(end($path) => $value));
	}

	public function __isset($name) 
	{
		return isset($this->_updated[$name]);
	}

	public function __unset($name) 
	{
		unset($this->_updated[$name]);
	}

	public function set(array $data, array $options = array()) 
	{
		$defaults = array('init' => false);
		$options += $defaults;

		foreach($data as $key => $val) 
		{
			if(strpos($key, '.')) {
				$this->_setNested($key, $val);
				unset($data[$key]);
			}
			unset($this->_increment[$key]);
		}

		if($data && $model = $this->_model) {
			$pathKey = $this->_pathKey;
			$data    = $model::connection()->cast($this, $data, compact('pathKey'));
		}

		foreach($data as $key => $value) 
		{
			if(is_a($value, __CLASS__)) 
			{
				if(!$options['init']) 
					$value->_exists = false;

				$value->_pathKey = ($this->_pathKey ? "{$this->_pathKey}." : '') . $key;
				$value->_model   = $value->_model ?: $this->_model;
				$value->_schema  = $value->_schema ?: $this->_schema;
			}
		}          
		
		$this->_updated = $data + $this->_updated;
	}

	public function offsetGet($offset) 
	{
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value) 
	{
		return $this->set(array($offset => $value));
	}

	public function offsetExists($offset) 
	{
		return $this->__isset($offset);
	}

	public function offsetUnset($key) 
	{
		return $this->__unset($key);
	}

	public function rewind() 
	{
		reset($this->_updated);
		$this->_valid = (count($this->_updated) > 0);  
		
		return current($this->_updated);
	}

	public function valid() 
	{
		return $this->_valid;
	}

	public function current() 
	{
		$current = current($this->_data);     
		
		return isset($this->_removed[key($this->_data)]) ? null : $current;
	}

	public function key() 
	{
		$key = key($this->_data);
		
		return isset($this->_removed[$key]) ? false : $key;
	}

	public function to($format, array $options = array()) 
	{
		$defaults = array('handlers' => array(
			'MongoId'   => function($value) { return (string) $value; },
			'MongoDate' => function($value) { return $value->sec; }
		));
		$options += $defaults;
		$options['internal'] = false;    
		
		return parent::to($format, $options);
	}

	public function next() 
	{
		$prev         = key($this->_data);
		$this->_valid = (next($this->_data) !== false);
		$cur          = key($this->_data);

		if(isset($this->_removed[$cur]))
			return $this->next();
		if(!$this->_valid && $cur !== $prev && $cur !== null)
			$this->_valid = true;

		return $this->_valid ? $this->__get(key($this->_data)) : null;
	}

	public function increment($field, $value = 1) 
	{
		if(!isset($this->_increment[$field]))
			$this->_increment[$field] = 0;

		$this->_increment[$field] += $value;

		if(!is_numeric($this->_updated[$field]))
			throw new UnexpectedValueException("Field `{$field}` cannot be incremented.");

		return $this->_updated[$field] += $value;
	}
}