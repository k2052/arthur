<?php

namespace arthur\test\filter;

class Profiler extends \arthur\test\Filter 
{
	protected static $_metrics = array(
		'Time' => array(
			'function' => array('microtime', true),
			'format'   => 'seconds'
		),
		'Current Memory' => array(
			'function' => 'memory_get_usage',
			'format'   => 'bytes'
		),
		'Peak Memory' => array(
			'function' => 'memory_get_peak_usage',
			'format'   => 'bytes'
		),
		'Current Memory (Xdebug)' => array(
			'function' => 'xdebug_memory_usage',
			'format'   => 'bytes'
		),
		'Peak Memory (Xdebug)' => array(
			'function' => 'xdebug_peak_memory_usage',
			'format'   => 'bytes'
		)
	);

	protected static $_formatters = array();

	public static function __init() 
	{
		foreach(static::$_metrics as $name => $check) 
		{
			$function = current((array) $check['function']);

			if(is_string($check['function']) && !function_exists($check['function']))
				unset(static::$_metrics[$name]);
		}

		static::$_formatters = array(
			'seconds' => function($value) { return number_format($value, 4) . 's'; },
			'bytes'   => function($value) { return number_format($value / 1024, 3) . 'k'; }
		);
	}

	public static function apply($report, $tests, array $options = array()) 
	{
		$defaults = array('method' => 'run', 'checks' => static::$_metrics);
		$options += $defaults;
		$m        = $options['method'];      
		
		$filter = function($self, $params, $chain) use ($report, $options) 
		{
			$start = $results = array();

			$runCheck = function($check) 
			{
				switch (true) {
					case (is_object($check) || is_string($check)):
						return $check();
					break;
					case (is_array($check)):
						$function = array_shift($check);
						$result = !$check ? $check() : call_user_func_array($function, $check);
					break;
				}       
				
				return $result;
			};

			foreach($options['checks'] as $name => $check) {
				$start[$name] = $runCheck($check['function']);
			}
			$methodResult = $chain->next($self, $params, $chain);

			foreach($options['checks'] as $name => $check) {
				$results[$name] = $runCheck($check['function']) - $start[$name];
			}
			$report->collect(
				__CLASS__,
				array(
					$self->subject() => $results,
					'options' => $options + array('test' => get_class($self)),
					'method' => $params['method']
				)
			);    
			
			return $methodResult;
		};                   
		
		$tests->invoke('applyFilter', array($m, $filter));
		return $tests;
	}


	public static function analyze($report, array $options = array()) 
	{
		$results          = $report->results['group'];
		$collectedResults = static::collect($report->results['filters'][__CLASS__]);
		extract($collectedResults, EXTR_OVERWRITE);
		$metrics          = array();

		foreach($results as $testCase) 
		{
			foreach((array) $testCase as $assertion) 
			{
				if($assertion['result'] != 'pass' && $assertion['result'] != 'fail')
					continue;

				$class = $classMap[$assertion['class']];

				if(!isset($metrics[$class]))
					$metrics[$class] = array('assertions' => 0);

				$metrics[$class]['assertions']++;
			}
		}

		foreach($filterResults as $class => $methods) 
		{
			foreach($methods as $methodName => $timers) 
			{
				foreach($timers as $title => $value) 
				{
					if(!isset($metrics[$class][$title]))
						$metrics[$class][$title] = 0;
					$metrics[$class][$title] += $value;
				}
			}
		}

		$totals = array();  
		
		foreach($metrics as $class => $data) 
		{
			foreach($data as $title => $value)
			{
				if(isset(static::$_metrics[$title])) 
				{
					if(isset($totals[$title]['value']))
						$totals[$title]['value'] += $value;
					else
						$totals[$title]['value'] = $value;

					if(!isset($totals[$title]['format'])) {
						$format = static::$_metrics[$title]['format'];
						$totals[$title]['formatter'] = static::$_formatters[$format];
					}
				}
			}
		}

		$metrics['totals'] = $totals; 
		
		return $metrics;
	}

	public function check($name, $value = null) 
	{
		if(is_null($value) && !is_array($name))
			return isset(static::$_metrics[$name]) ? static::$_metrics[$name] : null;

		if($value === false) {
			unset(static::$_metrics[$name]);
			return;
		}

		if(!empty($value))
			static::$_metrics[$name] = $value;

		if(is_array($name))
			static::$_metrics = $name + static::$_metrics;
	}

	public static function collect($filterResults) 
	{
		$defaults        = array('test' => null);
		$classMap        = array();
		$packagedResults = array();

		foreach($filterResults as $results) 
		{
			$class     = key($results);
			$options   = $results['options'];
			$options  += $defaults;
			$method    = $results['method'];

			$classMap[$options['test']] = $class;
			if(!isset($packagedResults[$class]))
				$packagedResults[$class] = array();

			$packagedResults[$class][$method] = $results[$class];
		}

		$filterResults = $packagedResults;

		return array(
			'filterResults' => $filterResults,
			'classMap' => $classMap
		);
	}
}