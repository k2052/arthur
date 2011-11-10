<?php

namespace arthur\tests\cases\console\command;

use arthur\console\command\Help;
use arthur\console\Request;    

/* 
 * TODO: Write tests.
 */

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
}