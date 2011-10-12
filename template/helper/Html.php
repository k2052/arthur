<?php

namespace arthur\template\helper;


class Html extends \arthur\template\Helper 
{
	protected $_strings = array(
		'block'            => '<div{:options}>{:content}</div>',
		'block-end'        => '</div>',
		'block-start'      => '<div{:options}>',
		'charset'          => '<meta charset="{:encoding}" />',
		'image'            => '<img src="{:path}"{:options} />',
		'js-block'         => '<script type="text/javascript"{:options}>{:content}</script>',
		'js-end'           => '</script>',
		'js-start'         => '<script type="text/javascript"{:options}>',
		'link'             => '<a href="{:url}"{:options}>{:title}</a>',
		'list'             => '<ul{:options}>{:content}</ul>',
		'list-item'        => '<li{:options}>{:content}</li>',
		'meta'             => '<meta{:options}/>',
		'meta-link'        => '<link href="{:url}"{:options} />',
		'para'             => '<p{:options}>{:content}</p>',
		'para-start'       => '<p{:options}>',
		'script'           => '<script type="text/javascript" src="{:path}"{:options}></script>',
		'style'            => '<style type="text/css"{:options}>{:content}</style>',
		'style-import'     => '<style type="text/css"{:options}>@import url({:url});</style>',
		'style-link'       => '<link rel="{:type}" type="text/css" href="{:path}"{:options} />',
		'table-header'     => '<th{:options}>{:content}</th>',
		'table-header-row' => '<tr{:options}>{:content}</tr>',
		'table-cell'       => '<td{:options}>{:content}</td>',
		'table-row'        => '<tr{:options}>{:content}</tr>',
		'tag'              => '<{:name}{:options}>{:content}</{:name}>',
		'tag-end'          => '</{:name}>',
		'tag-start'        => '<{:name}{:options}>'
	);
	protected $_metaLinks = array(
		'atom' => array('type' => 'application/atom+xml', 'rel' => 'alternate'),
		'rss'  => array('type' => 'application/rss+xml', 'rel' => 'alternate'),
		'icon' => array('type' => 'image/x-icon', 'rel' => 'icon')
	);

	protected $_metaList = array();

	public $contentMap = array(
		'script'    => 'js',
		'style'     => 'css',
		'image'     => 'image',
		'_metaLink' => 'generic'
	);

	public function charset($encoding = null) 
	{
		$encoding = $encoding ?: $this->_context->response()->encoding;      
		
		return $this->_render(__METHOD__, 'charset', compact('encoding'));
	}

	public function link($title, $url = null, array $options = array()) 
	{
		$defaults = array('escape' => true, 'type' => null);
		list($scope, $options) = $this->_options($defaults, $options);

		if(isset($scope['type']) && $type = $scope['type']) {
			$options += compact('title');
			return $this->_metaLink($type, $url, $options);
		}

		$url = is_null($url) ? $title : $url;     
		
		return $this->_render(__METHOD__, 'link', compact('title', 'url', 'options'), $scope);
	}

	public function script($path, array $options = array()) 
	{
		$defaults = array('inline' => true);
		list($scope, $options) = $this->_options($defaults, $options);

		if(is_array($path)) 
		{
			foreach($path as $i => $item) {
				$path[$i] = $this->script($item, $scope);
			}    
			
			return ($scope['inline']) ? join("\n\t", $path) . "\n" : null;
		}    
		
		$m      = __METHOD__;
		$params = compact('path', 'options');

		$script = $this->_filter(__METHOD__, $params, function($self, $params, $chain) use ($m) 
		{
			return $self->invokeMethod('_render', array($m, 'script', $params));
		}); 
		
		if($scope['inline'])
			return $script;
		if($this->_context)
			$this->_context->scripts($script);
	}

	public function style($path, array $options = array()) 
	{
		$defaults = array('type' => 'stylesheet', 'inline' => true);
		list($scope, $options) = $this->_options($defaults, $options);

		if(is_array($path)) 
		{
			foreach($path as $i => $item) {
				$path[$i] = $this->style($item, $scope);
			}   
			
			return ($scope['inline']) ? join("\n\t", $path) . "\n" : null;
		}           
		
		$method = __METHOD__;
		$type   = $scope['type'];
		$params = compact('type', 'path', 'options');     
		
		$filter = function($self, $params, $chain) use ($defaults, $method) 
		{
			$template = ($params['type'] == 'import') ? 'style-import' : 'style-link';
			return $self->invokeMethod('_render', array($method, $template, $params));
		};
		$style = $this->_filter($method, $params, $filter);

		if($scope['inline'])
			return $style;
		if($this->_context)
			$this->_context->styles($style);
	}

	public function head($tag, array $options) 
	{
		if(!isset($this->_strings[$tag])) 
			return null;   
			
		$method = __METHOD__;             
		
		$filter = function($self, $options, $chain) use ($method, $tag) 
		{
			return $self->invokeMethod('_render', array($method, $tag, $options));
		};
		$head = $this->_filter($method, $options, $filter);       
		
		if($this->_context)
			$this->_context->head($head);

		return $head;
	}

 	public function image($path, array $options = array()) 
 	{
		$defaults = array('alt' => '');
		$options += $defaults;
		$path     = is_array($path) ? $this->_context->url($path) : $path;
		$params   = compact('path', 'options');
		$method   = __METHOD__;
              
		return $this->_filter($method, $params, function($self, $params, $chain) use ($method) 
		{
			return $self->invokeMethod('_render', array($method, 'image', $params));
		});
	}

	protected function _metaLink($type, $url = null, array $options = array()) 
	{
		$options += isset($this->_metaLinks[$type]) ? $this->_metaLinks[$type] : array();

		if($type == 'icon') 
		{
			$url = $url ?: 'favicon.ico';  
			
			$standard = $this->_render(__METHOD__, 'meta-link', compact('url', 'options'), array(
				'handlers' => array('url' => 'path')
			));    
			
			$options['rel'] = 'shortcut icon';   
			
			$ieFix = $this->_render(__METHOD__, 'meta-link', compact('url', 'options'), array(
				'handlers' => array('url' => 'path')
			)); 
			
			return "{$standard}\n\t{$ieFix}";
		}      
		
		return $this->_render(__METHOD__, 'meta-link', compact('url', 'options'), array(
			'handlers' => array()
		));
	}
}