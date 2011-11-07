<?php

namespace arthur\tests\cases\core;

use arthur\core\Object;
use arthur\tests\mocks\core\MockRequest;
use arthur\tests\mocks\core\MockMethodFiltering;
use arthur\tests\mocks\core\MockExposed;
use arthur\tests\mocks\core\MockCallable;
use arthur\tests\mocks\core\MockObjectForParents;
use arthur\tests\mocks\core\MockObjectConfiguration;
use arthur\tests\mocks\core\MockInstantiator;

class ObjectTest extends \arthur\test\Unit 
{
	public function testMethodFiltering() 
	{
		$test     = new MockMethodFiltering();
		$result   = $test->method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Inside method implementation',
			'Ending outer method call'
		);
		$this->assertEqual($expected, $result);

		$test->applyFilter('method', function($self, $params, $chain)
		{
			$params['data'][] = 'Starting filter';
			$result   = $chain->next($self, $params, $chain);
			$result[] = 'Ending filter';
			return $result;
		});

		$result = $test->method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Starting filter',
			'Inside method implementation',
			'Ending filter',
			'Ending outer method call'
		);
		$this->assertEqual($expected, $result);

		$test->applyFilter('method', function($self, $params, $chain) 
		{
			$params['data'][] = 'Starting inner filter';
			$result   = $chain->next($self, $params, $chain);
			$result[] = 'Ending inner filter';
			return $result;
		});
		$result = $test->method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Starting filter',
			'Starting inner filter',
			'Inside method implementation',
			'Ending inner filter',
			'Ending filter',
			'Ending outer method call'
		);    
		
		$this->assertEqual($expected, $result);
	}

	public function testFilteringWithProtectedAccess() 
	{
		$object = new MockExposed();
		$this->assertEqual($object->get(), 'secret');
		$this->assertTrue($object->tamper());
		$this->assertEqual($object->get(), 'tampered');
	}

	public function testMultipleMethodFiltering() 
	{
		$object = new MockMethodFiltering();
		$this->assertIdentical($object->method2(), array());

		$object->applyFilter(array('method', 'method2'), function($self, $params, $chain) 
		{
			return $chain->next($self, $params, $chain);
		});
		$this->assertIdentical(array_keys($object->method2()), array('method', 'method2'));
	}

	public function testMethodInvocationWithParameters() 
	{
		$callable = new MockCallable();

		$this->assertEqual($callable->invokeMethod('foo'), array());
		$this->assertEqual($callable->invokeMethod('foo', array('bar')), array('bar'));

		$params = array('one', 'two');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array('short', 'parameter', 'list');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array('a', 'longer', 'parameter', 'list');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array('a', 'much', 'longer', 'parameter', 'list');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array('an', 'extremely', 'long', 'list', 'of', 'parameters');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array('an', 'extremely', 'long', 'list', 'of', 'parameters');
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);

		$params = array(
			'if', 'you', 'have', 'a', 'parameter', 'list', 'this',
			'long', 'then', 'UR', 'DOIN', 'IT', 'RONG'
		);
		$this->assertEqual($callable->invokeMethod('foo', $params), $params);
	}

	public function testParents() 
	{
		$expected = array('arthur\\core\\Object' => 'arthur\\core\\Object');

		$result = MockObjectForParents::parents();
		$this->assertEqual($expected, $result);

		$result = MockObjectForParents::parents();
		$this->assertEqual($expected, $result);
	}

	public function testObjectConfiguration() 
	{
		$expected = array('testScalar' => 'default', 'testArray' => array('default'));
		$config   = new MockObjectConfiguration();
		$this->assertEqual($expected, $config->getConfig());

		$config = new MockObjectConfiguration(array('autoConfig' => array('testInvalid')));
		$this->assertEqual($expected, $config->getConfig());

		$expected = array('testScalar' => 'override', 'testArray' => array('default', 'override'));
		$config  = new MockObjectConfiguration(array('autoConfig' => array(
			'testScalar', 'testArray' => 'merge'
		)) + $expected);     
		
		$this->assertEqual($expected, $config->getConfig());
	}


	public function testStateBasedInstantiation() 
	{
		$result = MockObjectConfiguration::__set_state(array(
			'key' => 'value', '_protected' => 'test'
		));
		$expected = 'arthur\tests\mocks\core\MockObjectConfiguration';
		$this->assertEqual($expected, get_class($result));

		$this->assertEqual('test', $result->getProtected());
	}

	public function testInstanceWithClassesKey() 
	{
		$object   = new MockInstantiator();
		$expected = 'arthur\tests\mocks\core\MockRequest';
		$result   = get_class($object->instance('request'));
		$this->assertEqual($expected, $result);
	}

	public function testInstanceWithNamespacedClass() 
	{
		$object   = new MockInstantiator();
		$expected = 'arthur\tests\mocks\core\MockRequest';
		$result   = get_class($object->instance('arthur\tests\mocks\core\MockRequest'));
		$this->assertEqual($expected, $result);
	}

	public function testInstanceWithObject() 
	{
		$object   = new MockInstantiator();
		$request  = new MockRequest();
		$expected = 'arthur\tests\mocks\core\MockRequest';
		$result   = get_class($object->instance($request));
		$this->assertEqual($expected, $result);
	}

	public function testInstanceFalse() 
	{
		$object = new MockInstantiator();
		$this->expectException('/^Invalid class lookup/');
		$object->instance(false);
	}
}