<?php

namespace arthur\tests\cases\storage\cache\strategy;

use arthur\storage\cache\strategy\Serializer;

class SerializerTest extends \arthur\test\Unit 
{

	public function setUp() 
	{
		$this->Serializer = new Serializer();
	}

	public function testWrite() {
		$data     = array('some' => 'data');
		$result   = $this->Serializer->write($data);
		$expected = serialize($data);
		$this->assertEqual($expected, $result);
	}

	public function testRead() 
	{
		$encoded  = 'a:1:{s:4:"some";s:4:"data";}';
		$expected = unserialize($encoded);
		$result   = $this->Serializer->read($encoded);
		$this->assertEqual($expected, $result);
	}
}