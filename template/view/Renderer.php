<?php

namespace arthur\template\view;

use RuntimeException;
use arthur\core\Libraries;
use arthur\core\ClassNotFoundException;

abstract class Renderer extends \arthur\core\Object 
{
	protected $_autoConfig = array(
		'request', 'response', 'context', 'strings', 'handlers', 'view', 'classes' => 'merge'
	);
	
	protected $_view = null;
	
	protected $_context = array(
		'content' => '', 'title' => '', 'scripts' => array(), 'styles' => array(), 'head' => array()
	);

	protected $_classes = array(
		'router' => 'arthur\net\http\Router',
		'media'  => 'arthur\net\http\Media'
	);

	protected $_helpers = array();
	protected $_strings = array();
	protected $_request = null;
	protected $_response = null;
	protected $_handlers = array();
	protected $_data = array();
	protected $_vars = array();

	abstract public function render($template, $data = array(), array $options = array());

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'view'     => null,
			'strings'  => array(),
			'handlers' => array(),
			'request'  => null,
			'response' => null,
			'context'  => array(
				'content' => '', 'title' => '', 'scripts' => array(),
				'styles' => array(), 'head' => array()
			)
		);   
		
		parent::__construct((array) $config + $defaults);
	}           
	
	protected function _init() 
	{
		parent::_init();

		$request =& $this->_request;
		$context =& $this->_context;
		$classes =& $this->_classes;
		$h = $this->_view ? $this->_view->outputFilters['h'] : null;

		$this->_handlers += array(
			'url' => function($url, $ref, array $options = array()) use (&$classes, &$request, $h) {
				$url = $classes['router']::match($url ?: '', $request, $options);
				return $h ? str_replace('&amp;', '&', $h($url)) : $url;
			},
			'path' => function($path, $ref, array $options = array()) use (&$classes, &$request) 
			{
				$defaults = array('base' => $request ? $request->env('base') : '');
				$type     = 'generic';

				if(is_array($ref) && $ref[0] && $ref[1]) 
				{
					list($helper, $methodRef) = $ref;
					list($class, $method) = explode('::', $methodRef);
					$type = $helper->contentMap[$method];
				}    
				
				return $classes['media']::asset($path, $type, $options + $defaults);
			},
			'options' => '_attributes',
			'title'   => 'escape',
			'scripts' => function($scripts) use (&$context) {
				return "\n\t" . join("\n\t", $context['scripts']) . "\n";
			},
			'styles' => function($styles) use (&$context) {
				return "\n\t" . join("\n\t", $context['styles']) . "\n";
			},
			'head' => function($head) use (&$context) {
				return "\n\t" . join("\n\t", $context['head']) . "\n";
			}
		);     
		
		unset($this->_config['view']);
	}

	public function __isset($property) 
	{
		return isset($this->_context[$property]);
	}

	public function __get($property) 
	{
		$context = $this->_context;
		$helpers = $this->_helpers;

		$filter = function($self, $params, $chain) use ($context, $helpers) 
		{
			$property = $params['property'];

			foreach(array('context', 'helpers') as $key) {
				if(isset(${$key}[$property]))
					return ${$key}[$property];
			}
			return $self->helper($property);
		};      
		
		return $this->_filter(__METHOD__, compact('property'), $filter);
	}

	public function __call($method, $params) 
	{
		if(!isset($this->_context[$method]) && !isset($this->_handlers[$method]))
			return isset($params[0]) ? $params[0] : null;
		if(!isset($this->_handlers[$method]) && !$params)
			return $this->_context[$method];   
			
		if(isset($this->_context[$method]) && $params) 
		{
			if(is_array($this->_context[$method]))
				$this->_context[$method][] = $params[0];
			else
				$this->_context[$method] = $params[0];
		}
		if(!isset($this->_context[$method])) {
			$params += array(null, array());
			return $this->applyHandler(null, null, $method, $params[0], $params[1]);
		} 
		
		return $this->applyHandler(null, null, $method, $this->_context[$method]);
	}

	public function helper($name, array $config = array())
	{
		if(isset($this->_helpers[$name]))
			return $this->_helpers[$name];
		try {
			$config += array('context' => $this);
			return $this->_helpers[$name] = Libraries::instance('helper', ucfirst($name), $config);
		} 
		catch (ClassNotFoundException $e) {
			throw new RuntimeException("Helper `{$name}` not found.");
		}
	}

	public function strings($strings = null) 
	{
		if(is_array($strings))
			return $this->_strings = $this->_strings + $strings;
		if(is_string($strings))
			return isset($this->_strings[$strings]) ? $this->_strings[$strings] : null;
		
		return $this->_strings;
	}

	public function context($property = null) 
	{
		if($property)
			return isset($this->_context[$property]) ? $this->_context[$property] : null;

		return $this->_context;
	}

	public function handlers($handlers = null) 
	{
		if(is_array($handlers))
			return $this->_handlers += $handlers;
		if(is_string($handlers)) 
			return isset($this->_handlers[$handlers]) ? $this->_handlers[$handlers] : null;

		return $this->_handlers;
	}

	public function applyHandler($helper, $method, $name, $value, array $options = array()) 
	{
		if(!(isset($this->_handlers[$name]) && $handler = $this->_handlers[$name]))
			return $value;

		switch(true) 
		{
			case is_string($handler) && !$helper:
				$helper = $this->helper('html');
			case is_string($handler) && is_object($helper):
				return $helper->invokeMethod($handler, array($value, $method, $options));
			case is_array($handler) && is_object($handler[0]):
				list($object, $func) = $handler;
				return $object->invokeMethod($func, array($value, $method, $options));
			case is_callable($handler):
				return $handler($value, array($helper, $method), $options);
			default:
				return $value;
		}
	}

	public function request() 
	{
		return $this->_request;
	}

	public function response() 
	{
		return $this->_response;
	}

	public function view() 
	{
		return $this->_view;
	}

	public function data() 
	{
		return $this->_data + $this->_vars;
	}

	public function set(array $data = array()) 
	{
		$this->_data = $data + $this->_data;
		$this->_vars = $data + $this->_vars;
	}
	
	protected function _render($type, $template, array $data = array(), array $options = array()) 
	{
		$library = $this->_request->library;
		$options += compact('library');    
		
		return $this->_view->render($type, $data + $this->_data, compact('template') + $options);
	}
}