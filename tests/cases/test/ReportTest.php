<?php

namespace arthur\tests\cases\test;

use arthur\test\Report;
use arthur\test\Group;

class ReportTest extends \arthur\test\Unit 
{

	public function testInit() 
	{
		$report = new Report(array(
			'title' => '\arthur\tests\mocks\test\MockUnitTest',
			'group' => new Group(array('data' => array('\arthur\tests\mocks\test\MockUnitTest')))
		));
		$report->run();

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

	public function testFilters() 
	{
		$report = new Report(array(
			'title' => '\arthur\tests\mocks\test\MockFilterClassTest',
			'group' => new Group(
				array('data' => array('\arthur\tests\mocks\test\MockFilterClassTest'))
			),
			'filters'  => array("Complexity" => ""),
			'format'   => 'html',
			'reporter' => 'html'
		));

		$expected = array('arthur\test\filter\Complexity' => array(
			'name' => 'complexity', 'apply' => array(), 'analyze' => array()
		));
		$result = $report->filters();
		$this->assertEqual($expected, $result);
	}

	public function testStats() 
	{
		$report = new Report(array(
			'title' => '\arthur\tests\mocks\test\MockUnitTest',
			'group' => new Group(array('data' => array('\arthur\tests\mocks\test\MockUnitTest')))
		));
		$report->run();

		$expected = 1;
		$result   = $report->stats();
		$this->assertEqual($expected, $result['count']['asserts']);
		$this->assertEqual($expected, $result['count']['passes']);
		$this->assertTrue($result['success']);
	}

	public function testRender() 
	{
		$report = new Report(array(
			'title'    => '\arthur\tests\mocks\test\MockUnitTest',
			'group'    => new Group(array('data' => array('\arthur\tests\mocks\test\MockUnitTest'))),
			'format'   => 'html',
			'reporter' => 'html'
		));
		$report->run();

		$result = $report->render("stats");
		$this->assertPattern("#1.*1.*passes,.*0.*fails.*0.*exceptions#s", $result);
	}

	public function testSingleFilter() 
	{
		$report = new Report(array(
			'title'  => '\arthur\tests\mocks\test\MockFilterClassTest',
			'group'  => new Group(array(
				'data' => array('\arthur\tests\mocks\test\MockFilterClassTest')
			)),
			'filters' => array("Complexity" => "")
		));
		$report->run();

		$class  = 'arthur\test\filter\Complexity';
		$result = $report->results['filters'][$class];
		$this->assertTrue(isset($report->results['filters'][$class]));
	}
}