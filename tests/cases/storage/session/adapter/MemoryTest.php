<?php

namespace arthur\tests\cases\storage\session\adapter;

use arthur\storage\session\adapter\Memory;

class MemoryTest extends \arthur\test\Unit 
{
	public function setUp() 
	{
		$this->Memory = new Memory();
	}

	public function tearDown() 
	{
		unset($this->Memory);
	}

	public function testKey() 
	{
		$key1 = Memory::key();
		$this->assertTrue($key1);

		$key2 = Memory::key();
		$this->assertNotEqual($key1, $key2);

		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
		$this->assertPattern($pattern, Memory::key());
	}

	public function testEnabled() 
	{
		$this->assertTrue(Memory::enabled());
	}
	
	public function testIsStarted() 
	{
		$this->assertTrue($this->Memory->isStarted());
	}

	public function testRead() 
	{
		$this->Memory->read();

		$key   = 'read_test';
		$value = 'value to be read';

		$this->Memory->_session[$key] = $value;

		$closure = $this->Memory->read($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);

		$this->assertIdentical($value, $result);

		$key     = 'non-existent';
		$closure = $this->Memory->read($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);
		$this->assertNull($result);

		$closure = $this->Memory->read();
		$this->assertTrue(is_callable($closure));

		$result   = $closure($this->Memory, array('key' => null), null);
		$expected = array('read_test' => 'value to be read');
		$this->assertEqual($expected, $result);
	}

	public function testWrite() 
	{
		$key   = 'write-test';
		$value = 'value to be written';

		$closure = $this->Memory->write($key, $value);
		$this->assertTrue(is_callable($closure));

		$params = compact('key', 'value');
		$result = $closure($this->Memory, $params, null);
		$this->assertEqual($this->Memory->_session[$key], $value);
	}

	public function testCheck() 
	{
		$this->Memory->read();

		$key   = 'read';
		$value = 'value to be read';
		$this->Memory->_session[$key] = $value;

		$closure = $this->Memory->check($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);
		$this->assertTrue($result);

		$key     = 'does_not_exist';
		$closure = $this->Memory->check($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);
		$this->assertFalse($result);
	}

	public function testDelete() 
	{
		$this->Memory->read();

		$key   = 'delete_test';
		$value = 'value to be deleted';

		$this->Memory->_session[$key] = $value;

		$closure = $this->Memory->delete($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);
		$this->assertTrue($result);

		$key     = 'non-existent';
		$closure = $this->Memory->delete($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Memory, $params, null);
		$this->assertTrue($result);
	}

	public function testClear() 
	{
		$this->Memory->_session['foobar'] = 'foo';
		$closure = $this->Memory->clear();
		$this->assertTrue(is_callable($closure));
		$result = $closure($this->Memory, array(), null);
		$this->assertTrue(empty($this->Memory->_session));
	}
}