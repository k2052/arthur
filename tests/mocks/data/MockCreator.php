<?php

namespace arthur\tests\mocks\data;

class MockCreator extends \arthur\data\Model 
{
	protected $_meta = array('connection' => 'mock-source');

	protected $_schema = array(
		'name' => array(
			'default' => 'Moe',
			'type'    => 'string',
			'null'    => false
		),
		'sign' => array(
			'default' => 'bar',
			'type'    => 'string',
			'null'    => false
		),
		'age' => array(
			'default' => 0,
			'type'    => 'number',
			'null'    => false
		)
	);
}