<?php

namespace arthur\util\collection;

class Filters extends \arthur\util\Collection
{
	protected static $_lazyFilters = array();
	protected $_autoConfig = array('data', 'class', 'method');
	protected $_class = null;
	protected $_method = null; 
	
	public static function apply($class, $method, $filter) 
	{
		if(class_exists($class, false))
			return $class::applyFilter($method, $filter);   
			
		static::$_lazyFilters[$class][$method][] = $filter;
	}   
	
	public static function hasApplied($class, $method) 
	{
		return isset(static::$_lazyFilters[$class][$method]);
	}  
	
	public static function run($class, $params, array $options = array()) 
	{      
		$defaults = array('class' => null, 'method' => null, 'data' => array());
		$options += $defaults;
		$lazyFilterCheck = (is_string($class) && $options['method']);      

		if(($lazyFilterCheck) && isset(static::$_lazyFilters[$class][$options['method']])) 
		{         
			$filters = static::$_lazyFilters[$class][$options['method']];
			unset(static::$_lazyFilters[$class][$options['method']]);
			$options['data'] = array_merge($filters, $options['data']);

			foreach ($filters as $filter) {
				$class::applyFilter($options['method'], $filter);
			}
		}       

		$chain = new Filters($options); 
		$next = $chain->rewind();  
		return $next($class, $params, $chain);
	}  
	
	public function next($self, $params, $chain) 
	{
		if(empty($self) || empty($chain)) 
			return parent::next();

		$next = parent::next();
		return $next($self, $params, $chain);
	} 
	
	public function method($full = false) 
	{
		return $full ? $this->_class . '::' . $this->_method : $this->_method;
	}            
}