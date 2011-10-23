<?php

namespace arthur\tests\mocks\data;

use arthur\data\model\Query;
use arthur\data\entity\Record;
use arthur\data\collection\RecordSet;

class MockComment extends \arthur\tests\mocks\data\MockBase 
{
	public $belongsTo = array('MockPost');
	protected $_meta = array('key' => 'comment_id');

	public static function find($type, array $options = array()) 
	{
		$defaults = array(
			'conditions' => null, 'fields' => null, 'order' => null, 'limit' => null, 'page' => 1
		);
		$options += $defaults;
		$params   = compact('type', 'options');
		$self     = static::_object();

		$filter = function($self, $params) 
		{
			extract($params);
			$query = new Query(array('type' => 'read') + $options);

			return new RecordSet(array(
				'query'    => $query,
				'data'    => array_map(
					function($data) { return new Record(compact('data')); },
					array(
						array('comment_id' => 1, 'author_id' => 123, 'text' => 'First comment'),
						array('comment_id' => 2, 'author_id' => 241, 'text' => 'Second comment'),
						array('comment_id' => 3, 'author_id' => 451, 'text' => 'Third comment')
					)
				)
			));
		};
		$finder = isset($self->_finders[$type]) ? array($self->_finders[$type]) : array();  
		
		return static::_filter(__METHOD__, $params, $filter, $finder);
	}
}