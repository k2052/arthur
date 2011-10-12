<?php

namespace arthur\security;

use arthur\util\String;

class Password 
{
	const BF = 10;
	const XDES = 18;

	public static function hash($password, $salt = null) 
	{
		return crypt($password, $salt ?: static::salt());
	}
	public static function check($password, $hash) 
	{
		$password = crypt($password, $hash);
		$result   = true;

		if(($length = strlen($password)) != strlen($hash))
			return false;

		for($i = 0; $i < $length; $i++) {
			$result = $result && ($password[$i] === $hash[$i]);
		}  
		
		return $result;
	}

	public static function salt($type = null, $count = null) 
	{
		switch(true) 
		{
			case CRYPT_BLOWFISH == 1 && (!$type || $type === 'bf'):
				return static::_genSaltBf($count);
			case CRYPT_EXT_DES == 1 && (!$type || $type === 'xdes'):
				return static::_genSaltXDES($count);
			default:
				return static::_genSaltMD5();
		}
	}

	protected static function _genSaltBf($count = 10) 
	{
		$count = (integer) $count;
		$count = ($count < 4 || $count > 31) ? 10 : $count;

		$base64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$i      = 0;

		$input  = String::random(16);
		$output = '';

		do 
		{
			$c1 = ord($input[$i++]);
			$output .= $base64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if($i >= 16) {
				$output .= $base64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $base64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $base64[$c1];
			$output .= $base64[$c2 & 0x3f];
		} while (1);

		return '$2a$' . chr(ord('0') + $count / 10) . chr(ord('0') + $count % 10) . '$' . $output;
	}

	protected static function _genSaltXDES($count = 18) 
	{
		$count = (integer) $count;
		$count = ($count < 1 || $count > 24) ? 16 : $count;

		$count  = (1 << $count) - 1;
		$base64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		$output  = '_' . $base64[$count & 0x3f] . $base64[($count >> 6) & 0x3f];
		$output .= $base64[($count >> 12) & 0x3f] . $base64[($count >> 18) & 0x3f];
		$output .= String::random(3, array('encode' => String::ENCODE_BASE_64));

		return $output;
	}

	protected static function _genSaltMD5() 
	{
		return '$1$' . String::random(6, array('encode' => String::ENCODE_BASE_64));
	}
}