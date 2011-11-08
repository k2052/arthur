<?php

namespace arthur\tests\cases\console\command\create;

use arthur\console\command\create\Model;
use arthur\console\Request;
use arthur\core\Libraries;

class ModelTest extends \arthur\test\Unit 
{
	public $request;
	protected $_backup = array();
	protected $_testPath = null;

	public function skip() 
	{
		$this->_testPath = Libraries::get(true, 'resources') . '/tmp/tests';
		$this->skipIf(!is_writable($this->_testPath), "{$this->_testPath} is not readable.");
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

	public function testClass() 
	{
		$this->request->params = array(
			'command' => 'model', 'action' => 'Posts'
		);
		$model = new Model(array(
			'request' => $this->request, 'classes' => $this->classes
		));

		$expected = 'Posts';
		$result   = $model->invokeMethod('_class', array($this->request));
		$this->assertEqual($expected, $result);
	}
}