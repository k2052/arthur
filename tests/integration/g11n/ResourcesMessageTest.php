<?php

namespace arthur\tests\integration\g11n;

use arthur\g11n\Catalog;

class ResourcesMessageTest extends \arthur\test\Integration 
{
	protected $_backup = array();

	public function setUp() 
	{
		$this->_backup['catalogConfig'] = Catalog::config();
		Catalog::reset();
		Catalog::config(array(
			'arthur' => array(
				'adapter' => 'Php',
				'path'    => ARTHUR_LIBRARY_PATH . '/arthur/g11n/resources/php'
			)
		));
	}

	public function tearDown() 
	{
		Catalog::reset();
		Catalog::config($this->_backup['catalogConfig']);
	}

	public function testPlurals1() 
	{
		$locales = array(
			'en', 'de'
		);
		foreach($locales as $locale) 
		{
			$expected = 2;
			$result   = Catalog::read(true, 'message.pluralForms', $locale);
			$this->assertEqual($expected, $result, "Locale: `{$locale}`\n{:message}");

			$rule = Catalog::read(true, 'message.pluralRule', $locale);

			$expected  = '10111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$result = '';

			for($n = 0; $n < 200; $n++) {
				$result .= $rule($n);
			}
			$this->assertIdentical($expected, $result, "Locale: `{$locale}`\n{:message}");
		}
	}

	public function testPlurals2() 
	{
		$locales = array(
			'fr'
		);
		foreach($locales as $locale) 
		{
			$expected = 2;
			$result   = Catalog::read(true, 'message.pluralForms', $locale);
			$this->assertEqual($expected, $result, "Locale: `{$locale}`\n{:message}");

			$rule = Catalog::read(true, 'message.pluralRule', $locale);

			$expected  = '00111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$expected .= '11111111111111111111111111111111111111111111111111';
			$result = '';

			for($n = 0; $n < 200; $n++) {
				$result .= $rule($n);
			}
			$this->assertIdentical($expected, $result, "Locale: `{$locale}`\n{:message}");
		}
	}
}