<?php

namespace arthur\net\http;

class MediaException extends \RuntimeException 
{
	protected $code = 415;
}