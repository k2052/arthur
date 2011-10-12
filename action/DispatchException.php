<?php

namespace arthur\action;

class DispatchException extends \RuntimeException 
{
	protected $code = 404;
}