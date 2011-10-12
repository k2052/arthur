<?php

namespace arthur\template\helper;

use arthur\util\Set;
use arthur\util\Inflector;

class Form extends \lithium\template\Helper 
{
	protected $_strings = array(
		'button'         => '<button{:options}>{:name}</button>',
		'checkbox'       => '<input type="checkbox" name="{:name}"{:options} />',
		'checkbox-multi' => '<input type="checkbox" name="{:name}[]"{:options} />',
		'checkbox-multi-group' => '{:raw}',
		'error'          => '<div{:options}>{:content}</div>',
		'errors'         => '{:raw}',
		'input'          => '<input type="{:type}" name="{:name}"{:options} />',
		'file'           => '<input type="file" name="{:name}"{:options} />',
		'form'           => '<form action="{:url}"{:options}>{:append}',
		'form-end'       => '</form>',
		'hidden'         => '<input type="hidden" name="{:name}"{:options} />',
		'field'          => '<div{:wrap}>{:label}{:input}{:error}</div>',
		'field-checkbox' => '<div{:wrap}>{:input}{:label}{:error}</div>',
		'field-radio'    => '<div{:wrap}>{:input}{:label}{:error}</div>',
		'label'          => '<label for="{:id}"{:options}>{:title}</label>',
		'legend'         => '<legend>{:content}</legend>',
		'option-group'   => '<optgroup label="{:label}"{:options}>{:raw}</optgroup>',
		'password'       => '<input type="password" name="{:name}"{:options} />',
		'radio'          => '<input type="radio" name="{:name}" {:options} />',
		'select'         => '<select name="{:name}"{:options}>{:raw}</select>',
		'select-empty'   => '<option value=""{:options}>&nbsp;</option>',
		'select-multi'   => '<select name="{:name}[]"{:options}>{:raw}</select>',
		'select-option'  => '<option value="{:value}"{:options}>{:title}</option>',
		'submit'         => '<input type="submit" value="{:title}"{:options} />',
		'submit-image'   => '<input type="image" src="{:url}"{:options} />',
		'text'           => '<input type="text" name="{:name}"{:options} />',
		'textarea'       => '<textarea name="{:name}"{:options}>{:value}</textarea>',
		'fieldset'       => '<fieldset{:options}><legend>{:content}</legend>{:raw}</fieldset>'
	);

	protected $_templateMap = array(
		'create' => 'form',
		'end'   => 'form-end'
	);

	protected $_binding = null;
	protected $_bindingOptions = array();

	public function __construct(array $config = array()) 
	{
		$self =& $this;

		$defaults = array(
			'base'       => array(),
			'text'       => array(),
			'textarea'   => array(),
			'select'     => array('multiple' => false),
			'attributes' => array(
				'id' => function($method, $name, $options) use (&$self) 
				{
					if(in_array($method, array('create', 'end', 'label', 'error')))
						return;
					if(!$name || ($method == 'hidden' && $name == '_method')) 
						return;

					$id    = Inflector::camelize(Inflector::slug($name));
					$model = ($binding = $self->binding()) ? $binding->model() : null;   
					
					return $model ? basename(str_replace('\\', '/', $model)) . $id : $id;
				},
				'name' => function($method, $name, $options) 
				{
					if(!strpos($name, '.')) return $name;

					$name  = explode('.', $name);
					$first = array_shift($name);    
					
					return $first . '[' . join('][', $name) . ']';
				}
			)
		);      
		
		parent::__construct(Set::merge($defaults, $config));
	}

	protected function _init() 
	{
		parent::_init();

		if($this->_context)
			$this->_context->handlers(array('wrap' => '_attributes'));
	}

	public function config(array $config = array()) 
	{
		if(!$config) 
		{
			$keys = array('base' => '', 'text' => '', 'textarea' => '', 'attributes' => '');
			return array('templates' => $this->_templateMap) + array_intersect_key(
				$this->_config, $keys
			);
		}
		if(isset($config['templates'])) {
			$this->_templateMap = $config['templates'] + $this->_templateMap;
			unset($config['templates']);
		}      
		
		return ($this->_config = Set::merge($this->_config, $config)) + array(
			'templates' => $this->_templateMap
		);
	}

	public function create($binding = null, array $options = array()) 
	{
		$request = $this->_context ? $this->_context->request() : null;

		$defaults = array(
			'url'    => $request ? $request->params : array(),
			'type'   => null,
			'action' => null,
			'method' => $binding ? ($binding->exists() ? 'put' : 'post') : 'post'
		);

		list(, $options, $tpl) = $this->_defaults(__FUNCTION__, null, $options);
		list($scope, $options) = $this->_options($defaults, $options);

		$_binding =& $this->_binding;
		$_options =& $this->_bindingOptions;    
		
		$params = compact('scope', 'options', 'binding');
		$extra  = array('method' => __METHOD__) + compact('tpl', 'defaults');

		$filter = function($self, $params) use ($extra, &$_binding, &$_options) 
		{
			$scope    = $params['scope'];
			$options  = $params['options'];
			$_binding = $params['binding'];
			$append   = null;  
			
			$scope['method'] = strtolower($scope['method']);

			if($scope['type'] == 'file') 
			{
				if($scope['method'] == 'get')
					$scope['method'] = 'post'; 
					
				$options['enctype'] = 'multipart/form-data';
			}

			if(!($scope['method'] == 'get' || $scope['method'] == 'post')) {
				$append = $self->hidden('_method', array('value' => strtoupper($scope['method'])));
				$scope['method'] = 'post';
			}

			$url               = $scope['action'] ? array('action' => $scope['action']) : $scope['url'];
			$options['method'] = strtolower($scope['method']);
			$args              = array($extra['method'], $extra['tpl'], compact('url', 'options', 'append'));
			$_options          = $scope + $options;

			return $self->invokeMethod('_render', $args);
		};      
		
		return $this->_filter(__METHOD__, $params, $filter);
	}

	public function end() 
	{
		list(, $options, $template) = $this->_defaults(__FUNCTION__, null, array());
		$params = compact('options', 'template');
		$_binding =& $this->_binding;
		$_context =& $this->_context;
		$_options =& $this->_bindingOptions;

		$filter = function($self, $params) use (&$_binding, &$_context, &$_options, $template) 
		{
			unset($_binding);
			$_options = array();
			
			return $self->invokeMethod('_render', array('end', $params['template'], array()));
		};     
		
		$result = $this->_filter(__METHOD__, $params, $filter);
		unset($this->_binding);
		$this->_binding = null;   
		
		return $result;
	}

	public function binding() 
	{
		return $this->_binding;
	}

	public function __call($type, array $params = array()) 
	{
		$params += array(null, array());
		list($name, $options) = $params;
		list($name, $options, $template) = $this->_defaults($type, $name, $options);
		$template = $this->_context->strings($template) ? $template : 'input';  
		
		return $this->_render($type, $template, compact('type', 'name', 'options', 'value'));
	}

	public function field($name, array $options = array()) 
	{
		if(is_array($name)) 
			return $this->_fields($name, $options); 
			
		$defaults = array(
			'label'    => null,
			'type'     => isset($options['list']) ? 'select' : 'text',
			'template' => 'field',
			'wrap'     => array(),
			'list'     => null
		);
		$type = isset($options['type']) ? $options['type'] : $defaults['type'];

		if($this->_context->strings('field-' . $type))
			$options['template'] = 'field-' . $type;
		list(, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		list($options, $field) = $this->_options($defaults, $options);

		if($options['template'] != $defaults['template'])
			$template = $options['template'];

		$wrap  = $options['wrap'];
		$type  = $options['type'];
		$list  = $options['list'];
		$label = $input = null;

		if(($options['label'] === null || $options['label']) && $options['type'] != 'hidden') 
		{
			if(!$options['label'])
				$options['label'] = Inflector::humanize(preg_replace('/[\[\]\.]/', '_', $name));

			$label = $this->label(isset($options['id']) ? $options['id'] : '', $options['label']);
		}

		$call  = ($type == 'select') ? array($name, $list, $field) : array($name, $field);
		$input = call_user_func_array(array($this, $type), $call);
		$error = ($this->_binding) ? $this->error($name) : null;           
		
		return $this->_render(__METHOD__, $template, compact('wrap', 'label', 'input', 'error'));
	}

	protected function _fields(array $fields, array $options = array()) 
	{
		$result = array();

		foreach($fields as $field => $label) 
		{
			if(is_numeric($field)) {
				$field = $label;
				unset($label);
			}    
			
			$result[] = $this->field($field, compact('label') + $options);
		}        
		
		return join("\n", $result);
	}

	public function submit($title = null, array $options = array()) 
	{
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, null, $options);    
		
		return $this->_render(__METHOD__, $template, compact('title', 'options'));
	}

	public function textarea($name, array $options = array()) 
	{
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		list($scope, $options) = $this->_options(array('value' => null), $options);
		$value = isset($scope['value']) ? $scope['value'] : '';     
		
		return $this->_render(__METHOD__, $template, compact('name', 'options', 'value'));
	}

	public function text($name, array $options = array()) 
	{
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);  
		
		return $this->_render(__METHOD__, $template, compact('name', 'options'));
	}

 
	public function select($name, $list = array(), array $options = array()) 
	{
		$defaults = array('empty' => false, 'value' => null);
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		list($scope, $options) = $this->_options($defaults, $options);

		if($scope['empty']) 
			$list = array('' => ($scope['empty'] === true) ? '' : $scope['empty']) + $list;
		if($template == __FUNCTION__ && $scope['multiple']) 
			$template = 'select-multi';            
			
		$raw = $this->_selectOptions($list, $scope);  
		
		return $this->_render(__METHOD__, $template, compact('name', 'options', 'raw'));
	}

	protected function _selectOptions(array $list, array $scope) 
	{
		$result = "";

		foreach($list as $value => $title) 
		{
			if(is_array($title)) 
			{
				$label   = $value;
				$options = array();

				$raw     = $this->_selectOptions($title, $scope);
				$params  = compact('label', 'options', 'raw');
				$result .= $this->_render('select', 'option-group', $params);
				continue;
			}     
			
			$selected = (
				(is_array($scope['value']) && in_array($value, $scope['value'])) ||
				($scope['value'] == $value)
			);    
			
			$options = $selected ? array('selected' => true) : array();
			$params  = compact('value', 'title', 'options');
			$result .= $this->_render('select', 'select-option',  $params);
		}  
		
		return $result;
	}

	public function checkbox($name, array $options = array()) 
	{
		$defaults = array('value' => '1', 'hidden' => true);
		$options += $defaults;
		$default  = $options['value'];
		$out      = '';

		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		list($scope, $options) = $this->_options($defaults, $options);

		if(!isset($options['checked'])) {
			if($this->_binding && $bound = $this->_binding->data($name))
				$options['checked'] = ($bound == $default);
		}
		if($scope['hidden']) 
			$out = $this->hidden($name, array('value' => '', 'id' => false));
		$options['value'] = $scope['value'];     
		
		return $out . $this->_render(__METHOD__, $template, compact('name', 'options'));
	}

	public function password($name, array $options = array()) 
	{
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		unset($options['value']);        
		
		return $this->_render(__METHOD__, $template, compact('name', 'options'));
	}

	public function hidden($name, array $options = array()) 
	{
		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);    
		
		return $this->_render(__METHOD__, $template, compact('name', 'options'));
	}
	
	public function label($id, $title = null, array $options = array()) 
	{
		$defaults = array('escape' => true);

		if(is_array($title))
			list($title, $options) = each($title);
		$title = $title ?: Inflector::humanize(str_replace('.', '_', $id));

		list($name, $options, $template) = $this->_defaults(__FUNCTION__, $id, $options);
		list($scope, $options) = $this->_options($defaults, $options);

		if(strpos($id, '.')) {
			$generator = $this->_config['attributes']['id'];
			$id = $generator(__METHOD__, $id, $options);
		}    
		
		return $this->_render(__METHOD__, $template, compact('id', 'title', 'options'), $scope);
	}

	public function error($name, $key = null, array $options = array()) 
	{
		$defaults = array('class' => 'error');
		list(, $options, $template) = $this->_defaults(__FUNCTION__, $name, $options);
		$options += $defaults;

		$_binding =& $this->_binding;
		$params   = compact('name', 'key', 'options', 'template');

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$_binding) 
		{
			$options  = $params['options'];
			$template = $params['template'];

			if(isset($options['value']))
				unset($options['value']);
			if(!$_binding || !$content = $_binding->errors($params['name']))
				return null;
			$result = '';

			if(!is_array($content)) {
				$args = array(__METHOD__, $template, compact('content', 'options'));
				return $self->invokeMethod('_render', $args);
			}
			$errors = $content;

			if($params['key'] === null) 
			{
				foreach ($errors as $content) {
					$args = array(__METHOD__, $template, compact('content', 'options'));
					$result .= $self->invokeMethod('_render', $args);
				}    
				
				return $result;
			}

			$key = $params['key'];
			$content = !isset($errors[$key]) || $key === true ? reset($errors) : $errors[$key];
			$args = array(__METHOD__, $template, compact('content', 'options'));  
			
			return $self->invokeMethod('_render', $args);
		});
	}

	protected function _defaults($method, $name, $options) 
	{
		$methodConfig = isset($this->_config[$method]) ? $this->_config[$method] : array();
		$options     += $methodConfig + $this->_config['base'];
		$options      = $this->_generators($method, $name, $options);
             
		$hasValue = (
			(!isset($options['value']) || $options['value'] === null) &&
			$name && $this->_binding && $value = $this->_binding->data($name)
		);
		if($hasValue) 
			$options['value'] = $value;
		if(isset($options['default']) && empty($options['value']))
			$options['value'] = $options['default'];
		unset($options['default']);

		$generator = $this->_config['attributes']['name'];
		$name      = $generator($method, $name, $options);

		$tplKey   = isset($options['template']) ? $options['template'] : $method;
		$template = isset($this->_templateMap[$tplKey]) ? $this->_templateMap[$tplKey] : $tplKey;        
		
		return array($name, $options, $template);
	}

	protected function _generators($method, $name, $options) 
	{
		foreach($this->_config['attributes'] as $key => $generator) 
		{
			if($key == 'name')
				continue;
			if($generator && !isset($options[$key])) 
			{
				if(($attr = $generator($method, $name, $options)) !== null)
					$options[$key] = $attr;
				continue;
			}
			if($generator && $options[$key] === false)
				unset($options[$key]);
		}             
		
		return $options;
	}
}