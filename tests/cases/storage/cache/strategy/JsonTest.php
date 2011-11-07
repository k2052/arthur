<?php

namespace arthur\tests\cases\storage\cache\strategy;

use arthur\storage\cache\strategy\Json;

class JsonTest extends \arthur\test\Unit 
{
	public function setUp() 
	{
		$this->Json = new Json();
	}

	public function testWrite() 
	{
		$data     = array('some' => 'data');
		$result   = $this->Json->write($data);
		$expected = json_encode($data);
		$this->assertEqual($expected, $result);
	}

	public function testRead() 
	{
		$expected = array('some' => 'data');
		$encoded  = json_encode($expected);
		$result   = $this->Json->read($encoded);
		$this->assertEqual($expected, $result);
	}
}