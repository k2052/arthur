<?php

namespace arthur\tests\cases\console\command;

use arthur\console\command\Test;
use arthur\console\Request;
use arthur\core\Libraries;

class TestTest extends \arthur\test\Unit 
{
	public $request;
	public $classes = array();
	protected $_backup = array();

	public function setUp() 
	{
		Libraries::cache(false);

		$this->classes = array(
			'response' => 'arthur\tests\mocks\console\MockResponse'
		);
		$this->_backup['cwd']     = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv']          = array();

		chdir(ARTHUR_LIBRARY_PATH . '/arthur');

		$this->request          = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->request->params = array('library' => 'build_test');
	}

	public function tearDown() 
	{
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
	}

	public function testRunWithoutPath() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$result = $command->run();
		$this->assertFalse($result);
	}

	public function testRunWithInvalidPath() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path = 'Foobar/arthur/tests/mocks/test/cases/MockTest.php';
		$command->run($path);
		$this->assertEqual("Not a valid path.\n", $command->response->error);
	}

	public function testRunWithInvalidHandler() 
	{
		$command = new Test(array(
			'request' => $this->request,
			'classes' => $this->classes
		));
		$command->format = 'foobar';
		$path            = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases/MockTest.php';
		$command->run($path);
		$this->assertEqual("No handler for format `foobar`... \n", $command->response->error);
	}

	public function testRunSingleTestWithAbsolutePath() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases/MockTest.php';
		$command->run($path);

		$expected = "1 passes\n0 fails and 0 exceptions\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testRunSingleTestWithRelativePath() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$path = 'tests/mocks/test/cases/MockTest.php';
		$command->run($path);

		$expected = "1 passes\n0 fails and 0 exceptions\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);

		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$current = basename(getcwd());
		$path    = "../{$current}/tests/mocks/test/cases/MockTest.php";
		$command->run($path);

		$expected = "1 passes\n0 fails and 0 exceptions\n";
		$expected = preg_quote($expected);
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testRunMultipleTestsWithAbsolutePath() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases';
		$command->run($path);

		$expected = "1 / 1 passes\n0 fails and 2 exceptions\n";
		$expected = preg_quote($expected, '/');
		$result   = $command->response->output;
		$this->assertPattern("/{$expected}/", $result);
	}

	public function testReturnRunTestPasses() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path   = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases/MockTest.php';
		$result = $command->run($path);
		$this->assertTrue($result);
	}

	public function testReturnRunTestFails() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path   = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases/MockTestErrorHandling.php';
		$result = $command->run($path);
		$this->assertFalse($result);
	}

	public function testJsonFormat() 
	{
		$command = new Test(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$path = ARTHUR_LIBRARY_PATH . '/arthur/tests/mocks/test/cases/MockTest.php';
		$command->format = 'json';
		$command->run($path);

		$result = $command->response->output;
		$result = json_decode($result, true);

		$this->assertTrue(isset($result['count']));
		$this->assertTrue(isset($result['stats']));
	}
}