<?php

namespace arthur\util;

use COM;
use Closure;
use Exception;

class String 
{ 
	const UUID_CLEAR_VER = 15;
	const UUID_VERSION_4 = 64;
	const UUID_CLEAR_VAR = 63;
	const UUID_VAR_RFC = 128;
	const ENCODE_BASE_64 = 1;
	
	protected static $_source;
	
	public static function uuid() 
	{
		$uuid = static::random(16);
		$uuid[6] = chr(ord($uuid[6]) & static::UUID_CLEAR_VER | static::UUID_VERSION_4);
		$uuid[8] = chr(ord($uuid[8]) & static::UUID_CLEAR_VAR | static::UUID_VAR_RFC);

		return join('-', array(
			bin2hex(substr($uuid, 0, 4)),
			bin2hex(substr($uuid, 4, 2)),
			bin2hex(substr($uuid, 6, 2)),
			bin2hex(substr($uuid, 8, 2)),
			bin2hex(substr($uuid, 10, 6))
		));
	} 
	
	public static function random($bytes, array $options = array()) 
	{
		$defaults = array('encode' => null);
		$options += $defaults;

		$source = static::$_source ?: static::_source();
		$result = $source($bytes);

		if($options['encode'] != static::ENCODE_BASE_64)
			return $result;

		return strtr(rtrim(base64_encode($result), '='), '+', '.');
	}

	protected static function _source() 
	{
		switch(true) 
		{
			case isset(static::$_source):
				return static::$_source;
			case is_readable('/dev/urandom') && $fp = fopen('/dev/urandom', 'rb'):
				return static::$_source = function($bytes) use (&$fp) {
					return fread($fp, $bytes);
				};
			case class_exists('COM', false):
				try 
				{
					$com = new COM('CAPICOM.Utilities.1');
					return static::$_source = function($bytes) use ($com) {
						return base64_decode($com->GetRandom($bytes, 0));
					};
				} 
				catch (Exception $e) {
				}
			default:
				return static::$_source = function($bytes) {
					$rand = '';

					for($i = 0; $i < $bytes; $i++) {
						$rand .= chr(mt_rand(0, 255));
					}
					return $rand;
				};
		}
	}

	public static function hash($string, array $options = array()) 
	{
		$defaults = array(
			'type' => 'sha512',
			'salt' => false,
			'key' => false,
			'raw' => false
		);
		$options += $defaults;

		if($options['salt'])
			$string = $options['salt'] . $string;
		if($options['key']) 
			return hash_hmac($options['type'], $string, $options['key'], $options['raw']);        
			
		return hash($options['type'], $string, $options['raw']);
	}

	public static function insert($str, array $data, array $options = array()) 
	{
		$defaults = array(
			'before' => '{:',
			'after' => '}',
			'escape' => null,
			'format' => null,
			'clean' => false
		);
		$options += $defaults;
		$format = $options['format'];
		reset($data);

		if($format == 'regex' || (!$format && $options['escape'])) 
		{
			$format = sprintf(
				'/(?<!%s)%s%%s%s/',
				preg_quote($options['escape'], '/'),
				str_replace('%', '%%', preg_quote($options['before'], '/')),
				str_replace('%', '%%', preg_quote($options['after'], '/'))
			);
		}

		if(!$format && key($data) !== 0) 
		{
			$replace = array();

			foreach($data as $key => $value) 
			{
				$value = (is_array($value) || $value instanceof Closure) ? '' : $value;

				try 
				{
					if(is_object($value) && method_exists($value, '__toString'))
						$value = (string) $value;
				} 
				catch (Exception $e) {
					$value = '';
				}
				$replace["{$options['before']}{$key}{$options['after']}"] = $value;
			}
			$str = strtr($str, $replace);
			return $options['clean'] ? static::clean($str, $options) : $str;
		}

		if(strpos($str, '?') !== false && isset($data[0])) 
		{
			$offset = 0;        
			
			while(($pos = strpos($str, '?', $offset)) !== false) 
			{
				$val = array_shift($data);
				$offset = $pos + strlen($val);
				$str = substr_replace($str, $val, $pos, 1);
			}    
			
			return $options['clean'] ? static::clean($str, $options) : $str;
		}

		foreach($data as $key => $value) 
		{
			$hashVal = crc32($key);
			$key = sprintf($format, preg_quote($key, '/'));

			if(!$key) continue;

			$str = preg_replace($key, $hashVal, $str);
			$str = str_replace($hashVal, $value, $str);
		}

		if (!isset($options['format']) && isset($options['before'])) {
			$str = str_replace($options['escape'] . $options['before'], $options['before'], $str);
		}
		return $options['clean'] ? static::clean($str, $options) : $str;
	}

	public static function clean($str, array $options = array()) 
	{
		if(!$options['clean']) return $str;

		$clean = $options['clean'];
		$clean = ($clean === true) ? array('method' => 'text') : $clean;
		$clean = (!is_array($clean)) ? array('method' => $options['clean']) : $clean;

		switch($clean['method']) 
		{
			case 'html':
				$clean += array('word' => '[\w,.]+', 'andText' => true, 'replacement' => '');
				$kleenex = sprintf(
					'/[\s]*[a-z]+=(")(%s%s%s[\s]*)+\\1/i',
					preg_quote($options['before'], '/'),
					$clean['word'],
					preg_quote($options['after'], '/')
				);
				$str = preg_replace($kleenex, $clean['replacement'], $str);

				if ($clean['andText']) {
					$options['clean'] = array('method' => 'text');
					$str = static::clean($str, $options);
				}
			break;
			case 'text':
				$clean += array(
					'word' => '[\w,.]+', 'gap' => '[\s]*(?:(?:and|or|,)[\s]*)?', 'replacement' => ''
				);
				$before = preg_quote($options['before'], '/');
				$after = preg_quote($options['after'], '/');

				$kleenex = sprintf(
					'/(%s%s%s%s|%s%s%s%s|%s%s%s%s%s)/',
					$before, $clean['word'], $after, $clean['gap'],
					$clean['gap'], $before, $clean['word'], $after,
					$clean['gap'], $before, $clean['word'], $after, $clean['gap']
				);
				$str = preg_replace($kleenex, $clean['replacement'], $str);
			break;
		} 
		
		return $str;
	}

	public static function extract($regex, $str, $index = 0) 
	{
		if(!preg_match($regex, $str, $match))
			return false;

		return isset($match[$index]) ? $match[$index] : null;
	}

	public static function tokenize($data, array $options = array()) 
	{
		$defaults = array('separator' => ',', 'leftBound' => '(', 'rightBound' => ')');
		extract($options + $defaults);

		if(!$data || is_array($data)) return $data;

		$depth = 0;
		$offset = 0;
		$buffer = '';
		$results = array();
		$length = strlen($data);
		$open = false;

		while($offset <= $length) 
		{
			$tmpOffset = -1;
			$offsets = array(
				strpos($data, $separator, $offset),
				strpos($data, $leftBound, $offset),
				strpos($data, $rightBound, $offset)
			);

			for($i = 0; $i < 3; $i++) {
				if($offsets[$i] !== false && ($offsets[$i] < $tmpOffset || $tmpOffset == -1))
					$tmpOffset = $offsets[$i];
			}

			if($tmpOffset === -1) 
			{
				$results[] = $buffer . substr($data, $offset);
				$offset = $length + 1;
				continue;
			}
			$buffer .= substr($data, $offset, ($tmpOffset - $offset));

			if($data{$tmpOffset} == $separator && $depth == 0) {
				$results[] = $buffer;
				$buffer = '';
			} 
			else 
				$buffer .= $data{$tmpOffset};

			if($leftBound != $rightBound) 
			{
				if($data{$tmpOffset} == $leftBound)
					$depth++;
				if($data{$tmpOffset} == $rightBound)
					$depth--;

				$offset = ++$tmpOffset;
				continue;
			}

			if($data{$tmpOffset} == $leftBound) {
				($open) ? $depth-- : $depth++;
				$open = !$open;
			}
			$offset = ++$tmpOffset;
		}

		if(!$results && $buffer)
			$results[] = $buffer;

		return $results ? array_map('trim', $results) : array();
	}
}