<?php

namespace arthur\g11n;

use BadMethodCallException;
use InvalidArgumentException;

class Locale extends \arthur\core\StaticObject 
{
	protected static $_tags = array(
		'language'  => array('formatter' => 'strtolower'),
		'script'    => array('formatter' => array('strtolower', 'ucfirst')),
		'territory' => array('formatter' => 'strtoupper'),
		'variant'   => array('formatter' => 'strtoupper')
	);

	public static function __callStatic($method, $params = array()) 
	{
		$tags = static::invokeMethod('decompose', $params);

		if(!isset(static::$_tags[$method]))
			throw new BadMethodCallException("Invalid locale tag `{$method}`.");

		return isset($tags[$method]) ? $tags[$method] : null;
	}

	public static function compose($tags) 
	{
		$result = array();

		foreach(static::$_tags as $name => $tag) {
			if(isset($tags[$name]))
				$result[] = $tags[$name];
		}
		if($result)
			return implode('_', $result);
	}

	public static function decompose($locale) 
	{
		$regex  = '(?P<language>[a-z]{2,3})';
		$regex .= '(?:[_-](?P<script>[a-z]{4}))?';
		$regex .= '(?:[_-](?P<territory>[a-z]{2}))?';
		$regex .= '(?:[_-](?P<variant>[a-z]{5,}))?';

		if(!preg_match("/^{$regex}$/i", $locale, $matches))
			throw new InvalidArgumentException("Locale `{$locale}` could not be parsed.");

		return array_filter(array_intersect_key($matches, static::$_tags));
	}

	public static function canonicalize($locale) 
	{
		$tags = static::decompose($locale);

		foreach($tags as $name => &$tag) {
			foreach((array) static::$_tags[$name]['formatter'] as $formatter) {
				$tag = $formatter($tag);
			}
		}      
		
		return static::compose($tags);
	}

	public static function cascade($locale) 
	{
		$locales[] = $locale;

		if($locale === 'root')
			return $locales;

		$tags = static::decompose($locale);

		while(count($tags) > 1) {
			array_pop($tags);
			$locales[] = static::compose($tags);
		}
		$locales[] = 'root';     
		
		return $locales;
	}

	public static function lookup($locales, $locale) 
	{
		$tags = static::decompose($locale);

		while(count($tags) > 0) 
		{
			if(($key = array_search(static::compose($tags), $locales)) !== false)
				return $locales[$key];
			array_pop($tags);
		}
	}   
	
	public static function preferred($request, $available = null) 
	{
		if(is_array($request))
			$result = $request;
		elseif($request instanceof \arthur\action\Request)
			$result = static::_preferredAction($request);
		elseif($request instanceof \arthur\console\Request)
			$result = static::_preferredConsole($request);
		else
			return null;

		if(!$available)
			return array_shift($result);

		foreach((array) $result as $locale) 
		{
			if($match = static::lookup($available, $locale))
				return $match;
		}
	}

	protected static function _preferredAction($request) 
	{
		$regex  = '(?P<locale>[\w\-]+)+(?:;q=(?P<quality>[0-9]+\.[0-9]+))?';
		$result = array();

		foreach(explode(',', $request->env('HTTP_ACCEPT_LANGUAGE')) as $part) 
		{
			if(preg_match("/{$regex}/", $part, $matches)) 
			{
				$locale = static::canonicalize($matches['locale']);
				$quality = isset($matches['quality']) ? $matches['quality'] : 1;
				$result[$locale] = $quality;
			}
		}
		arsort($result);      
		
		return array_keys($result);
	}

	protected static function _preferredConsole($request) 
	{
		$regex = '(?P<locale>[\w\_]+)(\.|@|$)+';
		$result = array();

		if($value = $request->env('LANGUAGE'))
			return explode(':', $value);

		foreach(array('LC_ALL', 'LANG') as $variable)
		{
			$value = $request->env($variable);

			if(!$value || $value == 'C' || $value == 'POSIX')
				continue;

			if(preg_match("/{$regex}/", $value, $matches))
				return (array) $matches['locale'];
		}   
		
		return $result;
	}
}