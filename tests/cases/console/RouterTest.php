<?php

namespace arthur\tests\cases\console;

use arthur\console\Router;
use arthur\console\Request;

class RouterTest extends \arthur\test\Unit 
{
	protected $_backup;

	public function setUp() 
	{
		$this->_backup   = $_SERVER;
		$_SERVER['argv'] = array();
	}

	public function tearDown() 
	{
		$_SERVER = $this->_backup;
	}

	public function testParseNoArgumentsNoOptions() 
	{
		$expected = array(
			'command' => null, 'action' => 'run', 'args' => array()
		);
		$result = Router::parse();
		$this->assertEqual($expected, $result);
	}

	public function testParseArguments() 
	{
		$expected = array(
			'command' => 'test', 'action' => 'action',
			'args'    => array('param')
		);
		$result = Router::parse(new Request(array(
			'args' => array('test', 'action', 'param')
		)));
		$this->assertEqual($expected, $result);
	}

	public function testParseGnuStyleLongOptions() 
	{
		$expected = array(
			'command' => 'test', 'action' => 'run', 'args' => array(),
			'case'    => 'arthur.tests.cases.console.RouterTest'
		);
		$result = Router::parse(new Request(array(
			'args' => array(
				'test', 'run',
				'--case=arthur.tests.cases.console.RouterTest'
			)
		)));
		$this->assertEqual($expected, $result);

		$expected = array(
			'command' => 'test', 'action' => 'run', 'args' => array(),
			'case'    => 'arthur.tests.cases.console.RouterTest',
			'phase'   => 'drowning'
		);
		$result = Router::parse(new Request(array(
			'args' => array(
				'test',
				'--case=arthur.tests.cases.console.RouterTest',
				'--phase=drowning'
			)
		)));
		$this->assertEqual($expected, $result);
	}

	public function testParseShortOption() 
	{
		$expected = array(
			'command' => 'test', 'action' => 'action', 'args' => array(),
			'i'       => true
		);
		$result = Router::parse(new Request(array(
			'args' => array('test', 'action', '-i')
		)));
		$this->assertEqual($expected, $result);

		$expected = array(
			'command' => 'test', 'action' => 'action', 'args' => array('something'),
			'i'       => true
		);
		$result = Router::parse(new Request(array(
			'args' => array('test', 'action', '-i', 'something')
		)));
		$this->assertEqual($expected, $result);
	}

	public function testParseShortOptionAsFirst() 
	{
		$expected = array(
			'command' => 'test', 'action' => 'action', 'args' => array(),
			'i'       => true
		);
		$result = Router::parse(new Request(array(
			'args' => array('-i', 'test', 'action')
		)));
		$this->assertEqual($expected, $result);

		$expected = array(
			'command' => 'test', 'action' => 'action', 'args' => array('something'),
			'i'       => true
		);
		$result = Router::parse(new Request(array(
			'args' => array('-i', 'test', 'action', 'something')
		)));
		$this->assertEqual($expected, $result);
	}

	public function testParseGnuStyleLongOptionAsFirst() 
	{
		$expected = array(
			'command' => 'test', 'action' => 'action', 'long' => 'something', 'i' => true,
			'args'    => array()
		);
		$result = Router::parse(new Request(array(
			'args' => array('--long=something', 'test', 'action', '-i')
		)));
		$this->assertEqual($expected, $result);
	}
}