<?php

namespace arthur\storage;

use arthur\core\Libraries;

class Session extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.storage.session';
	protected static $_strategies = 'strategy.storage.session';
	
	public static function key($name = null) 
	{
		return is_object($adapter = static::adapter($name)) ? $adapter->key() : null;
	}

	public static function isStarted($name = null) 
	{
		return is_object($adapter = static::adapter($name)) ? $adapter->isStarted() : false;
	}

	public static function read($key = null, array $options = array()) 
	{
		$defaults = array('name' => null, 'strategies' => true);
		$options += $defaults;
		$method   = ($name = $options['name']) ? static::adapter($name)->read($key, $options) : null;
		$settings = static::_config($name);

		if(!$method) 
		{
			foreach(array_keys(static::$_configurations) as $name) {
				if($method = static::adapter($name)->read($key, $options))
					break;
			}
			if(!$method || !$name) return null;
		}
		$filters = $settings['filters'] ?: array();
		$result  = static::_filter(__FUNCTION__, compact('key', 'options'), $method, $filters);

		if($options['strategies']) 
		{
			$options += array('key' => $key, 'mode' => 'LIFO', 'class' => __CLASS__);
			
			return static::applyStrategies(__FUNCTION__, $name, $result, $options);
		}    
		
		return $result;
	}

	public static function write($key, $value = null, array $options = array()) 
	{
		$defaults = array('name' => null, 'strategies' => true);
		$options += $defaults;

		if(is_resource($value) || !static::$_configurations)
			return false;
		$methods = array();

		if($name = $options['name'])
			$methods = array($name => static::adapter($name)->write($key, $value, $options));
		else 
		{
			foreach(array_keys(static::$_configurations) as $name) {
				if($method = static::adapter($name)->write($key, $value, $options))
					$methods[$name] = $method;
			}
		}
		$result   = false;
		$settings = static::_config($name);

		if($options['strategies']) {
			$options += array('key' => $key, 'class' => __CLASS__);
			$value    = static::applyStrategies(__FUNCTION__, $name, $value, $options);
		}
		$params = compact('key', 'value', 'options');

		foreach($methods as $name => $method) {
			$filters = $settings['filters'];
			$result = static::_filter(__FUNCTION__, $params, $method, $filters) || $result;
		}   
		
		return $result;
	}

	public static function delete($key, array $options = array()) 
	{
		$defaults = array('name' => null, 'strategies' => true);
		$options += $defaults;

		$methods = array();

		if($name = $options['name'])
			$methods = array($name => static::adapter($name)->delete($key, $options));
		else 
		{
			foreach(static::$_configurations as $name => $config) {
				if($method = static::adapter($name)->delete($key, $options)) 
					$methods[$name] = $method;
			}
		}
		$result  = false;
		$options += array('key' => $key, 'class' => __CLASS__);

		if($options['strategies']) {
			$options += array('key' => $key, 'class' => __CLASS__);
			$key = static::applyStrategies(__FUNCTION__, $name, $key, $options);
		}
		$params = compact('key', 'options');

		foreach($methods as $name => $method) 
		{
			$settings = static::_config($name);
			$filters  = $settings['filters'];
			$result   = static::_filter(__FUNCTION__, $params, $method, $filters) || $result;
		}    
		
		return $result;
	}

	public static function clear(array $options = array()) 
	{
		$defaults = array('name' => null, 'strategies' => true);
		$options += $defaults;
		$methods  = array();

		if($name = $options['name'])
			$methods = array($name => static::adapter($name)->clear($options));
		else 
		{
			foreach(static::$_configurations as $name => $config) {
				if($method = static::adapter($name)->clear($options))
					$methods[$name] = $method;
			}
		}
		$params = compact('options');
		$result = false;

		foreach($methods as $name => $method) 
		{
			$settings = static::_config($name);
			$filters = $settings['filters'];
			$result = static::_filter(__FUNCTION__, $params, $method, $filters) || $result;
		}
		if($options['strategies']) {
			$options += array('mode' => 'LIFO', 'class' => __CLASS__);
			return static::applyStrategies(__FUNCTION__, $name, $result, $options);
		}  
		
		return $result;
	}

	public static function check($key, array $options = array()) 
	{
		$defaults = array('name' => null, 'strategies' => true);
		$options += $defaults;
		$methods  = array();

		if($name = $options['name'])
			$methods = array($name => static::adapter($name)->check($key, $options));
		else 
		{
			foreach(static::$_configurations as $name => $config) {
				if($method = static::adapter($name)->check($key, $options))
					$methods[$name] = $method;
			}
		}
		$params = compact('key', 'options');
		$result = false;

		foreach($methods as $name => $method) 
		{
			$settings = static::_config($name);
			$filters  = $settings['filters'];
			$result   = static::_filter(__FUNCTION__, $params, $method, $filters) || $result;
		}
		if($options['strategies']) {
			$options += array('key' => $key, 'mode' => 'LIFO', 'class' => __CLASS__);
			return static::applyStrategies(__FUNCTION__, $name, $result, $options);
		}  
		
		return $result;
	}

	public static function adapter($name = null) 
	{
		if(!$name) 
		{
			if(!$names = array_keys(static::$_configurations))
				return;
			$name = end($names);
		}    
		
		return parent::adapter($name);
	}
}