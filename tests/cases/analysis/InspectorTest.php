<?php

namespace lithium\tests\cases\analysis;

use ReflectionMethod;
use lithium\analysis\Inspector;

class InspectorTest extends \lithium\test\Unit 
{
	public $test = 'foo';
	public static $test2 = 'bar';
	protected $_test = 'baz';

	public function testBasicMethodInspection() 
	{
		$class  = '\lithium\analysis\Inspector';
		$parent = '\lithium\core\StaticObject';

		$expected = array_diff(get_class_methods($class), get_class_methods($parent));
		$result = array_keys(Inspector::methods($class, 'extents'));
		$this->assertEqual(array_intersect($result, $expected), $result);

		$result = array_keys(Inspector::methods($class, 'extents', array(
			'self' => true, 'public' => true
		)));
		$this->assertEqual($expected, $result);

		$this->assertNull(Inspector::methods('\lithium\core\Foo'));

		$result = Inspector::methods('stdClass', 'extents');
		$this->assertEqual(array(), $result);
	}

	public function testMethodInspection() 
	{
		$result = Inspector::methods($this, null);
		$this->assertTrue($result[0] instanceof ReflectionMethod);

		$result   = Inspector::info('lithium\core\Object::_init()');
		$expected = '_init';
		$this->assertEqual($expected, $result['name']);

		$expected = 'void';
		$this->assertEqual($expected, $result['tags']['return']);
	}

	public function testMethodRange() 
	{
		$result   = Inspector::methods(__CLASS__, 'ranges', array('methods' => __FUNCTION__));
		$expected = array(__FUNCTION__ => array(__LINE__ - 1, __LINE__, __LINE__ + 1));
		$this->assertEqual($expected, $result);
	}

	public function testExecutableLines() 
	{
		do {
			// These lines should be ignored
		} while (false);

		$result   = Inspector::executable($this, array('methods' => __FUNCTION__));
		$expected = array(__LINE__ - 1, __LINE__, __LINE__ + 1);
		$this->assertEqual($expected, $result);
	}

	public function testLineIntrospection() 
	{
		$result   = Inspector::lines(__FILE__, array(__LINE__ - 1));
		$expected = array(__LINE__ - 2 => "\tpublic function testLineIntrospection() {");
		$this->assertEqual($expected, $result);

		$result   = Inspector::lines(__CLASS__, array(14));
		$expected = array(14 => 'class InspectorTest extends \lithium\test\Unit {');
		$this->assertEqual($expected, $result);

		$this->expectException('/Missing argument 2/');
		$this->assertNull(Inspector::lines('\lithium\core\Foo'));
		$this->assertNull(Inspector::lines(__CLASS__, array()));
	}
	public function testClassParents() 
	{
		$result = Inspector::parents($this);
		$this->assertEqual('lithium\test\Unit', current($result));

		$result2 = Inspector::parents(__CLASS__);
		$this->assertEqual($result2, $result);

		$this->assertFalse(Inspector::parents('lithium\core\Foo', array('autoLoad' => false)));
	}

	public function testClassFileIntrospection() 
	{
		$result = Inspector::classes(array('file' => __FILE__));
		$this->assertEqual(array(__CLASS__ => __FILE__), $result);

		$result = Inspector::classes(array('file' => __FILE__, 'group' => 'files'));
		$this->assertEqual(1, count($result));
		$this->assertEqual(__FILE__, key($result));

		$result = Inspector::classes(array('file' => __FILE__, 'group' => 'foo'));
		$this->assertEqual(array(), $result);
	}

	public function testTypeDetection() 
	{
		$this->assertEqual('namespace', Inspector::type('\lithium\util'));
		$this->assertEqual('namespace', Inspector::type('\lithium\analysis'));
		$this->assertEqual('class', Inspector::type('\lithium\analysis\Inspector'));
		$this->assertEqual('property', Inspector::type('Inspector::$_classes'));
		$this->assertEqual('method', Inspector::type('Inspector::type'));
		$this->assertEqual('method', Inspector::type('Inspector::type()'));

		$this->assertEqual('class', Inspector::type('\lithium\security\Auth'));
		$this->assertEqual('class', Inspector::type('lithium\security\Auth'));

		$this->assertEqual('namespace', Inspector::type('\lithium\security\auth'));
		$this->assertEqual('namespace', Inspector::type('lithium\security\auth'));
	}

	public function testIdentifierIntrospection() 
	{
		$result = Inspector::info(__METHOD__);
		$this->assertEqual(array('public'), $result['modifiers']);
		$this->assertEqual(__FUNCTION__, $result['name']);

		$this->assertNull(Inspector::info('\lithium\util'));

		$info   = Inspector::info('\lithium\analysis\Inspector');
		$result = str_replace('\\', '/', $info['file']);
		$this->assertTrue(strpos($result, '/analysis/Inspector.php'));
		$this->assertEqual('lithium\analysis', $info['namespace']);
		$this->assertEqual('Inspector', $info['shortName']);

		$result = Inspector::info('\lithium\analysis\Inspector::$_methodMap');
		$this->assertEqual('_methodMap', $result['name']);

		$expected = 'Maps reflect method names to result array keys.';
		$this->assertEqual($expected, $result['description']);
		$this->assertEqual(array('var' => 'array'), $result['tags']);

		$result = Inspector::info('\lithium\analysis\Inspector::info()', array(
			'modifiers', 'namespace', 'foo'
		));
		$this->assertEqual(array('modifiers', 'namespace'), array_keys($result));

		$this->assertNull(Inspector::info('\lithium\analysis\Inspector::$foo'));
		$this->assertNull(Inspector::info('\lithium\core\Foo::$foo'));
	}

	public function testClassDependencies() 
	{
		$expected = array(
			'Exception', 'ReflectionClass', 'ReflectionProperty', 'ReflectionException',
			'lithium\\core\\Libraries'
		);

		$result = Inspector::dependencies($this->subject(), array('type' => 'static'));
		$this->assertEqual($expected, $result);

		$expected[] = 'lithium\\util\\Collection';
		$result     = Inspector::dependencies($this->subject());
		$this->assertEqual($expected, $result);
	}

	public function testCaseSensitiveIdentifiers() 
	{
		$result   = Inspector::type('lithium\storage\Cache');
		$expected = 'class';
		$this->assertEqual($expected, $result);

		$result   = Inspector::type('lithium\storage\cache');
		$expected = 'namespace';
		$this->assertEqual($expected, $result);
	}

	public function testGetClassProperties() 
	{
		$result = array_map(
			function($property) { return $property['name']; },
			Inspector::properties(__CLASS__)
		);
		$expected = array('test', 'test2');
		$this->assertEqual($expected, $result);

		$result = array_map(
			function($property) { return $property['name']; },
			Inspector::properties(__CLASS__, array('public' => false))
		);
		$expected = array('test', 'test2', '_test');
		$this->assertEqual($expected, $result);

		$result = Inspector::properties(__CLASS__);
		$expected = array(
			array(
				'modifiers'  => array('public'),
				'docComment' => false,
				'name'       => 'test',
				'value'      => null
			),
			array(
				'modifiers'  => array('public', 'static'),
				'docComment' => false,
				'name'       => 'test2',
				'value'      => 'bar'
			)
		);
		$this->assertEqual($expected, $result);

		$result = array_map(
			function($property) { return $property['name']; },
			Inspector::properties('lithium\action\Controller')
		);
		$this->assertTrue(in_array('request', $result));
		$this->assertTrue(in_array('response', $result));
		$this->assertFalse(in_array('_render', $result));
		$this->assertFalse(in_array('_classes', $result));

		$result = array_map(
			function($property) { return $property['name']; },
			Inspector::properties('lithium\action\Controller', array('public' => false))
		);
		$this->assertTrue(in_array('request', $result));
		$this->assertTrue(in_array('response', $result));
		$this->assertTrue(in_array('_render', $result));
		$this->assertTrue(in_array('_classes', $result));

		$this->assertNull(Inspector::properties('\lithium\core\Foo'));
	}
}