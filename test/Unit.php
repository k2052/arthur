<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace arthur\test;

use Exception;
use arthur\util\String;
use arthur\core\Libraries;
use arthur\util\Validator;
use arthur\analysis\Debugger;
use arthur\analysis\Inspector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Unit extends \arthur\core\Object 
{
	protected $_reporter = null;
	protected $_results = array();
	protected $_expected = array();

	public static function get($class) 
	{
		$parts = explode('\\', $class);

		$library = array_shift($parts);
		$name    = array_pop($parts);
		$type    = "tests.cases." . implode('.', $parts);

		return Libraries::locate($type, $name, compact('library'));
	}

	public function setUp() {}
	public function tearDown() {}
	public function skip() {}
	
	public function skipIf($condition, $message = false) 
	{
		if($condition)
			throw new Exception(is_string($message) ? $message : null);
	}
	
	public function subject() 
	{
		return preg_replace('/Test$/', '', str_replace('tests\\cases\\', '', get_class($this)));
	}

	public function methods() 
	{
		static $methods;
		return $methods ?: $methods = array_values(preg_grep('/^test/', get_class_methods($this)));
	}

	public function run(array $options = array()) 
	{
		$defaults       = array('methods' => array(), 'reporter' => null, 'handler' => null);
		$options       += $defaults;
		$this->_results = array();
		$self           = $this;

		try {
			$this->skip();
		}
		catch(Exception $e) {
			$this->_handleException($e);
			return $this->_results;
		}

		$h = function($code, $message, $file, $line = 0, $context = array()) use ($self) 
		{
			$trace = debug_backtrace();
			$trace = array_slice($trace, 1, count($trace));
			$self->invokeMethod('_reportException', array(
				compact('code', 'message', 'file', 'line', 'trace', 'context')
			));
		};
		$options['handler'] = $options['handler'] ?: $h;
		set_error_handler($options['handler']);

		$methods         = $options['methods'] ?: $this->methods();
		$this->_reporter = $options['reporter'] ?: $this->_reporter;

		foreach($methods as $method) {
			if($this->_runTestMethod($method, $options) === false)
				break;
		}
		restore_error_handler();      
		
		return $this->_results;
	}

	public function assert($expression, $message = false, $data = array()) 
	{
		if(!is_string($message))
			$message = '{:message}';
		if(strpos($message, "{:message}") !== false) 
		{
			$params            = $data;
			$params['message'] = $this->_message($params);
			$message           = String::insert($message, $params);
		}
		$trace = Debugger::trace(array(
			'start' => 1, 'depth' => 4, 'format' => 'array', 'closures' => !$expression
		));
		$methods = $this->methods();
		$i = 1;

		while($i < count($trace)) {
			if(in_array($trace[$i]['function'], $methods) && $trace[$i - 1]['object'] == $this)
				break;
			$i++;
		}
		$class  = isset($trace[$i - 1]['object']) ? get_class($trace[$i - 1]['object']) : null;
		$method = isset($trace[$i]) ? $trace[$i]['function'] : $trace[$i - 1]['function'];

		$result = compact('class', 'method', 'message', 'data') + array(
			'file'      => $trace[$i - 1]['file'],
			'line'      => $trace[$i - 1]['line'],
			'assertion' => $trace[$i - 1]['function']
		);
		$this->_result($expression ? 'pass' : 'fail', $result);   
		
		return $expression;
	}

	public function assertEqual($expected, $result, $message = false) 
	{
		$data = ($expected != $result) ? $this->_compare('equal', $expected, $result) : null;
		$this->assert($expected == $result, $message, $data);
	}

	public function assertNotEqual($expected, $result, $message = false) 
	{
		$this->assert($result != $expected, $message, compact('expected', 'result'));
	}

	public function assertIdentical($expected, $result, $message = false) 
	{
		$data = ($expected !== $result) ? $this->_compare('identical', $expected, $result) : null;
		$this->assert($expected === $result, $message, $data);
	}

	public function assertTrue($result, $message = '{:message}') 
	{
		$expected = true;
		$this->assert(!empty($result), $message, compact('expected', 'result'));
	}

	public function assertFalse($result, $message = '{:message}') 
	{
		$expected = false;
		$this->assert(empty($result), $message, compact('expected', 'result'));
	}

	public function assertNull($result, $message = '{:message}') 
	{
		$expected = null;
		$this->assert($result === null, $message, compact('expected', 'result'));
	}

	public function assertNoPattern($expected, $result, $message = '{:message}') 
	{
		$this->assert(!preg_match($expected, $result), $message, compact('expected', 'result'));
	}

	public function assertPattern($expected, $result, $message = '{:message}') 
	{
		$this->assert(!!preg_match($expected, $result), $message, compact('expected', 'result'));
	}

	public function assertTags($string, $expected) 
	{
		$regex = array();
		$normalized = array();

		foreach((array) $expected as $key => $val) 
		{
			if(!is_numeric($key))
				$normalized[] = array($key => $val);
			else 
				$normalized[] = $val;
		}
		$i = 0;

		foreach($normalized as $tags) 
		{
			$i++;
			if(is_string($tags) && $tags{0} == '<')
				$tags = array(substr($tags, 1) => array());
			elseif (is_string($tags)) 
			{
				$tagsTrimmed = preg_replace('/\s+/m', '', $tags);

				if(preg_match('/^\*?\//', $tags, $match) && $tagsTrimmed !== '//') 
				{
					$prefix = array(null, null);

					if($match[0] == '*/')
						$prefix = array('Anything, ', '.*?');

					$regex[] = array(
						sprintf('%sClose %s tag', $prefix[0], substr($tags, strlen($match[0]))),
						sprintf('%s<[\s]*\/[\s]*%s[\s]*>[\n\r]*', $prefix[1], substr(
							$tags, strlen($match[0])
						)),
						$i
					);
					continue;
				}

				if(!empty($tags) && preg_match('/^regex\:\/(.+)\/$/i', $tags, $matches)) {
					$tags = $matches[1];
					$type = 'Regex matches';
				} 
				else {
					$tags = preg_quote($tags, '/');
					$type = 'Text equals';
				}       
				
				$regex[] = array(sprintf('%s "%s"', $type, $tags), $tags, $i);
				continue;
			}
			foreach($tags as $tag => $attributes) 
			{
				$regex[] = array(
					sprintf('Open %s tag', $tag),
					sprintf('[\s]*<%s', preg_quote($tag, '/')),
					$i
				);
				if($attributes === true)
					$attributes = array();

				$attrs        = array();
				$explanations = array();

				foreach($attributes as $attr => $val) 
				{
					if(is_numeric($attr) && preg_match('/^regex\:\/(.+)\/$/i', $val, $matches)) 
					{
						$attrs[] = $matches[1];
						$explanations[] = sprintf('Regex "%s" matches', $matches[1]);
						continue;
					} 
					else 
					{
						$quotes = '"';

						if(is_numeric($attr)) 
						{
							$attr = $val;
							$val  = '.+?';
							$explanations[] = sprintf('Attribute "%s" present', $attr);
						} 
						elseif(!empty($val) && preg_match('/^regex\:\/(.+)\/$/i', $val, $matches)) 
						{
							$quotes         = '"?';
							$val            = $matches[1];
							$explanations[] = sprintf('Attribute "%s" matches "%s"', $attr, $val);
						} 
						else {
							$explanations[] = sprintf('Attribute "%s" == "%s"', $attr, $val);
							$val = preg_quote($val, '/');
						}  
						
						$attrs[] = '[\s]+' . preg_quote($attr, '/') . "={$quotes}{$val}{$quotes}";
					}
				}
				if($attrs) 
				{
					$permutations       = $this->_arrayPermute($attrs);
					$permutationTokens  = array();
					foreach($permutations as $permutation) {
						$permutationTokens[] = join('', $permutation);
					}    
					
					$regex[] = array(
						sprintf('%s', join(', ', $explanations)),
						$permutationTokens,
						$i
					);
				} 
				
				$regex[] = array(sprintf('End %s tag', $tag), '[\s]*\/?[\s]*>[\n\r]*', $i);
			}
		}

		foreach($regex as $i => $assertation) 
		{
			list($description, $expressions, $itemNum) = $assertation;
			$matches = false;

			foreach((array) $expressions as $expression) 
			{
				if(preg_match(sprintf('/^%s/s', $expression), $string, $match)) 
				{
					$matches = true;
					$string  = substr($string, strlen($match[0]));
					break;
				}
			}

			if(!$matches) 
			{
				$this->assert(false, sprintf(
					'- Item #%d / regex #%d failed: %s', $itemNum, $i, $description
				));
				
				return false;
			}
		}     
		
		return $this->assert(true);
	}

	public function assertCookie($expected, $headers = null) 
	{
		$matched = $this->_cookieMatch($expected, $headers);   
		
		if(!$matched['match']) 
		{
			$message = sprintf('%s - Cookie not found in headers.', $matched['pattern']);
			$this->assert(false, $message, compact('expected', 'result'));   
			
			return false;
		}  
		
		return $this->assert(true, '%s');
	}

	public function assertNoCookie($expected, $headers = null) 
	{
		$matched = $this->_cookieMatch($expected, $headers);   
		
		if($matched['match']) 
		{
			$message = sprintf('%s - Cookie found in headers.', $matched['pattern']);
			$this->assert(false, $message, compact('expected', 'result'));    
			
			return false;
		}      
		
		return $this->assert(true, '%s');
	}

	protected function _cookieMatch($expected, $headers) 
	{
		$defaults  = array('path' => '/', 'name' => '[\w.-]+');
		$expected += $defaults;

		$headers = ($headers) ?: headers_list();
		$value   = preg_quote(urlencode($expected['value']), '/');

		$key = explode('.', $expected['key']);
		$key = (count($key) == 1) ? '[' . current($key) . ']' : ('[' . join('][', $key) . ']');
		$key = preg_quote($key, '/');

		if(isset($expected['expires'])) {
			$date = gmdate('D, d-M-Y H:i:s \G\M\T', strtotime($expected['expires']));
			$expires = preg_quote($date, '/');
		} 
		else
			$expires = '(?:.+?)';

		$path     = preg_quote($expected['path'], '/');
		$pattern  = "/^Set\-Cookie:\s{$expected['name']}$key=$value;";
		$pattern .= "\sexpires=$expires;\spath=$path/";
		$match    = false;

		foreach($headers as $header) 
		{
			if(preg_match($pattern, $header)) {
				$match = true;
				continue;
			}
		}   
		
		return compact('match', 'pattern');
	}

	public function expectException($message = true) 
	{
		$this->_expected[] = $message;
	}

	protected function _result($type, $info, array $options = array()) 
	{
		$info     = (array('result' => $type) + $info);
		$defaults = array();
		$options += $defaults; 
		
		if($this->_reporter) {
			$filtered = $this->_reporter->__invoke($info);
			$info = is_array($filtered) ? $filtered : $info;
		}       
		
		$this->_results[] = $info;
	}

	protected function _runTestMethod($method, $options) 
	{
		try {
			$this->setUp();
		} 
		catch (Exception $e) {
			$this->_handleException($e, __LINE__ - 2);
			return $this->_results;
		}
		$params = compact('options', 'method');

		$passed = $this->_filter(__CLASS__ . '::run', $params, function($self, $params, $chain) 
		{
			try 
			{
				$method   = $params['method'];
				$lineFlag = __LINE__ + 1;
				$self->$method();
			} 
			catch (Exception $e) {
				$self->invokeMethod('_handleException', array($e));
			}
		});
		$this->tearDown();

		return $passed;
	}

	protected function _handleException($exception, $lineFlag = null) 
	{
		$data = $exception;

		if(is_object($exception)) 
		{
			$data = array();

			foreach(array('message', 'file', 'line', 'trace') as $key) {
				$method = 'get' . ucfirst($key);
				$data[$key] = $exception->{$method}();
			}
			$ref = $exception->getTrace();
			$ref = $ref[0] + array('class' => null);

			if($ref['class'] == __CLASS__ && $ref['function'] == 'skipIf')
				return $this->_result('skip', $data);
		}      
		
		return $this->_reportException($data, $lineFlag);
	}

	protected function _reportException($exception, $lineFlag = null) 
	{
		$message = $exception['message'];

		$isExpected = (($exp = end($this->_expected)) && ($exp === true || $exp == $message || (
			Validator::isRegex($exp) && preg_match($exp, $message)
		)));
		if($isExpected)
			return array_pop($this->_expected);
		$initFrame = current($exception['trace']) + array('class' => '-', 'function' => '-');

		foreach ($exception['trace'] as $frame) 
		{
			if(isset($scopedFrame))
				break;
			if(!class_exists('arthur\analysis\Inspector'))
				continue;
			if(isset($frame['class']) && in_array($frame['class'], Inspector::parents($this)))
				$scopedFrame = $frame;
		}
		if(class_exists('arthur\analysis\Debugger')) 
		{
			$exception['trace'] = Debugger::trace(array(
				'trace'        => $exception['trace'],
				'format'       => '{:functionRef}, line {:line}',
				'includeScope' => false,
				'scope'        => array_filter(array(
					'functionRef' => __NAMESPACE__ . '\{closure}',
					'line'        => $lineFlag
				))
			));
		}
		$this->_result('exception', $exception + array(
			'class'     => $initFrame['class'],
			'method'    => $initFrame['function']
		));
	}

	protected function _compare($type, $expected, $result = null, $trace = null) 
	{
		$compareTypes = function($expected, $result, $trace) 
		{
			$types = array('expected' => gettype($expected), 'result' => gettype($result));

			if($types['expected'] !== $types['result']) 
			{
				$expected = trim("({$types['expected']}) " . print_r($expected, true));
				$result   = trim("({$types['result']}) " . print_r($result, true));     
				
				return compact('trace', 'expected', 'result');
			}
		};
		if($types = $compareTypes($expected, $result, $trace))
			return $types;

		$data = array();

		if(!is_scalar($expected)) 
		{
			foreach($expected as $key => $value) 
			{
				$newTrace = "{$trace}[{$key}]";
				$isObject = false;

				if(is_object($expected)) 
				{
					$isObject = true;
					$expected = (array) $expected;
					$result   = (array) $result;
				}
				if(!array_key_exists($key, $result)) 
				{
					$trace    = (!$key) ? null : $newTrace;
					$expected = (!$key) ? $expected : $value;
					$result   = ($key) ? null : $result;
					
					return compact('trace', 'expected', 'result');
				}
				$check = $result[$key];

				if($isObject)
				{
					$newTrace = ($trace) ? "{$trace}->{$key}" : $key;
					$expected = (object) $expected;
					$result   = (object) $result;
				}
				if($type === 'identical') 
				{
					if($value === $check) 
					{
						if($types = $compareTypes($value, $check, $trace))
							return $types;

						continue;
					}
					if($check === array()) {
						$trace = $newTrace;
						return compact('trace', 'expected', 'result');
					}
					if(is_string($check))
					 {
						$trace    = $newTrace;
						$expected = $value;
						$result   = $check;    
						
						return compact('trace', 'expected', 'result');
					}
				} 
				else 
				{
					if($value == $check) 
					{
						if($types = $compareTypes($value, $check, $trace))
							return $types;

						continue;
					}
					if(!is_array($value)) {
						$trace = $newTrace;
						return compact('trace', 'expected', 'result');
					}
				}
				$compare = $this->_compare($type, $value, $check, $newTrace);

				if($compare !== true)
					$data[] = $compare;
			}    
			
			if(!empty($data))
				return $data;
		} 
		
		if(!is_scalar($result)) 
		{
			$data = $this->_compare($type, $result, $expected);

			if(!empty($data)) 
			{
				return array(
					'trace'    => $data['trace'],
					'expected' => $data['result'],
					'result'   => $data['expected']
				);
			}
		}  
		
		if((($type === 'identical') ? $expected === $result : $expected == $result)) 
		{
			if($types = $compareTypes($expected, $result, $trace))
				return $types;

			return true;
		}     
		
		return compact('trace', 'expected', 'result');
	}

	protected function _message(&$data = array(), $message =  null) 
	{
		if(!empty($data[0])) 
		{
			foreach($data as $key => $value) 
			{
				$message = (!empty($data[$key][0])) ? $message : null;
				$message .= $this->_message($value, $message);
				unset($data[$key]);
			}
			return $message;
		}
		$defaults = array('trace' => null, 'expected' => null, 'result' => null);
		$result   = (array) $data + $defaults;

		$message = null;
		if(!empty($result['trace']))
			$message = sprintf("trace: %s\n", $result['trace']);
		if(is_object($result['expected']))
			$result['expected'] = get_object_vars($result['expected']);
		if(is_object($result['result'])) 
			$result['result'] = get_object_vars($result['result']);
		
		return $message . sprintf("expected: %s\nresult: %s\n",
			var_export($result['expected'], true),
			var_export($result['result'], true)
		);
	}

	protected function _arrayPermute($items, $perms = array()) 
	{
		static $permuted;

		if(empty($perms))
			$permuted = array();

		if(empty($items)) {
			$permuted[] = $perms;
			return;
		}
		$numItems = count($items) - 1;

		for($i = $numItems; $i >= 0; --$i) 
		{
			$newItems = $items;
			$newPerms = $perms;
			list($tmp) = array_splice($newItems, $i, 1);
			array_unshift($newPerms, $tmp);
			$this->_arrayPermute($newItems, $newPerms);
		}    
		
		return $permuted;
	}

	protected function _cleanUp($path = null) 
	{
		$resources = Libraries::get(true, 'resources');
		$path      = $path ?: $resources . '/tmp/tests';
		$path      = preg_match('/^\w:|^\//', $path) ? $path : $resources . '/tmp/' . $path;

		if(!is_dir($path))
			return;

		$dirs     = new RecursiveDirectoryIterator($path);
		$iterator = new RecursiveIteratorIterator($dirs, RecursiveIteratorIterator::CHILD_FIRST);

		foreach($iterator as $item) 
		{
			if($item->getPathname() === "{$path}/empty" || $iterator->isDot())
				continue;

			($item->isDir()) ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
	}
	
	public function results() 
	{
		return $this->_results;
	}

	protected function _hasNetwork($config = array()) 
	{
		$defaults = array(
			'scheme' => 'http',
			'host'   => 'lithify.me'
		);
		$config += $defaults;

		$url    = "{$config['scheme']}://{$config['host']}";
		$failed = false;

		set_error_handler(function($errno, $errstr) use (&$failed) {
			$failed = true;
		});

		$dnsCheck  = dns_check_record($config['host'], "ANY");
		$fileCheck = fopen($url, "r");

		restore_error_handler();     
		
		return !$failed;
	}
}