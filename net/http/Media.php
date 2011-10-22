<?php

namespace arthur\net\http;

use arthur\util\String;
use arthur\core\Libraries;
use arthur\net\http\MediaException;

class Media extends \arthur\core\StaticObject 
{
	protected static $_types = array();
	protected static $_handlers = array();
	protected static $_assets = array();
	protected static $_classes = array();

	public static function types() 
	{
		return array_keys(static::_types());
	}

	public static function formats() 
	{
		return static::types();
	}

	public static function to($format, $data, array $options = array()) 
	{
		return static::encode($format, $data, $options);
	}

	public static function type($type, $content = null, array $options = array()) 
	{
		$defaults = array(
			'view'       => false,
			'template'   => false,
			'layout'     => false,
			'encode'     => false,
			'decode'     => false,
			'cast'       => true,
			'conditions' => array()
		);

		if($content === false) 
			unset(static::$_types[$type], static::$_handlers[$type]);

		if(!$content && !$options) 
		{
			if(!$content = static::_types($type))
				return;
			if(strpos($type, '/'))
				return $content;
			if(is_array($content) && isset($content['alias']))
				return static::type($content['alias']);

			return compact('content') + array('options' => static::_handlers($type));
		}
		if($content)
			static::$_types[$type] = $content;

		static::$_handlers[$type] = $options ? ($options + $defaults) : array();
	}

	public static function negotiate($request) 
	{
		$self = get_called_class();

		$match = function($name) use ($self, $request) 
		{
			if(($cfg = $self::type($name)) && $self::match($request, compact('name') + $cfg)) 
				return true;        
				
			return false;
		};

		if(($type = $request->type) && $match($type))
			return $type;

		foreach($request->accepts(true) as $type) 
		{
			if(!$types = (array) static::_types($type))
				continue;

			foreach($types as $name) {
				if(!$match($name)) continue;
				return $name;
			}
		}
	}

	public static function match($request, array $config) 
	{
		if(!isset($config['options']['conditions']))
			return true;
		$conditions = $config['options']['conditions'];

		foreach($conditions as $key => $value) 
		{
			switch(true) 
			{
				case $key == 'type':
					if($value !== ($request->type === $config['name']))
						return false;
				break;
				case strpos($key, ':'):
					if($request->get($key) !== $value)
						return false;
				break;
				case ($request->is($key) !== $value):
					return false;
				break;
			}
		}   
		
		return true;
	}

	public static function assets($type = null, $options = array()) 
	{
		$defaults = array('suffix' => null, 'filter' => null, 'path' => array());

		if(!$type)
			return static::_assets();
		if($options === false)
			unset(static::$_assets[$type]);
		if(!$options)
			return static::_assets($type);

		$options = (array) $options + $defaults;

		if($base = static::_assets($type)) 
			$options = array_merge($base, array_filter($options));

		static::$_assets[$type] = $options;
	}

	public static function asset($path, $type, array $options = array()) 
	{
		$defaults = array(
			'base'      => null,
			'timestamp' => false,
			'filter'    => null,
			'path'      => array(),
			'suffix'    => null,
			'check'     => false,
			'library'   => true
		);
		if(!$base = static::_assets($type)) {
			$type = 'generic';
			$base = static::_assets('generic');
		}
		$options += ($base + $defaults);
		$params   = compact('path', 'type', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$path    = $params['path'];
			$type    = $params['type'];
			$options = $params['options'];
			$library = $options['library'];

			if(preg_match('/^[a-z0-9-]+:\/\//i', $path))
				return $path;
			$config = Libraries::get($library);
			$paths = $options['path'];

			$config['default'] ? end($paths) : reset($paths);
			$options['library'] = basename($config['path']);

			if($options['suffix'] && strpos($path, $options['suffix']) === false)
				$path .= $options['suffix'];

			if($options['check'] || $options['timestamp'])
				$file = $self::path($path, $type, $options);

			if($path[0] === '/') {
				if($options['base'] && strpos($path, $options['base']) !== 0) 
					$path = "{$options['base']}{$path}";
			} 
			else
				$path = String::insert(key($paths), compact('path') + $options);

			if($options['check'] && !is_file($file))
				return false;

			if(is_array($options['filter']) && !empty($options['filter'])) 
			{
				$keys   = array_keys($options['filter']);
				$values = array_values($options['filter']);
				$path   = str_replace($keys, $values, $path);
			}

			if($options['timestamp'] && is_file($file)) {
				$separator = (strpos($path, '?') !== false) ? '&' : '?';
				$path .= $separator . filemtime($file);
			}    
			
			return $path;
		});
	}

	public static function webroot($library = true) 
	{
		if(!$config = Libraries::get($library))
			return null;
		if(isset($config['webroot']))
			return $config['webroot'];
		if(isset($config['path'])) 
			return $config['path'] . '/webroot';
	}

	public static function path($path, $type, array $options = array()) 
	{
		$defaults = array(
			'base'    => null,
			'path'    => array(),
			'suffix'  => null,
			'library' => true
		);
		if(!$base = static::_assets($type)) {
			$type = 'generic';
			$base = static::_assets('generic');
		}
		$options += ($base + $defaults);
		$config   = Libraries::get($options['library']);
		$root     = static::webroot($options['library']);
		$paths    = $options['path'];

		$config['default'] ? end($paths) : reset($paths);
		$options['library'] = basename($config['path']);

		if($qOffset = strpos($path, '?'))
			$path = substr($path, 0, $qOffset);

		if($path[0] === '/')
			$file = $root . $path;
		else 
		{
			$template = str_replace('{:library}/', '', key($paths));
			$insert = array('base' => $root) + compact('path');
			$file = String::insert($template, $insert);
		}       
		
		return realpath($file);
	}

	public static function render(&$response, $data = null, array $options = array()) 
	{
		$params   = array('response' => &$response) + compact('data', 'options');
		$types    = static::_types();
		$handlers = static::_handlers();

		static::_filter(__FUNCTION__, $params, function($self, $params) use ($types, $handlers) 
		{
			$defaults = array('encode' => null, 'template' => null, 'layout' => '', 'view' => null);
			$response =& $params['response'];
			$data     = $params['data'];
			$options  = $params['options'] + array('type' => $response->type());
                
			$result = null;
			$type   = $options['type'];

			if(!isset($handlers[$type]))
				throw new MediaException("Unhandled media type `{$type}`.");

			$handler = $options + $handlers[$type] + $defaults;
			$filter  = function($v) { return $v !== null; };
			$handler = array_filter($handler, $filter) + $handlers['default'] + $defaults;

			if(isset($types[$type])) 
			{
				$header  = current((array) $types[$type]);
				$header .= $response->encoding ? "; charset={$response->encoding}" : '';
				$response->headers('Content-type', $header);
			}  
			
			$response->body($self::invokeMethod('_handle', array($handler, $data, $response)));
		});
	}

	public static function view($handler, $data, &$response = null, array $options = array()) 
	{
		$params = array('response' => &$response) + compact('handler', 'data', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$data     = $params['data'];
			$options  = $params['options'];
			$handler  = $params['handler'];
			$response =& $params['response'];

			if(!is_array($handler))
				$handler = $self::invokeMethod('_handlers', array($handler)); 
				
			$class = $handler['view'];
			unset($handler['view']);

			$config = $handler + array('response' => &$response); 
			
			return $self::invokeMethod('_instance', array($class, $config));
		});
	}

	public static function encode($handler, $data, &$response = null) 
	{
		$params = array('response' => &$response) + compact('handler', 'data');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$data     = $params['data'];
			$handler  = $params['handler'];
			$response =& $params['response'];

			if(!is_array($handler))
				$handler = $self::invokeMethod('_handlers', array($handler));

			if(!$handler || !isset($handler['encode']))
				return null;

			$cast = function($data) 
			{
				if(!is_object($data))
					return $data;

				return method_exists($data, 'to') ? $data->to('array') : get_object_vars($data);
			};

			if(!isset($handler['cast']) || $handler['cast']) {
				$data = is_object($data) ? $cast($data) : $data;
				$data = is_array($data) ? array_map($cast, $data) : $data;
			}
			$method = $handler['encode']; 
			
			return is_string($method) ? $method($data) : $method($data, $handler, $response);
		});
	}

	public static function decode($type, $data, array $options = array()) 
	{
		if((!$handler = static::_handlers($type)) || empty($handler['decode']))
			return null;

		$method = $handler['decode'];                        
		
		return is_string($method) ? $method($data) : $method($data, $handler + $options);
	}
	
	public static function reset() 
	{
		foreach(get_class_vars(__CLASS__) as $name => $value) {
			static::${$name} = array();
		}
	}
	
	protected static function _handle($handler, $data, &$response) 
	{
		$params = array('response' => &$response) + compact('handler', 'data');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$response = $params['response'];
			$handler  = $params['handler'];
			$data     = $params['data'];
			$options  = $handler;

			if(isset($options['request'])) {
				$options += $options['request']->params;
				unset($options['request']);
			}

			switch(true) 
			{
				case $handler['encode']:
					return $self::encode($handler, $data, $response);
				case ($handler['template'] === false) && is_string($data):
					return $data;
				case $handler['view']:
					unset($options['view']);
					$instance = $self::view($handler, $data, $response, $options);
					return $instance->render('all', (array) $data, $options);
				default:
					throw new MediaException("Could not interpret type settings for handler.");
			}
		});
	}

	protected static function _types($type = null) 
	{
		$types = static::$_types + array(
			'html'  => array('text/html', 'application/xhtml+xml', '*/*'),
			'htm'   => array('alias' => 'html'),
			'form'  => array('application/x-www-form-urlencoded', 'multipart/form-data'),
			'json'  => 'application/json',
			'rss'   => 'application/rss+xml',
			'atom'  => 'application/atom+xml',
			'css'   => 'text/css',
			'js'    => array('application/javascript', 'text/javascript'),
			'text'  => 'text/plain',
			'txt'   => array('alias' => 'text'),
			'xml'   => array('application/xml', 'text/xml')
		);

		if(!$type) 
			return $types;
		if(strpos($type, '/') === false)
			return isset($types[$type]) ? $types[$type] : null;
		if(strpos($type, ';'))
			list($type) = explode(';', $type, 2);
		$result = array();

		foreach($types as $name => $cTypes) {
			if($type == $cTypes || (is_array($cTypes) && in_array($type, $cTypes)))
				$result[] = $name;
		}
		if(count($result) == 1)
			return reset($result);

		return $result ?: null;
	}

	protected static function _handlers($type = null) 
	{
		$handlers = static::$_handlers + array(
			'default' => array(
				'view'     => 'arthur\template\View',
				'encode'   => false,
				'decode'   => false,
				'cast'     => false,
				'paths'    => array(
					'template' => '{:library}/views/{:controller}/{:template}.{:type}.php',
					'layout'   => '{:library}/views/layouts/{:layout}.{:type}.php',
					'element'  => '{:library}/views/elements/{:template}.{:type}.php'
				)
			),
			'html' => array(),
			'json' => array(
				'cast' => true,
				'encode' => 'json_encode',
				'decode' => function($data) {
					return json_decode($data, true);
				}
			),
			'text' => array('cast' => false, 'encode' => function($s) { return $s; }),
			'form' => array(
				'cast' => true,
				'encode' => 'http_build_query',
				'decode' => function($data) {
					$decoded = array();
					parse_str($data, $decoded);
					return $decoded;
				}
			)
		);

		if($type)
			return isset($handlers[$type]) ? $handlers[$type] : null;

		return $handlers;
	}

	protected static function _assets($type = null) 
	{
		$assets = static::$_assets + array(
			'js' => array('suffix' => '.js', 'filter' => null, 'path' => array(
				'{:base}/{:library}/js/{:path}' => array('base', 'library', 'path'),
				'{:base}/js/{:path}' => array('base', 'path')
			)),
			'css' => array('suffix' => '.css', 'filter' => null, 'path' => array(
				'{:base}/{:library}/css/{:path}' => array('base', 'library', 'path'),
				'{:base}/css/{:path}' => array('base', 'path')
			)),
			'image' => array('suffix' => null, 'filter' => null, 'path' => array(
				'{:base}/{:library}/img/{:path}' => array('base', 'library', 'path'),
				'{:base}/img/{:path}' => array('base', 'path')
			)),
			'generic' => array('suffix' => null, 'filter' => null, 'path' => array(
				'{:base}/{:library}/{:path}' => array('base', 'library', 'path'),
				'{:base}/{:path}' => array('base', 'path')
			))
		);
		if($type)
			return isset($assets[$type]) ? $assets[$type] : null; 
			
		return $assets;
	}
}