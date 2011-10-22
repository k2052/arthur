<?php

namespace arthur\analysis;

use arthur\util\Set;
use arthur\util\Collection;

class Parser extends \arthur\core\StaticObject 
{
	public static function token($string, array $options = array())
	{
		$defaults = array('id' => false);
		$options += $defaults;

		if(empty($string) && $string !== '0')
			return false;

		list($token) = static::tokenize($string);
		return $token[($options['id']) ? 'id' : 'name'];
	}

	public static function tokenize($code, array $options = array()) 
	{
		$defaults = array('wrap' => true, 'ignore' => array(), 'include' => array());
		$options += $defaults;
		$tokens   = array();
		$line     = 1;

		if ($options['wrap']) {
			$code = "<?php {$code}?>";
		}
		foreach(token_get_all($code) as $token) 
		{
			$token = (isset($token[1])) ? $token : array(null, $token, $line);
			list($id, $content, $line) = $token;
			$name = $id ? token_name($id) : $content;

			if(!empty($options['include'])) {
				if(!in_array($name, $options['include']) && !in_array($id, $options['include']))
					continue;
			}

			if(!empty($options['ignore'])) {
				if(in_array($name, $options['ignore']) || in_array($id, $options['ignore'])) 
					continue;
			}   
			
			$tokens[] = array('id' => $id, 'name' => $name, 'content' => $content, 'line' => $line);
		}

		if($options['wrap'] && empty($options['include']))
			$tokens = array_slice($tokens, 1, count($tokens) - 2);

		return $tokens;
	}

	public static function find($code, $pattern, array $options = array()) 
	{
		$defaults = array(
			'all'    => true, 'capture' => array(), 'ignore' => array('T_WHITESPACE'),
			'return' => true, 'lineBreaks' => false, 'startOfLine' => false
		);
		$options += $defaults;
		$results  = array();
		$matches  = array();
		$patternMatch = array();
		$ret = $options['return'];

		$tokens  = new Collection(array('data' => static::tokenize($code, $options)));
		$pattern = new Collection(array('data' => static::tokenize($pattern, $options)));

		$breaks = function($token) use (&$tokens, &$matches, &$patternMatch, $options) 
		{
			if(!$options['lineBreaks'])
				return true;
			if(empty($patternMatch) && !$options['startOfLine'])
				return true;

			if(empty($patternMatch)) {
				$prev = $tokens->prev();
				$tokens->next();
			} 
			else
				$prev = reset($patternMatch);

			if(empty($patternMatch) && $options['startOfLine'])
				return ($token['line'] > $prev['line']);

			return ($token['line'] == $prev['line']);
		};

		$capture = function($token) use (&$matches, &$patternMatch, $tokens, $breaks, $options) 
		{
			if(is_null($token)) {
				$matches = $patternMatch = array();
				return false;
			}

			if(empty($patternMatch)) 
			{
				$prev = $tokens->prev();
				$tokens->next();    
				
				if($options['startOfLine'] && $token['line'] == $prev['line']) {
					$patternMatch = $matches = array();
					return false;
				}
			}
			$patternMatch[] = $token;

			if(empty($options['capture']) || !in_array($token['name'], $options['capture']))
				return true;

			if(!$breaks($token)) {
				$matches = array();
				return true;
			}   
			
			$matches[] = $token;
			return true;
		};

		$executors = array(
			'*' => function(&$tokens, &$pattern) use ($options, $capture) {
				$closing = $pattern->next();
				$tokens->prev();

				while (($t = $tokens->next()) && !Parser::matchToken($closing, $t)) {
					$capture($t);
				}
				$pattern->next();
			}
		);

		$tokens->rewind();
		$pattern->rewind();

		while($tokens->valid()) 
		{
			if(!$pattern->valid()) 
			{
				$pattern->rewind();

				if(!empty($matches)) 
				{
					$results[] = array_map(
						function($i) use ($ret) { return isset($i[$ret]) ? $i[$ret] : $i; },
						$matches
					);
				}   
				
				$capture(null);
			}

			$p = $pattern->current();
			$t = $tokens->current();

			switch(true) 
			{
				case (static::matchToken($p, $t)):
					$capture($t) ? $pattern->next() : $pattern->rewind();
				break;
				case (isset($executors[$p['name']])):
					$exec = $executors[$p['name']];
					$exec($tokens, $pattern);
				break;
				default:
					$capture(null);
					$pattern->rewind();
				break;
			}     
			
			$tokens->next();
		}     
		
		return $results;
	}

	public static function match($code, $parameters, array $options = array()) 
	{
		$defaults   = array('ignore' => array('T_WHITESPACE'), 'return' => true);
		$options   += $defaults;
		$parameters = static::_prepareMatchParams($parameters);

		$tokens  = is_array($code) ? $code : static::tokenize($code, $options);
		$results = array();

		foreach($tokens as $i => $token) 
		{
			if(!array_key_exists($token['name'], $parameters)) {
				if(!in_array('*', $parameters))
					continue;
			}
			$param = $parameters[$token['name']];

			if(isset($param['before']) && $i > 0) {
				if(!in_array($tokens[$i - 1]['name'], (array) $param['before']))
					continue;
			}

			if(isset($param['after']) && $i + 1 < count($tokens)) {
				 if(!in_array($tokens[$i + 1]['name'], (array) $param['after'])) 
					continue;
			}    
			
			$results[] = isset($token[$options['return']]) ? $token[$options['return']] : $token;
		}    
		
		return $results;
	}

	public static function matchToken($pattern, $token) 
	{
		if($pattern['name'] != $token['name'])
			return false;

		if(!isset($pattern['content']))
			return true;

		$match   = $pattern['content'];
		$content = $token['content'];

		if($pattern['name'] == 'T_VARIABLE') {
			$match = substr($match, 1);
			$content = substr($content, 1);
		}

		switch(true) 
		{
			case($match == '_' || $match == $content):
				return true;
		}
		
		return false;
	}
	
	protected static function _prepareMatchParams($parameters) 
	{
		foreach(Set::normalize($parameters) as $token => $scope)
		{
			if(strpos($token, 'T_') !== 0) 
			{
				unset($parameters[$token]);

				foreach(array('before', 'after') as $key) 
				{
					if(!isset($scope[$key]))
						continue;
					$items = array();

					foreach((array) $scope[$key] as $item) {
						$items[] = (strpos($item, 'T_') !== 0)  ? static::token($item) : $item;
					}  
					
					$scope[$key] = $items;
				}    
				
				$parameters[static::token($token)] = $scope;
			}
		}  
		
		return $parameters;
	}
}