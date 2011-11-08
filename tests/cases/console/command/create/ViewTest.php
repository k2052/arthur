<?php

namespace arthur\tests\cases\console\command\create;

use arthur\console\command\create\View;
use arthur\console\Request;
use arthur\core\Libraries;

class ViewTest extends \arthur\test\Unit 
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
		$this->request = new Request(array('input' => fopen('php://temp', 'w+')));
		$this->request->params = array('library' => 'create_test');
	}

	public function tearDown() 
	{
		$_SERVER = $this->_backup['_SERVER'];
		chdir($this->_backup['cwd']);
		$this->_cleanUp();
	}

	public function testIndexView() 
	{
		$this->request->params += array(
			'command' => 'create', 'action' => 'view',
			'args'    => array('Posts', 'index.html')
		);
		$view = new View(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$view->run('view');
		$expected = "index.html.php created in views/posts.\n";
		$result   = $view->response->output;
		$this->assertEqual($expected, $result);

		$result = file_exists($this->_testPath . '/create_test/views/posts/index.html.php');
		$this->assertTrue($result);
	}
}