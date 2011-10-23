<?php

namespace arthur\tests\mocks\test;

class MockIntegrationTest extends \arthur\test\Integration 
{
	public function testPass() 
	{
		$this->assertTrue(true);
	}

	public function testFail() 
	{
		$this->assertTrue(false);
	}

	public function testAnotherPass() 
	{
		$this->assertTrue(true);
	}
}