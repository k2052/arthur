<?php

namespace arthur\tests\mocks\data\model;

class MockQueryPost extends \arthur\data\Model 
{
	public $hasMany = array('MockQueryComment');

	protected $_meta = array(
		'source'     => false,
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'        => array('type' => 'integer', 'key' => 'primary'),
		'author_id' => array('type' => 'integer'),
		'title'     => array('type' => 'string', 'length' => 255),
		'body'      => array('type' => 'text'),
		'created'   => array('type' => 'datetime'),
		'updated'   => array('type' => 'datetime')
	);
}
