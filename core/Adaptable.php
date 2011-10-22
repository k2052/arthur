<?php

namespace arthur\core;

use arthur\core\Environment;
use SplDoublyLinkedList;
use arthur\core\ConfigException;

class Adaptable extends \arthur\core\StaticObject 
{
	protected static $_configurations = array();
	protected static $_strategies = null;
	protected static $_adapters = null;

	public static function config($config = null) 
	{
		if($config && is_array($config)) {
			static::$_configurations = $config;
			return;
		}
		if($config)
			return static::_config($config);

		$result = array();
		static::$_configurations = array_filter(static::$_configurations);

		foreach(array_keys(static::$_configurations) as $key) {
			$result[$key] = static::_config($key);
		}     
		
		return $result;
	}

	public static function reset() 
	{
		static::$_configurations = array();
	}

	public static function adapter($name = null) 
	{
		$config = static::_config($name);

		if($config === null) 
			throw new ConfigException("Configuration `{$name}` has not been defined.");

		if(isset($config['object']))
			return $config['object'];

		$class    = static::_class($config, static::$_adapters);
		$settings = static::$_configurations[$name];
		$settings[0]['object'] = static::_initAdapter($class, $config);
		static::$_configurations[$name] = $settings;  
		
		return static::$_configurations[$name][0]['object'];
	}

	public static function strategies($name) 
	{
		$config = static::_config($name);

		if($config === null)
			throw new ConfigException("Configuration `{$name}` has not been defined.");
		if(!isset($config['strategies']))
			return null;
			
		$stack = new SplDoublyLinkedList();

		foreach ($config['strategies'] as $key => $strategy) {
			$arguments = array();

			if(is_array($strategy)) 
			{
				$name      = $key;
				$class     = static::_strategy($name, static::$_strategies);
				$index     = (isset($config['strategies'][$name])) ? $name : $class;
				$arguments = $config['strategies'][$index];
			} 
			else {
				$name = $strategy;
				$class = static::_strategy($name, static::$_strategies);
			}
			$stack->push(new $class($arguments));
		}   
		
		return $stack;
	}

	public static function applyStrategies($method, $name, $data, array $options = array())
	{
		$options += array('mode' => null);

		if(!$strategies = static::strategies($name))
			return $data;
		if(!count($strategies))
			return $data;

		if(isset($options['mode']) && ($options['mode'] === 'LIFO')) {
			$strategies->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO);
			unset($options['mode']);
		}

		foreach($strategies as $strategy) {
			if(method_exists($strategy, $method))
				$data = $strategy->{$method}($data, $options);
		}  
		
		return $data;
	}

	public static function enabled($name) 
	{
		if(!static::_config($name)) 
			return null;
		$adapter = static::adapter($name);
		
		return $adapter::enabled();
	}

	protected static function _initAdapter($class, array $config) 
	{
		return static::_filter(__FUNCTION__, compact('class', 'config'), function($self, $params)
		{
			return new $params['class']($params['config']);
		});
	}

	protected static function _class($config, $paths = array()) 
	{
		if(!$name = $config['adapter']) {
			$self = get_called_class();
			throw new ConfigException("No adapter set for configuration in class `{$self}`.");
		}
		if(!$class = static::_locate($paths, $name)) {
			$self = get_called_class();
			throw new ConfigException("Could not find adapter `{$name}` in class `{$self}`.");
		} 
		
		return $class;
	}

	protected static function _strategy($name, $paths = array()) 
	{
		if(!$name) {
			$self = get_called_class();
			throw new ConfigException("No strategy set for configuration in class `{$self}`.");
		}
		if(!$class = static::_locate($paths, $name)) {
			$self = get_called_class();
			throw new ConfigException("Could not find strategy `{$name}` in class `{$self}`.");
		}  
		
		return $class;
	}

	protected static function _locate($paths, $name) 
	{
		foreach((array) $paths as $path) {
			if($class = Libraries::locate($path, $name)) 
				return $class;
		}  
		
		return null;
	}

	protected static function _config($name) 
	{
		if(!isset(static::$_configurations[$name]))
			return null;
		$settings = static::$_configurations[$name];

		if(isset($settings[0]))
			return $settings[0];    
			
		$env    = Environment::get();
		$config = isset($settings[$env]) ? $settings[$env] : $settings;

		if(isset($settings[$env]) && isset($settings[true]))
			$config += $settings[true];

		static::$_configurations[$name] += array(static::_initConfig($name, $config));   
		
		return static::$_configurations[$name][0];
	}

	protected static function _initConfig($name, $config) 
	{
		$defaults = array('adapter' => null, 'filters' => array());
		return (array) $config + $defaults;
	}
}