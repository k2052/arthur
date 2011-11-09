<?php

namespace arthur\tests\mocks\data\model;

class MockDatabaseComment extends \arthur\data\Model 
{
	public $belongsTo = array('MockDatabasePost');

	protected $_meta = array(
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'        => array('type' => 'integer'),
		'post_id'   => array('type' => 'integer'),
		'author_id' => array('type' => 'integer'),
		'body'      => array('type' => 'text'),
		'created'   => array('type' => 'datetime')
	);
}