<?php
declare(strict_types=1);

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Query_Resolver;
use FontAwesomeLib\Auth_Token_Provider;

/**
 * @covers \FontAwesomeLib\Query_Resolver
 */
class Query_Resolver_Test extends TestCase {

	public function tearDown(): void {
	    if ( isset( $this->pre_http_request_cb ) ) {
	        remove_filter( 'pre_http_request', $this->pre_http_request_cb, 10 );
	        unset( $this->pre_http_request_cb );
	    }
	    parent::tearDown();
	}

	/**
	 * Convenience helper: build a minimal valid query params array.
	 */
	private function valid_query_params(): array {
		return [
			'query'     => 'query Ping { ping }',
			'variables' => [ 'foo' => 'bar' ],
		];
	}

	private function create_mock_auth_token_provider_with_token( string $token ): Auth_Token_Provider {
		$mock = $this->createMock( Auth_Token_Provider::class );
		$mock->method( 'get_access_token' )->willReturn( $token );
		return $mock;
	}

	private function create_mock_auth_token_provider_with_error( WP_Error $error ): Auth_Token_Provider {
		$mock = $this->createMock( Auth_Token_Provider::class );
		$mock->method( 'get_access_token' )->willReturn( $error );
		return $mock;
	}

	public function test_query_returns_error_when_query_params_not_array(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( 'token' );

		$result = $resolver->query( 'not-an-array', $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fontawesome_invalid_query_params', $result->get_error_code() );
	}

	public function test_query_returns_error_when_query_key_missing(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( 'token' );

		$result = $resolver->query( [], $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fontawesome_invalid_query_params', $result->get_error_code() );
	}

	public function test_query_returns_error_when_query_not_string(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( 'token' );

		$result = $resolver->query( [ 'query' => [ 'nope' ] ], $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fontawesome_invalid_query_params', $result->get_error_code() );
	}

	public function test_query_returns_error_when_query_empty_string(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( 'token' );

		$result = $resolver->query( [ 'query' => '' ], $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fontawesome_invalid_query_params', $result->get_error_code() );
	}

	public function test_query_returns_error_when_api_base_url_invalid(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( 'token' );

		// Force invalid base URL state.
		$this->set_protected_property( $resolver, 'api_base_url', '' );

		$result = $resolver->query( $this->valid_query_params(), $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fontawesome_invalid_api_base_url', $result->get_error_code() );
	}

	public function test_query_returns_auth_token_provider_error_when_get_access_token_fails(): void {
		$resolver   = new Query_Resolver();
		$auth_error = new WP_Error( 'auth_failed', 'No token' );
		$auth       = $this->create_mock_auth_token_provider_with_error( $auth_error );

		$result = $resolver->query( $this->valid_query_params(), $auth );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auth_failed', $result->get_error_code() );
	}

	public function test_query_ignores_auth_when_option_set(): void {
		$resolver = new Query_Resolver();
		$auth     = $this->createMock( Auth_Token_Provider::class );
		$auth->expects( $this->never() )->method( 'get_access_token' );

		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) {
				// Ensure we do not set Authorization when ignore_auth is true.
				if ( isset( $args['headers']['authorization'] ) ) {
					return new WP_Error( 'unexpected_auth_header', 'Authorization header should not be set' );
				}

				// Return a successful mocked HTTP response.
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => [],
					'body'     => '{"data":{"ok":true}}',
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);

		$result = $resolver->query(
			$this->valid_query_params(),
			$auth,
			[
				'ignore_auth'     => true,
				'timeout_seconds' => 10,
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['response']['code'] );
	}

	public function test_query_sets_authorization_header_and_posts_json(): void {
		$token_value = 'my-access-token';
		$resolver = new Query_Resolver();
		$auth     = $this->create_mock_auth_token_provider_with_token( $token_value );

		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) use ( $token_value ) {
				// Ensure method and content-type.
				if ( ( $args['method'] ?? '' ) !== 'POST' ) {
					return new WP_Error( 'bad_method', 'Expected POST' );
				}
				if ( ( $args['headers']['Content-Type'] ?? '' ) !== 'application/json' ) {
					return new WP_Error( 'bad_content_type', 'Expected application/json' );
				}

				// Ensure authorization header is present.
				if ( ( $args['headers']['authorization'] ?? '' ) !== "Bearer $token_value" ) {
					return new WP_Error( 'missing_or_bad_auth', 'Expected Bearer token header' );
				}

				// Ensure JSON body contains the allowed keys.
				$decoded = json_decode( (string) ( $args['body'] ?? '' ), true );
				if ( ! is_array( $decoded ) ) {
					return new WP_Error( 'bad_json', 'Request body is not valid JSON' );
				}
				if ( ! array_key_exists( 'query', $decoded ) ) {
					return new WP_Error( 'missing_query', 'Request JSON missing query key' );
				}
				if ( ! array_key_exists( 'variables', $decoded ) ) {
					return new WP_Error( 'missing_variables', 'Request JSON missing variables key' );
				}

				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'headers'  => [],
					'body'     => '{"data":{"ok":true}}',
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);

		$result = $resolver->query( $this->valid_query_params(), $auth );

		$this->assertIsArray( $result );
		$this->assertSame( 200, $result['response']['code'] );
	}

	public function test_has_authorization_error_detects_unauthorized_message(): void {
		$this->assertTrue(
			Query_Resolver::has_authorization_error(
				[
					'errors' => [
						[ 'message' => 'unauthorized' ],
					],
				]
			)
		);

		$this->assertFalse(
			Query_Resolver::has_authorization_error(
				[
					'errors' => [
						[ 'message' => 'something else' ],
					],
				]
			)
		);

		$this->assertFalse( Query_Resolver::has_authorization_error( null ) );
		$this->assertFalse( Query_Resolver::has_authorization_error( 'nope' ) );
		$this->assertFalse( Query_Resolver::has_authorization_error( [ 'data' => [] ] ) );
	}

	public function test_has_any_error_returns_true_for_non_array_and_when_errors_present(): void {
		$this->assertTrue( Query_Resolver::has_any_error( null ) );
		$this->assertTrue( Query_Resolver::has_any_error( 'nope' ) );

		$this->assertFalse( Query_Resolver::has_any_error( [ 'data' => [ 'x' => 1 ] ] ) );
		$this->assertFalse( Query_Resolver::has_any_error( [ 'errors' => [] ] ) );

		$this->assertTrue(
			Query_Resolver::has_any_error(
				[
					'errors' => [
						[ 'message' => 'anything' ],
					],
				]
			)
		);
	}

	/**
	 * Set a protected property value via reflection.
	 *
	 * @param object $object
	 * @param string $property
	 * @param mixed  $value
	 * @return void
	 */
	private function set_protected_property( object $object, string $property, $value ): void {
		$ref = new ReflectionClass( $object );
		$prop = $ref->getProperty( $property );
		$prop->setAccessible( true );
		$prop->setValue( $object, $value );
	}
}
