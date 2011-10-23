<?php

namespace arthur\tests\mocks\data\model;

class MockDatabaseTag extends \arthur\data\Model 
{
	public $hasMany = array('MockDatabaseTagging');

	protected $_meta = array(
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'      => array('type' => 'integer'),
		'title'   => array('type' => 'string'),
		'created' => array('type' => 'datetime')
	);
}