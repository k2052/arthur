<?php

namespace arthur\console\command;

use arthur\util\String;
use arthur\core\Libraries;
use arthur\util\Inflector;
use arthur\core\ClassNotFoundException;

class Create extends \arthur\console\Command 
{
	public $library = null;
	public $template = null;
	protected $_library = array();

	protected function _init() 
	{
		parent::_init();
		$this->library = $this->library ?: true;
		$defaults = array('prefix' => null, 'path' => null);
		$this->_library = (array) Libraries::get($this->library) + $defaults;
	}

	public function run($command = null) 
	{
		if($command && !$this->request->args())
			return $this->_default($command);

		$this->request->shift();
		$this->template = $this->template ?: $command;

		if(!$command)
			return false;
		if($this->_execute($command))
			return true;
		
		$this->error("{$command} could not be created.");
		
		return false;
	}


	protected function _execute($command) 
	{
		try 
		{
			if(!$class = $this->_instance($command))
				return false;
		} 
		catch(ClassNotFoundException $e) {
			return false;
		}
		$data   = array();
		$params = $class->invokeMethod('_params');

		foreach($params as $i => $param) {
			$data[$param] = $class->invokeMethod("_{$param}", array($this->request));
		}

		if($message = $class->invokeMethod('_save', array($data))) {
			$this->out($message);
			return true;
		}   
		
		return false;
	}

	protected function _default($name) 
	{
		$commands = array(
			array('model', Inflector::pluralize($name)),
			array('controller', Inflector::pluralize($name)),
			array('test', 'model', Inflector::pluralize($name)),
			array('test', 'controller', Inflector::pluralize($name))
		);
		foreach($commands as $args) 
		{
			$command = $this->template = $this->request->params['command'] = array_shift($args);
			$this->request->params['action'] = array_shift($args);
			$this->request->params['args'] = $args;

			if(!$this->_execute($command))
				return false;
		}    
		
		return true;
	}

	protected function _namespace($request, $options  = array()) 
	{
		$name = $request->command;
		$defaults = array(
			'prefix'  => $this->_library['prefix'],
			'prepend' => null,
			'spaces' => array(
				'model'   => 'models', 'view' => 'views', 'controller' => 'controllers',
				'command' => 'extensions.command', 'adapter' => 'extensions.adapter',
				'helper'  => 'extensions.helper'
			)
		);
		$options += $defaults;

		if(isset($options['spaces'][$name]))
			$name = $options['spaces'][$name];
		
		return str_replace('.', '\\', $options['prefix'] . $options['prepend'] . $name);
	}

	protected function _params() 
	{
		$contents = $this->_template();

		if(empty($contents))
			return array();
		preg_match_all('/(?:\{:(?P<params>[^}]+)\})/', $contents, $keys);

		if(!empty($keys['params'])) 
			return array_values(array_unique($keys['params']));
		
		return array();
	}

	protected function _template() 
	{
		$file = Libraries::locate('command.create.template', $this->template, array(
			'filter' => false, 'type' => 'file', 'suffix' => '.txt.php'
		));
		if(!$file || is_array($file))
			return false;
		
		return file_get_contents($file);
	}

	protected function _instance($name, array $config = array()) 
	{
		if($class = Libraries::locate('command.create', Inflector::camelize($name))) 
		{
			$this->request->params['template'] = $this->template;

			return new $class(array(
				'request' => $this->request,
				'classes' => $this->_classes
			));
		}        
		
		return parent::_instance($name, $config);
	}

	protected function _save(array $params = array()) 
	{
		$defaults = array('namespace' => null, 'class' => null);
		$params  += $defaults;

		if(empty($params['class']) || empty($this->_library['path']))
			return false;  
			
		$contents = $this->_template();
		$result   = String::insert($contents, $params);

		$path      = str_replace('\\', '/', "{$params['namespace']}\\{$params['class']}");
		$path      = $this->_library['path'] . stristr($path, '/');
		$file      = str_replace('//', '/', "{$path}.php");
		$directory = dirname($file);

		if((!is_dir($directory)) && !mkdir($directory, 0755, true))
			return false;
		if(file_put_contents($file, "<?php\n\n{$result}\n\n?>"))
			return "{$params['class']} created in {$params['namespace']}.";
		
		return false;
	}
}