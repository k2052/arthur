<?php

namespace arthur\g11n\catalog;

class Adapter extends \arthur\core\Object 
{
	public function read($category, $locale, $scope) 
	{
		return null;
	}

	public function write($category, $locale, $scope, array $data) 
	{
		return false;
	}

	protected function _prepareForWrite(array $item) 
	{
		return $item;
	}

	protected function _merge(array $data, array $item) 
	{
		if(!isset($item['id']))
			return $data;
		$id = $item['id'];

		$defaults = array(
			'ids'         => array(),
			'translated'  => null,
			'flags'       => array(),
			'comments'    => array(),
			'occurrences' => array()
		);
		$item += $defaults;

		if(!isset($data[$id])) {
			$data[$id] = $item;
			return $data;
		}
		foreach(array('ids', 'flags', 'comments', 'occurrences') as $field) {
			$data[$id][$field] = array_merge($data[$id][$field], $item[$field]);
		}
		if(!isset($data[$id]['translated']))
			$data[$id]['translated'] = $item['translated'];
		elseif (is_array($item['translated']))
			$data[$id]['translated'] = (array) $data[$id]['translated'] + $item['translated'];

		return $data;
	}
}