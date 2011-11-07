<?php

namespace arthur\tests\cases\core;

use arthur\core\StaticObject;
use arthur\tests\mocks\core\MockRequest;
use arthur\tests\mocks\core\MockStaticInstantiator;

class StaticObjectTest extends \arthur\test\Unit 
{
	public function testMethodFiltering() 
	{
		$class = 'arthur\tests\mocks\core\MockStaticMethodFiltering';

		$result = $class::method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Inside method implementation of ' . $class,
			'Ending outer method call'
		);
		$this->assertEqual($expected, $result);

		$class::applyFilter('method', function($self, $params, $chain) 
		{
			$params['data'][] = 'Starting filter';
			$result   = $chain->next($self, $params, $chain);
			$result[] = 'Ending filter';
			return $result;
		});

		$result = $class::method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Starting filter',
			'Inside method implementation of ' . $class,
			'Ending filter',
			'Ending outer method call'
		);
		$this->assertEqual($expected, $result);

		$class::applyFilter('method', function($self, $params, $chain) 
		{
			$params['data'][] = 'Starting inner filter';
			$result   = $chain->next($self, $params, $chain);
			$result[] = 'Ending inner filter';
			return $result;
		});      
		
		$result   = $class::method(array('Starting test'));
		$expected = array(
			'Starting test',
			'Starting outer method call',
			'Starting filter',
			'Starting inner filter',
			'Inside method implementation of ' . $class,
			'Ending inner filter',
			'Ending filter',
			'Ending outer method call'
		);     
		
		$this->assertEqual($expected, $result);
	}

	public function testMethodInvocationWithParameters() 
	{
		$class = '\arthur\tests\mocks\core\MockStaticMethodFiltering';

		$this->assertEqual($class::invokeMethod('foo'), array());
		$this->assertEqual($class::invokeMethod('foo', array('bar')), array('bar'));

		$params = array('one', 'two');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array('short', 'parameter', 'list');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array('a', 'longer', 'parameter', 'list');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array('a', 'much', 'longer', 'parameter', 'list');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array('an', 'extremely', 'long', 'list', 'of', 'parameters');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array('an', 'extremely', 'long', 'list', 'of', 'parameters');
		$this->assertEqual($class::invokeMethod('foo', $params), $params);

		$params = array(
			'if', 'you', 'have', 'a', 'parameter', 'list', 'this',
			'long', 'then', 'UR', 'DOIN', 'IT', 'RONG'
		);   
		
		$this->assertEqual($class::invokeMethod('foo', $params), $params);
	}

	public function testCallingUnfilteredMethods() 
	{
		$class  = 'arthur\tests\mocks\core\MockStaticMethodFiltering';
		$result = $class::manual(array(function($self, $params, $chain) {
			return '-' . $chain->next($self, $params, $chain) . '-';
		}));
		$expected = '-Working-';
		$this->assertEqual($expected, $result);
	}

	public function testCallingSubclassMethodsInFilteredMethods() 
	{
		$class = 'arthur\tests\mocks\core\MockStaticFilteringExtended';
		$this->assertEqual('Working', $class::callSubclassMethod());
	}

	public function testClassParents() 
	{
		$class = 'arthur\tests\mocks\core\MockStaticMethodFiltering';
		$class::parents(null);

		$result   = $class::parents();
		$expected = array('arthur\core\StaticObject' => 'arthur\core\StaticObject');
		$this->assertEqual($expected, $result);

		$cache = $class::parents(true);
		$this->assertEqual(array($class => $expected), $cache);
	}

	public function testInstanceWithClassesKey() 
	{
		$expected = 'arthur\tests\mocks\core\MockRequest';
		$result   = get_class(MockStaticInstantiator::instance('request'));
		$this->assertEqual($expected, $result);
	}

	public function testInstanceWithNamespacedClass() 
	{
		$expected = 'arthur\tests\mocks\core\MockRequest';
		$result   = get_class(MockStaticInstantiator::instance(
			'arthur\tests\mocks\core\MockRequest'
		));        
		
		$this->assertEqual($expected, $result);
	}

	public function testInstanceWithObject() 
	{
		$request   = new MockRequest();
		$expected  = 'arthur\tests\mocks\core\MockRequest';
		$result    = get_class(MockStaticInstantiator::instance($request));
		$this->assertEqual($expected, $result);
	}

	public function testInstanceFalse() 
	{
		$this->expectException('/^Invalid class lookup/');
		MockStaticInstantiator::instance(false);
	}
}