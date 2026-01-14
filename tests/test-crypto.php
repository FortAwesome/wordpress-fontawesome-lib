<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Crypto;

class CryptoTest extends TestCase
{
    const VALID_LOGGED_IN_SALT = "d320f8c1f4308257176d6a213700bda0491d74f0";
    const VALID_LOGGED_IN_KEY = "e6e84e3a168c2131b7bcd01c8e710e5fc4b0214c";
    const TEST_INPUT = "test string";
    const VALID_ENCRYPTED_OUTPUT = "l6P+dueq0VqdTXSPnG7VZ0FOOW5XZ2xUSFAySUpHdGIzeEMvNStMaXNrYi9tS3pHMExwWk41TDNmMWxpdWFwblRtOGg2TnR1b20xQmdWZm5pMjlv";

    public function test_constructor()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);
        $this->assertTrue($obj instanceof Crypto);
    }

    public function test_encrypt()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);
        $encrypted = $obj->encrypt(self::TEST_INPUT);

        $this->assertIsString($encrypted);
    }

    public function test_encrypt_with_invalid_key()
    {
        $obj = new Crypto([
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);

        $encrypted = $obj->encrypt("test string");

        $this->assertTrue(is_wp_error($encrypted));
    }

    public function test_encrypt_with_invalid_salt()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
        ]);

        $encrypted = $obj->encrypt("test string");

        $this->assertTrue(is_wp_error($encrypted));
    }

    public function test_decrypt()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);
        $decrypted = $obj->decrypt(self::VALID_ENCRYPTED_OUTPUT);

        $this->assertIsString($decrypted);
        $this->assertEquals(self::TEST_INPUT, $decrypted);
    }

    public function test_decrypt_with_invalid_key()
    {
        $obj = new Crypto([
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);

        $decrypted = $obj->decrypt(self::VALID_ENCRYPTED_OUTPUT);

        $this->assertTrue(is_wp_error($decrypted));
    }

    public function test_decrypt_with_invalid_salt()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
        ]);

        $decrypted = $obj->decrypt(self::VALID_ENCRYPTED_OUTPUT);

        $this->assertTrue(is_wp_error($decrypted));
    }

    public function test_decrypt_with_invalid_encrypted_string()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);

        $decrypted = $obj->decrypt("invalid_encrypted_string");

        $this->assertTrue(is_wp_error($decrypted));
    }

    public function test_encrypt_decrypt_cycle()
    {
        $obj = new Crypto([
            "key" => self::VALID_LOGGED_IN_KEY,
            "salt" => self::VALID_LOGGED_IN_SALT,
        ]);

        $original_string = "another test string";
        $encrypted = $obj->encrypt($original_string);
        $this->assertIsString($encrypted);

        $decrypted = $obj->decrypt($encrypted);
        $this->assertIsString($decrypted);
        $this->assertEquals($original_string, $decrypted);
    }
}
