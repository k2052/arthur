<?php

namespace arthur\tests\cases\data\collection;

use arthur\data\collection\DocumentArray;

class DocumentArrayTest extends \arthur\test\Unit 
{
	protected $_model = 'arthur\tests\mocks\data\model\MockDocumentPost';

	public function testInitialCasting() 
	{
		$array = new DocumentArray(array(
			'model'   => $this->_model,
			'pathKey' => 'foo.bar',
			'data'    => array('5', '6', '7')
		));
		foreach($array as $value) {
			$this->assertTrue(is_int($value));
		}
	}

	public function testExport() 
	{
		$array = new DocumentArray(array(
			'model'   => $this->_model,
			'pathKey' => 'foo.bar',
			'data'    => array('5', '6', '7')
		));
		$array[] = 8;
	}
}