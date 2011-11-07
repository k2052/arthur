<?php

namespace arthur\tests\cases\test;

use arthur\test\Dispatcher;
use arthur\util\Collection;

class DispatcherTest extends \arthur\test\Unit 
{
	public function testRunDefaults() 
	{
		$report = Dispatcher::run();
		$this->assertTrue(is_a($report, '\arthur\test\Report'));

		$result = $report->group;
		$this->assertTrue(is_a($result, '\arthur\test\Group'));
	}

	public function testRunWithReporter() 
	{
		$report = Dispatcher::run(null, array('reporter' => 'html'));
		$this->assertTrue(is_a($report, '\arthur\test\Report'));

		$result = $report->group;
		$this->assertTrue(is_a($result, '\arthur\test\Group'));
	}

	public function testRunCaseWithString() 
	{
		$report = Dispatcher::run('\arthur\tests\mocks\test\MockUnitTest');

		$expected = '\arthur\tests\mocks\test\MockUnitTest';
		$result   = $report->title;
		$this->assertEqual($expected, $result);

		$expected = 'testNothing';
		$result   = $report->results['group'][0][0]['method'];
		$this->assertEqual($expected, $result);

		$expected = 'pass';
		$result   = $report->results['group'][0][0]['result'];
		$this->assertEqual($expected, $result);
	}

	public function testRunGroupWithString() 
	{
		$report = Dispatcher::run('\arthur\tests\mocks\test');

		$expected = '\arthur\tests\mocks\test';
		$result   = $report->title;
		$this->assertEqual($expected, $result);

		$expected = new Collection(array(
			'data' => array(
				new \arthur\tests\mocks\test\cases\MockSkipThrowsException(),
				new \arthur\tests\mocks\test\cases\MockTest(),
				new \arthur\tests\mocks\test\cases\MockTestErrorHandling()
			)
		));
		$result = $report->group->tests();
		$this->assertEqual($expected, $result);

		$expected = 'testNothing';
		$result   = $report->results['group'][1][0]['method'];
		$this->assertEqual($expected, $result);

		$expected = 'pass';
		$result   = $report->results['group'][1][0]['result'];
		$this->assertEqual($expected, $result);
	}
}