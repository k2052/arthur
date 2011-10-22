<?php

namespace arthur\test\filter;

use arthur\analysis\Parser;
use arthur\analysis\Inspector;

class Complexity extends \arthur\test\Filter 
{
	protected static $_include = array(
		'T_CASE', 'T_DEFAULT', 'T_CATCH', 'T_IF', 'T_FOR',
		'T_FOREACH', 'T_WHILE', 'T_DO', 'T_ELSEIF'
	);

	public static function apply($report, $tests, array $options = array()) 
	{
		$results = array();  
		
		foreach($tests->invoke('subject') as $class) 
		{
			$results[$class] = array();

			if(!$methods = Inspector::methods($class, 'ranges', array('public' => false)))
				continue;
			foreach($methods as $method => $lines) 
			{
				$lines = Inspector::lines($class, $lines);
				$branches = Parser::tokenize(join("\n", (array) $lines), array(
					'include' => static::$_include
				));
				$results[$class][$method] = count($branches) + 1;
				$report->collect(__CLASS__, $results);
			}
		}      
		
		return $tests;
	}

	public static function analyze($report, array $options = array()) 
	{
		$filterResults = static::collect($report->results['filters'][__CLASS__]);
		$metrics       = array('max' => array(), 'class' => array());

		foreach ($filterResults as $class => $methods) 
		{
			if(!$methods)
				continue;
			$metrics['class'][$class] = array_sum($methods) / count($methods);

			foreach($methods as $method => $count) {
				$metrics['max']["{$class}::{$method}()"] = $count;
			}
		}

		arsort($metrics['max']);
		arsort($metrics['class']);  
		
		return $metrics;
	}

	public static function collect($filterResults) 
	{
		$packagedResults = array();

		foreach($filterResults as $result) 
		{
			foreach($result as $class => $method) 
			{
				if(!isset($packagedResults[$class]))
					$packagedResults[$class] = array();

				$classResult             = (array) $result[$class];
				$packagedResults[$class] = array_merge($classResult, $packagedResults[$class]);
			}
		}

		return $packagedResults;
	}
}