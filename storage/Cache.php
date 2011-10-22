<?php

namespace arthur\storage;

class Cache extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.storage.cache';
	protected static $_strategies = 'strategy.storage.cache';

	public static function key($key, $data = array()) 
	{
		return is_object($key) ? $key($data) : $key;
	}

	public static function write($name, $key, $data, $expiry = null, array $options = array()) 
	{
		$options += array('conditions' => null, 'strategies' => true);
		$settings = static::config();

		if(!isset($settings[$name]))
			return false;

		$conditions = $options['conditions'];
		if(is_callable($conditions) && !$conditions())
			return false;
		$key = static::key($key);

		if(is_array($key)) {
			$expiry = $data;
			$data   = null;
		}

		if($options['strategies']) {
			$options = array('key' => $key, 'class' => __CLASS__);
			$data = static::applyStrategies(__FUNCTION__, $name, $data, $options);
		}

		$method = static::adapter($name)->write($key, $data, $expiry);
		$params = compact('key', 'data', 'expiry');        
		
		return static::_filter(__FUNCTION__, $params, $method, $settings[$name]['filters']);
	}

	public static function read($name, $key, array $options = array()) 
	{
		$options += array('conditions' => null, 'strategies' => true, 'write' => null);
		$settings = static::config();

		if(!isset($settings[$name]))
			return false;

		$conditions = $options['conditions'];
		if(is_callable($conditions) && !$conditions())
			return false;

		$key     = static::key($key);
		$method  = static::adapter($name)->read($key);
		$params  = compact('key');
		$filters = $settings[$name]['filters'];
		$result  = static::_filter(__FUNCTION__, $params, $method, $filters);

		if($result === null && $options['write']) 
		{
			$write = (is_callable($options['write'])) ? $options['write']() : $options['write'];
			list($expiry, $value) = each($write);

			return static::write($name, $key, $value, $expiry);
		}

		if($options['strategies']) {
			$options = array('key' => $key, 'mode' => 'LIFO', 'class' => __CLASS__);
			$result = static::applyStrategies(__FUNCTION__, $name, $result, $options);
		}  
		
		return $result;
	}

	public static function delete($name, $key, array $options = array()) 
	{
		$options += array('conditions' => null, 'strategies' => true);
		$settings = static::config();

		if(!isset($settings[$name]))
			return false;

		$conditions = $options['conditions'];
		if(is_callable($conditions) && !$conditions())
			return false;

		$key     = static::key($key);
		$method  = static::adapter($name)->delete($key);
		$filters = $settings[$name]['filters'];

		if($options['strategies']) {
			$options += array('key' => $key, 'class' => __CLASS__);
			$key = static::applyStrategies(__FUNCTION__, $name, $key, $options);
		}    
		
		return static::_filter(__FUNCTION__, compact('key'), $method, $filters);
	}

	public static function increment($name, $key, $offset = 1, array $options = array()) 
	{
		$options += array('conditions' => null);
		$settings = static::config();

		if(!isset($settings[$name]))
			return false;
		$conditions = $options['conditions'];

		if(is_callable($conditions) && !$conditions())
			return false;

		$key     = static::key($key);
		$method  = static::adapter($name)->increment($key, $offset);
		$params  = compact('key', 'offset');
		$filters = $settings[$name]['filters'];

		return static::_filter(__FUNCTION__, $params, $method, $filters);
	}

	public static function decrement($name, $key, $offset = 1, array $options = array()) 
	{
		$options += array('conditions' => null);
		$settings = static::config();

		if(!isset($settings[$name])) 
			return false;

		$conditions = $options['conditions'];

		if(is_callable($conditions) && !$conditions())
			return false;

		$key     = static::key($key);
		$method  = static::adapter($name)->decrement($key, $offset);
		$params  = compact('key', 'offset');
		$filters = $settings[$name]['filters'];

		return static::_filter(__FUNCTION__, $params, $method, $filters);
	}
	
	public static function clean($name) 
	{
		$settings = static::config();
		return (isset($settings[$name])) ? static::adapter($name)->clean() : false;
	}

	public static function clear($name) 
	{
		$settings = static::config();
		return (isset($settings[$name])) ? static::adapter($name)->clear() : false;
	}
}