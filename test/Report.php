<?php

namespace arthur\test;

use arthur\core\Libraries;
use arthur\util\Inflector;
use arthur\core\ClassNotFoundException;

class Report extends \arthur\core\Object 
{
	public $group = null;
	public $title = null;
	public $results = array('group' => array(), 'filters' => array());
	public $timer = array('start' => null, 'end' => null);

	protected $_filters = array();

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'title'    => null,
			'group'    => null,
			'filters'  => array(),
			'format'   => 'txt',
			'reporter' => 'console'
		);    
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		$this->group = $this->_config['group'];
		$this->title = $this->_config['title'] ?: $this->_config['title'];
	}

	public function run() 
	{
		$tests = $this->group->tests();

		foreach($this->filters() as $filter => $options) {
			$this->results['filters'][$filter] = array();
			$tests = $filter::apply($this, $tests, $options['apply']) ?: $tests;
		}
		$this->results['group'] = $tests->run();

		foreach($this->filters() as $filter => $options) {
			$this->results['filters'][$filter] = $filter::analyze($this, $options['analyze']);
		}
	}

	public function collect($class, $results) 
	{
		$this->results['filters'][$class][] = $results;
	}
	
	public function stats() 
	{
		$results = (array) $this->results['group'];
		$defaults = array(
			'asserts'    => 0,
			'passes'     => array(),
			'fails'      => array(),
			'exceptions' => array(),
			'errors'     => array(),
			'skips'      => array()
		);          
		
		$stats = array_reduce($results, function($stats, $result) use ($defaults) 
		{
			$stats = (array) $stats + $defaults;
			$result = empty($result[0]) ? array($result) : $result;
			foreach($result as $response) 
			{
				if(empty($response['result']))
					continue;
				$result = $response['result'];

				if(in_array($result, array('fail', 'exception'))) 
				{
					$response = array_merge(
						array('class' => 'unknown', 'method' => 'unknown'), $response
					);
					$stats['errors'][] = $response;
				}
				unset($response['file'], $response['result']);

				if(in_array($result, array('pass', 'fail')))
					$stats['asserts']++;
				if(in_array($result, array('pass', 'fail', 'exception', 'skip')))
					$stats[Inflector::pluralize($result)][] = $response;
			}   
			
			return $stats;
		});   
		
		$stats = (array) $stats + $defaults;
		$count = array_map(
			function($value) { return is_array($value) ? count($value) : $value; }, $stats
		);
		$success = $count['passes'] == $count['asserts'] && $count['errors'] === 0;  
		
		return compact("stats", "count", "success");
	}

	public function render($template, $data = array()) 
	{
		$config = $this->_config;

		if($template == "stats" && !$data)
			$data = $this->stats();

		$template = Libraries::locate("test.templates.{$config['reporter']}", $template, array(
			'filter' => false, 'type' => 'file', 'suffix' => ".{$config['format']}.php"
		));
		$params = compact('template', 'data', 'config');

		return $this->_filter(__METHOD__, $params, function($self, $params, $chain) {
			extract($params['data']);
			ob_start();
			include $params['template'];
			return ob_get_clean();
		});
	}

	public function filters(array $filters = array()) 
	{
		if($this->_filters && !$filters)
			return $this->_filters;

		$filters += (array) $this->_config['filters'];
		$results = array();

		foreach($filters as $filter => $options) 
		{
			if(!$class = Libraries::locate('test.filter', $filter))
				throw new ClassNotFoundException("`{$class}` is not a valid test filter.");

			$options['name'] = strtolower(join('', array_slice(explode("\\", $class), -1)));
			$results[$class] = $options + array('apply' => array(), 'analyze' => array());
		}                              
		
		return $this->_filters = $results;
	}
}