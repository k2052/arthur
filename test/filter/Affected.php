<?php

namespace arthur\test\filter;

use arthur\test\Unit;
use arthur\core\Libraries;
use arthur\analysis\Inspector;

class Affected extends \arthur\test\Filter 
{
	protected static $_cachedDepends = array();

	public static function apply($report, $tests, array $options = array()) 
	{
		$affected     = array();
		$testsClasses = $tests->map('get_class', array('collect' => false));

		foreach($tests as $test) {
			$affected = array_merge($affected, self::_affected($test->subject()));
		}
		$affected = array_unique($affected);

		foreach ($affected as $class) 
		{
			$test = Unit::get($class);

			if($test && !in_array($test, $testsClasses))
				$tests[] = new $test();

			$report->collect(__CLASS__, array($class => $test));
		}    
		
		return $tests;
	}

	public static function analyze($report, array $options = array()) 
	{
		$analyze = array();  
		
		foreach($report->results['filters'][__CLASS__] as $result) 
		{
			foreach($result as $class => $test) {
				$analyze[$class] = $test;
			}
		}

		return $analyze;
	}
	
	protected static function _affected($dependency, $exclude = null) 
	{
		$exclude    = $exclude ?: '/(tests|webroot|resources|libraries|plugins)/';
		$classes    = Libraries::find(true, compact('exclude') + array('recursive' => true));
		$dependency = ltrim($dependency, '\\');
		$affected   = array();

		foreach($classes as $class) 
		{
			if(isset(static::$_cachedDepends[$class]))
				$depends = static::$_cachedDepends[$class];
			else 
			{
				$depends = Inspector::dependencies($class);
				$depends = array_map(function($c) { return ltrim($c, '\\'); }, $depends);   
				
				static::$_cachedDepends[$class] = $depends;
			}

			if(in_array($dependency, $depends))
				$affected[] = $class;
		}          
		
		return $affected;
	}
}