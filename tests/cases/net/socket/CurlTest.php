<?php

namespace arthur\tests\cases\net\socket;

use arthur\net\http\Request;
use arthur\net\socket\Curl;

class CurlTest extends \arthur\test\Unit 
{
  protected $_testConfig = array(
		'persistent' => false,
		'scheme'     => 'http',
		'host'       => 'lithify.me',
		'port'       => 80,
		'timeout'    => 2,
		'classes'    => array('request' => 'arthur\net\http\Request')
	);

	public function skip() 
	{
		$message = 'Your PHP installation was not compiled with curl support.';
		$this->skipIf(!function_exists('curl_init'), $message);

		$config  = $this->_testConfig;
		$url     = "{$config['scheme']}://{$config['host']}";
		$message = "Could not open {$url} - skipping " . __CLASS__;
		$this->skipIf(!curl_init($url), $message);

		$message = "No internet connection established.";
		$this->skipIf(!$this->_hasNetwork($this->_testConfig), $message);
	}

	public function testAllMethodsNoConnection() 
	{
		$stream = new Curl(array('scheme' => null));
		$this->assertFalse($stream->open());
		$this->assertTrue($stream->close());
		$this->assertFalse($stream->timeout(2));
		$this->assertFalse($stream->encoding('UTF-8'));
		$this->assertFalse($stream->write(null));
		$this->assertFalse($stream->read());
	}

	public function testOpen() 
	{
		$stream = new Curl($this->_testConfig);
		$result = $stream->open();
		$this->assertTrue($result);

		$result = $stream->resource();
		$this->assertTrue(is_resource($result));
	}

	public function testClose() 
	{
		$stream = new Curl($this->_testConfig);
		$result = $stream->open();
		$this->assertTrue($result);

		$result = $stream->close();
		$this->assertTrue($result);

		$result = $stream->resource();
		$this->assertFalse(is_resource($result));
	}

	public function testTimeout() 
	{
		$stream = new Curl($this->_testConfig);
		$result = $stream->open();
		$stream->timeout(10);
		$result = $stream->resource();
		$this->assertTrue(is_resource($result));
	}

	public function testEncoding() 
	{
		$stream = new Curl($this->_testConfig);
		$result = $stream->open();
		$stream->encoding('UTF-8');
		$result = $stream->resource();
		$this->assertTrue(is_resource($result));

		$stream = new Curl($this->_testConfig + array('encoding' => 'UTF-8'));
		$result = $stream->open();
		$result = $stream->resource();
		$this->assertTrue(is_resource($result));
	}

	public function testWriteAndRead() 
	{
		$stream = new Curl($this->_testConfig);
		$this->assertTrue(is_resource($stream->open()));
		$this->assertTrue(is_resource($stream->resource()));
		$this->assertEqual(1, $stream->write());
		$this->assertPattern("/^HTTP/", (string) $stream->read());
	}

	public function testSendWithNull() 
	{
		$stream = new Curl($this->_testConfig);
		$this->assertTrue(is_resource($stream->open()));
		$result = $stream->send(
			new Request($this->_testConfig),
			array('response' => 'arthur\net\http\Response')
		);
		$this->assertTrue($result instanceof \arthur\net\http\Response);
		$this->assertPattern("/^HTTP/", (string) $result);
	}

	public function testSendWithArray() 
	{
		$stream = new Curl($this->_testConfig);
		$this->assertTrue(is_resource($stream->open()));
		$result = $stream->send($this->_testConfig,
			array('response' => 'arthur\net\http\Response')
		);
		$this->assertTrue($result instanceof \arthur\net\http\Response);
		$this->assertPattern("/^HTTP/", (string) $result);
	}

	public function testSendWithObject() 
	{
		$stream = new Curl($this->_testConfig);
		$this->assertTrue(is_resource($stream->open()));
		$result = $stream->send(
			new Request($this->_testConfig),
			array('response' => 'arthur\net\http\Response')
		);
		$this->assertTrue($result instanceof \arthur\net\http\Response);
		$this->assertPattern("/^HTTP/", (string) $result);
	}
}