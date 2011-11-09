<?php

namespace arthur\analysis;

use Exception;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use arthur\core\Libraries;

class Inspector extends \arthur\core\StaticObject 
{
	protected static $_classes = array(
		'collection' => '\arthur\util\Collection'
	);

	protected static $_methodMap = array(
		'name'      => 'getName',
		'start'     => 'getStartLine',
		'end'       => 'getEndLine',
		'file'      => 'getFileName',
		'comment'   => 'getDocComment',
		'namespace' => 'getNamespaceName',
		'shortName' => 'getShortName'
	);

	public static function type($identifier) 
	{
		$identifier = ltrim($identifier, '\\');

		if(strpos($identifier, '::'))
			return (strpos($identifier, '$') !== false) ? 'property' : 'method';
		if(is_readable(Libraries::path($identifier))) 
		{
			if(class_exists($identifier) && in_array($identifier, get_declared_classes()))
				return 'class';
		}                     
		
		return 'namespace';
	}

	public static function info($identifier, $info = array()) 
	{
		$info   = $info ?: array_keys(static::$_methodMap);
		$type   = static::type($identifier);
		$result = array();
		$class  = null;

		if($type == 'method' || $type == 'property') 
		{
			list($class, $identifier) = explode('::', $identifier);

			try {
				$classInspector = new ReflectionClass($class);
			} 
			catch(Exception $e) {
				return null;
			}

			if($type == 'property') {
				$identifier = substr($identifier, 1);
				$accessor   = 'getProperty';
			}
			else {
				$identifier = str_replace('()', '', $identifier);
				$accessor   = 'getMethod';
			}

			try {
				$inspector = $classInspector->{$accessor}($identifier);
			} 
			catch(Exception $e) {
				return null;
			}  
			
			$result['modifiers'] = static::_modifiers($inspector);
		} 
		elseif($type == 'class')
			$inspector = new ReflectionClass($identifier);
		else
			return null;

		foreach($info as $key) 
		{
			if(!isset(static::$_methodMap[$key]))
				continue;
			if(method_exists($inspector, static::$_methodMap[$key])) 
			{
				$setAccess = (
					($type == 'method' || $type == 'property') &&
					array_intersect($result['modifiers'], array('private', 'protected')) != array()
					&& method_exists($inspector, 'setAccessible')
				);

				if($setAccess)
					$inspector->setAccessible(true);
				$result[$key] = $inspector->{static::$_methodMap[$key]}();

				if($setAccess) {
					$inspector->setAccessible(false);
					$setAccess = false;
				}
			}
		}

		if($type == 'property' && !$classInspector->isAbstract()) 
		{
			$inspector->setAccessible(true);

			try {
				$result['value'] = $inspector->getValue(static::_class($class));
			} 
			catch(Exception $e) {
				return null;
			}
		}

		if(isset($result['start']) && isset($result['end']))
			$result['length'] = $result['end'] - $result['start'];
		if(isset($result['comment']))
			$result += Docblock::comment($result['comment']);

		return $result;
	}

	public static function executable($class, array $options = array()) 
	{
		$defaults = array(
			'self'         => true, 'filter' => true, 'methods' => array(),
			'empty'        => array(' ', "\t", '}', ')', ';'), 'pattern' => null,
			'blockOpeners' => array('switch (', 'try {', '} else {', 'do {', '} while')
		);
		$options += $defaults;

		if(empty($options['pattern']) && $options['filter']) 
		{
			$pattern = str_replace(' ', '\s*', join('|', array_map(
				function($str) { return preg_quote($str, '/'); },
				$options['blockOpeners']
			)));
			$pattern = join('|', array(
				"({$pattern})",
				"\\$(.+)\($",
				"\s*['\"]\w+['\"]\s*=>\s*.+[\{\(]$",
				"\s*['\"]\w+['\"]\s*=>\s*['\"]*.+['\"]*\s*"
			));
			$options['pattern'] = "/^({$pattern})/";
		}

		if(!$class instanceof ReflectionClass)
			$class = new ReflectionClass(is_object($class) ? get_class($class) : $class);

		$options += array('group' => false);
		$result   = array_filter(static::methods($class, 'ranges', $options));

		if($options['filter'] && $class->getFileName()) 
		{
			$file   = explode("\n", "\n" . file_get_contents($class->getFileName()));
			$lines  = array_intersect_key($file, array_flip($result));
			$result = array_keys(array_filter($lines, function($line) use ($options) 
			{
				$line  = trim($line);
				$empty = (strpos($line, '//') === 0 || preg_match($options['pattern'], $line));
				return $empty ? false : (str_replace($options['empty'], '', $line) != '');
			}));
		}        
		
		return $result;
	}

	public static function methods($class, $format = null, array $options = array()) 
	{
		$defaults = array('methods' => array(), 'group' => true, 'self' => true);
		$options += $defaults;

		if(!(is_object($class) && $class instanceof ReflectionClass)) 
		{
			try {
				$class = new ReflectionClass($class);
			} 
			catch(ReflectionException $e) {
				return null;
			}
		}
		$options += array('names' => $options['methods']);
		$methods  = static::_items($class, 'getMethods', $options);
		$result   = array();

		switch($format) 
		{
			case null:
				return $methods;
			case 'extents':
				if($methods->getName() == array()
					return array();

				$extents = function($start, $end) { return array($start, $end); };
				$result  = array_combine($methods->getName(), array_map(
					$extents, $methods->getStartLine(), $methods->getEndLine()
				));
			break;
			case 'ranges':
				$ranges = function($lines) 
				{
					list($start, $end) = $lines;
					return ($end <= $start + 1) ? array() : range($start + 1, $end - 1);
				};
				$result = array_map($ranges, static::methods(
					$class, 'extents', array('group' => true) + $options
				));
			break;
		}

		if($options['group'])
			return $result;

		$tmp    = $result;
		$result = array();

		array_map(function($ln) use (&$result) { $result = array_merge($result, $ln); }, $tmp);
		return $result;
	}

	public static function properties($class, array $options = array()) 
	{
		$defaults = array('properties' => array(), 'self' => true);
		$options += $defaults;

		if(!(is_object($class) && $class instanceof ReflectionClass)) 
		{
			try {
				$class = new ReflectionClass($class);
			} 
			catch(ReflectionException $e) {
				return null;
			}
		}
		$options += array('names' => $options['properties']);

		return static::_items($class, 'getProperties', $options)->map(function($item) 
		{
			$class     = __CLASS__;
			$modifiers = array_values($class::invokeMethod('_modifiers', array($item)));
			$setAccess = (
				array_intersect($modifiers, array('private', 'protected')) != array()
			);
			if($setAccess)
				$item->setAccessible(true);

			$result = compact('modifiers') + array(
				'docComment' => $item->getDocComment(),
				'name'       => $item->getName(),
				'value'      => $item->getValue($item->getDeclaringClass())
			);
			if($setAccess)
				$item->setAccessible(false);

			return $result;
		}, array('collect' => false));
	}

	public static function lines($data, $lines) 
	{
		if(!strpos($data, PHP_EOL)) 
		{
			if(!file_exists($data)) 
			{
				$data = Libraries::path($data);
				if(!file_exists($data))
					return null;
			}    
			
			$data = PHP_EOL . file_get_contents($data);
		}          
		
		$c = explode(PHP_EOL, $data);

		if(!count($c) || !count($lines))
			return null;

		return array_intersect_key($c, array_combine($lines, array_fill(0, count($lines), null)));
	}

	public static function parents($class, array $options = array()) 
	{
		$defaults = array('autoLoad' => false);
		$options += $defaults;
		$class    = is_object($class) ? get_class($class) : $class;

		if(!class_exists($class, $options['autoLoad']))
			return false;

		return class_parents($class);
	}
	
	public static function classes(array $options = array()) 
	{
		$defaults = array('group' => 'classes', 'file' => null);
		$options += $defaults;

		$list    = get_declared_classes();
		$files   = get_included_files();
		$classes = array();

		if($file = $options['file']) 
		{
			$loaded = static::_instance('collection', array('data' => array_map(
				function($class) { return new ReflectionClass($class); }, $list
			)));
			$classFiles = $loaded->getFileName();

			if(in_array($file, $files) && !in_array($file, $classFiles))
				return array();

			if(!in_array($file, $classFiles)) 
			{
				include $file;
				$list = array_diff(get_declared_classes(), $list);
			} 
			else {
				$filter = function($class) use ($file) { return $class->getFileName() == $file; };
				$list   = $loaded->find($filter)->getName();
			}
		}

		foreach($list as $class) 
		{
			$inspector = new ReflectionClass($class);

			if($options['group'] == 'classes')
				$inspector->getFileName() ? $classes[$class] = $inspector->getFileName() : null;
			else if($options['group'] == 'files')
				$classes[$inspector->getFileName()][] = $inspector;
		}     
		
		return $classes;
	}

	public static function dependencies($classes, array $options = array()) 
	{
		$defaults = array('type' => null);
		$options += $defaults;
		$static   = $dynamic = array();
		$trim     = function($c) { return trim(trim($c, '\\')); };
		$join     = function ($i) { return join('', $i); };

		foreach((array) $classes as $class) 
		{
			$data = explode("\n", file_get_contents(Libraries::path($class)));
			$data = "<?php \n" . join("\n", preg_grep('/^\s*use /', $data)) . "\n ?>";

			$classes = array_map($join, Parser::find($data, 'use *;', array(
				'return'      => 'content',
				'lineBreaks'  => true,
				'startOfLine' => true,
				'capture'     => array('T_STRING', 'T_NS_SEPARATOR')
			)));

			if($classes)
				$static = array_unique(array_merge($static, array_map($trim, $classes))); 
				
			$classes = static::info($class . '::$_classes', array('value'));

			if(isset($classes['value']))
				$dynamic = array_merge($dynamic, array_map($trim, array_values($classes['value'])));
		}

		if(empty($options['type']))
			return array_unique(array_merge($static, $dynamic));

		$type = $options['type'];  
		
		return isset(${$type}) ? ${$type} : null;
	}

	protected static function _class($class) 
	{
		if(!class_exists($class))
			throw new RuntimeException(sprintf('Class `%s` could not be found.', $class));

		return unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
	}

	protected static function _items($class, $method, $options) 
	{
		$defaults = array('names' => array(), 'self' => true, 'public' => true);
		$options += $defaults;

		$params = array(
			'getProperties' => ReflectionProperty::IS_PUBLIC | (
				$options['public'] ? 0 : ReflectionProperty::IS_PROTECTED
			)
		);
		$data = isset($params[$method]) ? $class->{$method}($params[$method]) : $class->{$method}();

		if(!empty($options['names'])) 
		{
			$data = array_filter($data, function($item) use ($options) {
				return in_array($item->getName(), (array) $options['names']);
			});
		}

		if($options['self']) 
		{
			$data = array_filter($data, function($item) use ($class) {
				return ($item->getDeclaringClass()->getName() == $class->getName());
			});
		}

		if($options['public'])
			$data = array_filter($data, function($item) { return $item->isPublic(); });

		return static::_instance('collection', compact('data'));
	}

	protected static function _modifiers($inspector, $list = array()) 
	{
		$list = $list ?: array('public', 'private', 'protected', 'abstract', 'final', 'static');   
		
		return array_filter($list, function($modifier) use ($inspector) 
		{
			$method = 'is' . ucfirst($modifier);
			return (method_exists($inspector, $method) && $inspector->{$method}());
		});
	}
}