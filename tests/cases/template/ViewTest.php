<?php

namespace arthur\tests\cases\template;

use arthur\template\View;
use arthur\action\Response;
use arthur\template\view\adapter\Simple;
use arthur\tests\mocks\template\MockView;
use arthur\tests\mocks\template\view\adapters\TestRenderer;

class ViewTest extends \arthur\test\Unit 
{
	protected $_view = null;

	public function setUp() 
	{
		$this->_view = new View();
	}

	public function testInitialization() 
	{
		$expected    = new Simple();
		$this->_view = new MockView(array('renderer' => $expected));
		$result      = $this->_view->renderer();
		$this->assertEqual($expected, $result);
	}

	public function testInitializationWithBadLoader() 
	{
		$this->expectException("Class `Badness` of type `adapter.template.view` not found.");
		new View(array('loader' => 'Badness'));
	}

	public function testInitializationWithBadRenderer() 
	{
		$this->expectException("Class `Badness` of type `adapter.template.view` not found.");
		new View(array('renderer' => 'Badness'));
	}

	public function testEscapeOutputFilter() 
	{
		$h        = $this->_view->outputFilters['h'];
		$expected = '&lt;p&gt;Foo, Bar &amp; Baz&lt;/p&gt;';
		$result   = $h('<p>Foo, Bar & Baz</p>');
		$this->assertEqual($expected, $result);
	}

	public function testEscapeOutputFilterWithInjectedEncoding() 
	{
		$message = "Multibyte string support must be enabled to test character encodings.";
		$this->skipIf(!function_exists('mb_convert_encoding'), $message);

		$string = "Joël";

		$response = new Response();
		$response->encoding = 'UTF-8';
		$view    = new View(compact('response'));
		$handler = $view->outputFilters['h'];
		$this->assertTrue(mb_check_encoding($handler($string), "UTF-8"));

		$response = new Response();
		$response->encoding = 'ISO-8859-1';
		$view    = new View(compact('response'));
		$handler = $view->outputFilters['h'];
		$this->assertTrue(mb_check_encoding($handler($string), "ISO-8859-1"));
	}

	public function testBasicRenderModes() 
	{
		$view = new View(array('loader' => 'Simple', 'renderer' => 'Simple'));

		$result = $view->render('template', array('content' => 'world'), array(
			'template' => 'Hello {:content}!'
		));
		$expected = 'Hello world!';
		$this->assertEqual($expected, $result);

		$result = $view->render(array('element' => 'Logged in as: {:name}.'), array(
			'name' => "Cap'n Crunch"
		));
		$expected = "Logged in as: Cap'n Crunch.";
		$this->assertEqual($expected, $result);

		$result = $view->render('element', array('name' => "Cap'n Crunch"), array(
			'element' => 'Logged in as: {:name}.'
		));
		$expected = "Logged in as: Cap'n Crunch.";
		$this->assertEqual($expected, $result);

		$xmlHeader = '<' . '?xml version="1.0" ?' . '>' . "\n";
		$result = $view->render('all', array('type' => 'auth', 'success' => 'true'), array(
			'layout'   => $xmlHeader . "\n{:content}\n",
			'template' => '<{:type}>{:success}</{:type}>'
		));
		$expected = "{$xmlHeader}\n<auth>true</auth>\n";
		$this->assertEqual($expected, $result);
	}

	public function testTwoStepRenderWithVariableCapture() 
	{
		$view = new View(array('loader' => 'Simple', 'renderer' => 'Simple'));

		$result = $view->render(
			array(
				array('path' => 'element', 'capture' => array('data' => 'foo')),
				array('path' => 'template')
			),
			array('name' => "Cap'n Crunch"),
			array('element' => 'Logged in as: {:name}.', 'template' => '--{:foo}--')
		);
		$this->assertEqual('--Logged in as: Cap\'n Crunch.--', $result);
	}

	public function testFullRenderNoLayout() 
	{
		$view = new View(array('loader' => 'Simple', 'renderer' => 'Simple'));
		$result = $view->render('all', array('type' => 'auth', 'success' => 'true'), array(
			'template' => '<{:type}>{:success}</{:type}>'
		));
		$expected = '<auth>true</auth>';
		$this->assertEqual($expected, $result);
	}

	public function testNolayout() 
	{
		$view = new View(array(
			'loader'   => 'arthur\tests\mocks\template\view\adapters\TestRenderer',
			'renderer' => 'arthur\tests\mocks\template\view\adapters\TestRenderer',
			'paths'    => array(
				'template' => '{:library}/tests/mocks/template/view/adapters/{:template}.html.php',
				'layout'   => false
			)
		));
		$options = array(
			'template' => 'testFile',
			'library'  => ARTHUR_LIBRARY_PATH . '/arthur'
		);
		$result   = $view->render('all', array(), $options);
		$expected = 'This is a test.';
		$this->assertEqual($expected, $result);

		$templateData  = TestRenderer::$templateData;
		$expectedPath  = ARTHUR_LIBRARY_PATH;
		$expectedPath .= '/arthur/tests/mocks/template/view/adapters/testFile.html.php';
		$expected = array (array (
				'type' => 'template',
				'params' =>
				array (
					'template' => 'testFile',
					'library'  => ARTHUR_LIBRARY_PATH . '/arthur',
					'type'     => 'html'
				),
				'return' => $expectedPath
			));
		$this->assertEqual($expected, $templateData);

		$renderData = TestRenderer::$renderData;
		$expected = array (
			  array (
				'template' => $expectedPath,
				'data'     => array (),
				'options'  => array (
					'template' => 'testFile',
					'library'  => $options['library'],
					'type'     => 'html',
					'layout'   => null,
					'context'  => array()
				)
			  )
			);
		$this->assertTrue($renderData[0]['data']['h'] instanceof \Closure);
		unset($renderData[0]['data']['h']);
		$this->assertEqual($expected, $renderData);
	}
}