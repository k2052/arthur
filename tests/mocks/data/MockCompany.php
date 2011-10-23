<?php

namespace arthur\tests\mocks\data;

class MockCompany extends \arthur\data\Model 
{
	public $hasMany = array(
		'Employee' => array(
			'keys' => array(
				'id' => 'company_id'
			),
			'to' => 'arthur\tests\mocks\data\MockEmployees'
		)
	); 
	
	protected $_meta = array(
		'source'     => 'companies',
		'connection' => 'test'
	);
}