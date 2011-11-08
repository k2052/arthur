<?php

namespace arthur\tests\integration\analysis;

use arthur\core\Libraries;
use arthur\analysis\Logger;
use arthur\util\collection\Filters;

class LoggerTest extends \arthur\test\Integration 
{
	public function testWriteFilter() 
	{

		$base = Libraries::get(true, 'resources') . '/tmp/logs';
		$this->skipIf(!is_writable($base), "{$base} is not writable.");

		Filters::apply('arthur\analysis\Logger', 'write', function($self, $params, $chain) {
			$params['message'] = 'Filtered Message';
			return $chain->next($self, $params, $chain);
		});

		$config = array('default' => array(
			'adapter' => 'File', 'timestamp' => false,	'format' => "{:message}\n"
		));
		Logger::config($config);

		$result = Logger::write('info', 'Original Message');
		$this->assertTrue(file_exists($base . '/info.log'));

		$expected = "Filtered Message\n";
		$result   = file_get_contents($base . '/info.log');
		$this->assertEqual($expected, $result);

		unlink($base . '/info.log');
	}
}