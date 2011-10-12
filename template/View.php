<?php

namespace arthur\template;

use arthur\core\Libraries;
use arthur\template\TemplateException;

class View extends \arthur\core\Object 
{
	public $outputFilters = array();
	protected $_request = null;
	protected $_response = null;
	protected $_loader = null;
	protected $_renderer = null;

	protected $_processes = array(
		'all'      => array('template', 'layout'),
		'template' => array('template'),
		'element'  => array('element')
	);

	protected $_steps = array(
		'template' => array('path' => 'template', 'capture' => array('context' => 'content')),
		'layout' => array(
			'path' => 'layout', 'conditions' => 'layout', 'multi' => true, 'capture' => array(
				'context' => 'content'
			)
		),
		'element' => array('path' => 'element')
	);

	protected $_autoConfig = array(
		'request', 'response', 'processes' => 'merge', 'steps' => 'merge'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'request'       => null,
			'response'      => null,
			'vars'          => array(),
			'loader'        => 'File',
			'renderer'      => 'File',
			'steps'         => array(),
			'processes'     => array(),
			'outputFilters' => array()
		);   
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();

		$encoding = 'UTF-8';

		if($this->_response)
			$encoding =& $this->_response->encoding;   
			
		$h = function($data) use (&$encoding) {
			return htmlspecialchars((string) $data, ENT_QUOTES, $encoding);
		};
		$this->outputFilters += compact('h') + $this->_config['outputFilters'];

		foreach(array('loader', 'renderer') as $key) 
		{
			if(is_object($this->_config[$key])) {
				$this->{'_' . $key} = $this->_config[$key];
				continue;
			}  
			
			$class = $this->_config[$key];
			$config = array('view' => $this) + $this->_config;  
			
			$this->{'_' . $key} = Libraries::instance('adapter.template.view', $class, $config);
		}
	}

	public function render($process, array $data = array(), array $options = array()) 
	{
		$defaults = array(
			'type'     => 'html',
			'layout'   => null,
			'template' => null,
			'context'  => array()
		);
		$options += $defaults;

		$data += isset($options['data']) ? (array) $options['data'] : array();
		$paths = isset($options['paths']) ? (array) $options['paths'] : array();
		unset($options['data'], $options['paths']);      
		
		$params = array_filter($options, function($val) { return $val && is_string($val); });
		$result = null;

		foreach($this->_process($process, $params) as $name => $step) 
		{
			if(isset($paths[$name]) && $paths[$name] === false)
				continue;
			if(!$this->_conditions($step, $params, $data, $options)) 
				continue;

			if($step['multi'] && isset($options[$name])) 
			{
				foreach((array) $options[$name] as $value) {
					$params[$name] = $value;
					$result = $this->_step($step, $params, $data, $options);
				}
				continue;
			}
			$result = $this->_step((array) $step, $params, $data, $options);
		} 
		
		return $result;
	}

	protected function _conditions($step, $params, $data, $options) 
	{
		if(!$conditions = $step['conditions'])
			return true;
		if(is_callable($conditions) && !$conditions($params, $data, $options))
			return false;
		if(is_string($conditions) && !(isset($options[$conditions]) && $options[$conditions]))
			return false;

		return true;
	}

	protected function _step(array $step, array $params, array &$data, array &$options = array()) 
	{
		$step      += array('path' => null, 'capture' => null);
		$_renderer = $this->_renderer;  
		$_loader = $this->_loader;       
		
		$filters = $this->outputFilters; 
		$params = compact('step', 'params', 'options') + array('data' => $data + $filters);   
		
		$filter = function($self, $params) use (&$_renderer, &$_loader) 
		{
			$template = $_loader->template($params['step']['path'], $params['params']);
			return $_renderer->render($template, $params['data'], $params['options']);
		};
		$result = $this->_filter(__METHOD__, $params, $filter);

		if(is_array($step['capture'])) 
		{
			switch(key($step['capture'])) 
			{
				case 'context':
					$options['context'][current($step['capture'])] = $result;
				break;
				case 'data':
					$data[current($step['capture'])] = $result;
				break;
			}
		}      
		
		return $result;
	}

	protected function _process($process, &$params) 
	{
		$defaults = array('conditions' => null, 'multi' => false);

		if(!is_array($process)) 
		{
			if(!isset($this->_processes[$process]))
				throw new TemplateException("Undefined rendering process '{$process}'.");

			$process = $this->_processes[$process];
		}  
		
		if(is_string(key($process)))
			return $this->_convertSteps($process, $params, $defaults);
		$result = array();

		foreach($process as $step) 
		{
			if(is_array($step)) {
				$result[] = $step + $defaults;
				continue;
			}
			if(!isset($this->_steps[$step]))
				throw new TemplateException("Undefined rendering step '{$step}'.");

			$result[$step] = $this->_steps[$step] + $defaults;
		}   
		
		return $result;
	}

	protected function _convertSteps($command, &$params, $defaults) 
	{
		if(count($command) == 1) {
			$params['template'] = current($command);
			return array(array('path' => key($command)) + $defaults);
		} 
		
		return $command;
	}
}