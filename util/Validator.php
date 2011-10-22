<?php

namespace arthur\util;

use arthur\util\Set;
use InvalidArgumentException;

class Validator extends \arthur\core\StaticObject 
{
	protected static $_rules = array();
	protected static $_options = array(
		'defaults' => array('contains' => true)
	);
	public static function __init() 
	{
		$alnum = '[A-Fa-f0-9]';
		$class = get_called_class();
		static::$_methodFilters[$class] = array();

		static::$_rules = array(
			'alphaNumeric' => '/^[\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]+$/mu',
			'blank'        => '/[^\\s]/',
			'creditCard'   => array(
				'amex'     => '/^3[4|7]\\d{13}$/',
				'bankcard' => '/^56(10\\d\\d|022[1-5])\\d{10}$/',
				'diners'   => '/^(?:3(0[0-5]|[68]\\d)\\d{11})|(?:5[1-5]\\d{14})$/',
				'disc'     => '/^(?:6011|650\\d)\\d{12}$/',
				'electron' => '/^(?:417500|4917\\d{2}|4913\\d{2})\\d{10}$/',
				'enroute'  => '/^2(?:014|149)\\d{11}$/',
				'jcb'      => '/^(3\\d{4}|2100|1800)\\d{11}$/',
				'maestro'  => '/^(?:5020|6\\d{3})\\d{12}$/',
				'mc'       => '/^5[1-5]\\d{14}$/',
				'solo'     => '/^(6334[5-9][0-9]|6767[0-9]{2})\\d{10}(\\d{2,3})?$/',
				'switch'   => '/^(?:49(03(0[2-9]|3[5-9])|11(0[1-2]|7[4-9]|8[1-2])|36[0-9]{2})' .
				              '\\d{10}(\\d{2,3})?)|(?:564182\\d{10}(\\d{2,3})?)|(6(3(33[0-4]' .
				              '[0-9])|759[0-9]{2})\\d{10}(\\d{2,3})?)$/',
				'visa'     => '/^4\\d{12}(\\d{3})?$/',
				'voyager'  => '/^8699[0-9]{11}$/',
				'fast'     => '/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3' .
				              '(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/'
			),
			'date'         => array(
				'dmy'      => '%^(?:(?:31(\\/|-|\\.|\\x20)(?:0?[13578]|1[02]))\\1|(?:(?:29|30)' .
				              '(\\/|-|\\.|\\x20)(?:0?[1,3-9]|1[0-2])\\2))(?:(?:1[6-9]|[2-9]\\d)?' .
				              '\\d{2})$|^(?:29(\\/|-|\\.|\\x20)0?2\\3(?:(?:(?:1[6-9]|[2-9]\\d)?' .
				              '(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])' .
				              '00))))$|^(?:0?[1-9]|1\\d|2[0-8])(\\/|-|\\.|\\x20)(?:(?:0?[1-9])|' .
				              '(?:1[0-2]))\\4(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$%',
				'mdy'      => '%^(?:(?:(?:0?[13578]|1[02])(\\/|-|\\.|\\x20)31)\\1|(?:(?:0?[13-9]|' .
				              '1[0-2])(\\/|-|\\.|\\x20)(?:29|30)\\2))(?:(?:1[6-9]|[2-9]\\d)?\\d' .
				              '{2})$|^(?:0?2(\\/|-|\\.|\\x20)29\\3(?:(?:(?:1[6-9]|[2-9]\\d)?' .
				              '(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])' .
				              '00))))$|^(?:(?:0?[1-9])|(?:1[0-2]))(\\/|-|\\.|\\x20)(?:0?[1-9]|1' .
				              '\\d|2[0-8])\\4(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$%',
				'ymd'      => '%^(?:(?:(?:(?:(?:1[6-9]|[2-9]\\d)?(?:0[48]|[2468][048]|[13579]' .
				              '[26])|(?:(?:16|[2468][048]|[3579][26])00)))(\\/|-|\\.|\\x20)' .
				              '(?:0?2\\1(?:29)))|(?:(?:(?:1[6-9]|[2-9]\\d)?\\d{2})(\\/|-|\\.|' .
				              '\\x20)(?:(?:(?:0?[13578]|1[02])\\2(?:31))|(?:(?:0?[1,3-9]|1[0-2])' .
				              '\\2(29|30))|(?:(?:0?[1-9])|(?:1[0-2]))\\2(?:0?[1-9]|1\\d|2[0-8]' .
				              '))))$%',
				'dMy'      => '/^((31(?!\\ (Feb(ruary)?|Apr(il)?|June?|(Sep(?=\\b|t)t?|Nov)' .
				              '(ember)?)))|((30|29)(?!\\ Feb(ruary)?))|(29(?=\\ Feb(ruary)?\\ ' .
				              '(((1[6-9]|[2-9]\\d)(0[48]|[2468][048]|[13579][26])|((16|[2468]' .
				              '[048]|[3579][26])00)))))|(0?[1-9])|1\\d|2[0-8])\\ (Jan(uary)?|' .
				              'Feb(ruary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)|(ne?))|Aug(ust)?|' .
				              'Oct(ober)?|(Sep(?=\\b|t)t?|Nov|Dec)(ember)?)\\ ((1[6-9]|[2-9]' .
				              '\\d)\\d{2})$/',
				'Mdy'      => '/^(?:(((Jan(uary)?|Ma(r(ch)?|y)|Jul(y)?|Aug(ust)?|Oct(ober)?' .
				              '|Dec(ember)?)\\ 31)|((Jan(uary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)' .
				              '|(ne?))|Aug(ust)?|Oct(ober)?|(Sept|Nov|Dec)(ember)?)\\ (0?[1-9]' .
				              '|([12]\\d)|30))|(Feb(ruary)?\\ (0?[1-9]|1\\d|2[0-8]|(29(?=,?\\ ' .
				              '((1[6-9]|[2-9]\\d)(0[48]|[2468][048]|[13579][26])|((16|[2468]' .
				              '[048]|[3579][26])00)))))))\\,?\\ ((1[6-9]|[2-9]\\d)\\d{2}))$/',
				'My'       => '%^(Jan(uary)?|Feb(ruary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)|(ne?))|' .
				              'Aug(ust)?|Oct(ober)?|(Sep(?=\\b|t)t?|Nov|Dec)(ember)?)[ /]((1[6-9]' .
				              '|[2-9]\\d)\\d{2})$%',
				'my'       => '%^(((0[123456789]|10|11|12)([- /.])(([1][9][0-9][0-9])|([2][0-9]' .
				              '[0-9][0-9]))))$%'
			),
			'ip' => function($value, $format = null, array $options = array()) {
				$options += array('flags' => array());
				return (boolean) filter_var($value, FILTER_VALIDATE_IP, $options);
			},
			'money'      => array(
				'right'    => '/^(?!0,?\d)(?:\d{1,3}(?:([, .])\d{3})?(?:\1\d{3})*|(?:\d+))' .
				              '((?!\1)[,.]\d{2})?(?<!\x{00a2})\p{Sc}?$/u',
				'left'     => '/^(?!\x{00a2})\p{Sc}?(?!0,?\d)(?:\d{1,3}(?:([, .])\d{3})?' .
				              '(?:\1\d{3})*|(?:\d+))((?!\1)[,.]\d{2})?$/u'
			),
			'notEmpty'     => '/[^\s]+/m',
			'phone'        => '/^\+?[0-9\(\)\-]{10,20}$/',
			'postalCode'   => '/(^|\A\b)[A-Z0-9\s\-]{5,}($|\b\z)/i',
			'regex'        => '/^(?:([^[:alpha:]\\\\{<\[\(])(.+)(?:\1))|(?:{(.+)})|(?:<(.+)>)|' .
			                  '(?:\[(.+)\])|(?:\((.+)\))[gimsxu]*$/',
			'time'         => '%^((0?[1-9]|1[012])(:[0-5]\d){0,2}([AP]M|[ap]m))$|^([01]\d|2[0-3])' .
			                  '(:[0-5]\d){0,2}$%',
			'boolean'      => function($value) 
			{
				$bool = is_bool($value);
				$filter = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				return ($bool || $filter !== null);
			},
			'decimal' => function($value, $format = null, array $options = array()) 
			{
				if(isset($options['precision'])) 
				{
					$precision = strlen($value) - strrpos($value, '.') - 1;

					if($precision !== (int) $options['precision'])
						return false;
				}   
				
				return (filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) !== null);
			},
			'inList' => function($value, $format, $options) {
				$options += array('list' => array());
				return in_array($value, $options['list']);
			},
			'lengthBetween' => function($value, $format, $options) 
			{
				$length = strlen($value);
				$options += array('min' => 1, 'max' => 255);
				return ($length >= $options['min'] && $length <= $options['max']);
			},
			'luhn' => function($value) 
			{
				if(empty($value) || !is_string($value)) 
					return false;

				$sum    = 0;
				$length = strlen($value);

				for($position = 1 - ($length % 2); $position < $length; $position += 2) {
					$sum += $value[$position];
				}
				for($position = ($length % 2); $position < $length; $position += 2) {
					$number = $value[$position] * 2;
					$sum += ($number < 10) ? $number : $number - 9;
				}
				return ($sum % 10 == 0);
			},
			'numeric' => function($value) {
				return is_numeric($value);
			},
			'inRange' => function($value, $format, $options) 
			{
				$defaults = array('upper' => null, 'lower' => null);
				$options += $defaults;

				if(!is_numeric($value)) 
					return false;  
					
				switch(true) 
				{
					case(!is_null($options['upper']) && !is_null($options['lower'])):
						return ($value > $options['lower'] && $value < $options['upper']);
					case(!is_null($options['upper'])):
						return ($value < $options['upper']);
					case(!is_null($options['lower'])):
						return ($value > $options['lower']);
				}
				return is_finite($value);
			},
			'uuid' => "/^{$alnum}{8}-{$alnum}{4}-{$alnum}{4}-{$alnum}{4}-{$alnum}{12}$/",
			'email' => function($value) {
				return filter_var($value, FILTER_VALIDATE_EMAIL);
			},
			'url' => function($value, $format = null, array $options = array()) {
				$options += array('flags' => array());
				return (boolean) filter_var($value, FILTER_VALIDATE_URL, $options);
			}
		);

		$isEmpty = function($self, $params, $chain) {
			extract($params);
			return (empty($value) && $value != '0') ? false : $chain->next($self, $params, $chain);
		};

		static::$_methodFilters[$class]['alphaNumeric'] = array($isEmpty);
		static::$_methodFilters[$class]['notEmpty'] = array($isEmpty);

		static::$_methodFilters[$class]['creditCard'] = array(function($self, $params, $chain) 
		{
			extract($params);
			$options += array('deep' => false);

			if(strlen($value = str_replace(array('-', ' '), '', $value)) < 13)
				return false;
			if(!$chain->next($self, compact('value') + $params, $chain)) 
				return false;          
				
			return $options['deep'] ? Validator::isLuhn($value) : true;
		});

		static::$_methodFilters[$class]['email'] = array(function($self, $params, $chain) 
		{
			extract($params);
			$defaults = array('deep' => false);
			$options += $defaults;

			if(!$chain->next($self, $params, $chain))
				return false;
			if(!$options['deep'])
				return true;
			list($prefix, $host) = explode('@', $params['value']);

			if(getmxrr($host, $mxhosts))
				return is_array($mxhosts);

			return false;
		});
	}

	public static function __callStatic($method, $args = array()) 
	{
		if(!isset($args[0])) return false;         
		
		$args = array_filter($args) + array(0 => $args[0], 1 => 'any', 2 => array());
		$rule = preg_replace("/^is([A-Z][A-Za-z0-9]+)$/", '$1', $method);
		$rule[0] = strtolower($rule[0]); 
		
		return static::rule($rule, $args[0], $args[1], $args[2]);
	}

	public static function check(array $values, array $rules, array $options = array()) 
	{
		$defaults = array(
			'notEmpty',
			'message' => null,
			'required' => true,
			'skipEmpty' => false,
			'format' => 'any',
			'on' => null,
			'last' => false
		);
		$errors = array();
		$events = (array) (isset($options['events']) ? $options['events'] : null);
		$values = Set::flatten($values);

		foreach($rules as $field => $rules) 
		{
			$rules = is_string($rules) ? array('message' => $rules) : $rules;
			$rules = is_array(current($rules)) ? $rules : array($rules);
			$errors[$field] = array();
			$options['field'] = $field;

			foreach($rules as $key => $rule) 
			{
				$rule += $defaults + compact('values');
				list($name) = $rule;

				if($events && $rule['on'] && !array_intersect($events, (array) $rule['on']))
					continue;      
					
				if(!array_key_exists($field, $values)) 
				{
					if($rule['required']) 
						$errors[$field][] = $rule['message'] ?: $key;
					if($rule['last']) 
						break; 
						
					continue;
				}
				if(empty($values[$field]) && $rule['skipEmpty'])
					continue;

				if(!static::rule($name, $values[$field], $rule['format'], $rule + $options)) 
				{
					$errors[$field][] = $rule['message'] ?: $key;

					if($rule['last']) break;
				}
			}
		}     
		
		return array_filter($errors);
	}

	public static function add($name, $rule = null, array $options = array()) 
	{
		if(!is_array($name)) 
			$name = array($name => $rule);

		static::$_rules = Set::merge(static::$_rules, $name);

		if(!empty($options)) {
			$options = array_combine(array_keys($name), array_fill(0, count($name), $options));
			static::$_options = Set::merge(static::$_options, $options);
		}
	}

	public static function rule($rule, $value, $format = 'any', array $options = array()) 
	{
		if(!isset(static::$_rules[$rule])) 
			throw new InvalidArgumentException("Rule `{$rule}` is not a validation rule.");

		$defaults = isset(static::$_options[$rule]) ? static::$_options[$rule] : array();
		$options  = (array) $options + $defaults + static::$_options['defaults'];

		$ruleCheck = static::$_rules[$rule];
		$ruleCheck = is_array($ruleCheck) ? $ruleCheck : array($ruleCheck);

		if(!$options['contains'] && !empty($ruleCheck)) 
		{
			foreach($ruleCheck as $key => $item) {
				$ruleCheck[$key] = is_string($item) ? "/^{$item}$/" : $item;
			}
		}

		$params = compact('value', 'format', 'options');            
		
		return static::_filter($rule, $params, static::_checkFormats($ruleCheck));
	}

	public static function rules($name = null) 
	{
		if(!$name) 
			return array_keys(static::$_rules);

		return isset(static::$_rules[$name]) ? static::$_rules[$name] : null;
	}

	protected static function _checkFormats($rules) 
	{
		return function($self, $params, $chain) use ($rules) 
		{
			$value   = $params['value'];
			$format  = $params['format'];
			$options = $params['options'];

			$defaults = array('all' => true);
			$options += $defaults;

			$formats        = (array) $format;
			$options['all'] = ($format == 'any');

			foreach($rules as $index => $check) 
			{
				if(!$options['all'] && !(in_array($index, $formats) || isset($formats[$index])))
					continue;

				$regexPassed   = (is_string($check) && preg_match($check, $value));
				$closurePassed = (is_object($check) && $check($value, $format, $options));

				if(!$options['all'] && ($regexPassed || $closurePassed)) 
					return true;
				if($options['all'] && (!$regexPassed && !$closurePassed))
					return false;
			}  
			
			return $options['all'];
		};
	}
}