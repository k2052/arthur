<?php

namespace arthur\tests\cases\test\filter;

use arthur\test\filter\Complexity;
use arthur\test\Group;
use arthur\test\Report;

class ComplexityTest extends \arthur\test\Unit 
{
	protected $_paths = array(
		'complexity'    => 'arthur\test\filter\Complexity',
		'testClass'     => 'arthur\core\StaticObject',
		'testClassTest' => 'arthur\tests\cases\core\StaticObjectTest'
	);

	protected $_metrics = array(
		'invokeMethod' => 8,
		'_filter'      => 3,
		'applyFilter'  => 3,
		'_parents'     => 2,
		'_instance'    => 2,
		'_stop'        => 1
	);

	public function setUp() 
	{
		$this->report = new Report();
	}

	public function testApply() 
	{
		extract($this->_paths);

		$group = new Group();
		$group->add($testClassTest);
		$this->report->group = $group;

		Complexity::apply($this->report, $group->tests());
		$results  = array_pop($this->report->results['filters'][$complexity]);
		$expected = array($testClass => $this->_metrics);
		$this->assertEqual($expected, $results);
	}

	public function testAnalyze() 
	{
		extract($this->_paths);

		$group = new Group();
		$group->add($testClassTest);
		$this->report->group = $group;

		Complexity::apply($this->report, $group->tests());

		$results = Complexity::analyze($this->report);
		$expected = array('class' => array($testClass => 3));
		foreach($this->_metrics as $method => $metric) {
			$expected['max'][$testClass . '::' . $method . '()'] = $metric;
		}
		$this->assertEqual($expected['max'], $results['max']);
		$this->assertEqual($expected['class'][$testClass], round($results['class'][$testClass]));
	}

	public function testCollect() 
	{
		extract($this->_paths);

		$group = new Group();
		$group->add($testClassTest);
		$this->report->group = $group;

		Complexity::apply($this->report, $group->tests());

		$results  = Complexity::collect($this->report->results['filters'][$complexity]);
		$expected = array($testClass => $this->_metrics);
		$this->assertEqual($expected, $results);
	}
}