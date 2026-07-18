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
     * Build a minimal kit zip payload in a temp file and return:
     * - `zip_path` (string): absolute path to the zip file
     * - `entries` (array): list of entries included
     */
    /**
     * A single SVG object shape reused across fixtures.
     */
    private function fixture_svg(): array
    {
        return [
            'width' => 1,
            'height' => 1,
            'path' => 'M0 0h1v1H0z',
        ];
    }

    /**
     * @param array|null $icon_families Optional override for the `metadata/icon-families.json` payload.
     *   When null, a minimal single-icon (classic/solid) payload is used.
     */
    private function build_minimal_kit_zip_fixture(?array $icon_families = null): array
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive not available in this environment.');
        }

        if (null === $icon_families) {
            $icon_families = [
                'test-icon' => [
                    'label' => 'Test Icon',
                    'unicode' => 'f000',
                    'svgs' => [
                        'classic' => [
                            'solid' => $this->fixture_svg(),
                        ],
                    ],
                ],
            ];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fa-kit-zip-');
        if (false === $tmp) {
            $this->fail('Failed to create temp file for zip fixture.');
        }

        // ZipArchive expects a .zip extension in some environments/tools.
        $zip_path = $tmp . '.zip';
        rename($tmp, $zip_path);

        $zip = new ZipArchive();

        $open = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if (true !== $open) {
            $this->fail('Failed to open zip fixture for writing.');
        }

        // `Kit_Download::prepare_selfhosting()` only extracts entries starting with css/, webfonts/, metadata/
        // `Kit_Download::build_svg_objects_and_metadata()` expects metadata/icon-families.json to exist and be readable.
        $entries = [
            'css/all.css' => "/* test fixture */\n",
            'webfonts/fa-solid-900.woff2' => "FAKE-WOFF2",
            'metadata/icons.json' => json_encode(['fixture' => true]),
            'metadata/icon-families.json' => json_encode($icon_families),
        ];

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return [
            'zip_path' => $zip_path,
            'entries' => array_keys($entries),
        ];
    }

    /**
     * Intercept the HTTP download to return our kit zip fixture.
     *
     * `Kit_Download::download()` uses:
     *   wp_remote_get($this->url, ['stream' => true, 'filename' => $zip_file_path])
     *
     * So we copy our fixture into the requested `filename` and respond with a 200.
     */
    private function intercept_kit_zip_download(string $expected_url, string $zip_fixture_path): void
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) use ($expected_url, $zip_fixture_path) {
            if ($url !== $expected_url) {
                return $preempt;
            }

            if (!is_array($args) || empty($args['filename']) || !is_string($args['filename'])) {
                return new WP_Error('fontawesome_test_missing_filename', 'Expected streaming download with a filename.');
            }

            $dest = $args['filename'];

            // Ensure target dir exists.
            $dir = dirname($dest);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                return new WP_Error('fontawesome_test_mkdir_failed', 'Failed creating destination directory for streamed zip.');
            }

            if (!copy($zip_fixture_path, $dest)) {
                return new WP_Error('fontawesome_test_copy_zip_failed', 'Failed copying zip fixture to streaming destination.');
            }

            return [
                'headers' => [],
                'body' => '',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
                'filename' => $dest,
            ];
        }, 10, 3);
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
    // download_and_prepare_selfhosting Integration Tests
    // =========================================================================

    public function test_download_and_prepare_selfhosting_downloads_and_extracts_and_returns_expected_path()
    {
        // Arrange: create a "ready" Kit_Download via the public factory.
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->assertInstanceOf(Kit_Download::class, $kit_download);
        $this->assertTrue($kit_download->is_ready());
        $this->assertEquals(self::VALID_DOWNLOAD_URL, $kit_download->get_url());

        // Arrange: create a minimal zip fixture and intercept the HTTP download.
        $zip_fixture = $this->build_minimal_kit_zip_fixture();
        $this->intercept_kit_zip_download(self::VALID_DOWNLOAD_URL, $zip_fixture['zip_path']);

        // Arrange: mock the metadata query to return whatever minimum shape is required.
        // If prepare_selfhosting/take_kit_metadata expects more, update this fixture accordingly.
        $metadata_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    'me' => [
                        'kit' => [
                            'token' => self::VALID_KIT_TOKEN,
                            'licenseSelected' => 'free',
                            'release' => [
                                'version' => '6.0.0',
                                'familyStyles' => [
                                    [
                                        'family' => 'classic',
                                        'style' => 'solid',
                                        'prefix' => 'fas',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];
        $metadata_query_resolver = $this->create_mock_query_resolver($metadata_response);

        $uploads = wp_upload_dir();
        $this->assertIsArray($uploads);
        $this->assertArrayHasKey('basedir', $uploads);
        $this->assertTrue(is_string($uploads['basedir']) && '' !== $uploads['basedir']);

        $destination_base_dir = $uploads['basedir'];

        // Act
        $result = $kit_download->download_and_prepare_selfhosting(
            $metadata_query_resolver,
            $auth_token_provider,
            $destination_base_dir,
            ['overwrite' => true]
        );

        // Assert
        $this->assertFalse(is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : '');
        $this->assertIsString($result);

        $expected_dir =
            trailingslashit($destination_base_dir) .
            'fontawesome-kit/' .
            self::VALID_KIT_TOKEN .
            '/' .
            self::VALID_BUILD_ID .
            '/';

        $this->assertEquals($expected_dir, $result);

        // Assert extracted directories exist
        $this->assertDirectoryExists($expected_dir);
        $this->assertDirectoryExists($expected_dir . 'css/');
        $this->assertDirectoryExists($expected_dir . 'webfonts/');
        $this->assertDirectoryExists($expected_dir . 'metadata/');

        // Assert key expected outputs exist.
        //
        // The selfhosting preparation writes `metadata/kit.json` based on the metadata query response,
        // and it generates family-style metadata JSON files (e.g. `metadata/fas.json`) derived from
        // `metadata/icon-families.json` in the downloaded kit zip. It does NOT write `metadata/icons.json`.
        $this->assertFileExists($expected_dir . 'css/all.css');
        $this->assertFileExists($expected_dir . 'webfonts/fa-solid-900.woff2');
        $this->assertFileExists($expected_dir . 'metadata/kit.json');
        $this->assertFileExists($expected_dir . 'metadata/solid.json');
    }

    /**
     * An `icon-families.json` payload containing a known mix of official and custom icons.
     *
     * Official icons live under standard families (classic, sharp). Custom icons (aka "icon uploads")
     * live under the `kit` (monotone) and `kit-duotone` families, both with the `custom` style.
     *
     * Breakdown (each family/style entry is one counted icon):
     *   - Official: coffee/classic-solid, mug/classic-regular, star/sharp-solid  => 3
     *   - Custom:   my-logo/kit-custom, my-duo-logo/kit-duotone-custom           => 2
     *   - Total                                                                   => 5
     */
    private function mixed_official_and_custom_icon_families(): array
    {
        return [
            // --- Official icons ---
            'coffee' => [
                'label' => 'Coffee',
                'unicode' => 'f0f4',
                'svgs' => [
                    'classic' => [
                        'solid' => $this->fixture_svg(),
                    ],
                ],
            ],
            'mug' => [
                'label' => 'Mug',
                'unicode' => 'f874',
                'svgs' => [
                    'classic' => [
                        'regular' => $this->fixture_svg(),
                    ],
                ],
            ],
            'star' => [
                'label' => 'Star',
                'unicode' => 'f005',
                'svgs' => [
                    'sharp' => [
                        'solid' => $this->fixture_svg(),
                    ],
                ],
            ],
            // --- Custom icons (icon uploads) ---
            'my-logo' => [
                'label' => 'My Logo',
                'unicode' => 'e001',
                'svgs' => [
                    'kit' => [
                        'custom' => $this->fixture_svg(),
                    ],
                ],
            ],
            'my-duo-logo' => [
                'label' => 'My Duotone Logo',
                'unicode' => 'e002',
                'svgs' => [
                    'kit-duotone' => [
                        'custom' => $this->fixture_svg(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Run `download_and_prepare_selfhosting()` end-to-end against the given zip fixture and options,
     * wiring up a READY Kit_Download, intercepting the HTTP download, and mocking the metadata query.
     *
     * @return string|WP_Error the return value of download_and_prepare_selfhosting()
     */
    private function run_download_and_prepare_selfhosting(array $zip_fixture, array $opts)
    {
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $this->create_mock_query_resolver($create_response),
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $this->intercept_kit_zip_download(self::VALID_DOWNLOAD_URL, $zip_fixture['zip_path']);

        $metadata_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    'me' => [
                        'kit' => [
                            'token' => self::VALID_KIT_TOKEN,
                            'licenseSelected' => 'free',
                            'release' => [
                                'version' => '6.0.0',
                                'familyStyles' => [
                                    [
                                        'family' => 'classic',
                                        'style' => 'solid',
                                        'prefix' => 'fas',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        return $kit_download->download_and_prepare_selfhosting(
            $this->create_mock_query_resolver($metadata_response),
            $auth_token_provider,
            wp_upload_dir()['basedir'],
            $opts
        );
    }

    public function test_icon_count_includes_custom_icons()
    {
        // The mixed fixture has 3 official + 2 custom icons (total 5).
        $zip_fixture = $this->build_minimal_kit_zip_fixture(
            $this->mixed_official_and_custom_icon_families()
        );

        // Set the max to the number of OFFICIAL icons (3). If custom icons were NOT counted,
        // the total would be 3 and the limit would not be exceeded. The error therefore proves
        // the 2 custom icons were counted on top of the official ones.
        $result = $this->run_download_and_prepare_selfhosting(
            $zip_fixture,
            ['overwrite' => true, 'max_icon_count' => 3]
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_kit_download_too_many_icons', $result->get_error_code());
        $this->assertEquals(5, $result->get_error_data()['icon_count']);
    }

    public function test_icon_count_includes_official_icons()
    {
        // The mixed fixture has 3 official + 2 custom icons (total 5).
        $zip_fixture = $this->build_minimal_kit_zip_fixture(
            $this->mixed_official_and_custom_icon_families()
        );

        // Set the max to the number of CUSTOM icons (2). If official icons were NOT counted,
        // the total would be 2 and the limit would not be exceeded. The error therefore proves
        // the 3 official icons were counted on top of the custom ones.
        $result = $this->run_download_and_prepare_selfhosting(
            $zip_fixture,
            ['overwrite' => true, 'max_icon_count' => 2]
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_kit_download_too_many_icons', $result->get_error_code());
        $this->assertEquals(5, $result->get_error_data()['icon_count']);
    }

    public function test_icon_count_of_mixed_official_and_custom_icons_is_within_limit()
    {
        // The mixed fixture has 3 official + 2 custom icons (total 5). With a max of 5, the
        // combined count is exactly at the limit (not exceeded), so preparation succeeds.
        // This confirms all 5 (official + custom) are counted without an off-by-one rejection.
        $zip_fixture = $this->build_minimal_kit_zip_fixture(
            $this->mixed_official_and_custom_icon_families()
        );

        $result = $this->run_download_and_prepare_selfhosting(
            $zip_fixture,
            ['overwrite' => true, 'max_icon_count' => 5]
        );

        $this->assertFalse(
            is_wp_error($result),
            is_wp_error($result) ? $result->get_error_message() : ''
        );
        $this->assertIsString($result);
    }

    public function test_download_and_prepare_selfhosting_returns_error_when_icon_count_exceeds_max()
    {
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $zip_fixture = $this->build_minimal_kit_zip_fixture();
        $this->intercept_kit_zip_download(self::VALID_DOWNLOAD_URL, $zip_fixture['zip_path']);

        $metadata_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    'me' => [
                        'kit' => [
                            'token' => self::VALID_KIT_TOKEN,
                            'licenseSelected' => 'free',
                            'release' => [
                                'version' => '6.0.0',
                                'familyStyles' => [
                                    [
                                        'family' => 'classic',
                                        'style' => 'solid',
                                        'prefix' => 'fas',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];
        $metadata_query_resolver = $this->create_mock_query_resolver($metadata_response);

        $destination_base_dir = wp_upload_dir()['basedir'];

        // The fixture contains a single icon, so a max of 0 forces the limit to be exceeded.
        $result = $kit_download->download_and_prepare_selfhosting(
            $metadata_query_resolver,
            $auth_token_provider,
            $destination_base_dir,
            ['overwrite' => true, 'max_icon_count' => 0]
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_kit_download_too_many_icons', $result->get_error_code());

        $data = $result->get_error_data();
        $this->assertIsArray($data);
        $this->assertEquals(1, $data['icon_count']);
        $this->assertEquals(0, $data['max_icon_count']);
    }

    public function test_download_and_prepare_selfhosting_max_icon_count_is_filterable()
    {
        $create_response = $this->build_create_kit_download_response(
            self::VALID_BUILD_ID,
            Kit_Download::STATUS_READY,
            self::VALID_DOWNLOAD_URL
        );
        $create_query_resolver = $this->create_mock_query_resolver($create_response);
        $auth_token_provider = $this->create_mock_auth_token_provider();

        $kit_download = Kit_Download::create_kit_download(
            $create_query_resolver,
            $auth_token_provider,
            self::VALID_KIT_TOKEN
        );

        $zip_fixture = $this->build_minimal_kit_zip_fixture();
        $this->intercept_kit_zip_download(self::VALID_DOWNLOAD_URL, $zip_fixture['zip_path']);

        $metadata_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    'me' => [
                        'kit' => [
                            'token' => self::VALID_KIT_TOKEN,
                            'licenseSelected' => 'free',
                            'release' => [
                                'version' => '6.0.0',
                                'familyStyles' => [
                                    [
                                        'family' => 'classic',
                                        'style' => 'solid',
                                        'prefix' => 'fas',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];
        $metadata_query_resolver = $this->create_mock_query_resolver($metadata_response);

        $destination_base_dir = wp_upload_dir()['basedir'];

        // The filter has the final say, overriding a permissive opts value.
        $filter = function () {
            return 0;
        };
        add_filter('fontawesome_lib_max_icon_count', $filter);

        try {
            $result = $kit_download->download_and_prepare_selfhosting(
                $metadata_query_resolver,
                $auth_token_provider,
                $destination_base_dir,
                ['overwrite' => true, 'max_icon_count' => 100000]
            );
        } finally {
            remove_filter('fontawesome_lib_max_icon_count', $filter);
        }

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_kit_download_too_many_icons', $result->get_error_code());
    }

    public function test_download_and_prepare_selfhosting_returns_error_when_zip_exceeds_max_bytes()
    {
        $zip_fixture = $this->build_minimal_kit_zip_fixture();
        $zip_file_size = filesize($zip_fixture['zip_path']);
        $this->assertIsInt($zip_file_size);

        // Force the limit to one byte below the fixture's actual size so it is exceeded.
        $filter = function () use ($zip_file_size) {
            return $zip_file_size - 1;
        };
        add_filter('fontawesome_lib_max_kit_zip_bytes', $filter);

        try {
            $result = $this->run_download_and_prepare_selfhosting(
                $zip_fixture,
                ['overwrite' => true]
            );
        } finally {
            remove_filter('fontawesome_lib_max_kit_zip_bytes', $filter);
        }

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_api_kit_download_too_large', $result->get_error_code());

        $data = $result->get_error_data();
        $this->assertIsArray($data);
        $this->assertEquals($zip_file_size, $data['zip_file_size']);
        $this->assertEquals($zip_file_size - 1, $data['max_zip_bytes']);
    }

    public function test_download_and_prepare_selfhosting_allows_zip_size_at_the_limit()
    {
        $zip_fixture = $this->build_minimal_kit_zip_fixture();
        $zip_file_size = filesize($zip_fixture['zip_path']);
        $this->assertIsInt($zip_file_size);

        // The check rejects only when the size is strictly greater than the limit, so a
        // limit equal to the fixture's exact size must be allowed through.
        $filter = function () use ($zip_file_size) {
            return $zip_file_size;
        };
        add_filter('fontawesome_lib_max_kit_zip_bytes', $filter);

        try {
            $result = $this->run_download_and_prepare_selfhosting(
                $zip_fixture,
                ['overwrite' => true]
            );
        } finally {
            remove_filter('fontawesome_lib_max_kit_zip_bytes', $filter);
        }

        $this->assertFalse(
            is_wp_error($result),
            is_wp_error($result) ? $result->get_error_message() : ''
        );
        $this->assertIsString($result);
    }

    public function test_prepare_selfhosting_returns_error_when_zip_cannot_be_opened()
    {
        // A readable, non-empty file that is NOT a valid zip archive. It passes the
        // download-side size checks but ZipArchive::open() rejects it with ER_NOZIP.
        $not_a_zip = tempnam(sys_get_temp_dir(), 'fa-not-a-zip-');
        if (false === $not_a_zip) {
            $this->fail('Failed to create temp file for non-zip fixture.');
        }
        file_put_contents($not_a_zip, "this is not a zip archive\n");

        $result = $this->run_download_and_prepare_selfhosting(
            ['zip_path' => $not_a_zip],
            ['overwrite' => true]
        );

        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('fontawesome_zip_file_open_failure', $result->get_error_code());
        // The mapped ER_NOZIP reason should surface in the message.
        $this->assertStringContainsString('Not a zip archive.', $result->get_error_message());
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
