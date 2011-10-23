<?php

namespace arthur\tests\mocks\data\model;

class MockDatabaseTagging extends \arthur\data\Model 
{
	public $belongsTo = array('MockDatabasePost', 'MockDatabaseTag');

	protected $_meta = array(
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'      => array('type' => 'integer'),
		'post_id' => array('type' => 'integer'),
		'tag_id'  => array('type' => 'integer')
	);
}