<?php

namespace arthur\tests\cases\console\command\create;

use arthur\console\command\create\Mock;
use arthur\console\Request;
use arthur\core\Libraries;

class MockTest extends \arthur\test\Unit 
{
	public $request;
	protected $_backup = array();
	protected $_testPath = null;

	public function skip() 
	{
		$this->_testPath = Libraries::get(true, 'resources') . '/tmp/tests';
		$this->skipIf(!is_writable($this->_testPath), "{$this->_testPath} is not writable.");
	}

	public function setUp() 
	{
		$this->classes            = array('response' => 'arthur\tests\mocks\console\MockResponse');
		$this->_backup['cwd']     = getcwd();
		$this->_backup['_SERVER'] = $_SERVER;
		$_SERVER['argv']          = array();

		Libraries::add('create_test', array('path' => $this->_testPath . '/create_test'));
		$this->request         = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->request->params = array('library' => 'create_test');
	}

	public function tearDown() 
	{
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
		$this->_cleanUp();
	}

	public function testMockModel() 
	{
		$this->request->params += array(
			'command' => 'create', 'action' => 'mock',
			'args' => array('model', 'Posts')
		);
		$mock = new Mock(array(
			'request' => $this->request, 'classes' => $this->classes
		));
		$mock->path = $this->_testPath;
		$mock->run('mock');
		$expected = "MockPosts created in create_test\\tests\\mocks\\models.\n";
		$result   = $mock->response->output;
		$this->assertEqual($expected, $result);

		$expected = <<<'test'


namespace create_test\tests\mocks\models;

class MockPosts extends \create_test\models\Posts {


}


test;
		$replace = array("<?php", "?>");
		$result = str_replace($replace, '',
			file_get_contents($this->_testPath . '/create_test/tests/mocks/models/MockPosts.php')
		);
		$this->assertEqual($expected, $result);
	}
}

?>