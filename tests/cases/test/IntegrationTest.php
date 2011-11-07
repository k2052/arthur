<?php

namespace arthur\tests\cases\test;

use arthur\tests\mocks\test\MockIntegrationTest;

class IntegrationTest extends \arthur\test\Unit {

	public function testIntegrationHaltsOnFail() 
	{
		$test = new MockIntegrationTest();

		$expected = 2;
		$report   = $test->run();
		$result   = count($report);

		$this->assertEqual($expected, $result);
	}
}