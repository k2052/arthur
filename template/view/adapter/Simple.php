<?php

namespace arthur\template\view\adapter;

use Closure;
use Exception;
use arthur\util\Set;
use arthur\util\String;

class Simple extends \arthur\template\view\Renderer 
{

	public function render($template, $data = array(), array $options = array()) 
	{
		$defaults = array('context' => array());
		$options += $defaults;

		$context = array();
		$this->_context = $options['context'] + $this->_context;

		foreach(array_keys($this->_context) as $key) {
			$context[$key] = $this->__get($key);
		}
		$data = array_merge($this->_toString($context), $this->_toString($data));    
		
		return String::insert($template, $data, $options);
	}

	public function template($type, $options) 
	{
		if(isset($options[$type]))
			return $options[$type];
		
		return isset($options['template']) ? $options['template'] : '';
	}

	protected function _toString($data) 
	{
		foreach($data as $key => $val) 
		{
			switch(true) 
			{
				case is_object($val) && !$val instanceof Closure:
					try {
						$data[$key] = (string) $val;
					} 
					catch (Exception $e) {
						$data[$key] = '';
					}
				break;
				case is_array($val):
					$data = array_merge($data, Set::flatten($val));
				break;
			}
		}   
		
		return $data;
	}
}