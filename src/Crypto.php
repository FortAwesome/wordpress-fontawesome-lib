<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

use WP_Error;

class Crypto {

	const PREFERRED_ENCRYPTION_METHOD = 'aes-256-ctr';

	protected $encryption_method = null;
	protected $encryption_cipher_length = null;
	protected $encryption_key = null;
	protected $encryption_salt = null;

	/**
	 * Constructs a new Crypto object with the given encryption key and salt.
	 *
	 * @param array $params An associative array with the following keys:
	 *  - key: string. The encryption key.
	 *  - salt: string. The encryption salt.
	 */
	public function __construct( $params ) {
		if (
			is_array( $params ) &&
			isset( $params['key'] ) &&
			is_string( $params['key'] ) &&
			isset( $params['salt'] ) &&
			is_string( $params['salt'] )
		) {
			$this->encryption_key = $params['key'];
			$this->encryption_salt = $params['salt'];
		}
	}

	/**
	 * Checks if the current environment is compatible with the Crypto class.
	 *
	 * @return bool|WP_Error True if compatible, WP_Error otherwise.
	 */
	public function is_compatible(): bool|WP_Error {
		if (
			extension_loaded( 'openssl' ) &&
			function_exists( 'openssl_get_cipher_methods' ) &&
			function_exists( 'openssl_cipher_iv_length' ) &&
			function_exists( 'openssl_random_pseudo_bytes' ) &&
			function_exists( 'openssl_encrypt' )
		) {
			$methods = openssl_get_cipher_methods();

			if ( is_array( $methods ) && count( $methods ) > 0 ) {
				return true;
			}
		}

		return new WP_Error(
			'fontawesome_crypto_incompatible',
			__(
				'Crypto support requires the openssl PHP extension to be installed and enabled.',
				'wordpress-fontawesome-lib',
			),
		);
	}

	private function prepare(): bool|WP_Error {
		$compatibility = $this->is_compatible();

		if ( is_wp_error( $compatibility ) ) {
			return $compatibility;
		}

		if ( ! is_string( $this->encryption_key ) || $this->encryption_key === '' ) {
			return new WP_Error(
				'fontawesome_crypto_invalid_key',
				__(
					'Encryption key must be a non-empty string.',
					'wordpress-fontawesome-lib',
				),
			);
		}

		if ( ! is_string( $this->encryption_salt ) ) {
			return new WP_Error(
				'fontawesome_crypto_invalid_salt',
				__(
					'Encryption salt must be a string.',
					'wordpress-fontawesome-lib',
				),
			);
		}

		$method = null;

		$methods = openssl_get_cipher_methods();

		if ( array_search( self::PREFERRED_ENCRYPTION_METHOD, $methods, true ) ) {
			$method = self::PREFERRED_ENCRYPTION_METHOD;
		} elseif ( is_array( $methods ) && count( $methods ) > 0 ) {
			// Take the first available method as a fallback.
			$method = $methods[0];
		}

		if ( $method === null ) {
			return new WP_Error(
				'fontawesome_crypto_no_cipher_methods',
				__(
					'No OpenSSL cipher methods are available.',
					'wordpress-fontawesome-lib',
				),
			);
		}

		$this->encryption_method = $method;
		$this->encryption_cipher_length = openssl_cipher_iv_length( $method );

		return true;
	}

	/**
	 * Encrypts data.
	 *
	 * The method is patterned after the Data_Encryption::encrypt() method
	 * in the Site Kit by Google plugin, version 1.4.0, licensed under Apache v2.0.
	 * https://www.apache.org/licenses/LICENSE-2.0
	 */
	public function encrypt( $data ): string|WP_Error {
		$prepare_result = $this->prepare();

		if ( is_wp_error( $prepare_result ) ) {
			return $prepare_result;
		}

		$init_vec = openssl_random_pseudo_bytes(
			$this->encryption_cipher_length,
		);

		$raw = openssl_encrypt(
			$data . $this->encryption_salt,
			$this->encryption_method,
			$this->encryption_key,
			0,
			$init_vec,
		);

		if ( $raw === false ) {
			return new WP_Error(
				'fontawesome_crypto_encryption_failed',
				__( 'Data encryption failed.', 'wordpress-fontawesome-lib' ),
			);
		}

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $init_vec . $raw );
	}

	/**
	 * Decrypts and returns data.
	 *
	 * This method is patterned after the Data_Encryption::decrypt() method
	 * in the Site Kit by Google plugin, version 1.4.0, licensed under Apache v2.0.
	 * https://www.apache.org/licenses/LICENSE-2.0
	 *
	 * @param string $data base64 encoded data to decrypt.
	 * @return string|WP_Error Decrypted string on success, WP_Error on failure.
	 */
	public function decrypt( $data ): string|WP_Error {
		$prepare_result = $this->prepare();

		if ( is_wp_error( $prepare_result ) ) {
			return $prepare_result;
		}

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( $data, true );

		if ( $raw === false ) {
			return new WP_Error(
				'fontawesome_crypto_decryption_failed',
				__(
					'Data decryption failed when base64 decoding input.',
					'wordpress-fontawesome-lib',
				),
			);
		}

		$init_vec = substr( $raw, 0, $this->encryption_cipher_length );

		$raw = substr( $raw, $this->encryption_cipher_length );

		$result = openssl_decrypt(
			$raw,
			$this->encryption_method,
			$this->encryption_key,
			0,
			$init_vec,
		);

		if (
			! $result ||
			substr( $result, -strlen( $this->encryption_salt ) ) !==
				$this->encryption_salt
		) {
			return new WP_Error(
				'fontawesome_crypto_decryption_failed',
				__( 'Data decryption failed.', 'wordpress-fontawesome-lib' ),
			);
		}

		return substr( $result, 0, -strlen( $this->encryption_salt ) );
	}
}
