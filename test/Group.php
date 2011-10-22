<?php

namespace arthur\test;

use Exception;
use arthur\test\Unit;
use arthur\core\Libraries;
use arthur\util\Collection;

class Group extends \arthur\util\Collection 
{
	protected function _init() {
		parent::_init();
		$data = $this->_data;
		$this->_data = array();

		foreach($data as $item) {
			$this->add($item);
		}
	}

	public static function all(array $options = array()) 
	{
		$defaults = array(
			'filter'    => '/cases/',
			'exclude'   => '/mock/',
			'recursive' => true
		);    
		
		return Libraries::locate('tests', null, $options + $defaults);
	}

	public function add($test = null, array $options = array()) 
	{
		$resolve = function($self, $test) {
			switch (true) {
				case !$test:
					return array();
				case is_object($test) && $test instanceof Unit:
					return array(get_class($test));
				case is_string($test) && !file_exists(Libraries::path($test)):
					return $self->invokeMethod('_resolve', array($test));
				default:
					return (array) $test;
			}
		}; 
		
		if(is_array($test)) 
		{
			foreach($test as $t) {
				$this->_data = array_filter(array_merge($this->_data, $resolve($this, $t)));
			}    
			
			return $this->_data;
		}     
		
		return $this->_data = array_merge($this->_data, $resolve($this, $test));
	}

	public function tests($params = array(), array $options = array()) 
	{
		$tests = new Collection();

		foreach($this->_data as $test) 
		{
			if(!class_exists($test))
				throw new Exception("Test case `{$test}` not found.");

			$tests[] = new $test;
		}    
		
		return $tests;
	}

	protected function _resolve($test) 
	{
		if(strpos($test, '\\') === false && Libraries::get($test)) {
			return (array) Libraries::find($test, array(
				'recursive' => true, 'filter' => '/cases|integration|functional/'
			));
		}
		if(preg_match("/Test/", $test))
			return array($test);
		if(!$test = trim($test, '\\'))
			return array();

		list($library, $path) = explode('\\', $test, 2) + array($test, null);

		return (array) Libraries::find($library, array(
			'recursive' => true,
			'path' => '/' . str_replace('\\', '/', $path),
			'filter' => '/cases|integration|functional/'
		));
	}
}