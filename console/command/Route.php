<?php

namespace arthur\console\command;

use arthur\net\http\Router;
use arthur\action\Request;
use arthur\core\Environment;

class Route extends \arthur\console\Command 
{
	public $env = 'development';

	public function __construct($config = array()) 
	{
		$defaults = array('routes_file' => LITHIUM_APP_PATH . '/config/routes.php');
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();
		Environment::set($this->env);

		if(file_exists($this->_config['routes_file']))
			return require $this->_config['routes_file'];
		
		$this->error("The routes file for this library doesn't exist or can't be found.");
	}

	public function run() 
	{
		$this->all();
	}

	public function all() 
	{
		$routes  = Router::get();
		$columns = array(array('Template', 'Params'), array('--------', '------'));

		foreach($routes As $route) {
			$info = $route->export();
			$columns[] = array($info['template'], json_encode($info['params']));
		}     
		
		$this->columns($columns);
	}

	public function show() 
	{
		$url    = join(" ", $this->request->params['args']);
		$method = 'GET';

		if(!$url)
			$this->error('Please provide a valid URL');

		if(preg_match('/^(GET|POST|PUT|DELETE|HEAD|OPTIONS) (.+)/i', $url, $matches)) {
			$method = strtoupper($matches[1]);
			$url = $matches[2];
		}

		$request = new Request(compact('url') + array('env' => array('REQUEST_METHOD' => $method)));
		$result  = Router::process($request);
		$this->out($result->params ? json_encode($result->params) : "No route found.");
	}
}