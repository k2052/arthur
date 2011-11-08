<?php

namespace arthur\tests\cases\console\command;

use arthur\console\command\Help;
use arthur\console\Request;

class HelpTest extends \arthur\test\Unit 
{
	public $request;
	public $classes = array();
	protected $_backup = array();

	public function setUp() 
	{
		$this->classes            = array('response' => 'arthur\tests\mocks\console\MockResponse');
		$this->_backup['cwd']     = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv']          = array();

		$this->request         = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->request->params = array('library' => 'build_test');
	}

	public function tearDown()
	{
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
	}

	public function testRun() 
	{
		$command = new Help(array('request' => $this->request, 'classes' => $this->classes));
		$this->assertTrue($command->run());

		$expected = "COMMANDS via arthur\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);

		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$pattern  = "/\s+test\s+Runs a given set of tests and outputs the results\./ms";
		$this->assertPattern($pattern, $result);
	}

	public function testRunWithName() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$result = $command->run('Test');
		$this->assertTrue($result);
		$result = $command->run('test');
		$this->assertTrue($result);

		$expected = "li3 test [--filters=<string>] [--format=<string>] [<path>]";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);

		$expected = "OPTIONS\n    <path>\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);

		$expected = "DESCRIPTION\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiClass() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->api('arthur.util.Inflector');
		$this->assertNull($result);

		$expected = "Utility for modifying format of words";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiMethod() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->api('arthur.util.Inflector', 'method');
		$this->assertNull($result);

		$expected = "rules";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiMethodWithName() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->api('arthur.util.Inflector', 'method', 'rules');
		$this->assertNull($result);

		$expected = "rules";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiProperty() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->api('arthur.net.Message', 'property');
		$this->assertNull($result);

		$expected = "    --host=<string>\n        The hostname for this endpoint.";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiPropertyWithName() 
	{
		$command = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->api('arthur.net.Message', 'property');
		$this->assertNull($result);

		$expected = "    --host=<string>\n        The hostname for this endpoint.";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testApiProperties() 
	{
		$help = new Help(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$expected = null;
		$result   = $help->api('arthur.tests.mocks.console.command.MockCommandHelp', 'property');
		$this->assertEqual($expected, $result);

		$expected = "\-\-long=<string>.*\-\-blong.*\-s";
		$result   = $help->response->output;
		$this->assertPattern("/{$expected}/s", $result);
	}
}