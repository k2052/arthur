<?php

namespace arthur\tests\mocks\data;

class MockTagging extends \arthur\data\Model 
{
	protected $_meta = array(
		'connection' => 'mock-source',
		'source' => 'posts_tags', 'key' => array('post_id', 'tag_id')
	);
}
