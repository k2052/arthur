<?php

namespace arthur\storage\session\strategy;

class MissingSignatureException extends \RuntimeException 
{
  protected $code = 403;
}