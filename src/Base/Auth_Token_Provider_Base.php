<?php
declare(strict_types=1);

namespace FontAwesomeLib\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

use WP_Error;

class Auth_Token_Provider_Base {

	protected $api_token = null;

	protected $access_token = null;

	protected $access_token_expiration_time_unix = null;

	protected $api_base_url = Query_Resolver_Base::DEFAULT_API_BASE_URL;

	/**
	 * Construct a new Auth_Token_Provider_Base object, using the given Font Awesome API token.
	 * A valid API token is required to obtain access tokens.
	 *
	 * @param string $api_token
	 * @param array  $opts Optional settings:
	 *    - api_base_url: string. The base URL for the Font Awesome API.
	 */
	public function __construct( $api_token, $opts = [] ) {
		if ( ! is_string( $api_token ) || '' === $api_token ) {
			return;
		}

		if (
			isset( $opts['api_base_url'] ) &&
			is_string( $opts['api_base_url'] ) &&
			'' !== $opts['api_base_url']
		) {
			$this->api_base_url = untrailingslashit( $opts['api_base_url'] );
		}

		$this->api_token = $api_token;
	}

	/**
	 * @return string|WP_Error
	 */
	public function get_api_token(): string|WP_Error {
		if ( ! is_string( $this->api_token ) || '' === $this->api_token ) {
			return new WP_Error(
				'fontawesome_invalid_api_token',
				__(
					'Font Awesome Lib: API token is invalid or missing.',
					'wordpress-fontawesome-lib'
				)
			);
		}

		return $this->api_token;
	}

	/**
	 * Get a current valid access token, refreshing it if necessary.
	 *
	 * @return string|WP_Error a current valid access token or WP_Error on failure.
	 */
	public function get_access_token(): string|WP_Error {
		$api_token = $this->get_api_token();

		if ( is_wp_error( $api_token ) ) {
			return $api_token;
		}

		$exp = $this->get_access_token_expiration_time_unix();

		if ( is_string( $this->access_token ) && $exp > time() - 5 ) {
			return $this->access_token;
		} else {
			// refresh the access token.
			$access_token = $this->request_access_token();

			if ( is_wp_error( $access_token ) ) {
				return $access_token;
			}

			$this->access_token = $access_token;

			return $this->access_token;
		}
	}

	/**
	 * Get the expiration time of the current access token as a Unix timestamp.
	 *
	 * @return int|null The expiration time as a Unix timestamp, or null if no access token is available.
	 */
	public function get_access_token_expiration_time_unix(): ?int {
		return $this->access_token_expiration_time_unix;
	}

	/**
	 * Uses this object's API token to request a new access token from the API token endpoint.
	 * This both updates this object's access token and its access token expiration time, and
	 * returns the new access token.
	 *
	 * @return string|WP_Error The new access token or WP_Error on failure.
	 */
	public function request_access_token(): string|WP_Error {
		if ( ! is_string( $this->api_token ) ) {
			return new WP_Error(
				'fontawesome_missing_api_token',
				__(
					'Font Awesome Lib: missing API token when requesting access token.',
					'wordpress-fontawesome-lib'
				)
			);
		}

		$response = $this->post([
			'body'   => '',
			'headers' => [
				'authorization' => 'Bearer ' . $this->api_token,
			],
		]);

		if ( \is_wp_error( $response ) ) {
			return new WP_Error(
				'fontawesome_api_token_endpoint_error',
				__(
					'Font Awesome Lib: error response from API token endpoint.',
					'wordpress-fontawesome-lib'
				),
				$response
			);
		}

		if ( 200 !== $response['response']['code'] ) {
			return new WP_Error(
				'fontawesome_api_token_endpoint_http_error',
				__(
					'Font Awesome Lib: unexpected HTTP status from API token endpoint.',
					'wordpress-fontawesome-lib'
				),
				$response
			);
		}

		$body = json_decode( $response['body'], true );

		if (
			! isset( $body['access_token'] ) ||
			! is_string( $body['access_token'] ) ||
			! isset( $body['expires_in'] ) ||
			! is_int( $body['expires_in'] )
		) {
			return new WP_Error(
				'fontawesome_api_token_endpoint_invalid_body',
				__(
					'Font Awesome Lib: API token endpoint response was invalid.',
					'wordpress-fontawesome-lib'
				),
				$response
			);
		}

		try {
			$this->access_token_expiration_time_unix =
				$body['expires_in'] + time();
			$this->access_token = $body['access_token'];

			return $this->access_token;
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error(
				'fontawesome_api_token_endpoint_processing_error',
				__(
					'Font Awesome Lib: failed to process API token endpoint response.',
					'wordpress-fontawesome-lib'
				),
				[
					'response' => $response,
					'exception' => $e,
				]
			);
		}
	}

	/**
	 * Make a POST request to the API token endpoint via `wp_remote_post()`.
	 *
	 * @param array $args Arguments to pass to `wp_remote_post()`.
	 * @return array|WP_Error The response or WP_Error on failure.
	 * See WP_Http::request() for information on return value.
	 */
	public function post( $args ) {
		return \wp_remote_post( $this->api_base_url . '/token', $args );
	}
}
