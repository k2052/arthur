<?php

namespace arthur\tests\mocks\template\helper;

class MockFormPost extends \arthur\data\Model 
{
	public $hasMany = array('MockQueryComment');

	protected $_schema = array(
		'id'        => array('type' => 'integer'),
		'author_id' => array('type' => 'integer'),
		'title'     => array('type' => 'string'),
		'body'      => array('type' => 'text'),
		'created'   => array('type' => 'datetime'),
		'updated'   => array('type' => 'datetime')
	);
}