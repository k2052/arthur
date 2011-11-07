<?php

namespace arthur\tests\cases\net\http;

use arthur\net\http\Media;
use arthur\action\Request;
use arthur\action\Response;
use arthur\core\Libraries;
use arthur\data\entity\Record;
use arthur\data\collection\RecordSet;

class MediaTest extends \arthur\test\Unit 
{
	public function setUp() 
	{
		Media::reset();
	}

	public function testMediaTypes() 
	{
		$types = Media::types(); 

		$expected = array(
			'html', 'htm', 'form', 'json', 'rss', 'atom', 'css', 'js', 'text', 'txt', 'xml'
		);
		$this->assertEqual($expected, $types);
		$this->assertEqual($expected, Media::formats());

		$result   = Media::type('json');
		$expected = 'application/json';
		$this->assertEqual($expected, $result['content']);

		$expected = array(
			'cast' => true, 'encode' => 'json_encode', 'decode' => $result['options']['decode']
		);
		$this->assertEqual($expected, $result['options']);

		Media::type('my', 'text/x-my', array('view' => 'my\custom\View', 'layout' => false));

		$result = Media::types();
		$this->assertTrue(in_array('my', $result));

		$result   = Media::type('my');
		$expected = 'text/x-my';
		$this->assertEqual($expected, $result['content']);

		$expected = array(
			'view' => 'my\custom\View', 'template' => null, 'layout' => null,
			'encode' => null, 'decode' => null, 'cast' => true, 'conditions' => array()
		);
		$this->assertEqual($expected, $result['options']);

		Media::type('my', false);
		$result = Media::types();
		$this->assertFalse(in_array('my', $result));
	}

	public function testContentTypeDetection() 
	{
		$this->assertNull(Media::type('application/foo'));
		$this->assertEqual('js', Media::type('application/javascript'));
		$this->assertEqual('html', Media::type('*/*'));
		$this->assertEqual('json', Media::type('application/json'));
		$this->assertEqual('json', Media::type('application/json; charset=UTF-8'));

		$result = Media::type('json');
		$expected = array('content' => 'application/json', 'options' => array(
			'cast' => true, 'encode' => 'json_encode', 'decode' => $result['options']['decode']
		));
		$this->assertEqual($expected, $result);
	}

	public function testAssetTypeHandling() 
	{
		$result   = Media::assets();
		$expected = array('js', 'css', 'image', 'generic');
		$this->assertEqual($expected, array_keys($result));

		$result   = Media::assets('css');
		$expected = '.css';
		$this->assertEqual($expected, $result['suffix']);
		$this->assertTrue(isset($result['path']['{:base}/{:library}/css/{:path}']));

		$result = Media::assets('my');
		$this->assertNull($result);

		$result = Media::assets('my', array('suffix' => '.my', 'path' => array(
			'{:base}/my/{:path}' => array('base', 'path')
		)));
		$this->assertNull($result);

		$result   = Media::assets('my');
		$expected = '.my';
		$this->assertEqual($expected, $result['suffix']);
		$this->assertTrue(isset($result['path']['{:base}/my/{:path}']));

		$this->assertNull($result['filter']);
		Media::assets('my', array('filter' => array('/my/' => '/your/')));

		$result   = Media::assets('my');
		$expected = array('/my/' => '/your/');
		$this->assertEqual($expected, $result['filter']);

		$expected = '.my';
		$this->assertEqual($expected, $result['suffix']);

		Media::assets('my', false);
		$result = Media::assets('my');
		$this->assertNull($result);

		$this->assertEqual('/foo.exe', Media::asset('foo.exe', 'bar'));
	}

	public function testAssetPathGeneration() 
	{
		$result   = Media::asset('scheme://host/subpath/file', 'js');
		$expected = 'scheme://host/subpath/file';
		$this->assertEqual($expected, $result);

		$result   = Media::asset('subpath/file', 'js');
		$expected = '/js/subpath/file.js';
		$this->assertEqual($expected, $result);

		$result = Media::asset('this.file.should.not.exist', 'css', array('check' => true));
		$this->assertFalse($result);

		$result   = Media::asset('debug', 'css', array('check' => 'true', 'library' => 'app'));
		$expected = '/css/debug.css';
		$this->assertEqual($expected, $result);

		$result = Media::asset('debug', 'css', array('timestamp' => true));
		$this->assertPattern('%^/css/debug\.css\?\d+$%', $result);

		$result = Media::asset('debug.css?type=test', 'css', array(
			'check' => 'true', 'base' => 'foo'
		));
		$expected = 'foo/css/debug.css?type=test';
		$this->assertEqual($expected, $result);

		$result = Media::asset('debug.css?type=test', 'css', array(
			'check' => 'true', 'base' => 'foo', 'timestamp' => true
		));
		$this->assertPattern('%^foo/css/debug\.css\?type=test&\d+$%', $result);

		$file = Media::path('css/debug.css', 'bar');
		$this->assertTrue(file_exists($file));
	}

	public function testCustomAssetPathGeneration() 
	{
		Media::assets('my', array('suffix' => '.my', 'path' => array(
			'{:base}/my/{:path}' => array('base', 'path')
		)));

		$result   = Media::asset('subpath/file', 'my');
		$expected = '/my/subpath/file.my';
		$this->assertEqual($expected, $result);

		Media::assets('my', array('filter' => array('/my/' => '/your/')));

		$result   = Media::asset('subpath/file', 'my');
		$expected = '/your/subpath/file.my';
		$this->assertEqual($expected, $result);

		$result   = Media::asset('subpath/file', 'my', array('base' => '/app/path'));
		$expected = '/app/path/your/subpath/file.my';
		$this->assertEqual($expected, $result);

		$result   = Media::asset('subpath/file', 'my', array('base' => '/app/path/'));
		$expected = '/app/path//your/subpath/file.my';
		$this->assertEqual($expected, $result);
	}

	public function testMultiLibraryAssetPaths() 
	{
		$result   = Media::asset('path/file', 'js', array('library' => 'app', 'base' => '/app/base'));
		$expected = '/app/base/js/path/file.js';
		$this->assertEqual($expected, $result);

		Libraries::add('li3_foo_blog', array(
			'path'      => ARTHUR_APP_PATH . '/libraries/plugins/blog',
			'bootstrap' => false,
			'route'     => false
		));

		$result = Media::asset('path/file', 'js', array(
			'library' => 'li3_foo_blog', 'base' => '/app/base'
		));
		$expected = '/app/base/blog/js/path/file.js';
		$this->assertEqual($expected, $result);

		Libraries::remove('li3_foo_blog');
	}

	public function testManualAssetPaths() 
	{
		$result   = Media::asset('/path/file', 'js', array('base' => '/base'));
		$expected = '/base/path/file.js';
		$this->assertEqual($expected, $result);

		$result = Media::asset('/foo/bar', 'js', array('base' => '/base', 'check' => true));
		$this->assertFalse($result);

		$result   = Media::asset('/css/debug', 'css', array('base' => '/base', 'check' => true));
		$expected = '/base/css/debug.css';
		$this->assertEqual($expected, $result);

		$result   = Media::asset('/css/debug.css', 'css', array('base' => '/base', 'check' => true));
		$expected = '/base/css/debug.css';
		$this->assertEqual($expected, $result);

		$result = Media::asset('/css/debug.css?foo', 'css', array(
			'base' => '/base', 'check' => true
		));
		$expected = '/base/css/debug.css?foo';
		$this->assertEqual($expected, $result);
	}

	public function testRender() 
	{
		$response = new Response();
		$response->type('json');
		$data = array('something');
		Media::render($response, $data);

		$result = $response->headers();
		$this->assertEqual(array('Content-type: application/json; charset=UTF-8'), $result);

		$result = $response->body();
		$this->assertEqual(json_encode($data), $result);
	}

	public function testNoDecode() 
	{
		Media::type('my', 'text/x-my', array('decode' => false));

		$result = Media::decode('my', 'Hello World');
		$this->assertEqual(null, $result);
	}

	public function testDecode() 
	{
		$data = array('movies' => array(
			array('name' => 'Shaun of the Dead', 'year' => 2004),
			array('name' => 'V for Vendetta', 'year' => 2005)
		));
		$jsonEncoded  = '{"movies":[{"name":"Shaun of the Dead","year":2004},';
		$jsonEncoded .= '{"name":"V for Vendetta","year":2005}]}';

		$result = Media::decode('json', $jsonEncoded);
		$this->assertEqual($data, $result);

		$formEncoded  = 'movies%5B0%5D%5Bname%5D=Shaun+of+the+Dead&movies%5B0%5D%5Byear%5D=2004';
		$formEncoded .= '&movies%5B1%5D%5Bname%5D=V+for+Vendetta&movies%5B1%5D%5Byear%5D=2005';

		$result = Media::decode('form', $formEncoded);
		$this->assertEqual($data, $result);
	}

	public function testCustomEncodeHandler() 
	{
		$response = new Response();
		$response->type('csv');

		Media::type('csv', 'application/csv', array('encode' => function($data) 
		{
			ob_start();
			$out = fopen('php://output', 'w');
			foreach($data as $record) {
				fputcsv($out, $record);
			}
			fclose($out);   
			
			return ob_get_clean();
		}));

		$data = array(
			array('John', 'Doe', '123 Main St.', 'Anytown, CA', '91724'),
			array('Jane', 'Doe', '124 Main St.', 'Anytown, CA', '91724')
		);

		Media::render($response, $data); 
		
		$result    = $response->body;
		$expected  = 'John,Doe,"123 Main St.","Anytown, CA",91724' . "\n";
		$expected .= 'Jane,Doe,"124 Main St.","Anytown, CA",91724' . "\n";
		$this->assertEqual(array($expected), $result);

		$result = $response->headers['Content-type'];
		$this->assertEqual('application/csv; charset=UTF-8', $result);
	}

	public function testPlainTextOutput() 
	{
		$response = new Response();
		$response->type('text');
		Media::render($response, "Hello, world!");

		$result = $response->body;
		$this->assertEqual(array("Hello, world!"), $result);
	}

	public function testUndhandledContent()
	{
		$response = new Response();
		$response->type('bad');

		$this->expectException("Unhandled media type `bad`.");
		Media::render($response, array('foo' => 'bar'));

		$result = $response->body;
		$this->assertNull($result);
	}

	public function testUnregisteredContentHandler() 
	{
		$response = new Response();
		$response->type('xml');

		$this->expectException("Unhandled media type `xml`.");
		Media::render($response, array('foo' => 'bar'));

		$result = $response->body;
		$this->assertNull($result);
	}

	public function testManualContentHandling() 
	{
		Media::type('custom', 'text/x-custom');
		$response = new Response();
		$response->type = 'custom';

		Media::render($response, 'Hello, world!', array(
			'layout'   => false,
			'template' => false,
			'encode'   => function($data) { return "Message: {$data}"; }
		));

		$result   = $response->body;
		$expected = array("Message: Hello, world!");
		$this->assertEqual($expected, $result);

		$this->expectException("/Template not found/");
		Media::render($response, 'Hello, world!');

		$result = $response->body;
		$this->assertNull($result);
	}

	public function testRequestOptionMerging() 
	{
		Media::type('custom', 'text/x-custom');
		$request = new Request();
		$request->params['foo'] = 'bar';

		$response       = new Response();
		$response->type = 'custom';

		Media::render($response, null, compact('request') + array(
			'layout'   => false,
			'template' => false,
			'encode'   => function($data, $handler) { return $handler['request']->foo; }
		));
		$this->assertEqual(array('bar'), $response->body);
	}

	public function testMediaEncoding() 
	{
		$data     = array('hello', 'goodbye', 'foo' => array('bar', 'baz' => 'dib'));
		$expected = json_encode($data);
		$result   = Media::encode('json', $data);
		$this->assertEqual($expected, $result);

		$this->assertEqual($result, Media::to('json', $data));
		$this->assertNull(Media::encode('badness', $data));

		$result = Media::decode('json', $expected);
		$this->assertEqual($data, $result);
	}

	public function testRenderWithOptionsMerging() 
	{
		$base = Libraries::get(true, 'resources') . '/tmp';
		$this->skipIf(!is_writable($base), "{$base} is not writable.");

		$request = new Request();
		$request->params['controller'] = 'pages';

		$response       = new Response();
		$response->type = 'html';

		$this->expectException('/Template not found/');
		Media::render($response, null, compact('request'));
		$this->_cleanUp();
	}

	public function testCustomWebroot() 
	{
		Libraries::add('defaultStyleApp', array('path' => ARTHUR_APP_PATH, 'bootstrap' => false));
		$this->assertEqual(ARTHUR_APP_PATH . '/webroot', Media::webroot('defaultStyleApp'));

		Libraries::add('customWebRootApp', array(
			'path'      => ARTHUR_APP_PATH,
			'webroot'   => ARTHUR_APP_PATH,
			'bootstrap' => false
		));
		$this->assertEqual(ARTHUR_APP_PATH, Media::webroot('customWebRootApp'));

		Libraries::remove('defaultStyleApp');
		Libraries::remove('customWebRootApp');
		$this->assertNull(Media::webroot('defaultStyleApp'));
	}

	public function testStateReset() 
	{
		$this->assertFalse(in_array('foo', Media::types()));

		Media::type('foo', 'text/x-foo');
		$this->assertTrue(in_array('foo', Media::types()));

		Media::reset();
		$this->assertFalse(in_array('foo', Media::types()));
	}

	public function testEncodeRecordSet() 
	{
		$data = new RecordSet(array('data' => array(
			1 => new Record(array('data' => array('id' => 1, 'foo' => 'bar'))),
			2 => new Record(array('data' => array('id' => 2, 'foo' => 'baz'))),
			3 => new Record(array('data' => array('id' => 3, 'baz' => 'dib')))
		)));
		$json = '{"1":{"id":1,"foo":"bar"},"2":{"id":2,"foo":"baz"},"3":{"id":3,"baz":"dib"}}';
		$this->assertEqual($json, Media::encode(array('encode' => 'json_encode'), $data));
	}

	public function testTypeAliasResolution() 
	{
		$resolved = Media::type('text');
		$this->assertEqual('text/plain', $resolved['content']);
		unset($resolved['options']['encode']);

		$result = Media::type('txt');
		unset($result['options']['encode']);
		$this->assertEqual($resolved, $result);
	}

	public function testQueryUndefinedAssetTypes() 
	{
		$base   = Media::path('index.php', 'generic');
		$result = Media::path('index.php', 'foo');
		$this->assertEqual($result, $base);

		$base   = Media::asset('/bar', 'generic');
		$result = Media::asset('/bar', 'foo');
		$this->assertEqual($result, $base);
	}

	public function testGetLibraryWebroot() 
	{
		$this->assertTrue(is_dir(Media::webroot(true)));
		$this->assertNull(Media::webroot('foobar'));

		Libraries::add('foobar', array('path' => __DIR__, 'webroot' => __DIR__));
		$this->assertEqual(__DIR__, Media::webroot('foobar'));
		Libraries::remove('foobar');
	}

	public function testResponseModification() 
	{
		Media::type('my', 'text/x-my', array('view' => 'arthur\tests\mocks\net\http\Template'));
		$response = new Response();

		Media::render($response, null, array('type' => 'my'));
		$this->assertEqual('Value', $response->headers('Custom'));
	}


	public function testDuplicateBasePathCheck() 
	{
		$result = Media::asset('/foo/bar/image.jpg', 'image', array('base' => '/bar'));
		$this->assertEqual('/bar/foo/bar/image.jpg', $result);

		$result = Media::asset('/foo/bar/image.jpg', 'image', array('base' => '/foo/bar'));
		$this->assertEqual('/foo/bar/image.jpg', $result);

		$result = Media::asset('foo/bar/image.jpg', 'image', array('base' => 'foo'));
		$this->assertEqual('foo/img/foo/bar/image.jpg', $result);

		$result = Media::asset('/foo/bar/image.jpg', 'image', array('base' => ''));
		$this->assertEqual('/foo/bar/image.jpg', $result);
	}

	public function testContentNegotiationByType() 
	{
		$this->assertEqual('html', Media::type('text/html'));

		Media::type('jsonp', 'text/html', array(
			'conditions' => array('type' => true)
		));
		$this->assertEqual(array('jsonp', 'html'), Media::type('text/html'));

		$config  = array('env' => array('HTTP_ACCEPT' => 'text/html,text/plain;q=0.5'));
		$request = new Request($config);
		$request->params = array('type' => 'jsonp');
		$this->assertEqual('jsonp', Media::negotiate($request));

		$request = new Request($config);
		$this->assertEqual('html', Media::negotiate($request));
	}

	public function testContentNegotiationByUserAgent() 
	{
		Media::type('iphone', 'application/xhtml+xml', array(
			'conditions' => array('mobile' => true)
		));
		$request = new Request(array('env' => array(
			'HTTP_USER_AGENT' => 'Safari',
			'HTTP_ACCEPT'     => 'application/xhtml+xml,text/html'
		)));
		$this->assertEqual('html', Media::negotiate($request));

		$request = new Request(array('env' => array(
			'HTTP_USER_AGENT' => 'iPhone',
			'HTTP_ACCEPT'     => 'application/xhtml+xml,text/html'
		)));
		$this->assertEqual('iphone', Media::negotiate($request));
	}
}