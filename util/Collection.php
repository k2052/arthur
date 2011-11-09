<?php

namespace arthur\util;

class Collection extends \arthur\core\Object implements \ArrayAccess, \Iterator, \Countable 
{
	protected static $_formats = array(
		'array' => 'arthur\util\Collection::toArray'
	);
	
	protected $_data = array();
	protected $_valid = false;
	protected $_autoConfig = array('data');

	public static function formats($format, $handler = null) 
	{
		if($format === false)
			return static::$_formats = array('array' => '\arthur\util\Collection::toArray');
		if((is_null($handler)) && class_exists($format))
			return static::$_formats[] = $format;

		return static::$_formats[$format] = $handler;
	}

	protected function _init() 
	{
		parent::_init();
		unset($this->_config['data']);
	}
	
	public function invoke($method, array $params = array(), array $options = array()) 
	{
		$class    = get_class($this);
		$defaults = array('merge' => false, 'collect' => false);
		$options += $defaults;
		$data     = array();

		foreach($this as $object) {
			$value = call_user_func_array(array(&$object, $method), $params);
			($options['merge']) ? $data = array_merge($data, $value) : $data[$this->key()] = $value;
		}   
		
		return ($options['collect']) ? new $class(compact('data')) : $data;
	}

	public function __call($method, $parameters = array()) 
	{
		return $this->invoke($method, $parameters);
	}

	public function to($format, array $options = array()) 
	{
		$defaults = array('internal' => false);
		$options += $defaults;
		$data     = $options['internal'] ? $this->_data : $this;

		if(is_object($format) && is_callable($format))
			return $format($data, $options);

		if(isset(static::$_formats[$format]) && is_callable(static::$_formats[$format])) 
		{
			$handler = static::$_formats[$format];
			$handler = is_string($handler) ? explode('::', $handler, 2) : $handler;

			if(is_array($handler)) {
				list($class, $method) = $handler;
				return $class::$method($data, $options);
			}      
			
			return $handler($data, $options);
		}

		foreach(static::$_formats as $key => $handler) 
		{
			if(!is_int($key))
				continue;
			if(in_array($format, $handler::formats($format, $data, $options)))
				return $handler::to($format, $data, $options);
		}
	}

	public function find($filter, array $options = array()) 
	{
		$defaults = array('collect' => true);
		$options += $defaults;
		$data     = array_filter($this->_data, $filter);

		if($options['collect']) {
			$class = get_class($this);
			$data  = new $class(compact('data'));
		}     
		
		return $data;
	}

	public function first($filter = null) 
	{
		if(!$filter)
			return $this->rewind();

		foreach($this as $item) 
		{
			if($filter($item))
				return $item;
		}
	}

	public function each($filter) 
	{
		$this->_data = array_map($filter, $this->_data);
		return $this;
	}

	public function map($filter, array $options = array()) 
	{
		$defaults = array('collect' => true);
		$options += $defaults;
		$data     = array_map($filter, $this->_data);

		if($options['collect']) {
			$class = get_class($this);
			return new $class(compact('data'));
		}          
		
		return $data;
	}

	public function sort($sorter = 'sort', array $options = array()) 
	{
		if(is_string($sorter) && strpos($sorter, 'sort') !== false && is_callable($sorter))
			call_user_func_array($sorter, array(&$this->_data));
		else if(is_callable($sorter))
			usort($this->_data, $sorter);

		return $this;
	}

	public function offsetExists($offset) 
	{
		return isset($this->_data[$offset]);
	}

	public function offsetGet($offset) 
	{
		return $this->_data[$offset];
	}

	public function offsetSet($offset, $value) 
	{
		if(is_null($offset))
			return $this->_data[] = $value;

		return $this->_data[$offset] = $value;
	}

	public function offsetUnset($offset) 
	{
		unset($this->_data[$offset]);
		prev($this->_data);
	}


	public function rewind() 
	{
		$this->_valid = !(reset($this->_data) === false && key($this->_data) === null);
		return current($this->_data);
	}

 
	public function end() 
	{
		$this->_valid = !(end($this->_data) === false && key($this->_data) === null);
		return current($this->_data);
	}
        
	public function valid() 
	{
		return $this->_valid;
	}
       
	public function current() 
	{
		return current($this->_data);
	}
     
	public function key() 
	{
		return key($this->_data);
	}
     
	public function prev() 
	{
		if(!prev($this->_data))
			end($this->_data);
			
		return current($this->_data);
	}
     
	public function next() 
	{
		$this->_valid = !(next($this->_data) === false && key($this->_data) === null);
		return current($this->_data);
	}

	public function append($value) 
	{
		is_object($value) ? $this->_data[] =& $value : $this->_data[] = $value;
	}

	public function count() 
	{
		$count = iterator_count($this);
		$this->rewind();
		return $count;
	}

	public function keys() 
	{
		return array_keys($this->_data);
	}


	public static function toArray($data, array $options = array()) 
	{
		$defaults = array('handlers' => array());
		$options += $defaults;
		$result  = array();

		foreach($data as $key => $item) 
		{
			switch (true) 
			{
				case is_array($item):
					$result[$key] = static::toArray($item, $options);
				break;
				case (!is_object($item)):
					$result[$key] = $item;
				break;
				case (isset($options['handlers'][$class = get_class($item)])):
					$result[$key] = $options['handlers'][$class]($item);
				break;
				case (method_exists($item, 'to')):
					$result[$key] = $item->to('array');
				break;
				case ($vars = get_object_vars($item)):
					$result[$key] = static::toArray($vars, $options);
				break;
				case (method_exists($item, '__toString')):
					$result[$key] = (string) $item;
				break;
				default:
					$result[$key] = $item;
				break;
			}
		}    
		
		return $result;
	}
}