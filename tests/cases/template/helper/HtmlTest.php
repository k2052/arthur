<?php

namespace arthur\tests\cases\template\helper;

use arthur\net\http\Router;
use arthur\template\helper\Html;
use arthur\action\Request;
use arthur\action\Response;
use arthur\tests\mocks\template\helper\MockHtmlRenderer;

class HtmlTest extends \arthur\test\Unit 
{
	public $html = null;
	protected $_routes = array();

	public function setUp() 
	{
		$this->_routes = Router::get();
		Router::reset();
		Router::connect('/{:controller}/{:action}/{:id}.{:type}');
		Router::connect('/{:controller}/{:action}.{:type}');

		$this->context = new MockHtmlRenderer(array(
			'request' => new Request(array(
				'base' => '', 'env' => array('HTTP_HOST' => 'foo.local')
			)),
			'response' => new Response()
		));
		$this->html = new Html(array('context' => &$this->context));
	}

	public function tearDown() 
	{
		Router::reset();

		foreach($this->_routes as $route) {
			Router::connect($route);
		}
		unset($this->html);
	}

	public function testCharset() 
	{
		$result = $this->html->charset();
		$this->assertTags($result, array('meta' => array(
			'charset' => 'UTF-8'
		)));

		$result = $this->html->charset('utf-8');
		$this->assertTags($result, array('meta' => array(
			'charset' => 'utf-8'
		)));

		$result = $this->html->charset('UTF-7');
		$this->assertTags($result, array('meta' => array(
			'charset' => 'UTF-7'
		)));
	}

	public function testMetaLink() 
	{
		$result = $this->html->link(
			'RSS Feed',
			array('controller' => 'posts', 'type' => 'rss'),
			array('type' => 'rss')
		);
		$this->assertTags($result, array('link' => array(
			'href'  => 'regex:/.*\/posts\/index\.rss/',
			'type'  => 'application/rss+xml',
			'rel'   => 'alternate',
			'title' => 'RSS Feed'
		)));

		$result = $this->html->link(
			'Atom Feed', array('controller' => 'posts', 'type' => 'xml'), array('type' => 'atom')
		);
		$this->assertTags($result, array('link' => array(
			'href'  => 'regex:/.*\/posts\/index\.xml/',
			'type'  => 'application/atom+xml',
			'title' => 'Atom Feed',
			'rel'   => 'alternate'
		)));

		$result = $this->html->link('No-existy', '/posts.xmp', array('type' => 'rong'));
		$this->assertTags($result, array('link' => array(
			'href'  => 'regex:/.*\/posts\.xmp/',
			'title' => 'No-existy'
		)));

		$result = $this->html->link('No-existy', '/posts.xpp', array('type' => 'atom'));
		$this->assertTags($result, array('link' => array(
			'href'  => 'regex:/.*\/posts\.xpp/',
			'type'  => 'application/atom+xml',
			'title' => 'No-existy',
			'rel'   => 'alternate'
		)));

		$result = $this->html->link('Favicon', array(), array('type' => 'icon'));
		$expected = array(
			'link' => array(
				'href'  => 'regex:/.*favicon\.ico/',
				'type'  => 'image/x-icon',
				'rel'   => 'icon',
				'title' => 'Favicon'
			),
			array('link' => array(
				'href'  => 'regex:/.*favicon\.ico/',
				'type'  => 'image/x-icon',
				'rel'   => 'shortcut icon',
				'title' => 'Favicon'
			))
		);
		$this->assertTags($result, $expected);
	}

	public function testLink() 
	{
		$result   = $this->html->link('/home');
		$expected = array('a' => array('href' => '/home'), 'regex:/\/home/', '/a');
		$this->assertTags($result, $expected);

		$result   = $this->html->link('Next >', '#');
		$expected = array('a' => array('href' => '#'), 'Next &gt;', '/a');
		$this->assertTags($result, $expected);

		$result   = $this->html->link('Next >', '#', array('escape' => true));
		$expected = array(
			'a' => array('href' => '#'),
			'Next &gt;',
			'/a'
		);
		$this->assertTags($result, $expected);

		$result   = $this->html->link('Next >', '#', array('escape' => 'utf-8'));
		$expected = array(
			'a' => array('href' => '#'),
			'Next &gt;',
			'/a'
		);
		$this->assertTags($result, $expected);

		$result   = $this->html->link('Next >', '#', array('escape' => false));
		$expected = array('a' => array('href' => '#'), 'Next >', '/a');
		$this->assertTags($result, $expected);

		$result = $this->html->link('Next >', '#', array(
			'title'  => 'to escape &#8230; or not escape?',
			'escape' => false
		));
		$expected = array(
			'a' => array('href' => '#', 'title' => 'to escape &#8230; or not escape?'),
			'Next >',
			'/a'
		);
		$this->assertTags($result, $expected);

		$result = $this->html->link('Next >', '#', array(
			'title' => 'to escape &#8230; or not escape?', 'escape' => true
		));
		$expected = array(
			'a' => array('href' => '#', 'title' => 'to escape &amp;#8230; or not escape?'),
			'Next &gt;',
			'/a'
		);
		$this->assertTags($result, $expected);
	}

	public function testScriptLinking() 
	{
		$result   = $this->html->script('script.js');
		$expected = '<script type="text/javascript" src="/js/script.js"></script>';
		$this->assertEqual($expected, $result);

		$result   = $this->html->script('script');
		$expected = '<script type="text/javascript" src="/js/script.js"></script>';
		$this->assertEqual($expected, $result);

		$result    = $this->html->script('scriptaculous.js?load=effects');
		$expected  = '<script type="text/javascript"';
		$expected .= ' src="/js/scriptaculous.js?load=effects"></script>';
		$this->assertEqual($expected, $result);

		$result   = $this->html->script('jquery-1.1.2');
		$expected = '<script type="text/javascript" src="/js/jquery-1.1.2.js"></script>';
		$this->assertEqual($result, $expected);

		$result   = $this->html->script('jquery-1.1.2');
		$expected = '<script type="text/javascript" src="/js/jquery-1.1.2.js"></script>';
		$this->assertEqual($result, $expected);

		$result   = $this->html->script('/plugin/js/jquery-1.1.2');
		$expected = '<script type="text/javascript" src="/plugin/js/jquery-1.1.2.js"></script>';
		$this->assertEqual($result, $expected);

		$result    = $this->html->script('/some_other_path/myfile.1.2.2.min.js');
		$expected  = '<script type="text/javascript"';
		$expected .= ' src="/some_other_path/myfile.1.2.2.min.js"></script>';
		$this->assertEqual($result, $expected);

		$result    = $this->html->script('some_other_path/myfile.1.2.2.min.js');
		$expected  = '<script type="text/javascript"';
		$expected .= ' src="/js/some_other_path/myfile.1.2.2.min.js"></script>';
		$this->assertEqual($result, $expected);

		$result    = $this->html->script('some_other_path/myfile.1.2.2.min');
		$expected  = '<script type="text/javascript"';
		$expected .= ' src="/js/some_other_path/myfile.1.2.2.min.js"></script>';
		$this->assertEqual($result, $expected);

		$result   = $this->html->script('http://example.com/jquery.js');
		$expected = '<script type="text/javascript" src="http://example.com/jquery.js"></script>';
		$this->assertEqual($result, $expected);

    $result   = $this->html->script('//example.com/jquery.js');
		$expected = '<script type="text/javascript" src="//example.com/jquery.js"></script>';
		$this->assertEqual($result, $expected);

		$result = $this->html->script(array('prototype', 'scriptaculous'));
		$this->assertPattern(
			'/^\s*<script\s+type="text\/javascript"\s+src=".*js\/prototype\.js"[^<>]*><\/script>/',
			$result
		);
		$this->assertPattern('/<\/script>\s*<script[^<>]+>/', $result);
		$this->assertPattern(
			'/<script\s+type="text\/javascript"\s+src=".*js\/scriptaculous\.js"[^<>]*>' .
			'<\/script>\s*$/',
			$result
		);

		$result = $this->html->script("foo", array(
			'async' => true, 'defer' => true, 'onload' => 'init()'
		));

		$this->assertTags($result, array('script' => array(
			'type'   => 'text/javascript',
			'src'    => '/js/foo.js',
			'async'  => 'async',
			'defer'  => 'defer',
			'onload' => 'init()'
		)));
	}

	public function testImage() 
	{
		$result = $this->html->image('test.gif');
		$this->assertTags($result, array('img' => array('src' => '/img/test.gif', 'alt' => '')));

		$result = $this->html->image('http://example.com/logo.gif');
		$this->assertTags($result, array('img' => array(
			'src' => 'http://example.com/logo.gif', 'alt' => ''
		)));

		$result = $this->html->image(array(
			'controller' => 'test', 'action' => 'view', 'id' => '1', 'type' => 'gif'
		));
		$this->assertTags($result, array('img' => array('src' => '/test/view/1.gif', 'alt' => '')));

		$result = $this->html->image('/test/view/1.gif');
		$this->assertTags($result, array('img' => array('src' => '/test/view/1.gif', 'alt' => '')));
	}

	public function testStyleLink() 
	{
		$result = $this->html->style('screen');
		$expected = array('link' => array(
			'rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'regex:/.*css\/screen\.css/'
		));
		$this->assertTags($result, $expected);

		$result = $this->html->style('screen.css');
		$this->assertTags($result, $expected);

		$result = $this->html->style('screen.css?1234');
		$expected['link']['href'] = 'regex:/.*css\/screen\.css\?1234/';
		$this->assertTags($result, $expected);

		$result = $this->html->style('http://whatever.com/screen.css?1234');
		$expected['link']['href'] = 'regex:/http:\/\/.*\/screen\.css\?1234/';
		$this->assertTags($result, $expected);
	}

	public function testHead() 
	{
		$result   = $this->html->head('meta', array('options' => array('author' => 'foo')));
		$expected = array('meta' => array('author' => 'foo'));
		$this->assertTags($result, $expected);

		$result = $this->html->head('unexisting-name', array(
			'options' => array('author' => 'foo')
		));
		$this->assertNull($result);
	}

	public function testStyleMulti() 
	{
		$result = $this->html->style(array('base', 'layout'));
		$expected = array(
			'link' => array(
				'rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'regex:/.*css\/base\.css/'
			),
			array(
				'link' => array(
					'rel'  => 'stylesheet', 'type' => 'text/css',
					'href' => 'regex:/.*css\/layout\.css/'
				)
			)
		);
		$this->assertTags($result, $expected);
	}

	public function testNonInlineScriptsAndStyles() 
	{
		$result = trim($this->context->scripts());
		$this->assertFalse($result);

		$result = $this->html->script('application', array('inline' => false));
		$this->assertFalse($result);

		$result = $this->context->scripts();
		$this->assertTags($result, array('script' => array(
			'type' => 'text/javascript', 'src' => 'regex:/.*js\/application\.js/'
		)));

		$result = trim($this->context->styles());
		$this->assertFalse($result);

		$result = $this->html->style('base', array('inline' => false));
		$this->assertFalse($result);

		$result = $this->context->styles();
		$this->assertTags($result, array('link' => array(
			'rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'regex:/.*css\/base\.css/'
		)));
	}

	public function testMultiNonInlineScriptsAndStyles() 
	{
		$result = $this->html->script(array('foo', 'bar'));
		$expected = array(
			array('script' => array('type' => 'text/javascript', 'src' => 'regex:/.*\/foo\.js/')),
			'/script',
			array('script' => array('type' => 'text/javascript', 'src' => 'regex:/.*\/bar\.js/')),
			'/script'
		);
		$this->assertTags($result, $expected);

		$this->assertNulL($this->html->script(array('foo', 'bar'), array('inline' => false)));
		$result = $this->context->scripts();
		$this->assertTags($result, $expected);
	}
}