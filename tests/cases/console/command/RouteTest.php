<?php

namespace arthur\tests\cases\console\command;

use arthur\console\command\Route;
use arthur\console\Request;
use arthur\net\http\Router;

class RouteTest extends \arthur\test\Unit 
{
	protected $_config = array('routes_file' => '');
	protected $_testPath = null;

	public function skip() 
	{
		$this->_testPath = ARTHUR_APP_PATH . '/resources/tmp/tests';
		$this->skipIf(!is_writable($this->_testPath), "{$this->_testPath} is not writable.");
	}

	public function setUp() 
	{
		$this->_config['routes_file'] = "{$this->_testPath}/routes.php";

		$testParams = 'array("controller" => "arthur\test\Controller")';
		$content = array(
			'<?php',
			'use arthur\net\http\Router;',
			'use arthur\core\Environment;',
			'Router::connect("/", "Pages::view");',
			'Router::connect("/pages/{:args}", "Pages::view");',
			'if (!Environment::is("production")) {',
				'Router::connect("/test/{:args}", ' . $testParams . ');',
				'Router::connect("/test", ' . $testParams . ');',
			'}',
			'?>'
		);
		file_put_contents($this->_config['routes_file'], join("\n", $content));

		Router::reset();
	}

	public function tearDown() 
	{
		if(file_exists($this->_config['routes_file']))
			unlink($this->_config['routes_file']);
	}

	public function testEnvironment() 
	{
		$command  = new Route();
		$expected = 'development';
		$this->assertEqual($expected, $command->env);

		$request = new Request();
		$request->params['env'] = 'production';   
		
		$command  = new Route(array('request' => $request));
		$expected = 'production';
		$this->assertEqual($expected, $command->env);
	}

	public function testRouteLoading() 
	{
		$this->assertFalse(Router::get());

		$command = new Route(array('routes_file' => $this->_config['routes_file']));
		$this->assertEqual(4, count(Router::get()));

		Router::reset();

		$request = new Request();
		$request->params['env'] = 'production';
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'request'     => $request
		));
		$this->assertEqual(2, count(Router::get()));
	}

	public function testAllWithoutEnvironment() 
	{
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => new Request()
		));

		$command->all();

		$expected = 'TemplateParams--------------
			/{"controller":"pages","action":"view"}
			/pages/{:args}{"controller":"pages","action":"view"}
			/test/{:args}{"controller":"arthur\\test\\\\Controller","action":"index"}
			/test{"controller":"arthur\\test\\\\Controller","action":"index"}';
		$this->assertEqual($this->_strip($expected),$this->_strip($command->response->output));
	}

	public function testAllWithEnvironment()
	 {
		$request         = new Request();
		$request->params = array(
			'env' => 'production'
		);
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => $request
		));

		$command->all();

		$expected = 'TemplateParams--------------
			/{"controller":"pages","action":"view"}
			/pages/{:args}{"controller":"pages","action":"view"}';
		$this->assertEqual($this->_strip($expected),$this->_strip($command->response->output));
	}

	public function testRun() 
	{
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => new Request()
		));

		$command->run();

		$expected = 'TemplateParams--------------
			/{"controller":"pages","action":"view"}
			/pages/{:args}{"controller":"pages","action":"view"}
			/test/{:args}{"controller":"arthur\\test\\\\Controller","action":"index"}
			/test{"controller":"arthur\\test\\\\Controller","action":"index"}';
		$this->assertEqual($this->_strip($expected),$this->_strip($command->response->output));
	}

	public function testShowWithNoRoute() 
	{
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => new Request()
		));

		$command->show();

		$expected = "Please provide a valid URL\n";
		$this->assertEqual($expected, $command->response->error);
	}

	public function testShowWithInvalidRoute() 
	{
		$request = new Request();
		$request->params = array(
			'args' => array('/foobar')
		);
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => $request
		));
		$command->show();

		$expected = "No route found.\n";
		$this->assertEqual($expected, $command->response->output);
	}

	public function testShowWithValidRoute() 
	{
		$request         = new Request();
		$request->params = array('args' => array('/'));
		$command         = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => $request
		));
		$command->show();

		$expected = "{\"controller\":\"pages\",\"action\":\"view\"}\n";
		$this->assertEqual($expected, $command->response->output);
	}

	public function testShowWithEnvironment() 
	{
		$request         = new Request();
		$request->params = array(
			'env'   => 'production',
			'args' => array('/test')
		);   
		
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => $request
		));

		$command->show();

		$expected = "No route found.\n";
		$this->assertEqual($expected, $command->response->output);
	}

	public function testShowWithHttpMethod() 
	{
		$request = new Request();
		$request->params = array(
			'args' => array('post', '/')
		);
		$command = new Route(array(
			'routes_file' => $this->_config['routes_file'],
			'classes'     => array('response' => '\arthur\tests\mocks\console\MockResponse'),
			'request'     => $request
		));

		$command->show();

		$expected = "{\"controller\":\"pages\",\"action\":\"view\"}\n";
		$this->assertEqual($expected, $command->response->output);
	}
	
	protected function _strip($str) 
	{
		return preg_replace('/\s/', '', $str);
	}
}