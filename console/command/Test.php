<?php

namespace arthur\console\command;

use arthur\core\Libraries;
use arthur\test\Dispatcher;

class Test extends \arthur\console\Command 
{
	public $filters;
	public $format = 'txt';
	protected $_handlers = array();

	protected function _init() 
	{
		parent::_init();
		$self = $this;
		$this->_handlers += array(
			'txt' => function($runner, $path) use ($self) 
			{
				$message = sprintf('Running test(s) in `%s`... ', ltrim($path, '\\'));
				$self->header('Test');
				$self->out($message, array('nl' => false));

				$report = $runner();
				$self->out('done.', 2);
				$self->out('{:heading}Results{:end}', 0);
				$self->out($report->render('stats', $report->stats()));

				foreach($report->filters() as $filter => $options) {
					$data = $report->results['filters'][$filter];
					$self->out($report->render($options['name'], compact('data')));
				}

				$self->hr();
				$self->nl();     
				
				return $report;
			},
			'json' => function($runner, $path) use ($self) 
			{
				$report = $runner();

				if($results = $report->filters()) 
				{
					$filters = array();

					foreach($results as $filter => $options) {
						$filters[$options['name']] = $report->results['filters'][$filter];
					}
				}
				$self->out($report->render('stats', $report->stats() + compact('filters')));    
				
				return $report;
			}
		);
	}

	public function run($path = null) 
	{
		if(!$path = $this->_path($path))
			return false;
		$handlers = $this->_handlers;

		if(!isset($handlers[$this->format]) || !is_callable($handlers[$this->format])) {
			$this->error(sprintf('No handler for format `%s`... ', $this->format));
			return false;
		}
		$filters = $this->filters ? array_map('trim', explode(',', $this->filters)) : array();
		$params  = compact('filters') + array('reporter' => 'console', 'format' => $this->format);

		$runner = function() use ($path, $params) 
		{
			error_reporting(E_ALL | E_STRICT | E_DEPRECATED);
			return Dispatcher::run($path, $params);
		};      
		
		$report = $handlers[$this->format]($runner, $path);
		$stats  = $report->stats();                
		
		return $stats['success'];
	}

	protected function _library($path) 
	{
		foreach(Libraries::get() as $name => $library) 
		{
			if(strpos($path, $library['path']) !== 0)
				continue;
			$path = str_replace(array($library['path'], '.php'), null, $path); 
			
			return '\\' . $name . str_replace('/', '\\', $path);
		}
	}

	protected function _path($path)
	{
		$path = str_replace('\\', '/', $path);

		if(!$path) {
			$this->error('Please provide a path to tests.');
			return false;
		}
		if($path[0] != '/')
			$path = $this->request->env('working') . '/' . $path;    
			
		if(!$path = realpath($path)) {
			$this->error('Not a valid path.');
			return false;
		}

		if(!$libraryPath = $this->_library($path)) {
			$this->error("No library registered for path `{$path}`.");
			return false;
		}     
		
		return $libraryPath;
	}
}