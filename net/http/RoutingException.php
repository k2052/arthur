<?php       

namespace arthur\net\http;

class RoutingException extends \RuntimeException 
{
	protected $code = 500;
}