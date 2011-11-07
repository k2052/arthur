<?php

namespace arthur\g11n\catalog\adapter;

use arthur\core\ConfigException;

class Php extends \arthur\g11n\catalog\Adapter 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array('path' => null);
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();          
		
		if(!is_dir($this->_config['path'])) {
			$message = "Php directory does not exist at path `{$this->_config['path']}`.";
			throw new ConfigException($message);
		}
	}

	public function read($category, $locale, $scope) 
	{
		$path = $this->_config['path'];
		$file = $this->_file($category, $locale, $scope);
		$data = array();

		if(file_exists($file)) 
		{
			foreach(require $file as $id => $translated) {
				$data = $this->_merge($data, compact('id', 'translated'));
			}
		}       
		
		return $data;
	}

	protected function _file($category, $locale, $scope) 
	{
		$path  = $this->_config['path'];
		$scope = $scope ?: 'default';

		if(($pos = strpos($category, 'Template')) !== false) {
			$category = substr($category, 0, $pos);
			return "{$path}/{$category}_{$scope}.php";
		}      
		
		return "{$path}/{$locale}/{$category}/{$scope}.php";
	}
}