<?php

namespace arthur\template;

use arthur\util\String;

abstract class Helper extends \arthur\core\Object 
{
	public $contentMap = array();
	protected $_strings = array();
	protected $_context = null;
	protected $_classes = array();
	protected $_autoConfig = array('classes' => 'merge', 'context');

	protected $_minimized = array(
		'compact', 'checked', 'declare', 'readonly', 'disabled', 'selected', 'defer', 'ismap',
		'nohref', 'noshade', 'nowrap', 'multiple', 'noresize', 'async', 'autofocus'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('handlers' => array(), 'context' => null);
		parent::__construct($config + $defaults);
	}
	
	protected function _init() 
	{
		parent::_init();

		if(!$this->_context)  return;
		$this->_context->strings($this->_strings);

		if($this->_config['handlers'])
			$this->_context->handlers($this->_config['handlers']);
	}
	
	public function escape($value, $method = null, array $options = array()) 
	{
		$defaults = array('escape' => true);
		$options += $defaults;

		if($options['escape'] === false) 
			return $value;
		if(is_array($value))
			return array_map(array($this, __FUNCTION__), $value);   
			
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	protected function _options(array $defaults, array $scope) 
	{
		$scope  += $defaults;
		$options = array_diff_key($scope, $defaults);  
		
		return array($scope, $options);
	}

	protected function _render($method, $string, $params, array $options = array()) 
	{
		$strings = $this->_strings;

		if($this->_context) 
		{
			foreach($params as $key => $value) 
			{
				$params[$key] = $this->_context->applyHandler(
					$this, $method, $key, $value, $options
				);
			}
			$strings = $this->_context->strings();
		}          
		
		return String::insert(isset($strings[$string]) ? $strings[$string] : $string, $params);
	}

	protected function _attributes($params, $method = null, array $options = array()) 
	{
		$defaults = array('escape' => true, 'prepend' => ' ', 'append' => '');
		$options += $defaults;
		$result   = array();

		if(!is_array($params))
			return !$params ? '' : $options['prepend'] . $params;
			
		foreach($params as $key => $value) {
			if($next = $this->_attribute($key, $value, $options)) 
				$result[] = $next;
		}        
		
		return $result ? $options['prepend'] . implode(' ', $result) . $options['append'] : '';
	}

	protected function _attribute($key, $value, array $options = array()) 
	{
		$defaults = array('escape' => true, 'format' => '%s="%s"');
		$options += $defaults;

		if(in_array($key, $this->_minimized)) 
		{
			$isMini = ($value == 1 || $value === true || $value == $key);
			if(!($value = $isMini ? $key : $value)) 
				return null;
		}
		$value = (string) $value;

		if($options['escape'])
			return sprintf($options['format'], $this->escape($key), $this->escape($value));

		return sprintf($options['format'], $key, $value);
	}
}