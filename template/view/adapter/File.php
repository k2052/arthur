<?php

namespace arthur\template\view\adapter;

use arthur\util\String;
use arthur\core\Libraries;
use arthur\template\TemplateException;

class File extends \arthur\template\view\Renderer implements \ArrayAccess 
{
	protected $_autoConfig = array(
		'classes' => 'merge', 'request', 'response', 'context',
		'strings', 'handlers', 'view', 'compile', 'paths'
	);

	protected $_compile = true;
	protected $_data = array();
	protected $_vars = array();
	protected $_paths = array();

	protected $_classes = array(
		'compiler' => 'arthur\template\view\Compiler',
		'router'   => 'arthur\net\http\Router',
		'media'    => 'arthur\net\http\Media'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'classes' => array(), 'compile' => true, 'extract' => true, 'paths' => array()
		);
		parent::__construct($config + $defaults);
	}

	public function render($template, $data = array(), array $options = array()) 
	{
		$defaults = array('context' => array());
		$options += $defaults;

		$this->_context = $options['context'] + $this->_context;
		$this->_data    = (array) $data + $this->_vars;
		$template__     = $template;
		unset($options, $template, $defaults, $data);

		if($this->_config['extract'])
			extract($this->_data, EXTR_OVERWRITE);
		elseif($this->_view) 
			extract((array) $this->_view->outputFilters, EXTR_OVERWRITE);

		ob_start();
		include $template__;
		return ob_get_clean();
	}

	public function template($type, array $params) 
	{
		$library           = Libraries::get(isset($params['library']) ? $params['library'] : true);
		$params['library'] = $library['path'];
		$path              = $this->_paths($type, $params);

		if($this->_compile) {
			$compiler = $this->_classes['compiler'];
			$path = $compiler::template($path);
		}   
		
		return $path;
	}

	public function offsetExists($offset) 
	{
		return array_key_exists($offset, $this->_data);
	}

	public function offsetGet($offset) 
	{
		return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
	}

	public function offsetSet($offset, $value) 
	{
		$this->_data[$offset] = $value;
	}

	public function offsetUnset($offset) 
	{
		unset($this->_data[$offset]);
	}

	protected function _paths($type, array $params) 
	{
		if(!isset($this->_paths[$type]))
			throw new TemplateException("Invalid template type '{$type}'.");

		foreach((array) $this->_paths[$type] as $path) 
		{
			if(!file_exists($path = String::insert($path, $params)))
				continue;

			return $path;
		}           
		
		throw new TemplateException("Template not found at path `{$path}`.");
	}
}