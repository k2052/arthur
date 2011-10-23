<?php

namespace arthur\tests\mocks\data;

class MockBase extends \arthur\data\Model 
{
	protected $_meta = array('connection' => 'mock-source');

	public static function __init() 
	{
		static::_isBase(__CLASS__, true);
		parent::__init();
	}
}