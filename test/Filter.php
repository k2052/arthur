<?php

namespace arthur\test;

abstract class Filter extends \arthur\core\StaticObject 
{
	public static function apply($report, $tests, array $options = array()) { }

	public static function analyze($report, array $options = array()) 
	{
		return $report->results['filters'][get_called_class()];
	}

	public static function output($format, $analysis) {}
}