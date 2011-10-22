<?php

namespace arthur\test;

use arthur\util\Set;
use arthur\core\Libraries;
use arthur\core\Environment;

class Dispatcher extends \arthur\core\StaticObject 
{
	protected static $_classes = array(
		'group'  => 'arthur\test\Group',
		'report' => 'arthur\test\Report'
	);

	public static function run($group = null, array $options = array()) 
	{
		$defaults = array(
			'title'    => $group,
			'filters'  => array(),
			'reporter' => 'text'
		);
		$options += $defaults;
		$isCase = is_string($group) && preg_match('/Test$/', $group);
		$items = ($isCase) ? array(new $group()) : (array) $group;

		$options['filters'] = Set::normalize($options['filters']);
		$group = static::_group($items);
		$report = static::_report($group, $options);

		return static::_filter(__FUNCTION__, compact('report'), function($self, $params, $chain) {
			$environment = Environment::get();
			Environment::set('test');

			$params['report']->run();

			Environment::set($environment);
			return $params['report'];
		});
	}

	protected static function _group($data) 
	{
		$group = Libraries::locate('test', static::$_classes['group']);
		$class = static::_instance($group, compact('data'));
		return $class;
	}

	protected static function _report($group, $options) 
	{
		$report = Libraries::locate('test', static::$_classes['report']);
		$class  = static::_instance($report, compact('group') + $options);
		return $class;
	}
}