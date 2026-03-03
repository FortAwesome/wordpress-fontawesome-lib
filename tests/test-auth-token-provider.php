<?php

declare(strict_types=1);

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Auth_Token_Provider;

/**
 * Tests for Auth_Token_Provider.
 *
 * Note: This class relies on WordPress functions (e.g. wp_remote_post(), untrailingslashit(), __()).
 * These tests intentionally avoid real HTTP requests by overriding post() in a test double.
 */
class Auth_Token_ProviderTest extends TestCase {

	const VALID_API_TOKEN = 'fa_test_api_token_123';
	const VALID_ACCESS_TOKEN = 'fa_test_access_token_456';

	/**
	 * Create a test double that returns a configured response from post()
	 * and allows optional request_access_token() override for spying.
	 *
	 * @param mixed $post_response Return value for post(): array|WP_Error
	 * @param array $args_out Captured args passed to post()
	 * @return Auth_Token_Provider
	 */
	private function create_provider_with_post_response( $post_response, array &$args_out = [] ): Auth_Token_Provider {
		return new class( self::VALID_API_TOKEN, $post_response, $args_out ) extends Auth_Token_Provider {
			private $post_response;
			private $args_out;

			public function __construct( $api_token, $post_response, array &$args_out ) {
				$this->post_response = $post_response;
				$this->args_out      = &$args_out;
				parent::__construct( $api_token );
			}

			public function post( $args ) {
				$this->args_out = $args;
				return $this->post_response;
			}
		};
	}

	/**
	 * Provider that counts how many times request_access_token() is invoked.
	 *
	 * @param mixed $post_response
	 * @param int   $count_out
	 * @return Auth_Token_Provider
	 */
	private function create_provider_with_request_counter( $post_response, int &$count_out ): Auth_Token_Provider {
		return new class( self::VALID_API_TOKEN, $post_response, $count_out ) extends Auth_Token_Provider {
			private $post_response;
			private $count_out;

			public function __construct( $api_token, $post_response, int &$count_out ) {
				$this->post_response = $post_response;
				$this->count_out     = &$count_out;
				parent::__construct( $api_token );
			}

			public function request_access_token(): string|WP_Error {
				$this->count_out++;
				return parent::request_access_token();
			}

			public function post( $args ) {
				return $this->post_response;
			}
		};
	}

	/**
	 * Use Reflection to set protected properties on the base class instance for caching tests.
	 *
	 * @param object $obj
	 * @param string $prop
	 * @param mixed  $value
	 * @return void
	 */
	private function set_protected_property( object $obj, string $prop, $value ): void {
		$ref  = new ReflectionClass( $obj );
		$p    = $ref->getProperty( $prop );
		$p->setAccessible( true );
		$p->setValue( $obj, $value );
	}

	/**
	 * Build a valid API token endpoint response body.
	 *
	 * @param string $access_token
	 * @param int    $expires_in
	 * @return array
	 */
	private function build_token_endpoint_success_response( string $access_token, int $expires_in ): array {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode(
				[
					'access_token' => $access_token,
					'expires_in'   => $expires_in,
				]
			),
		];
	}

	// =========================================================================
	// Constructor / API token validation
	// =========================================================================

	public function test_get_api_token_returns_error_when_constructed_with_empty_string(): void {
		$provider = new Auth_Token_Provider( '' );

		$result = $provider->get_api_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_invalid_api_token', $result->get_error_code() );
	}

	public function test_get_api_token_returns_error_when_constructed_with_non_string(): void {
		$provider = new Auth_Token_Provider( 12345 );

		$result = $provider->get_api_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_invalid_api_token', $result->get_error_code() );
	}

	public function test_get_api_token_returns_string_for_valid_token(): void {
		$provider = new Auth_Token_Provider( self::VALID_API_TOKEN );

		$result = $provider->get_api_token();

		$this->assertIsString( $result );
		$this->assertEquals( self::VALID_API_TOKEN, $result );
	}

	public function test_constructor_allows_custom_api_base_url_and_trims_trailing_slash(): void {
		$response = $this->build_token_endpoint_success_response( self::VALID_ACCESS_TOKEN, 60 );

		$captured = null;

		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( &$captured, $response ) {
		    $captured = [
		        'url'  => $url,
		        'args' => $args,
		    ];

		    return $response;
		}, 10, 3 );

		$provider = new Auth_Token_Provider( self::VALID_API_TOKEN, [ 'api_base_url' => 'https://example.test/api/' ] );

		// Trigger a request so post() is used (and therefore api_base_url is used).
		$token = $provider->request_access_token();

		$this->assertIsString( $token );
		$this->assertEquals( self::VALID_ACCESS_TOKEN, $token );

		$this->assertArrayHasKey( 'url', $captured );
		$this->assertEquals( 'https://example.test/api/token', $captured['url'] );
		$this->assertArrayHasKey( 'args', $captured );
		$this->assertArrayHasKey( 'headers', $captured['args'] );
		$this->assertArrayHasKey( 'authorization', $captured['args']['headers'] );
		$this->assertEquals( 'Bearer ' . self::VALID_API_TOKEN, $captured['args']['headers']['authorization'] );
	}

	// =========================================================================
	// get_access_token caching behavior
	// =========================================================================

	public function test_get_access_token_returns_cached_token_when_not_expired_and_does_not_refresh(): void {
		$count_out = 0;

		// If refresh happens, it would call request_access_token (counted). The post response doesn't matter.
		$provider = $this->create_provider_with_request_counter(
			$this->build_token_endpoint_success_response( self::VALID_ACCESS_TOKEN, 60 ),
			$count_out
		);

		$this->set_protected_property( $provider, 'access_token', 'cached-token' );
		$this->set_protected_property( $provider, 'access_token_expiration_time_unix', time() + 3600 );

		$token = $provider->get_access_token();

		$this->assertIsString( $token );
		$this->assertEquals( 'cached-token', $token );
		$this->assertEquals( 0, $count_out, 'Expected request_access_token() not to be called for valid cached token.' );
	}

	public function test_get_access_token_refreshes_when_expired(): void {
		$count_out = 0;

		$provider = $this->create_provider_with_request_counter(
			$this->build_token_endpoint_success_response( self::VALID_ACCESS_TOKEN, 60 ),
			$count_out
		);

		$this->set_protected_property( $provider, 'access_token', 'old-token' );
		$this->set_protected_property( $provider, 'access_token_expiration_time_unix', time() - 10 );

		$token = $provider->get_access_token();

		$this->assertIsString( $token );
		$this->assertEquals( self::VALID_ACCESS_TOKEN, $token );
		$this->assertEquals( 1, $count_out );
	}

	// =========================================================================
	// request_access_token error handling and parsing
	// =========================================================================

	public function test_request_access_token_returns_error_when_api_token_missing(): void {
		$provider = new Auth_Token_Provider( '' );

		$result = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_missing_api_token', $result->get_error_code() );
	}

	public function test_request_access_token_wraps_wp_error_from_post(): void {
		$wp_error  = new WP_Error( 'http_request_failed', 'Network down' );
		$provider  = $this->create_provider_with_post_response( $wp_error );
		$result    = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_api_token_endpoint_error', $result->get_error_code() );
	}

	public function test_request_access_token_returns_error_on_non_200_http_status(): void {
		$provider = $this->create_provider_with_post_response(
			[
				'response' => [ 'code' => 500 ],
				'body'     => wp_json_encode( [ 'error' => 'nope' ] ),
			]
		);

		$result = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_api_token_endpoint_http_error', $result->get_error_code() );
	}

	public function test_request_access_token_returns_error_on_invalid_body_missing_access_token(): void {
		$provider = $this->create_provider_with_post_response(
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						// missing access_token
						'expires_in' => 60,
					]
				),
			]
		);

		$result = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_api_token_endpoint_invalid_body', $result->get_error_code() );
	}

	public function test_request_access_token_returns_error_on_invalid_body_missing_expires_in(): void {
		$provider = $this->create_provider_with_post_response(
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						'access_token' => self::VALID_ACCESS_TOKEN,
						// missing expires_in
					]
				),
			]
		);

		$result = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_api_token_endpoint_invalid_body', $result->get_error_code() );
	}

	public function test_request_access_token_returns_error_on_invalid_body_expires_in_not_int(): void {
		$provider = $this->create_provider_with_post_response(
			[
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						'access_token' => self::VALID_ACCESS_TOKEN,
						'expires_in'   => '60', // should be int
					]
				),
			]
		);

		$result = $provider->request_access_token();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'fontawesome_api_token_endpoint_invalid_body', $result->get_error_code() );
	}

	public function test_request_access_token_sets_access_token_and_expiration_and_sends_bearer_header(): void {
		$args_out = [];
		$response = $this->build_token_endpoint_success_response( self::VALID_ACCESS_TOKEN, 60 );

		$provider = $this->create_provider_with_post_response( $response, $args_out );

		$before = time();
		$token  = $provider->request_access_token();
		$after  = time();

		$this->assertIsString( $token );
		$this->assertEquals( self::VALID_ACCESS_TOKEN, $token );

		$exp = $provider->get_access_token_expiration_time_unix();
		$this->assertIsInt( $exp );
		$this->assertGreaterThanOrEqual( $before + 60, $exp );
		$this->assertLessThanOrEqual( $after + 60, $exp );

		$this->assertArrayHasKey( 'headers', $args_out );
		$this->assertArrayHasKey( 'authorization', $args_out['headers'] );
		$this->assertEquals( 'Bearer ' . self::VALID_API_TOKEN, $args_out['headers']['authorization'] );
	}
}
