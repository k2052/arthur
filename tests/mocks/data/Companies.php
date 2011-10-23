<?php

namespace arthur\tests\mocks\data;

class Companies extends \arthur\data\Model 
{
	public $hasMany = array('Employees');
	protected $_meta = array('connection' => 'test');
}