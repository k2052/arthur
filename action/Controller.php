<?php

namespace arthur\action;

use arthur\util\Inflector;
use arthur\action\DispatchException;

class Controller extends \arthur\core\Object 
{    
  public $request = null;
	public $response = null; 
	
	protected $_render = array(
		'type'        => null,
		'data'        => array(),
		'auto'        => true,
		'layout'      => 'default',
		'template'    => null,
		'hasRendered' => false,
		'negotiate'   => false
	);
	
	protected $_classes = array(
		'media'    => 'arthur\net\http\Media',
		'router'   => 'arthur\net\http\Router',
		'response' => 'arthur\action\Response'
	);    
	
	protected $_autoConfig = array('render' => 'merge', 'classes' => 'merge');

 	public function __construct(array $config = array()) {
 		$defaults = array(
 			'request' => null, 'response' => array(), 'render' => array(), 'classes' => array()
 		);
 		parent::__construct($config + $defaults);
 	}

 	protected function _init() {
 		parent::_init();
 		$this->request = $this->request ?: $this->_config['request'];
 		$this->response = $this->_instance('response', $this->_config['response']);

 		if (!$this->request || $this->_render['type']) {
 			return;
 		}
 		if ($this->_render['negotiate']) {
 			$this->_render['type'] = $this->request->accepts();
 			return;
 		}
 		$this->_render['type'] = $this->request->type ?: 'html';
 	}

 	public function __invoke($request, $dispatchParams, array $options = array()) 
 	{
 		$render =& $this->_render;
 		$params = compact('request', 'dispatchParams', 'options');

 		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$render) 
 		{
 			$dispatchParams = $params['dispatchParams'];

 			$action = isset($dispatchParams['action']) ? $dispatchParams['action'] : 'index';
 			$args = isset($dispatchParams['args']) ? $dispatchParams['args'] : array();
 			$result = null;

 			if(substr($action, 0, 1) == '_' || method_exists(__CLASS__, $action))
 				throw new DispatchException('Attempted to invoke a private method.');
 			if(!method_exists($self, $action))
 				throw new DispatchException("Action `{$action}` not found.");  
 				
 			$render['template'] = $render['template'] ?: $action;

 			if($result = $self->invokeMethod($action, $args)) 
 			{
 				if(is_string($result)) {
 					$self->render(array('text' => $result));
 					return $self->response;
 				}
 				if(is_array($result)) 
 					$self->set($result);
 			}

 			if(!$render['hasRendered'] && $render['auto']) 
 				$self->render();
 			
 			return $self->response;
 		});
 	}

 	public function set($data = array()) 
 	{
 		$this->_render['data'] = (array) $data + $this->_render['data'];
 	}
 	public function render(array $options = array()) 
 	{
 		$media = $this->_classes['media'];
 		$class = get_class($this);
 		$name  = preg_replace('/Controller$/', '', substr($class, strrpos($class, '\\') + 1));
 		$key   = key($options);

 		if(isset($options['data'])) {
 			$this->set($options['data']);
 			unset($options['data']);
 		}
 		$defaults = array(
 			'status'     => null,
 			'location'   => false,
 			'data'       => null,
 			'head'       => false,
 			'controller' => Inflector::underscore($name)
 		);
 		$options += $this->_render + $defaults;

 		if($key && $media::type($key)) 
 		{
 			$options['type'] = $key;
 			$this->set($options[$key]);
 			unset($options[$key]);
 		}

 		$this->_render['hasRendered'] = true;
 		$this->response->type($options['type']);
 		$this->response->status($options['status']);
 		$this->response->headers('Location', $options['location']);

 		if($options['head']) 
 			return;        
 			
 		$data = $this->_render['data'];
 		$media::render($this->response, $data, $options + array('request' => $this->request));
 	}

 	public function redirect($url, array $options = array()) 
 	{
 		$router = $this->_classes['router'];
 		$defaults = array('location' => null, 'status' => 302, 'head' => true, 'exit' => false);
 		$options += $defaults;
 		$params = compact('url', 'options');

 		$this->_filter(__METHOD__, $params, function($self, $params) use ($router) 
 		{
 			$options = $params['options'];
 			$location = $options['location'] ?: $router::match($params['url'], $self->request);
 			$self->render(compact('location') + $options);
 		});

 		if($options['exit']) {
 			$this->response->render();
 			$this->_stop();
 		}
 		return $this->response;
 	}
}