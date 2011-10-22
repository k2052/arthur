<?php

namespace arthur\core;

use arthur\core\Libraries;
use arthur\util\collection\Filters;  

class Object 
{
  protected $_config = array();
	protected $_autoConfig = array();
	protected $_methodFilters = array();
	protected static $_parents = array();
	
	public function __construct(array $config = array()) 
	{
		$defaults = array('init' => true);
		$this->_config = $config + $defaults;

		if($this->_config['init']) $this->_init();
	}           
	
	protected function _init() 
	{
		foreach($this->_autoConfig as $key => $flag) 
		{
			if(!isset($this->_config[$key]) && !isset($this->_config[$flag]))
				continue;

			if($flag === 'merge')
				$this->{"_{$key}"} = $this->_config[$key] + $this->{"_{$key}"};
			else 
				$this->{"_$flag"} = $this->_config[$flag];
		}
	}    
	
	public function applyFilter($method, $filter = null) 
	{
		foreach((array) $method as $m) 
		{
			if(!isset($this->_methodFilters[$m]))
				$this->_methodFilters[$m] = array(); 
				
			$this->_methodFilters[$m][] = $filter;
		}
	}   
	
	public function invokeMethod($method, $params = array())
	{
		switch(count($params)) 
		{
			case 0:
				return $this->{$method}();
			case 1:
				return $this->{$method}($params[0]);
			case 2:
				return $this->{$method}($params[0], $params[1]);
			case 3:
				return $this->{$method}($params[0], $params[1], $params[2]);
			case 4:
				return $this->{$method}($params[0], $params[1], $params[2], $params[3]);
			case 5:
				return $this->{$method}($params[0], $params[1], $params[2], $params[3], $params[4]);
			default:
				return call_user_func_array(array(&$this, $method), $params);
		}
	}   
	
	public static function __set_state($data) 
	{
		$class = get_called_class();
		$object = new $class();

		foreach($data as $property => $value) {
			$object->{$property} = $value;
		}
		return $object;
	}  
	
	protected function _instance($name, array $options = array()) 
	{
		if(is_string($name) && isset($this->_classes[$name]))
			$name = $this->_classes[$name];

		return Libraries::instance(null, $name, $options);
	}   
	
	protected function _filter($method, $params, $callback, $filters = array()) 
	{
		list($class, $method) = explode('::', $method);

		if(empty($this->_methodFilters[$method]) && empty($filters))
			return $callback($this, $params, null);

		$f = isset($this->_methodFilters[$method]) ? $this->_methodFilters[$method] : array();
		$data = array_merge($f, $filters, array($callback));    
		
		return Filters::run($this, $params, compact('data', 'class', 'method'));
	}   
	
	protected static function _parents() 
	{
		$class = get_called_class();

		if(!isset(self::$_parents[$class]))
			self::$_parents[$class] = class_parents($class);

		return self::$_parents[$class];
	}  
	
	protected function _stop($status = 0) 
	{
		exit($status);
	}
}