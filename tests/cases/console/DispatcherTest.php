<?php

namespace arthur\tests\cases\console;

use arthur\console\Dispatcher;
use arthur\console\Request;

class DispatcherTest extends \arthur\test\Unit 
{
	protected $_backup = array();

	public function setUp() 
	{
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv']          = array();
	}

	public function tearDown() 
	{
		$_SERVER = $this->_backup['_SERVER'];
	}

	public function testEmptyConfigReturnRules() 
	{
		$result = Dispatcher::config();
		$expected = array('rules' => array(
			'command' => array(array('arthur\util\Inflector', 'camelize')),
			'action'  => array(array('arthur\util\Inflector', 'camelize', array(false)))
		));
		$this->assertEqual($expected, $result);
	}

	public function testConfigWithClasses() 
	{
		Dispatcher::config(array(
			'classes' => array(
				'request' => 'arthur\tests\mocks\console\MockDispatcherRequest'
			)
		));
		$expected = 'run';
		$result   = Dispatcher::run()->testAction;
		$this->assertEqual($expected, $result);
	}

	public function testRunWithCommand() 
	{
		$response = Dispatcher::run(new Request(array(
			'args' => array(
				'arthur\tests\mocks\console\MockDispatcherCommand'
			)
		)));
		$expected = 'run';
		$result   = $response->testAction;
		$this->assertEqual($expected, $result);
	}

	public function testRunWithPassed() 
	{
		$response = Dispatcher::run(new Request(array(
			'args' => array('arthur\tests\mocks\console\MockDispatcherCommand', 'with param')
		)));

		$expected = 'run';
		$result  = $response->testAction;
		$this->assertEqual($expected, $result);

		$expected = 'with param';
		$result   = $response->testParam;
		$this->assertEqual($expected, $result);
	}

	public function testRunWithAction() 
	{
		$response = Dispatcher::run(new Request(array(
			'args' => array('arthur\tests\mocks\console\MockDispatcherCommand', 'testAction')
		)));
		$expected = 'testAction';
		$result   = $response->testAction;
		$this->assertEqual($expected, $result);
	}

	public function testInvalidCommand() 
	{
		$expected = (object) array('status' => "Command `\\this\\command\\is\\fake` not found.\n");
		$result = Dispatcher::run(new Request(array(
			'args' => array(
				'\this\command\is\fake',
				'testAction'
			)
		)));

		$this->assertEqual($expected, $result);
	}

	public function testRunWithCamelizingCommand() 
	{
		$expected = (object) array('status' => "Command `FooBar` not found.\n");
		$result = Dispatcher::run(new Request(array(
			'args' => array(
				'foo-bar'
			)
		)));
		$this->assertEqual($expected, $result);

		$expected = (object) array('status' => "Command `FooBar` not found.\n");
		$result   = Dispatcher::run(new Request(array(
			'args' => array('foo_bar')
		)));
		$this->assertEqual($expected, $result);
	}

	public function testRunWithCamelizingAction() 
	{
		$result = Dispatcher::run(new Request(array(
			'args' => array(
				'arthur\tests\mocks\console\command\MockCommandHelp',
				'sample-task-with-optional-args'
			)
		)));
		$this->assertTrue($result);

		$result = Dispatcher::run(new Request(array(
			'args' => array(
				'arthur\tests\mocks\console\command\MockCommandHelp',
				'sample_task_with_optional_args'
			)
		)));
		$this->assertTrue($result);
	}
}