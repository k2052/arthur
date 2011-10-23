<?php

namespace arthur\tests\mocks\data\model;

class MockQueryComment extends \arthur\data\Model 
{
	protected $_meta = array(
		'source'     => false,
		'connection' => 'mock-database-connection'
	);

	protected $_schema = array(
		'id'        => array('type' => 'integer', 'key' => 'primary'),
		'author_id' => array('type' => 'integer'),
		'comment'   => array('type' => 'text'),
		'created'   => array('type' => 'datetime'),
		'updated'   => array('type' => 'datetime')
	);
}