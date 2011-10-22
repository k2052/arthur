<?php

namespace arthur\test;

use arthur\test\Dispatcher;
use arthur\core\Libraries;
use arthur\test\Group;

class Controller extends \arthur\core\Object 
{
	public function __invoke($request, $dispatchParams, array $options = array()) 
	{
		$dispatchParamsDefaults = array('args' => array());
		$dispatchParams        += $dispatchParamsDefaults;      
		
		$defaults = array('reporter' => 'html', 'format' => 'html', 'timeout' => 0);
		$options += (array) $request->query + $defaults;
		$params   = compact('request', 'dispatchParams', 'options');

		return $this->_filter(__METHOD__, $params, function($self, $params) {
			$request = $params['request'];
			$options = $params['options'];
			$params  = $params['dispatchParams'];
			set_time_limit((integer) $options['timeout']);
			$group  = join('\\', (array) $params['args']);

			if($group === "all") {
				$group = Group::all();
				$options['title'] = 'All Tests';
			}   
			
			$report  = Dispatcher::run($group, $options);
			$filters = Libraries::locate('test.filter');
			$menu    = Libraries::locate('tests', null, array(
				'filter'  => '/cases|integration|functional/',
				'exclude' => '/mocks/'
			));
			sort($menu);

			$result = compact('request', 'report', 'filters', 'menu');
			return $report->render('layout', $result);
		});
	}
}