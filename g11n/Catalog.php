<?php

namespace arthur\g11n;

use arthur\core\Libraries;

class Catalog extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.g11n.catalog';
	
	public static function config($config = null) 
	{
		$defaults = array('scope' => null);

		if(is_array($config)) 
		{
			foreach($config as $i => $item) {
				$config[$i] += $defaults;
			}
		}          
		
		return parent::config($config);
	}

	public static function read($name, $category, $locale, array $options = array()) 
	{
		$defaults = array('scope' => null, 'lossy' => true);
		$options += $defaults;

		$category = strtok($category, '.');
		$id       = strtok('.');

		$names   = $name === true ? array_keys(static::$_configurations) : (array) $name;
		$results = array();

		foreach(Locale::cascade($locale) as $cascaded) 
		{
			foreach($names as $name) 
			{
				$adapter = static::adapter($name);

				if($result = $adapter->read($category, $cascaded, $options['scope']))
					$results += $result;
			}
		}
		if($options['lossy']) 
		{
			array_walk($results, function(&$value) {
				$value = $value['translated'];
			});
		}

		if($id)
			return isset($results[$id]) ? $results[$id] : null;

		return $results ?: null;
	}

	public static function write($name, $category, $locale, $data, array $options = array()) 
	{
		$defaults = array('scope' => null);
		$options += $defaults;

		$category = strtok($category, '.');
		$id       = strtok('.');

		if($id)
			$data = array($id => $data);

		array_walk($data, function(&$value, $key) 
		{
			if(!is_array($value) || !array_key_exists('translated', $value))
				$value = array('id' => $key, 'translated' => $value);
		});

		$adapter = static::adapter($name);
		return $adapter->write($category, $locale, $options['scope'], $data);
	}
}