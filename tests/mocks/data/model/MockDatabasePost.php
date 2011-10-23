<?php

namespace arthur\tests\mocks\data\model;

class MockDatabasePost extends \arthur\data\Model 
{
	public $hasMany = array('MockDatabaseComment');

	protected $_meta = array(
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'        => array('type' => 'integer'),
		'author_id' => array('type' => 'integer'),
		'title'     => array('type' => 'string'),
		'created'   => array('type' => 'datetime')
	);
}