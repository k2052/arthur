<?php

namespace lithium\tests\cases\analysis\logger\adapter;

use lithium\analysis\Logger;
use lithium\analysis\logger\adapter\FirePhp;
use lithium\action\Response;

class FirePhpTest extends \lithium\test\Unit 
{
	public function setUp() 
	{
		$this->firephp = new FirePhp();
		Logger::config(array('firephp' => array('adapter' => $this->firephp)));
	}

	public function testConstruct() 
	{
		$expected = array('init' => true);
		$this->assertEqual($expected, $this->firephp->_config);
	}

	public function testConfiguration() 
	{
		$loggers = Logger::config();
		$result  = isset($loggers['firephp']);
		$this->assertTrue($result);
	}

	public function testWrite() 
	{
		$response = new Response();
		$result   = Logger::write('debug', 'FirePhp to the rescue!', array('name' => 'firephp'));
		$this->assertTrue($result);
		$this->assertFalse($response->headers());

		$host     = 'meta.firephp.org';
		$expected = array(
			"X-Wf-Protocol-1: http://meta.wildfirehq.org/Protocol/JsonStream/0.2",
			"X-Wf-1-Plugin-1: http://{$host}/Wildfire/Plugin/FirePHP/Library-FirePHPCore/0.3",
			"X-Wf-1-Structure-1: http://{$host}/Wildfire/Structure/FirePHP/FirebugConsole/0.1",
			"X-Wf-1-1-1-1: 41|[{\"Type\":\"LOG\"},\"FirePhp to the rescue!\"]|"
		);
		Logger::adapter('firephp')->bind($response);
		$this->assertEqual($expected, $response->headers());

		$result = Logger::write('debug', 'Add this immediately.', array('name' => 'firephp'));
		$this->assertTrue($result);
		$expected[] = 'X-Wf-1-1-1-2: 40|[{"Type":"LOG"},"Add this immediately."]|';
		$this->assertEqual($expected, $response->headers());
	}
}