<?php

namespace arthur\storage\cache\adapter;

class Memory extends \arthur\core\Object 
{
	protected $_cache = array();

	public function __get($variable) 
	{
		if(isset($this->{"_$variable"}))
			return $this->{"_$variable"};
	}

	public function read($key) 
	{
		$cache =& $this->_cache;

		return function($self, $params) use (&$cache) 
		{
			extract($params);

			if(is_array($key)) 
			{
				$results = array();

				foreach($key as $k) 
				{
					if(isset($cache[$k]))
						$results[$k] = $cache[$k];
				}    
				
				return $results;
			}   
			
			return isset($cache[$key]) ? $cache[$key] : null;
		};
	}

	public function write($key, $data, $expiry) 
	{
		$cache =& $this->_cache;

		return function($self, $params) use (&$cache) 
		{
			extract($params);

			if(is_array($key)) 
			{
				foreach($key as $k => &$v) {
					$cache[$k] = $v;
				} 
				
				return true;
			}    
			
			return (boolean) ($cache[$key] = $data);
		};
	}

	public function delete($key) 
	{
		$cache =& $this->_cache;

		return function($self, $params) use (&$cache) 
		{
			extract($params);
			if(isset($cache[$key])) {
				unset($cache[$key]);
				return true;
			} 
			else
				return false;
		};
	}

	public function decrement($key, $offset = 1) 
	{
		$cache =& $this->_cache;

		return function($self, $params) use (&$cache, $offset) 
		{
			extract($params);
			return $cache[$key] -= 1;
		};
	}

	public function increment($key, $offset = 1) 
	{
		$cache =& $this->_cache;

		return function($self, $params) use (&$cache, $offset) 
		{
			extract($params);
			return $cache[$key] += 1;
		};
	}

	public function clear() 
	{
		foreach($this->_cache as $key => &$value) {
			unset($this->_cache[$key]);
		}      
		
		return true;
	}

	public static function enabled() 
	{
		return true;
	}

	public function clean() 
	{
		return false;
	}
}