<?php

namespace arthur\tests\mocks\data;

class Employees extends \arthur\data\Model 
{
	public $belongsTo = array('Companies');
	protected $_meta = array('connection' => 'test');

	public function lastName($entity) 
	{
		$name = explode(' ', $entity->name);
		return $name[1];
	}
}