<?php

namespace arthur\data;

use arthur\core\Libraries;

class Connections extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'data.source';

	public static function add($name, array $config = array()) 
	{
		$defaults = array(
			'type'     => null,
			'adapter'  => null,
			'login'    => '',
			'password' => ''
		);     
		
		return static::$_configurations[$name] = $config + $defaults;
	}

	public static function get($name = null, array $options = array()) 
	{
		static $mockAdapter;

		$defaults = array('config' => false, 'autoCreate' => true);
		$options += $defaults;

		if($name === false) 
		{
			if(!$mockAdapter) {
				$class = Libraries::locate('data.source', 'Mock');
				$mockAdapter = new $class();
			} 
			
			return $mockAdapter;
		}

		if(!$name)
			return array_keys(static::$_configurations);

		if(!isset(static::$_configurations[$name])) 
			return null;
		if($options['config'])
			return static::_config($name);

		$settings = static::$_configurations[$name];

		if(!isset($settings[0]['object'])) {
			if(!$options['autoCreate']) 
				return null;
		}      
		
		return static::adapter($name);
	}

	protected static function _class($config, $paths = array()) 
	{
		if(!$config['adapter'])
			$config['adapter'] = $config['type'];
		else 
			$paths = array_merge(array("adapter.data.source.{$config['type']}"), (array) $paths);

		return parent::_class($config, $paths);
	}
}