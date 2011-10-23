<?php

namespace arthur\tests\mocks\data;

class MockEmployees extends \arthur\data\Model 
{
	protected $_meta = array(
		'source'     => 'employees',
		'connection' => 'test'
	);
}