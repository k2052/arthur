<?php

namespace arthur\g11n;

use arthur\core\Environment;
use arthur\util\String;
use arthur\g11n\Catalog;

class Message extends \arthur\core\StaticObject 
{
	protected static $_cachedPages = array();

	public static function translate($id, array $options = array()) 
	{
		$defaults = array(
			'count'   => 1,
			'locale'  => Environment::get('locale'),
			'scope'   => null,
			'default' => null,
			'noop'    => false
		);
		$options += $defaults;

		if($options['noop'])
			$result = null;
		else {
			$result = static::_translated($id, abs($options['count']), $options['locale'], array(
				'scope' => $options['scope']
			));
		}

		if($result || $options['default'])
			return String::insert($result ?: $options['default'], $options);
	}
 
	public static function aliases() 
	{
		$t = function($message, array $options = array()) 
		{
			return Message::translate($message, $options + array('default' => $message));
		};
		$tn = function($message1, $message2, $count, array $options = array()) 
		{
			return Message::translate($message1, $options + compact('count') + array(
				'default' => $count == 1 ? $message1 : $message2
			));
		};  
		
		return compact('t', 'tn');
	}

	public static function cache($cache = null) 
	{
		if($cache === false)
			static::$_cachedPages = array();
		if(is_array($cache))
			static::$_cachedPages += $cache;

		return static::$_cachedPages;
	}

	protected static function _translated($id, $count, $locale, array $options = array()) 
	{
		$params = compact('id', 'count', 'locale', 'options');

		$cache =& static::$_cachedPages;
		return static::_filter(__FUNCTION__, $params, function($self, $params) use (&$cache) 
		{
			extract($params);

			if(!isset($cache[$options['scope']][$locale])) 
			{
				$cache[$options['scope']][$locale] = Catalog::read(
					true, 'message', $locale, $options
				);
			}
			$page = $cache[$options['scope']][$locale];

			if(!isset($page[$id]))
				return null;
			if(!is_array($page[$id]))
				return $page[$id];

			if(!isset($page['pluralRule']) || !is_callable($page['pluralRule']))
				return null;

			$key = $page['pluralRule']($count);

			if(isset($page[$id][$key])) 
				return $page[$id][$key];
		});
	}
}