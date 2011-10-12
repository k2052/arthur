<?php

namespace arthur\core;

class NetworkException extends \RuntimeException 
{
	protected $code = 503;
}