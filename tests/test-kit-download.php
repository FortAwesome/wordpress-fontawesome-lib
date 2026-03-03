<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Kit_Download;
use FontAwesomeLib\Auth_Token_Provider;
use FontAwesomeLib\Query_Resolver;

class Kit_DownloadTest extends TestCase
{
    const VALID_KIT_TOKEN = 'abc123def456';
    const VALID_BUILD_ID = 'build-id-12345';
    const VALID_DOWNLOAD_URL = 'https://kit-downloads.fontawesome.com/abc123.zip';

    /**
     * Create a mock Auth_Token_Provider that returns a valid access token.
     */
    private function create_mock_auth_token_provider(): Auth_Token_Provider
    {
        $mock = $this->createMock(Auth_Token_Provider::class);
        $mock->method('get_access_token')
            ->willReturn('valid-access-token');
        return $mock;
    }

    /**
     * Create a mock Auth_Token_Provider that returns a WP_Error.
     */
    private function create_mock_auth_token_provider_with_error(): Auth_Token_Provider
    {
        $mock = $this->createMock(Auth_Token_Provider::class);
        $mock->method('get_access_token')
            ->willReturn(new WP_Error('fontawesome_invalid_api_token', 'Invalid API token'));
        return $mock;
    }

    /**
     * Create a mock Query_Resolver with a configured response.
     *
     * @param array|WP_Error $response The response to return from query().
     */
    private function create_mock_query_resolver($response): Query_Resolver
    {
        $mock = $this->createMock(Query_Resolver::class);
        $mock->method('query')
            ->willReturn($response);
        return $mock;
    }

    /**
     * Build a successful HTTP response array for createKitDownload mutation.
     */
    private function build_create_kit_download_response(
        string $build_id,
        string $status,
        ?string $url = null
    ): array {
        $body = [
            'data' => [
                'createKitDownload' => [
                    'buildId' => $build_id,
                    'status' => $status,
                    'url' => $url,
                ],
            ],
        ];

        return [
            'response' => ['code' => 200],
            'body' => json_encode($body),
        ];
    }

    /**
     * Build a successful HTTP response array for getKitDownload query.
     */
    private function build_get_kit_download_response(
        string $build_id,
        string $status,
        ?string $url = null
    ): array {
        $body = [
            'data' => [
                'getKitDownload' => [
                    'buildId' => $build_id,
                    'status' => $status,
                    'url' => $url,
                ],
            ],
        ];

        return [
            'response' => ['code' => 200],
            'body' => json_encode($body),
        ];
    }

    /**
     * Build an error HTTP response.
     */
    private function build_error_response(int $code = 500): array
    {
        return [
            'response' => ['code' => $code],
            'body' => json_encode(['error' => 'Internal server error']),
        ];
    }

    /**
     * Build a GraphQL error response.
     */
    private function build_graphql_error_response(string $message = 'Some error'): array
    {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'errors' => [['message' => $message]],
            ]),
        ];
    }

    /**
     * Build an authorization error response.
     */
    private function build_authorization_error_response(): array
    {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'errors' => [['message' => 'unauthorized']],
            ]),
        ];
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    public function test_constructor_sets_kit_token_and_build_id()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);

        $this->assertInstanceOf(Kit_Download::class, $kit_download);
        $this->assertEquals(self::VALID_KIT_TOKEN, $kit_download->get_kit_token());
        $this->assertEquals(self::VALID_BUILD_ID, $kit_download->get_build_id());
    }

    // =========================================================================
    // create_kit_download Tests
    // =========================================================================

    public function test_create_kit_download_with_empty_kit_token_returns_error()
    {
        $query_resolver = $this->create_mock_query_resolver([]);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            ''
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_token', $result->get_error_code());
    }

    public function test_create_kit_download_with_non_string_kit_token_returns_error()
    {
        $query_resolver = $this->create_mock_query_resolver([]);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            12345
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_token', $result->get_error_code());
    }

    public function test_create_kit_download_with_null_kit_token_returns_error()
    {
        $query_resolver = $this->create_mock_query_resolver([]);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            null
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_token', $result->get_error_code());
    }

    public function test_create_kit_download_returns_kit_download_on_success_with_ready_status()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertInstanceOf(Kit_Download::class, $result);
        $this->assertEquals(self::VALID_BUILD_ID, $result->get_build_id());
        $this->assertEquals(self::VALID_KIT_TOKEN, $result->get_kit_token());
        $this->assertEquals(Kit_Download::STATUS_READY, $result->get_status());
        $this->assertEquals(self::VALID_DOWNLOAD_URL, $result->get_url());
        $this->assertTrue($result->is_ready());
        $this->assertFalse($result->is_pending());
        $this->assertFalse($result->is_failed());
    }

    public function test_create_kit_download_returns_kit_download_on_success_with_pending_status()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertInstanceOf(Kit_Download::class, $result);
        $this->assertEquals(Kit_Download::STATUS_PENDING, $result->get_status());
        $this->assertNull($result->get_url());
        $this->assertFalse($result->is_ready());
        $this->assertTrue($result->is_pending());
        $this->assertFalse($result->is_failed());
    }

    public function test_create_kit_download_returns_kit_download_on_success_with_failed_status()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_FAILED,
            null
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertInstanceOf(Kit_Download::class, $result);
        $this->assertEquals(Kit_Download::STATUS_FAILED, $result->get_status());
        $this->assertNull($result->get_url());
        $this->assertFalse($result->is_ready());
        $this->assertFalse($result->is_pending());
        $this->assertTrue($result->is_failed());
    }

    public function test_create_kit_download_returns_error_when_query_returns_wp_error()
    {
        $wp_error = new WP_Error('fontawesome_test_error', 'Test error message');
        $query_resolver = $this->create_mock_query_resolver($wp_error);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertContains('fontawesome_api_request_error', $result->get_error_codes());
    }

    public function test_create_kit_download_returns_error_on_non_200_response()
    {
        $response = $this->build_error_response(500);
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_response_not_ok', $result->get_error_code());
    }

    public function test_create_kit_download_returns_error_on_invalid_json_response()
    {
        $response = [
            'response' => ['code' => 200],
            'body' => 'not valid json{{{',
        ];
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_response_json_parse_error', $result->get_error_code());
    }

    public function test_create_kit_download_returns_error_on_authorization_error()
    {
        $response = $this->build_authorization_error_response();
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_unauthorized_query', $result->get_error_code());
    }

    public function test_create_kit_download_returns_error_on_graphql_error()
    {
        $response = $this->build_graphql_error_response('Some GraphQL error');
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_query_error', $result->get_error_code());
    }

    public function test_create_kit_download_returns_error_on_missing_data_in_response()
    {
        $response = [
            'response' => ['code' => 200],
            'body' => json_encode(['data' => []]),
        ];
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_query_unexpected_response', $result->get_error_code());
    }

    public function test_create_kit_download_returns_error_on_missing_build_id_in_response()
    {
        $response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    'createKitDownload' => [
                        'status' => 'READY',
                        'url' => self::VALID_DOWNLOAD_URL,
                    ],
                ],
            ]),
        ];
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $result = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_query_unexpected_response', $result->get_error_code());
    }

    // =========================================================================
    // poll Tests
    // =========================================================================

    public function test_poll_returns_true_when_already_ready()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Create a new mock that should NOT be called since status is already READY
        $poll_query_resolver = $this->createMock(Query_Resolver::class);
        $poll_query_resolver->expects($this->never())->method('query');

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertTrue($result);
    }

    public function test_poll_updates_status_from_pending_to_ready()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertTrue($kit_download->is_pending());

        // Now poll with a READY response
        $poll_response = $this->build_get_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $poll_query_resolver = $this->create_mock_query_resolver($poll_response);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertTrue($result);
        $this->assertTrue($kit_download->is_ready());
        $this->assertEquals(self::VALID_DOWNLOAD_URL, $kit_download->get_url());
    }

    public function test_poll_returns_false_when_still_pending()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Poll with still PENDING response
        $poll_response = $this->build_get_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $poll_query_resolver = $this->create_mock_query_resolver($poll_response);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertFalse($result);
        $this->assertTrue($kit_download->is_pending());
    }

    public function test_poll_returns_false_when_status_becomes_failed()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Poll with FAILED response
        $poll_response = $this->build_get_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_FAILED,
            null
        );
        $poll_query_resolver = $this->create_mock_query_resolver($poll_response);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertFalse($result);
        $this->assertTrue($kit_download->is_failed());
    }

    public function test_poll_returns_error_when_query_fails()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Poll with error response
        $wp_error = new WP_Error('fontawesome_test_error', 'Test error');
        $poll_query_resolver = $this->create_mock_query_resolver($wp_error);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertTrue(is_wp_error($result));
    }

    public function test_poll_returns_error_on_non_200_response()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Poll with 500 error
        $poll_response = $this->build_error_response(500);
        $poll_query_resolver = $this->create_mock_query_resolver($poll_response);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_response_not_ok', $result->get_error_code());
    }

    public function test_poll_returns_error_on_missing_data_in_response()
    {
        // First create a kit download with PENDING status
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        // Poll with incomplete response
        $poll_response = [
            'response' => ['code' => 200],
            'body' => json_encode(['data' => ['getKitDownload' => []]]),
        ];
        $poll_query_resolver = $this->create_mock_query_resolver($poll_response);

        $result = $kit_download->poll($poll_query_resolver, $auth_token_provider);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_query_unexpected_response', $result->get_error_code());
    }

    // =========================================================================
    // kit_assets_selfhosting_dir_path Tests
    // =========================================================================

    public function test_kit_assets_selfhosting_dir_path_returns_correct_path()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);
        $base_dir = '/var/www/wp-content/uploads';

        $result = $kit_download->kit_assets_selfhosting_dir_path($base_dir);

        $expected = '/var/www/wp-content/uploads/fontawesome-kit/' . self::VALID_KIT_TOKEN . '/' . self::VALID_BUILD_ID . '/';
        $this->assertEquals($expected, $result);
    }

    public function test_kit_assets_selfhosting_dir_path_handles_trailing_slash()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);
        $base_dir = '/var/www/wp-content/uploads/';

        $result = $kit_download->kit_assets_selfhosting_dir_path($base_dir);

        $expected = '/var/www/wp-content/uploads/fontawesome-kit/' . self::VALID_KIT_TOKEN . '/' . self::VALID_BUILD_ID . '/';
        $this->assertEquals($expected, $result);
    }

    public function test_kit_assets_selfhosting_dir_path_returns_error_for_empty_string()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);

        $result = $kit_download->kit_assets_selfhosting_dir_path('');

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_assets_selfhosting_dir_path', $result->get_error_code());
    }

    public function test_kit_assets_selfhosting_dir_path_returns_error_for_non_string()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);

        $result = $kit_download->kit_assets_selfhosting_dir_path(12345);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_assets_selfhosting_dir_path', $result->get_error_code());
    }

    public function test_kit_assets_selfhosting_dir_path_returns_error_for_null()
    {
        $kit_download = new Kit_Download(self::VALID_KIT_TOKEN, self::VALID_BUILD_ID);

        $result = $kit_download->kit_assets_selfhosting_dir_path(null);

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_invalid_kit_assets_selfhosting_dir_path', $result->get_error_code());
    }

    // =========================================================================
    // Status Constants Tests
    // =========================================================================

    public function test_status_constants_are_defined()
    {
        $this->assertEquals('READY', Kit_Download::STATUS_READY);
        $this->assertEquals('FAILED', Kit_Download::STATUS_FAILED);
        $this->assertEquals('PENDING', Kit_Download::STATUS_PENDING);
    }

    // =========================================================================
    // Getter Tests
    // =========================================================================

    public function test_get_url_returns_null_when_not_ready()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_PENDING,
            null
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertNull($kit_download->get_url());
    }

    public function test_get_url_returns_url_when_ready()
    {
        $response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $query_resolver = $this->create_mock_query_resolver($response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertEquals(self::VALID_DOWNLOAD_URL, $kit_download->get_url());
    }
}
