<?php

namespace arthur\tests\cases\test;

use arthur\test\Group;
use arthur\util\Collection;
use arthur\core\Libraries;

class GroupTest extends \arthur\test\Unit 
{
	public function testAdd() 
	{
		$group = new Group();

		$expected = new Collection();
		$result   = $group->tests();
		$this->assertEqual($expected, $result);
	}

	public function testAddCaseThroughConstructor() 
	{
		$data  = (array) "\arthur\\tests\mocks\\test";
		$group = new Group(compact('data'));

		$expected = new Collection(array(
			'data' => array(
				new \arthur\tests\mocks\test\cases\MockSkipThrowsException(),
				new \arthur\tests\mocks\test\cases\MockTest(),
				new \arthur\tests\mocks\test\cases\MockTestErrorHandling()
			)
		));
		$result = $group->tests();
		$this->assertEqual($expected, $result);
	}

	public function testAddEmpty() 
	{
		$group = new Group();
		$group->add('');
		$group->add('\\');
		$group->add('foobar');
		$this->assertFalse($group->items());
	}

	public function testAddByString() 
	{
		$group    = new Group();
		$result   = $group->add('arthur\tests\cases\g11n');
		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest',
			'arthur\tests\cases\g11n\LocaleTest',
			'arthur\tests\cases\g11n\MessageTest',
			'arthur\tests\cases\g11n\catalog\AdapterTest',
			'arthur\tests\cases\g11n\catalog\adapter\CodeTest',
			'arthur\tests\cases\g11n\catalog\adapter\GettextTest',
			'arthur\tests\cases\g11n\catalog\adapter\PhpTest'
		);
		$this->assertEqual($expected, $result);

		$result   = $group->add('arthur\tests\cases\data\ModelTest');
		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest',
			'arthur\tests\cases\g11n\LocaleTest',
			'arthur\tests\cases\g11n\MessageTest',
			'arthur\tests\cases\g11n\catalog\AdapterTest',
			'arthur\tests\cases\g11n\catalog\adapter\CodeTest',
			'arthur\tests\cases\g11n\catalog\adapter\GettextTest',
			'arthur\tests\cases\g11n\catalog\adapter\PhpTest',
			'arthur\tests\cases\data\ModelTest'
		);
		$this->assertEqual($expected, $result);
	}

	public function testAddByMixedThroughConstructor() 
	{
		$group = new Group(array('data' => array(
			'arthur\tests\cases\data\ModelTest',
			new \arthur\tests\cases\core\ObjectTest()
		)));
		$expected = new Collection(array('data' => array(
			new \arthur\tests\cases\data\ModelTest(),
			new \arthur\tests\cases\core\ObjectTest()
		)));
		$result = $group->tests();
		$this->assertEqual($expected, $result);
	}

	public function testTests() 
	{
		$group    = new Group();
		$expected = array(
			'arthur\tests\cases\g11n\CatalogTest'
		);
		$result = $group->add('arthur\tests\cases\g11n\CatalogTest');
		$this->assertEqual($expected, $result);

		$results = $group->tests();
		$this->assertTrue(is_a($results, '\arthur\util\Collection'));

		$results = $group->tests();
		$this->assertTrue(is_a($results->current(), 'arthur\tests\cases\g11n\CatalogTest'));
	}

	public function testAddEmptyTestsRun() 
	{
		$group    = new Group();
		$result   = $group->add('arthur\tests\mocks\test\MockUnitTest');
		$expected = array('arthur\tests\mocks\test\MockUnitTest');
		$this->assertEqual($expected, $result);

		$results = $group->tests();
		$this->assertTrue(is_a($results, 'arthur\util\Collection'));
		$this->assertTrue(is_a($results->current(), 'arthur\tests\mocks\test\MockUnitTest'));

		$results = $group->tests()->run();

		$expected = 'pass';
		$result   = $results[0][0]['result'];
		$this->assertEqual($expected, $result);

		$expected = 'testNothing';
		$result   = $results[0][0]['method'];
		$this->assertEqual($expected, $result);

		$expected = 'arthur\tests\mocks\test\MockUnitTest';
		$result   = $results[0][0]['class'];
		$this->assertEqual($expected, $result);

		$expected = str_replace('\\', '/', ARTHUR_LIBRARY_PATH);
		$expected = realpath($expected . '/arthur/tests/mocks/test/MockUnitTest.php');
		$result   = $results[0][0]['file'];
		$this->assertEqual($expected, str_replace('\\', '/', $result));
	}

	public function testGroupAllForArthur() 
	{
		Libraries::cache(false);
		$result = Group::all(array('library' => 'arthur'));
		$this->assertTrue(count($result) >= 60);
	}

	public function testAddTestAppGroup() 
	{
		$test_app = Libraries::get(true, 'resources') . '/tmp/tests/test_app';
		mkdir($test_app);
		Libraries::add('test_app', array('path' => $test_app));

		mkdir($test_app . '/tests/cases/models', 0777, true);
		file_put_contents($test_app . '/tests/cases/models/UserTest.php',
		"<?php namespace test_app\\tests\\cases\\models;\n
			class UserTest extends \\arthur\\test\\Unit { public function testMe() {
				\$this->assertTrue(true);
			}}"
		);
		Libraries::cache(false);

		$expected = (array) Libraries::find('test_app', array(
			'recursive' => true,
			'path'      => '/tests',
			'filter'    => '/cases|integration|functional/'
		));

		Libraries::cache(false);

		$group  = new Group();
		$result = $group->add('test_app');
		$this->assertEqual($expected, $result);

		Libraries::cache(false);
		$this->_cleanUp();
	}

	public function testRunGroupAllForTestApp() 
	{
		$test_app = Libraries::get(true, 'resources') . '/tmp/tests/test_app';
		mkdir($test_app);
		Libraries::add('test_app', array('path' => $test_app));

		mkdir($test_app . '/tests/cases/models', 0777, true);
		file_put_contents($test_app . '/tests/cases/models/UserTest.php',
		"<?php namespace test_app\\tests\\cases\\models;\n
			class UserTest extends \\arthur\\test\\Unit { public function testMe() {
				\$this->assertTrue(true);
			}}"
		);
		Libraries::cache(false);

		$expected = array('test_app\\tests\\cases\\models\\UserTest');
		$result   = Group::all(array('library' => 'test_app'));
	    $this->assertEqual($expected, $result);

		Libraries::cache(false);
		$this->_cleanUp();
	}

	public function testRunGroupForTestAppModel() 
	{
		$test_app = Libraries::get(true, 'resources') . '/tmp/tests/test_app';
		mkdir($test_app);
		Libraries::add('test_app', array('path' => $test_app));

		mkdir($test_app . '/tests/cases/models', 0777, true);
		file_put_contents($test_app . '/tests/cases/models/UserTest.php',
		"<?php namespace test_app\\tests\\cases\\models;\n
			class UserTest extends \\arthur\\test\\Unit { public function testMe() {
				\$this->assertTrue(true);
			}}"
		);
		Libraries::cache(false);

		$group = new Group(array('data' => array('\\test_app\\tests\\cases')));

		$expected = array('test_app\\tests\\cases\\models\\UserTest');
		$result   = $group->to('array');
    $this->assertEqual($expected, $result);

		$expected = 'pass';
		$result   = $group->tests()->run();
    $this->assertEqual($expected, $result[0][0]['result']);

		Libraries::cache(false);
		$this->_cleanUp();
	}
}