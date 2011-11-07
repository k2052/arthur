<?php

namespace arthur\tests\cases\core;

use stdClass;
use SplFileInfo;
use arthur\util\Inflector;
use arthur\core\Libraries;

class LibrariesTest extends \arthur\test\Unit 
{
	protected $_cache = array();

	public function setUp() 
	{
		$this->_cache = Libraries::cache();
		Libraries::cache(false);
	}

	public function tearDown() 
	{
		Libraries::cache(false);
		Libraries::cache($this->_cache);
	}

	public function testNamespaceToFileTranslation()
	{
		$result = Libraries::path('\arthur\core\Libraries');
		$this->assertTrue(strpos($result, '/arthur/core/Libraries.php'));
		$this->assertTrue(file_exists($result));
		$this->assertFalse(strpos($result, '\\'));

		$result = Libraries::path('arthur\core\Libraries');
		$this->assertTrue(strpos($result, '/arthur/core/Libraries.php'));
		$this->assertTrue(file_exists($result));
		$this->assertFalse(strpos($result, '\\'));
	}

	public function testPathTemplate() 
	{
		$expected = array('{:app}/libraries/{:name}', '{:root}/{:name}');
		$result   = Libraries::paths('libraries');
		$this->assertEqual($expected, $result);

		$this->assertNull(Libraries::locate('authAdapter', 'Form'));

		$paths = Libraries::paths();
		$test  = array('authAdapter' => array('arthur\security\auth\adapter\{:name}'));
		Libraries::paths($test);
		$this->assertEqual($paths + $test, Libraries::paths());

		$class    = Libraries::locate('authAdapter', 'Form');
		$expected = 'arthur\security\auth\adapter\Form';
		$this->assertEqual($expected, $class);

		Libraries::paths($paths + array('authAdapter' => false));
		$this->assertEqual($paths, Libraries::paths());
	}

	public function testPathTransform() 
	{
		$expected = 'Library/Class/Separated/By/Underscore';
		$result = Libraries::path('Library_Class_Separated_By_Underscore', array(
			'prefix' => 'Library_',
			'transform' => function ($class, $options) {
				return str_replace('_', '/', $class);
			}
		));
		$this->assertEqual($expected, $result);

		$expected = 'Library/Class/Separated/By/Nothing';
		$result = Libraries::path('LibraryClassSeparatedByNothing', array(
			'prefix' => 'Library',
			'transform' => array('/([a-z])([A-Z])/', '$1/$2')
		));
		$this->assertEqual($expected, $result);
	}

	public function testPathFiltering() 
	{
		$tests  = Libraries::find('arthur', array('recursive' => true, 'path' => '/tests/cases'));
		$result = preg_grep('/^arthur\\\\tests\\\\cases\\\\/', $tests);
		$this->assertIdentical($tests, $result);

		$all    = Libraries::find('arthur', array('recursive' => true));
		$result = array_values(preg_grep('/^arthur\\\\tests\\\\cases\\\\/', $all));
		$this->assertIdentical($tests, $result);

		$tests  = Libraries::find('app', array('recursive' => true, 'path' => '/tests/cases'));
		$result = preg_grep('/^app\\\\tests\\\\cases\\\\/', $tests);
		$this->assertIdentical($tests, $result);
	}

	public function testLibraryConfigAccess()
	{
		$result   = Libraries::get('arthur');
		$expected = array(
			'path'        => str_replace('\\', '/', realpath(realpath(LITHIUM_LIBRARY_PATH) . '/arthur')),
			'prefix'      => 'arthur\\',
			'suffix'      => '.php',
			'loader'      => 'arthur\\core\\Libraries::load',
			'includePath' => false,
			'transform'   => null,
			'bootstrap'   => false,
			'defer'       => true,
			'default'     => false
		);

		$this->assertEqual($expected, $result);
		$this->assertNull(Libraries::get('foo'));

		$result = Libraries::get();
		$this->assertTrue(isset($result['arthur']));
		$this->assertTrue(isset($result['app']));
		$this->assertEqual($expected, $result['arthur']);
	}

	public function testLibraryAddRemove() 
	{
		$arthur = Libraries::get('arthur');
		$this->assertFalse(empty($arthur));

		$app = Libraries::get(true);
		$this->assertFalse(empty($app));

		Libraries::remove(array('arthur', 'app'));

		$result = Libraries::get('arthur');
		$this->assertTrue(empty($result));

		$result = Libraries::get('app');
		$this->assertTrue(empty($result));

		$result = Libraries::add('arthur', array('bootstrap' => false) + $arthur);
		$this->assertEqual($arthur, $result);

		$result = Libraries::add('app', array('bootstrap' => false) + $app);
		$this->assertEqual(array('bootstrap' => false) + $app, $result);
	}

	public function testAddInvalidLibrary() 
	{
		$this->expectException("Library `invalid_foo` not found.");
		Libraries::add('invalid_foo');
	}

	public function testAddNonPrefixedLibrary() 
	{
		$tmpDir = realpath(Libraries::get(true, 'resources') . '/tmp');
		$this->skipIf(!is_writable($tmpDir), "Can't write to resources directory.");

		$fakeDir = $tmpDir . '/fake';
		$fake    = "<?php class Fake {} ?>";
		$fakeFilename = $fakeDir . '/fake.php';
		mkdir($fakeDir);
		file_put_contents($fakeFilename, $fake);

		Libraries::add('bad', array(
			'prefix' => false,
			'path'   => $fakeDir,
			'transform' => function($class, $config) { return ''; }
		));

		Libraries::add('fake', array(
			'path'        => $fakeDir,
			'includePath' => true,
			'prefix'      => false,
			'transform' => function($class, $config) 
			{
				return $config['path'] . '/' . Inflector::underscore($class) . '.php';
			}
		));

		$this->assertFalse(class_exists('Fake', false));
		$this->assertTrue(class_exists('Fake'));
		unlink($fakeFilename);
		rmdir($fakeDir);
		Libraries::remove('fake');
	}

	public function testExcludeNonClassFiles() 
	{
		$result = Libraries::find('arthur');
		$this->assertFalse($result);

		$result = Libraries::find('arthur', array('namespaces' => true));

		$this->assertTrue(in_array('arthur\action', $result));
		$this->assertTrue(in_array('arthur\core', $result));
		$this->assertTrue(in_array('arthur\util', $result));

		$this->assertFalse(in_array('arthur\LICENSE.txt', $result));
		$this->assertFalse(in_array('arthur\readme.wiki', $result));

		$this->assertFalse(Libraries::find('arthur'));
		$result = Libraries::find('arthur', array('path' => '/test/filter/reporter/template'));
		$this->assertFalse($result);

		$result = Libraries::find('arthur', array(
			'path'       => '/test/filter/reporter/template',
			'namespaces' => true
		));
		$this->assertFalse($result);
	}

	public function testLibraryLoad() 
	{
		$this->expectException('Failed to load class `SomeInvalidLibrary` from path ``.');
		Libraries::load('SomeInvalidLibrary', true);
	}

	public function testPathCaching() 
	{
		$this->assertFalse(Libraries::cache(false));
		$path = Libraries::path(__CLASS__);
		$this->assertEqual(__FILE__, realpath($path));

		$result = Libraries::cache();
		$this->assertEqual(realpath($result[__CLASS__]), __FILE__);
	}

	public function testCacheControl() 
	{
		$this->assertNull(Libraries::path('Foo'));
		$cache = Libraries::cache();
		Libraries::cache(array('Foo' => 'Bar'));
		$this->assertEqual('Bar', Libraries::path('Foo'));

		Libraries::cache(false);
		Libraries::cache($cache);
	}

	public function testFindingClasses() 
	{
		$result = Libraries::find('arthur', array(
			'recursive' => true, 'path' => '/tests/cases', 'filter' => '/LibrariesTest/'
		));
		$this->assertIdentical(array(__CLASS__), $result);

		$result = Libraries::find('arthur', array(
			'path' => '/tests/cases/', 'filter' => '/LibrariesTest/'
		));
		$this->assertIdentical(array(), $result);

		$result = Libraries::find('arthur', array(
			'path' => '/tests/cases/core', 'filter' => '/LibrariesTest/'
		));
		$this->assertIdentical(array(__CLASS__), $result);

		$count  = Libraries::find('arthur', array('recursive' => true));
		$count2 = Libraries::find(true, array('recursive' => true));
		$this->assertTrue($count < $count2);

		$result = Libraries::find('foo', array('recursive' => true));
		$this->assertNull($result);
	}

	public function testFindingClassesAndNamespaces() 
	{
		$result = Libraries::find('app', array('namespaces' => true));
		$this->assertTrue(in_array('app\config', $result));
		$this->assertTrue(in_array('app\controllers', $result));
		$this->assertTrue(in_array('app\models', $result));
		$this->assertFalse(in_array('app\index', $result));
	}

	public function testFindingClassesWithExclude() 
	{
		$options = array(
			'recursive' => true,
			'filter'    => false,
			'exclude'   => '/\w+Test$|webroot|index$|^app\\\\config|^\w+\\\\views\/|\./'
		);
		$classes = Libraries::find('arthur', $options);

		$this->assertTrue(in_array('arthur\util\Set', $classes));
		$this->assertTrue(in_array('arthur\util\Collection', $classes));
		$this->assertTrue(in_array('arthur\core\Libraries', $classes));
		$this->assertTrue(in_array('arthur\action\Dispatcher', $classes));

		$this->assertFalse(in_array('arthur\tests\integration\data\SourceTest', $classes));
		$this->assertFalse(preg_grep('/\w+Test$/', $classes));

		$expected = Libraries::find('arthur', array(
			'filter' => '/\w+Test$/', 'recursive' => true
		));
		$result = preg_grep('/\w+Test/', $expected);
		$this->assertEqual($expected, $result);
	}

	public function testServiceLocateAll() 
	{
		$result = Libraries::locate('tests');
		$this->assertTrue(count($result) > 30);

		$expected = array(
			'arthur\template\view\adapter\File',
			'arthur\template\view\adapter\Simple'
		);
		$result = Libraries::locate('adapter.template.view');
		$this->assertEqual($expected, $result);

		$result = Libraries::locate('test.filter');
		$this->assertTrue(count($result) >= 4);
		$this->assertTrue(in_array('arthur\test\filter\Affected', $result));
		$this->assertTrue(in_array('arthur\test\filter\Complexity', $result));
		$this->assertTrue(in_array('arthur\test\filter\Coverage', $result));
		$this->assertTrue(in_array('arthur\test\filter\Profiler', $result));
	}

	public function testServiceLocateInstantiation()
	{
		$result = Libraries::instance('adapter.template.view', 'Simple');
		$this->assertTrue(is_a($result, 'arthur\template\view\adapter\Simple'));
		$this->expectException("Class `Foo` of type `adapter.template.view` not found.");
		$result = Libraries::instance('adapter.template.view', 'Foo');
	}

	public function testServiceLocateAllCommands() 
	{
		$result = Libraries::locate('command');
		$this->assertTrue(count($result) > 7);

		$expected = array('arthur\console\command\g11n\Extract');
		$result   = Libraries::locate('command.g11n');
		$this->assertEqual($expected, $result);
	}

	public function testServiceLocation() 
	{
		$this->assertNull(Libraries::locate('adapter', 'File'));
		$this->assertNull(Libraries::locate('adapter.view', 'File'));
		$this->assertNull(Libraries::locate('invalid_package', 'InvalidClass'));

		$result = Libraries::locate('adapter.template.view', 'File');
		$this->assertEqual('arthur\template\view\adapter\File', $result);

		$result   = Libraries::locate('adapter.storage.cache', 'File');
		$expected = 'arthur\storage\cache\adapter\File';
		$this->assertEqual($expected, $result);

		$result   = Libraries::locate('data.source', 'Database');
		$expected = 'arthur\data\source\Database';
		$this->assertEqual($expected, $result);

		$result   = Libraries::locate('adapter.data.source.database', 'MySql');
		$expected = 'arthur\data\source\database\adapter\MySql';
		$this->assertEqual($expected, $result);

		$result   = Libraries::locate(null, '\arthur\data\source\Database');
		$expected = '\arthur\data\source\Database';
		$this->assertEqual($expected, $result);

		$expected = new stdClass();
		$result   = Libraries::locate(null, $expected);
		$this->assertEqual($expected, $result);
	}

	public function testServiceLocateApp() 
	{
		$result   = Libraries::locate('controllers', 'HelloWorld');
		$expected = 'app\controllers\HelloWorldController';
		$this->assertEqual($expected, $result);

		// Tests caching of paths
		$result = Libraries::locate('controllers', 'HelloWorld');
		$this->assertEqual($expected, $result);
	}

	public function testServiceLocateCommand() 
	{
		$result   = Libraries::locate('command.g11n', 'Extract');
		$expected = 'arthur\console\command\g11n\Extract';
		$this->assertEqual($expected, $result);
	}

	public function testCaseSensitivePathLookups() 
	{
		Libraries::cache(false);
		$library = Libraries::get('arthur');
		$base    = $library['path'] . '/';

		$expected = $base . 'template/View.php';

		$result = Libraries::path('\arthur\template\View');
		$this->assertEqual($expected, $result);

		$result = Libraries::path('arthur\template\View');
		$this->assertEqual($expected, $result);

		$expected = $base . 'template/view';

		$result = Libraries::path('\arthur\template\view', array('dirs' => true));
		$this->assertEqual($expected, $result);

		$result = Libraries::path('arthur\template\view', array('dirs' => true));
		$this->assertEqual($expected, $result);
	}

	public function testPathDirectoryLookups() 
	{
		$library = Libraries::get('arthur');
		$base    = $library['path'] . '/';

		$result   = Libraries::path('arthur\template\View', array('dirs' => true));
		$expected = $base . 'template/View.php';
		$this->assertEqual($expected, $result);

		$result = Libraries::path('arthur\template\views', array('dirs' => true));
		$this->assertNull($result);
	}

	public function testFindingClassesWithCallableFilters() 
	{
		$result = Libraries::find('arthur', array(
			'recursive' => true, 'path' => '/tests/cases', 'format' => function($file, $config) 
			{
				return new SplFileInfo($file);
			},
			'filter' => function($file) 
			{
				if($file->getFilename() === 'LibrariesTest.php')
					return $file;
			}
		));
		$this->assertEqual(1, count($result));
		$this->assertIdentical(__FILE__, $result[0]->getRealPath());
	}

	public function testFindingClassesWithCallableExcludes() 
	{
		$result = Libraries::find('arthur', array(
			'recursive' => true, 'path' => '/tests/cases',
			'format' => function($file, $config)
			{
				return new SplFileInfo($file);
			},
			'filter' => null,
			'exclude' =>  function($file) 
			{
				if($file->getFilename() == 'LibrariesTest.php')
					return true;
			}
		));
		$this->assertEqual(1, count($result));
		$this->assertIdentical(__FILE__, $result[0]->getRealPath());
	}

	public function testFindWithOptions() 
	{
		$result = Libraries::find('arthur', array(
			'path'       => '/console/command/create/template',
			'namespaces' => false,
			'suffix'     => false,
			'filter'     => false,
			'exclude'    => false,
			'format' => function ($file, $config)
			{
				return basename($file);
			}
		));
		$this->assertTrue(count($result) > 3);
		$this->assertTrue(array_search('controller.txt.php', $result) !== false);
		$this->assertTrue(array_search('model.txt.php', $result) !== false);
		$this->assertTrue(array_search('plugin.phar.gz', $result) !== false);
	}

	public function testLocateWithDotSyntax() 
	{
		$expected = 'app\controllers\PagesController';
		$result   = Libraries::locate('controllers', 'app.Pages');
		$this->assertEqual($expected, $result);
	}

	public function testLocateCommandInArthur() 
	{
		$expected = array(
			'arthur\console\command\Create',
			'arthur\console\command\G11n',
			'arthur\console\command\Help',
			'arthur\console\command\Library',
			'arthur\console\command\Route',
			'arthur\console\command\Test'
		);
		$result = Libraries::locate('command', null, array(
			'library' => 'arthur', 'recursive' => false
		));
		$this->assertEqual($expected, $result);
	}

	public function testLocateCommandInArthurRecursiveTrue() 
	{
		$expected = array(
			'arthur\console\command\Create',
			'arthur\console\command\G11n',
			'arthur\console\command\Help',
			'arthur\console\command\Library',
			'arthur\console\command\Route',
			'arthur\console\command\Test',
			'arthur\console\command\g11n\Extract',
			'arthur\console\command\create\Controller',
			'arthur\console\command\create\Mock',
			'arthur\console\command\create\Model',
			'arthur\console\command\create\Test',
			'arthur\console\command\create\View'
		);
		$result = Libraries::locate('command', null, array(
			'library' => 'arthur', 'recursive' => true
		));
		$this->assertEqual($expected, $result);
	}

	public function testLocateWithLibrary() 
	{
    $expected = array();
    $result   = (array) Libraries::locate("tests", null, array('library' => 'doesntExist'));
    $this->assertIdentical($expected, $result);    
	}

	public function testLocateWithArthurLibrary()
	{
    $expected = (array) Libraries::find('arthur', array(
  	  'path'      => '/tests',
  		'preFilter' => '/[A-Z][A-Za-z0-9]+\Test\./',
      'recursive' => true,
      'filter'    => '/cases|integration|functional|mocks/'     
    ));
    $result = (array) Libraries::locate("tests", null, array('library' => 'arthur'));
    $this->assertEqual($expected, $result);       
	}

	public function testLocateWithTestAppLibrary() 
	{
		$testApp = Libraries::get(true, 'resources') . '/tmp/tests/test_app';
		mkdir($testApp);
		Libraries::add('test_app', array('path' => $testApp));

		mkdir($testApp . '/tests/cases/models', 0777, true);
		file_put_contents($testApp . '/tests/cases/models/UserTest.php',
		"<?php namespace test_app\\tests\\cases\\models;\n
			class UserTest extends \\arthur\\test\\Unit { public function testMe() {
				\$this->assertTrue(true);
			}}"
		);
		Libraries::cache(false);

		$expected = array('test_app\\tests\\cases\\models\\UserTest');
    $result = (array) Libraries::locate("tests", null, array('library' => 'test_app'));
    $this->assertEqual($expected, $result);   

		$this->_cleanUp();
	}

	public function testPathsInPharArchives() 
	{
		$base = Libraries::get('arthur', 'path');
		$path = "{$base}/console/command/create/template/app.phar.gz";

		$expected = "phar://{$path}/controllers/HelloWorldController.php";
		$result   = Libraries::realPath($expected);
		$this->assertEqual($expected, $result);
	}

	public function testClassInstanceWithSubnamespace() 
	{
		$testApp = Libraries::get(true, 'resources') . '/tmp/tests/test_app';
		mkdir($testApp);
		$paths = array("/controllers", "/controllers/admin");

		foreach($paths as $path) 
		{
			$namespace = str_replace('/', '\\', $path);
			$dotsyntax = str_replace('/', '.', trim($path, '/'));
			$class     = 'Posts';

			Libraries::add('test_app', array('path' => $testApp));

			mkdir($testApp . $path, 0777, true);
			file_put_contents($testApp . $path . "/{$class}Controller.php",
			"<?php namespace test_app{$namespace};\n
				class {$class}Controller extends \\arthur\\action\\Controller {
				public function index() {
					return true;
				}}"
			);
			Libraries::cache(false);

			$expected = "test_app{$namespace}\\{$class}Controller";
			$instance = Libraries::instance($dotsyntax, "Posts", array('library' => 'test_app'));
	    $result = get_class($instance);
	    $this->assertEqual($expected, $result, "{$path} did not work");    
		}

		$this->_cleanUp();
	}
}