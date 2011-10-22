<?php

namespace arthur\core;

use RuntimeException;
use arthur\util\String;
use arthur\core\ConfigException;
use arthur\core\ClassNotFoundException;

class Libraries 
{  
	protected static $_methodFilters = array();
	protected static $_configurations = array();

	protected static $_paths = array(
		'adapter' => array(
			'{:library}\extensions\adapter\{:namespace}\{:class}\{:name}',
			'{:library}\{:namespace}\{:class}\adapter\{:name}' => array('libraries' => 'arthur')
		),
		'command' => array(
			'{:library}\extensions\command\{:namespace}\{:class}\{:name}',
			'{:library}\console\command\{:namespace}\{:class}\{:name}' => array(
				'libraries' => 'arthur'
			)
		),
		'controllers' => array(
			'{:library}\controllers\{:namespace}\{:class}\{:name}Controller'
		),
		'data' => array(
			'{:library}\extensions\data\{:namespace}\{:class}\{:name}',
			'{:library}\data\{:namespace}\{:class}\adapter\{:name}' => array(
				'libraries' => 'arthur'
			),
			'{:library}\data\{:namespace}\{:class}\{:name}' => array('libraries' => 'arthur'),
			'{:library}\data\{:class}\adapter\{:name}' => array('libraries' => 'arthur')
		),
		'helper' => array(
			'{:library}\extensions\helper\{:name}',
			'{:library}\template\helper\{:name}' => array('libraries' => 'arthur')
		),
		'libraries' => array(
			'{:app}/libraries/{:name}',
			'{:root}/{:name}'
		),
		'models' => array(
			'{:library}\models\{:name}'
		),
		'strategy' => array(
			'{:library}\extensions\strategy\{:namespace}\{:class}\{:name}',
			'{:library}\extensions\strategy\{:class}\{:name}',
			'{:library}\{:namespace}\{:class}\strategy\{:name}' => array('libraries' => 'arthur')
		),
		'socket' => array(
			'{:library}\extensions\net\socket\{:name}',
			'{:library}\extensions\socket\{:name}',
			'{:library}\net\socket\{:name}'
		),
		'test' => array(
			'{:library}\extensions\test\{:namespace}\{:class}\{:name}',
			'{:library}\test\{:namespace}\{:class}\{:name}' => array('libraries' => 'arthur')
		),
		'tests' => array(
			'{:library}\tests\{:namespace}\{:class}\{:name}Test'
		)
	);

	protected static $_default;
	protected static $_cachedPaths = array();

	public static function paths($path = null)
	{
		if(empty($path))
			return static::$_paths;
		if(is_string($path))
			return isset(static::$_paths[$path]) ? static::$_paths[$path] : null;

		static::$_paths = array_filter(array_merge(static::$_paths, (array) $path));
	}
	
	public static function add($name, array $config = array()) 
	{
		$defaults = array(
			'path' => null,
			'prefix' => $name . "\\",
			'suffix' => '.php',
			'loader' => null,
			'includePath' => false,
			'transform' => null,
			'bootstrap' => true,
			'defer' => false,
			'default' => false
		);
		if($name === 'arthur') 
		{
			$defaults['defer'] = true;
			$defaults['bootstrap'] = false;
			$defaults['path'] = dirname(__DIR__);
			$defaults['loader'] = 'arthur\core\Libraries::load';
		}
		if(isset($config['default']) && $config['default']) 
		{
			static::$_default = $name;
			$defaults['path'] = ARTHUR_APP_PATH;
			$defaults['bootstrap'] = false;
			$defaults['resources'] = ARTHUR_APP_PATH . '/resources';
		}
		$config += $defaults;

		if(!$config['path']) 
		{
			if(!$config['path'] = static::_locatePath('libraries', compact('name'))) 
				throw new ConfigException("Library `{$name}` not found.");
		}
		$config['path'] = str_replace('\\', '/', $config['path']);
		static::_configure(static::$_configurations[$name] = $config);          
		
		return $config;
	}

	protected static function _configure($config) 
	{
		if($config['includePath']) {
			$path = ($config['includePath'] === true) ? $config['path'] : $config['includePath'];
			set_include_path(get_include_path() . PATH_SEPARATOR . $path);
		}
		if($config['bootstrap'] === true) {
			$path = "{$config['path']}/config/bootstrap.php";
			$config['bootstrap'] = file_exists($path) ? 'config/bootstrap.php' : false;
		}
		if($config['bootstrap']) 
			require "{$config['path']}/{$config['bootstrap']}";
		if(!empty($config['loader'])) 
			spl_autoload_register($config['loader']);
	}

	public static function get($name = null, $key = null) 
	{
		$configs = static::$_configurations;

		if(!$name) return $configs;
		if($name === true) $name = static::$_default;    
		
		if(is_array($name)) 
		{
			foreach($name as $i => $key) {
				unset($name[$i]);
				$name[$key] = isset($configs[$key]) ? $configs[$key] : null;
			}    
			
			return $name;
		}  
		
		$config = isset($configs[$name]) ? $configs[$name] : null;

		if(!$key) return $config;
		return isset($config[$key]) ? $config[$key] : null;
	}

	public static function remove($name) 
	{
		foreach((array) $name as $library) 
		{
			if(isset(static::$_configurations[$library])) 
			{
				if(static::$_configurations[$library]['loader'])
					spl_autoload_unregister(static::$_configurations[$library]['loader']);
				unset(static::$_configurations[$library]);
			}
		}
	}
	public static function find($library, array $options = array()) 
	{
		$format = function($file, $config) 
		{
			$trim = array(strlen($config['path']) + 1, strlen($config['suffix']));
			$rTrim = strpos($file, $config['suffix']) !== false ? -$trim[1] : 9999;
			$file = preg_split('/[\/\\\\]/', substr($file, $trim[0], $rTrim));
			return $config['prefix'] . join('\\', $file);
		};

		$defaults = compact('format') + array(
			'path' => '',
			'recursive' => false,
			'filter' => '/^(\w+)?(\\\\[a-z0-9_]+)+\\\\[A-Z][a-zA-Z0-9]+$/',
			'exclude' => '',
			'namespaces' => false
		);
		$options += $defaults;
		$libs = array();

		if($options['namespaces'] && $options['filter'] == $defaults['filter']) 
		{
			$options['format'] = function($class, $config) use ($format, $defaults) 
			{
				if(is_dir($class))
					return $format($class, $config);
				if(preg_match($defaults['filter'], $class = $format($class, $config)))
					return $class;
			};
			$options['filter'] = false;
		}
		if($library === true) 
		{
			foreach(static::$_configurations as $library => $config) {
				$libs = array_merge($libs, static::find($library, $options));
			}
			return $libs;
		}
		if(!isset(static::$_configurations[$library]))
			return null;

		$config = static::$_configurations[$library];
		$options['path'] = "{$config['path']}{$options['path']}/*";
		$libs = static::_search($config, $options);    
		
		return array_values(array_filter($libs));
	}

	public static function load($class, $require = false) 
	{
		$path = isset(static::$_cachedPaths[$class]) ? static::$_cachedPaths[$class] : null;
		$path = $path ?: static::path($class);

		if($path && include $path) {
			static::$_cachedPaths[$class] = $path;
			method_exists($class, '__init') ? $class::__init() : null;
		} 
		elseif($require)
			throw new RuntimeException("Failed to load class `{$class}` from path `{$path}`.");
	}

	public static function path($class, array $options = array()) 
	{
		$defaults = array('dirs' => false);
		$options += $defaults;
		$class = ltrim($class, '\\');

		if(isset(static::$_cachedPaths[$class]) && !$options['dirs'])
			return static::$_cachedPaths[$class];

		foreach(static::$_configurations as $name => $config) 
		{
			$params = $options + $config;
			$suffix = $params['suffix'];

			if($params['prefix'] && strpos($class, $params['prefix']) !== 0)
				continue;

			if($transform = $params['transform']) 
			{
				if($file = static::_transformPath($transform, $class, $params))
					return $file;      
					
				continue;
			}
			$path = str_replace("\\", '/', substr($class, strlen($params['prefix'])));
			$fullPath = "{$params['path']}/{$path}";

			if(!$options['dirs'])
				return static::$_cachedPaths[$class] = static::realPath($fullPath . $suffix);

			$list = glob(dirname($fullPath) . '/*');
			$list = array_map(function($i) { return str_replace('\\', '/', $i); }, $list);

			if(in_array($fullPath . $suffix, $list))
				return static::$_cachedPaths[$class] = static::realPath($fullPath . $suffix);

			return is_dir($fullPath) ? static::realPath($fullPath) : null;
		}
	}

	public static function realPath($path) 
	{
		if (($absolutePath = realpath($path)) !== false) {
			return $absolutePath;
		}
		if (!preg_match('%^phar://([^.]+\.phar(?:\.gz)?)(.+)%', $path, $pathComponents)) {
			return;
		}
		list(, $relativePath, $pharPath) = $pathComponents;

		$pharPath = implode('/', array_reduce(explode('/', $pharPath), function ($parts, $value) 
		{
			if($value == '..') array_pop($parts);
			elseif($value != '.') $parts[] = $value;

			return $parts;
		}));

		if(($resolvedPath = realpath($relativePath)) !== false) {
			if(file_exists($absolutePath = "phar://{$resolvedPath}{$pharPath}"))
				return $absolutePath;
		}
	}

	protected static function _transformPath($transform, $class, array $options = array()) 
	{
		if((is_callable($transform)) && $file = $transform($class, $options)) 
			return $file;
		if(is_array($transform)) {
			list($match, $replace) = $transform;
			return preg_replace($match, $replace, $class) ?: null;
		}
	}

	public static function instance($type, $name, array $options = array()) 
	{
		$params = compact('type', 'name', 'options');
		$_paths =& static::$_paths;

		$implementation = function($self, $params) use (&$_paths) 
		{
			$name = $params['name'];
			$type = $params['type'];

			if(!$name && !$type) {
				$message = "Invalid class lookup: `\$name` and `\$type` are empty.";
				throw new ClassNotFoundException($message);
			}
			if(!is_string($type) && $type !== null && !isset($_paths[$type])) 
				throw new ClassNotFoundException("Invalid class type `{$type}`.");
			if(!$class = $self::locate($type, $name))
				throw new ClassNotFoundException("Class `{$name}` of type `{$type}` not found.");
			if(is_object($class)) 
  			return $class;
			if(!(is_string($class) && class_exists($class)))
				throw new ClassNotFoundException("Class `{$name}` of type `{$type}` not defined.");

			return new $class($params['options']);
		};
		if(!isset(static::$_methodFilters[__FUNCTION__]))
			return $implementation(get_called_class(), $params);

		$class = get_called_class();
		$method = __FUNCTION__;
		$data = array_merge(static::$_methodFilters[__FUNCTION__], array($implementation));
		return Filters::run($class, $params, compact('data', 'class', 'method'));
	}

	public static function applyFilter($method, $filter = null) 
	{
		if(!isset(static::$_methodFilters[$method]))
			static::$_methodFilters[$method] = array();

		static::$_methodFilters[$method][] = $filter;
	}

	public static function locate($type, $name = null, array $options = array()) 
	{
		if(is_object($name) || strpos($name, '\\') !== false) 
			return $name;

		$ident = $name ? ($type . '.' . $name) : ($type . '.*');
		$ident .= $options ? '.' . md5(serialize($options)) : null;

		if(isset(static::$_cachedPaths[$ident])) 
			return static::$_cachedPaths[$ident];

		$params = static::_params($type, $name);
		$defaults = array(
			'type' => 'class',
			'library' => $params['library'] !== '*' ? $params['library'] : null
		);
		$options += $defaults;
		unset($params['library']);
		$paths = static::paths($params['type']);

		if(!isset($paths)) return null;   
		
		if($params['name'] === '*') {
			$result = static::_locateAll($params, $options);
			return (static::$_cachedPaths[$ident] = $result);
		}
		if($options['library']) {
			$result = static::_locateDeferred(null, $paths, $params, $options);
			return static::$_cachedPaths[$ident] = $result;
		}      
		
		foreach(array(false, true) as $defer) {
			if($result = static::_locateDeferred($defer, $paths, $params, $options))
				return (static::$_cachedPaths[$ident] = $result);
		}
	}
	public static function cache($cache = null) 
	{
		if($cache === false)
			static::$_cachedPaths = array();
		if(is_array($cache))
			static::$_cachedPaths += $cache;

		return static::$_cachedPaths;
	}

	protected static function _locateDeferred($defer, $paths, $params, array $options = array()) 
	{
		$libraries = static::$_configurations;

		if(isset($options['library']))
			$libraries = static::get((array) $options['library']);

		foreach($libraries as $library => $config) 
		{
			if($config['defer'] !== $defer && $defer !== null) 
				continue;

			foreach(static::_searchPaths($paths, $library, $params) as $tpl) 
			{
				$params['library'] = $library;
				$class = str_replace('\\*', '', String::insert($tpl, $params));

				if(file_exists($file = Libraries::path($class, $options)))
					return ($options['type'] === 'file') ? $file : $class;
			}
		}
	}

	protected static function _searchPaths($paths, $library, $params) 
	{
		$result = array();
		$params = array('library' => null, 'type' => null) + $params;

		foreach($paths as $tpl => $opts) 
		{
			if(is_int($tpl)) {
				$tpl = $opts;
				$opts = array();
			}
			if(isset($opts['libraries']) && !in_array($library, (array) $opts['libraries']))
				continue;

			$result[] = $tpl;
		}   
		
		return $result;
	}

	protected static function _locateAll(array $params, array $options = array()) 
	{
		$defaults = array('libraries' => null, 'recursive' => true, 'namespaces' => false);
		$options += $defaults;

		$paths = (array) static::$_paths[$params['type']];
		$libraries = $options['library'] ? $options['library'] : $options['libraries'];
		$libraries = static::get((array) $libraries);
		$flags = array('escape' => '/');
		$classes = array();

		foreach($libraries as $library => $config) 
		{
			$params['library'] = $config['path'];

			foreach(static::_searchPaths($paths, $library, $params) as $tpl) 
			{
				$options['path'] = str_replace('\\', '/', String::insert($tpl, $params, $flags));
				$options['path'] = str_replace('*/', '', $options['path']);
				$classes = array_merge($classes, static::_search($config, $options));
			}
		}        
		
		return array_unique($classes);
	}
	
	protected static function _locatePath($type, $params) 
	{
		if(!isset(static::$_paths[$type]))
			return;

		$params += array('app' => ARTHUR_APP_PATH, 'root' => ARTHUR_LIBRARY_PATH);

		foreach(static::$_paths[$type] as $path) 
		{
			if(is_dir($path = str_replace('\\', '/', String::insert($path, $params))))
				return $path;
		}
	}

	protected static function _search($config, $options, $name = null) 
	{
		$defaults = array(
			'path' => null,
			'suffix' => null,
			'namespaces' => false,
			'recursive' => false,
			'preFilter' => '/[A-Z][A-Za-z0-9]+\./',
			'filter' => false,
			'exclude' => false,
			'format' => function ($file, $config) {
				$trim = array(strlen($config['path']) + 1, strlen($config['suffix']));
				$file = substr($file, $trim[0], -$trim[1]);
				return $config['prefix'] . str_replace('/', '\\', $file);
			}
		);
		$options += $defaults;      
		
		$path   = $options['path'];
		$suffix = $options['namespaces'] ? '' : $config['suffix'];
		$suffix = ($options['suffix'] === null) ? $suffix : $options['suffix'];

		$dFlags = GLOB_ONLYDIR;
		$libs = (array) glob($path . $suffix, $options['namespaces'] ? $dFlags : 0);

		if($options['recursive']) 
		{
			list($current, $match) = explode('/*', $path, 2);
			$dirs = $queue = array_diff((array) glob($current . '/*', $dFlags), $libs);
			$match = str_replace('##', '.+', preg_quote(str_replace('*', '##', $match), '/'));
			$match = '/' . $match . preg_quote($suffix, '/') . '$/';

			while($queue) 
			{
				if(!is_dir($dir = array_pop($queue)))
					continue;

				$libs = array_merge($libs, (array) glob("{$dir}/*{$suffix}"));
				$queue = array_merge($queue, array_diff((array) glob("{$dir}/*", $dFlags), $libs));
			}
			$libs = preg_grep($match, $libs);
		}     
		
		if($suffix)
			$libs = $options['preFilter'] ? preg_grep($options['preFilter'], $libs) : $libs;

		return static::_filter($libs, (array) $config, $options + compact('name'));
	}

	protected static function _filter($libs, array $config, array $options = array())
	{
		if(is_callable($options['format'])) 
		{
			foreach($libs as $i => $file) {
				$libs[$i] = $options['format']($file, $config);
			}     
			
			$libs = $options['name'] ? preg_grep("/{$options['name']}$/", $libs) : $libs;
		}
		if($exclude = $options['exclude']) 
		{
			if(is_string($exclude))
				$libs = preg_grep($exclude, $libs, PREG_GREP_INVERT);
			elseif(is_callable($exclude))
				$libs = array_values(array_filter($libs, $exclude));
		}
		if($filter = $options['filter']) 
		{
			if(is_string($filter))
				$libs = preg_grep($filter, $libs) ;
			elseif (is_callable($filter))
				$libs = array_filter(array_map($filter, $libs));
		}    
		
		return $libs;
	}
	
	protected static function _params($type, $name = "*") 
	{
		$name = $name ?: "*";
		$library = $namespace = $class = '*';

		if(strpos($type, '.') !== false) 
		{
			$parts = explode('.', $type);
			$type = array_shift($parts);

			switch(count($parts)) 
			{
				case 1:
					list($class) = $parts;
				break;
				case 2:
					list($namespace, $class) = $parts;
				break;
				default:
					$class = array_pop($parts);
					$namespace = join('\\', $parts);
				break;
			}
		}
		if(strpos($name, '.') !== false) 
		{
			$parts = explode('.', $name);
			$library = array_shift($parts);
			$name = array_pop($parts);
			$namespace = $parts ? join('\\', $parts) : "*";
		} 
		
		return compact('library', 'namespace', 'type', 'class', 'name');
	}
}