<?php

namespace arthur\tests\mocks\data\source;

class Images extends \arthur\data\Model 
{
	protected $_meta = array('connection' => 'test');
	public $belongsTo = array('Galleries');
}