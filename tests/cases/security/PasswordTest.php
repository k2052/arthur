<?php

namespace arthur\tests\cases\security;

use arthur\security\Password;

class PasswordTest extends \arthur\test\Unit 
{
	public function testPassword() 
	{
		$pass = 'Arthur rocks!';

		$bfSalt = "{^\\$2a\\$06\\$[0-9A-Za-z./]{22}$}";
		$bfHash = "{^\\$2a\\$06\\$[0-9A-Za-z./]{53}$}";

		$xdesSalt = "{^_zD..[0-9A-Za-z./]{4}$}";
		$xdesHash = "{^_zD..[0-9A-Za-z./]{15}$}";

		$md5Salt = "{^\\$1\\$[0-9A-Za-z./]{8}$}";
		$md5Hash = "{^\\$1\\$[0-9A-Za-z./]{8}\\$[0-9A-Za-z./]{22}$}";

		foreach(array('bf' => 6, 'xdes' => 10, 'md5' => null) as $method => $log2) 
		{
			$salts      = array();
			$hashes     = array();
			$count      = 20;
			$saltPattern = ${$method . 'Salt'};
			$hashPattern = ${$method . 'Hash'};

			for($i = 0; $i < $count; $i++) 
			{
				$salt = Password::salt($method, $log2);
				$this->assertPattern($saltPattern, $salt);
				$this->assertFalse(in_array($salt, $salts));
				$salts[] = $salt;

				$hash = Password::hash($pass, $salt);
				$this->assertPattern($hashPattern, $hash);
				$this->assertEqual(substr($hash, 0, strlen($salt)), $salt);
				$this->assertFalse(in_array($hash, $hashes));
				$hashes[] = $hash;

				$this->assertTrue(Password::check($pass, $hash), "{$method} failed");
			}
		}
	}
	
	public function testPasswordMaxLength() 
	{
		foreach(array('bf' => 72) as $method => $length) 
		{
			$salt = Password::salt($method);
			$pass = str_repeat('a', $length);

			$expected = Password::hash($pass, $salt);
			$result   = Password::hash($pass . 'a', $salt);
			$this->assertIdentical($expected, $result);
		}
	}
}