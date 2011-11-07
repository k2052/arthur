<?php

namespace arthur\tests\cases\test\filter;

use arthur\test\filter\Affected;
use arthur\test\Group;
use arthur\test\Report;

class AffectedTest extends \arthur\test\Unit 
{
	public function setUp() 
	{
		$this->report = new Report();
	}

	public function testSingleTest() 
	{
		$group = new Group();
		$group->add('arthur\tests\cases\g11n\CatalogTest');
		$this->report->group = $group;
		$tests = Affected::apply($this->report, $group->tests());

		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest',
			'arthur\tests\cases\g11n\MessageTest',
			'arthur\tests\cases\console\command\g11n\ExtractTest'
		);
		$result = $tests->map('get_class', array('collect' => false));
		$this->assertEqual($expected, $result);
	}

	public function testSingleTestWithSingleResult() 
	{
		$group = new Group();
		$group->add('arthur\tests\cases\core\StaticObjectTest');
		$this->report->group = $group;
		$tests = Affected::apply($this->report, $group->tests());

		$expected = array('arthur\tests\cases\core\StaticObjectTest');
		$result   = $tests->map('get_class', array('collect' => false));
		$this->assertEqual($expected, $result);
	}

	public function testMultipleTests() 
	{
		$group = new Group();
		$group->add('arthur\tests\cases\g11n\CatalogTest');
		$group->add('arthur\tests\cases\analysis\LoggerTest');
		$this->report->group = $group;
		$tests = Affected::apply($this->report, $group->tests());

		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest',
			'arthur\tests\cases\analysis\LoggerTest',
			'arthur\tests\cases\g11n\MessageTest',
			'arthur\tests\cases\console\command\g11n\ExtractTest'
		);
		$result = $tests->map('get_class', array('collect' => false));
		$this->assertEqual($expected, $result);
	}

	public function testCyclicDependency() 
	{
		$group = new Group();
		$group->add('arthur\tests\cases\g11n\CatalogTest');
		$group->add('arthur\tests\cases\g11n\MessageTest');
		$this->report->group = $group;
		$tests = Affected::apply($this->report, $group->tests());

		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest',
			'arthur\tests\cases\g11n\MessageTest',
			'arthur\tests\cases\console\command\g11n\ExtractTest'
		);
		$result = $tests->map('get_class', array('collect' => false));
		$this->assertEqual($expected, $result);
	}

	public function testAnalyze() 
	{
		$ns = 'arthur\tests\cases';

		$expected = array(
			'arthur\g11n\Message' => "{$ns}\g11n\MessageTest",
			'arthur\console\command\g11n\Extract' => "{$ns}\console\command\g11n\ExtractTest"
		);

		$group = new Group();
		$group->add('arthur\tests\cases\g11n\CatalogTest');
		$this->report->group = $group;
		$tests   = Affected::apply($this->report, $group->tests());
		$results = Affected::analyze($this->report);

		$this->assertEqual($results, $expected);
	}
}