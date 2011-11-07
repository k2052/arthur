<?php

namespace lithium\tests\cases\core;

use lithium\core\Environment;
use lithium\tests\mocks\core\MockRequest;

class EnvironmentTest extends \lithium\test\Unit 
{
	public function setUp() 
	{
		Environment::reset();
	}

	public function testSetAndGetCurrentEnvironment() 
	{
		Environment::set('production',  array('foo' => 'bar'));
		Environment::set('staging',     array('foo' => 'baz'));
		Environment::set('development', array('foo' => 'dib'));

		Environment::set('development');

		$this->assertEqual('development', Environment::get());
		$this->assertTrue(Environment::is('development'));
		$this->assertNull(Environment::get('doesNotExist'));

		$expected = array('foo' => 'dib');
		$config   = Environment::get('development');
		$this->assertEqual($expected, $config);

		$foo      = Environment::get('foo'); // returns 'dib', since the current env. is 'development'
		$expected = 'dib';
		$this->assertEqual($expected, $foo);
	}

	public function testCreateNonStandardEnvironment() 
	{
		Environment::set('custom', array('host' => 'server.local'));
		Environment::set('custom');

		$host     = Environment::get('host');
		$expected = 'server.local';
		$this->assertEqual($expected, $host);

		$custom   = Environment::get('custom');
		$expected = array('host' => 'server.local');
		$this->assertEqual($expected, $custom);
	}

	public function testModifyEnvironmentConfig()
	{
		Environment::set('test', array('foo' => 'bar'));
		$expected = array('foo' => 'bar');
		$this->assertEqual($expected, Environment::get('test'));

		$expected = array('foo' => 'bar', 'baz' => 'qux');
		Environment::set('test', array('baz' => 'qux'));
		$settings = Environment::get('test'); // returns array('foo' => 'bar', 'baz' => 'qux')
		$this->assertEqual($expected, Environment::get('test'));
	}

	public function testEnvironmentDetection() 
	{
		Environment::set(new MockRequest(array('SERVER_ADDR' => '::1')));
		$this->assertTrue(Environment::is('development'));

		$request = new MockRequest(array('SERVER_ADDR' => '1.1.1.1', 'HTTP_HOST' => 'test.local'));
		Environment::set($request);
		$this->assertTrue(Environment::is('test'));

		$request = new MockRequest(array('SERVER_ADDR' => '1.1.1.1', 'HTTP_HOST' => 'www.com'));
		Environment::set($request);
		$isProduction = Environment::is('production'); // returns true if not running locally
		$this->assertTrue($isProduction);

		$request      = new MockRequest(array('SERVER_ADDR' => '::1'));
		$request->url = 'test/myTest';
		Environment::set($request);
		$this->assertTrue(Environment::is('test'));

		$request       = new MockRequest();
		$request->argv = array(0 => 'test');
		Environment::set($request);
		$this->assertTrue(Environment::is('test'));

		$request       = new MockRequest();
		$request->argv = array(0 => 'something');
		Environment::set($request);
		$this->assertTrue(Environment::is('development'));

		$request         = new MockRequest();
		$request->params = array('env' => 'production');
		Environment::set($request);
		$this->assertTrue(Environment::is('production'));
	}

	public function testReset() 
	{
		Environment::set('test', array('foo' => 'bar'));
		Environment::set('test');
		$this->assertEqual('test', Environment::get());
		$this->assertEqual('bar', Environment::get('foo'));

		Environment::reset();
		$this->assertEqual('', Environment::get());
		$this->assertNull(Environment::get('foo'));
	}

	public function testCustomDetector() 
	{
		Environment::is(function($request) 
		{
			if($request->env('HTTP_HOST') == 'localhost')
				return 'development';
			if($request->env('HTTP_HOST') == 'staging.server')
				return 'test';

			return 'production';
		});

		$request = new MockRequest(array('HTTP_HOST' => 'localhost'));
		Environment::set($request);
		$this->assertTrue(Environment::is('development'));

		$request = new MockRequest(array('HTTP_HOST' => 'lappy.local'));
		Environment::set($request);
		$this->assertTrue(Environment::is('production'));

		$request = new MockRequest(array('HTTP_HOST' => 'staging.server'));
		Environment::set($request);
		$this->assertTrue(Environment::is('test'));

		$request = new MockRequest(array('HTTP_HOST' => 'test.local'));
		Environment::set($request);
		$this->assertTrue(Environment::is('production'));
	}

	public function testDotPath() 
	{
		$data = array(
			'foo' => array(
				'bar' => array(
					'baz' => 123
				)
			),
			'some' => array(
				'path' => true
			)
		);
		Environment::set('dotPathIndex', $data);

		$this->assertEqual(123, Environment::get('dotPathIndex.foo.bar.baz'));
		$this->assertEqual($data['foo'], Environment::get('dotPathIndex.foo'));
		$this->assertTrue(Environment::get('dotPathIndex.some.path'));
	}

	public function testReadWriteWithDefaultEnvironment() 
	{
		Environment::set('development');
		Environment::set(true, array('foo' => 'bar'));

		$this->assertEqual(array('foo' => 'bar'), Environment::get('development'));
		$this->assertEqual(Environment::get(true), Environment::get('development'));

		Environment::set('production');
		$this->assertFalse(Environment::get(true));
	}
}