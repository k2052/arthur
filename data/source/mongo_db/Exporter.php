<?php

namespace lithium\data\source\mongo_db;

use lithium\util\Set;

class Exporter extends \lithium\core\StaticObject 
{
	protected static $_classes = array(
		'array' => 'lithium\data\collection\DocumentArray'
	);

	protected static $_commands = array(
		'create'    => null,
		'update'    => '$set',
		'increment' => '$inc',
		'remove'    => '$unset',
		'rename'    => '$rename'
	);

	protected static $_types = array(
		'MongoId'      => 'id',
		'MongoDate'    => 'date',
		'MongoCode'    => 'code',
		'MongoBinData' => 'binary',
		'datetime'     => 'date',
		'timestamp'    => 'date',
		'int'          => 'integer'
	);

	public static function get($type, $export, array $options = array()) 
	{
		$defaults = array('whitelist' => array());
		$options += $defaults;

		if(!method_exists(get_called_class(), $method = "_{$type}") || !$export)
			return;

		return static::$method($export, array('finalize' => true) + $options);
	}

	public static function cast($data, $schema, $database, array $options = array()) 
	{
		$defaults = array(
			'handlers' => array(),
			'model'    => null,
			'arrays'   => true,
			'pathKey'  => null
		);
		$options += $defaults;

		foreach($data as $key => $value) 
		{
			$pathKey = $options['pathKey'] ? "{$options['pathKey']}.{$key}" : $key;

			$field = isset($schema[$pathKey]) ? $schema[$pathKey] : array();
			$field += array('type' => null, 'array' => null);
			$data[$key] = static::_cast($value, $field, $database, compact('pathKey') + $options);
		}
		
		return $data;
	}

	protected static function _cast($value, $def, $database, $options) 
	{
		if(is_object($value))
			return $value;

		$pathKey = $options['pathKey'];

		$typeMap = static::$_types;
		$type    = isset($typeMap[$def['type']]) ? $typeMap[$def['type']] : $def['type'];

		$isObject = ($type == 'object');
		$isArray  = (is_array($value) && $def['array'] !== false && !$isObject);
		$isArray  = $def['array'] || $isArray;

		if(isset($options['handlers'][$type]) && $handler = $options['handlers'][$type])
			$value = $isArray ? array_map($handler, (array) $value) : $handler($value);
		if(!$options['arrays'])
			return $value;                                                               

		if(!is_array($value) && !$def['array'])
			return $value;

		if($def['array']) 
		{
			$opts  = array('class' => 'array') + $options;
			$value = ($value === null) ? array() : $value;
			$value = is_array($value) ? $value : array($value);
		} 
		elseif(is_array($value)) {
			$arrayType = !$isObject && (array_keys($value) === range(0, count($value) - 1));
			$opts = $arrayType ? array('class' => 'array') + $options : $options;
		}

		unset($opts['handlers'], $opts['first']);
		return $database->item($options['model'], $value, compact('pathKey') + $opts);
	}

	public static function toCommand($changes) 
	{
		$result = array();

		foreach(static::$_commands as $from => $to) 
		{
			if(!isset($changes[$from]))
				continue;
			if(!$to)
				$result = array_merge($result, $changes[$from]);
			$result[$to] = $changes[$from];
		}
		unset($result['$set']['_id']);                      
		
		return $result;
	}

	protected static function _create($export, array $options) 
	{
		$export += array('data' => array(), 'update' => array(), 'key' => '');
		$data    = $export['update'];

		$result    = array('create' => array());
		$localOpts = array('finalize' => false) + $options;

		foreach($data as $key => $val) 
		{
			if(is_object($val) && method_exists($val, 'export'))
				$data[$key] = static::_create($val->export($options), $localOpts);
		}                                                                     
		
		return ($options['finalize']) ? array('create' => $data) : $data;
	}

	protected static function _update($export) 
	{
		$export += array(
			'data' => array(),
			'update' => array(),
			'remove' => array(),
			'rename' => array(),
			'key' => ''
		);
		$path   = $export['key'] ? "{$export['key']}." : "";
		$result = array('update' => array(), 'remove' => array());
		$left   = static::_diff($export['data'], $export['update']);
		$right  = static::_diff($export['update'], $export['data']);

		$objects = array_filter($export['update'], function($value) 
		{
			return (is_object($value) && method_exists($value, 'export'));
		});

		array_map(function($key) use (&$left) { unset($left[$key]); }, array_keys($right));
		foreach($left as $key => $value) {
			$result = static::_append($result, "{$path}{$key}", $value, 'remove');
		}

		foreach(array_merge($right, $objects) as $key => $value) 
		{
			$original = $export['data'];
			$isArray  = is_object($value) && get_class($value) == static::$_classes['array'];   
			
			if($isArray && isset($original[$key]) && $value->data() != $original[$key]->data())
				 $value = $value->data();
			$result = static::_append($result, "{$path}{$key}", $value, 'update');
		}

		return array_filter($result);
	}

	protected static function _diff($left, $right) 
	{
		$result = array();

		foreach($left as $key => $value) {
			if(!isset($right[$key]) || $left[$key] !== $right[$key])
				$result[$key] = $value;
		}    
		
		return $result;
	}

	protected static function _append($changes, $key, $value, $change) 
	{
		$options = array('finalize' => false);

		if(!is_object($value) || !method_exists($value, 'export')) {
			$changes[$change][$key] = ($change == 'update') ? $value : true;
			return $changes;
		}
		if($value->exists()) 
		{
			if($change == 'update') 
			{
				$export        = $value->export();
				$export['key'] = $key;  
				
				return Set::merge($changes, static::_update($export));
			}

			$children = static::_update($value->export());
			if(!empty($children))
				return Set::merge($changes, $children);

			$changes[$change][$key] = true;  
			
			return $changes;
		}
		$changes['update'][$key] = static::_create($value->export(), $options);     
		
		return $changes;
	}
}