<?php

namespace arthur\tests\mocks\data\source\http\adapter;

class MockCouchPost extends \arthur\data\Model 
{
	protected $_meta = array(
		'source'     => 'posts',
		'connection' => 'mock-couchdb-connection'
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