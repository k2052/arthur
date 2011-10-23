<?php

namespace arthur\tests\mocks\test;

use arthur\tests\mocks\test\MockFilterClass;

class MockFilterClassTest extends \arthur\test\Unit 
{
	public function testNothing() 
	{
		$coverage = new MockFilterClass();
		$this->assertTrue(true);
	}
}