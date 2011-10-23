<?php

namespace arthur\tests\mocks\data\source;

class Galleries extends \arthur\data\Model 
{
	protected $_meta = array('connection' => 'test');
	public $hasMany = array('Images');
}
