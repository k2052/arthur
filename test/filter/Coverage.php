<?php
=
namespace arthur\test\filter;

use arthur\core\Libraries;
use arthur\analysis\Inspector;

class Coverage extends \arthur\test\Filter 
{
	public static function apply($report, $tests, array $options = array()) 
	{
		$defaults = array('method' => 'run');
		$options += $defaults;
		$m = $options['method'];       
		
		$filter = function($self, $params, $chain) use ($report, $options) 
		{
			xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
			$chain->next($self, $params, $chain);
			$results = xdebug_get_code_coverage();
			xdebug_stop_code_coverage();
			$report->collect(__CLASS__, array($self->subject() => $results));
		};
		$tests->invoke('applyFilter', array($m, $filter));    
		
		return $tests;
	}

	public static function analyze($report, array $classes = array()) 
	{
		$data    = static::collect($report->results['filters'][__CLASS__]);
		$classes = $classes ?: array_filter(get_declared_classes(), function($class) use ($data) {
			$unit  = 'arthur\test\Unit';
			return (!(is_subclass_of($class, $unit)) || array_key_exists($class, $data));
		});
		$classes   = array_values(array_intersect((array) $classes, array_keys($data)));
		$densities = $result = array();

		foreach($classes as $class) {
			$classMap = array($class => Libraries::path($class));
			$densities += static::_density($data[$class], $classMap);
		}
		$executableLines = array();

		if($classes) 
		{
			$executableLines = array_combine($classes, array_map(
				function($cls) { return Inspector::executable($cls, array('public' => false)); },
				$classes
			));
		}

		foreach($densities as $class => $density) 
		{
			$executable = $executableLines[$class];
			$covered    = array_intersect(array_keys($density), $executable);
			$uncovered  = array_diff($executable, $covered);
			if(count($executable))
				$percentage = round(count($covered) / (count($executable) ?: 1), 4) * 100;
			else 
				$percentage = 100;

			$result[$class] = compact('class', 'executable', 'covered', 'uncovered', 'percentage');
		}

		$result = static::collectLines($result);  
		
		return $result;
	}

	protected static function collectLines($result) 
	{
		$output    = null;
		$aggregate = array('covered' => 0, 'executable' => 0);

		foreach ($result as $class => $coverage) 
		{
			$out  = array();
			$file = Libraries::path($class);

			$aggregate['covered']    += count($coverage['covered']);
			$aggregate['executable'] += count($coverage['executable']);

			$uncovered = array_flip($coverage['uncovered']);
			$contents  = explode("\n", file_get_contents($file));
			array_unshift($contents, ' ');
			$count     = count($contents);

			for($i = 1; $i <= $count; $i++) 
			{
				if(isset($uncovered[$i])) 
				{
					if(!isset($out[$i - 2])) 
					{
						$out[$i - 2] = array(
							'class' => 'ignored',
							'data' => '...'
						);
					}
					if(!isset($out[$i - 1])) 
					{
						$out[$i - 1] = array(
							'class' => 'covered',
							'data' => $contents[$i - 1]
						);
					}            
					
					$out[$i] = array(
						'class' => 'uncovered',
						'data'  => $contents[$i]
					);

					if(!isset($uncovered[$i + 1])) 
					{
						$out[$i + 1] = array(
							'class' => 'covered',
							'data' => $contents[$i + 1]
						);
					}
				} 
				elseif(isset($out[$i - 1]) && $out[$i - 1]['data'] !== '...'
						&& !isset($out[$i]) && !isset($out[$i + 1])) 
				{
					$out[$i] = array(
						'class' => 'ignored',
						'data' => '...'
					);
				}
			}
			$result[$class]['output'][$file] = $out;
		}  
		
		return $result;
	}

	public static function collect($filterResults, array $options = array()) 
	{
		$defaults = array('merging' => 'class');
		$options += $defaults;
		$packagedResults = array();

		foreach($filterResults as $results) 
		{
			$class   = key($results);
			$results = $results[$class];
			foreach($results as $file => $lines) {
				unset($results[$file][0]);
			}

			switch($options['merging']) 
			{
				case 'class':
				default:
					if (!isset($packagedResults[$class])) {
						$packagedResults[$class] = array();
					}
					$packagedResults[$class][] = $results;
				break;
			}
		}

		return $packagedResults;
	}

	protected static function _density($runs, $classMap = array()) 
	{
		$results = array();

		foreach($runs as $run) 
		{
			foreach($run as $file => $coverage) 
			{
				if($classMap) 
				{
					if(!$class = array_search($file, $classMap))
						continue;

					$file = $class;
				}
				if(!isset($results[$file]))
					$results[$file] = array();

				$coverage = array_filter($coverage, function($line) { return ($line === 1); });

				foreach($coverage as $line => $isCovered) 
				{
					if(!isset($results[$file][$line]))
						$results[$file][$line] = 0;

					$results[$file][$line]++;
				}
			}
		}
		return $results;
	}
}