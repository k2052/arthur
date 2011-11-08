<?php

namespace lithium\tests\cases\analysis\logger\adapter;

use lithium\storage\Cache As CacheStorage;
use lithium\analysis\Logger;
use lithium\analysis\logger\adapter\Cache;

class CacheTest extends \lithium\test\Unit 
{

	public function setUp() 
	{
		CacheStorage::config(array(
			'cachelog' => array(
				'adapter' => 'Memory'
			)
		));
		$this->cachelog = new Cache(array(
			'key'    => 'cachelog_testkey',
			'config' => 'cachelog'
		));
		Logger::config(array(
			'cachelog' => array(
				'adapter' => $this->cachelog,
				'key'     => 'cachelog_testkey',
				'config'  => 'cachelog'
			)
		));
	}
	
	public function testConstruct()
	{
		$expected = array(
			'config' => "cachelog",
			'expiry' => "+999 days",
			'key'    => "cachelog_testkey",
			'init'   => true
		);
		$result = $this->cachelog->_config;
		$this->assertEqual($expected, $result);
	}

	public function testConfiguration() 
	{
		$loggers = Logger::config();
		$result  = isset($loggers['cachelog']);
		$this->assertTrue($result);
	}

	public function testWrite() 
	{
		$message = "CacheLog test message...";
		$result  = Logger::write('info', $message, array('name' => 'cachelog'));
		$this->assertTrue($result);
		$result = CacheStorage::read('cachelog', 'cachelog_testkey');
		$this->assertEqual($message, $result);
	}
}