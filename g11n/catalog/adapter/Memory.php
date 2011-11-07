<?php

namespace arthur\g11n\catalog\adapter;

class Memory extends \arthur\g11n\catalog\Adapter 
{
	protected $_data = array();

	public function read($category, $locale, $scope) 
	{
		$scope = $scope ?: 'default';

		if(isset($this->_data[$scope][$category][$locale]))
			return $this->_data[$scope][$category][$locale];
	}

	public function write($category, $locale, $scope, array $data) 
	{
		$scope = $scope ?: 'default';

		if(!isset($this->_data[$scope][$category][$locale]))
			$this->_data[$scope][$category][$locale] = array();

		foreach($data as $item) 
		{
			$this->_data[$scope][$category][$locale] = $this->_merge(
				$this->_data[$scope][$category][$locale],
				$this->_prepareForWrite($item)
			);
		} 
		
		return true;
	}
}