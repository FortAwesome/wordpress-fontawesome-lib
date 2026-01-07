<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

use FontAwesomeLib\Base\Query_Resolver_Base;
use \WP_Error;

class Kit_Download
{
    public const STATUS_READY = "READY";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_PENDING = "PENDING";

    protected $build_id = null;
    protected $status = null;
    protected $url = null;
    protected $kit_token = null;

    /**
     * Construct a new Kit_Download object.
     *
     * @param string $kit_token
     * @param string $build_id
     * @param string|null $status
     * @param string|null $url
     * @throws \InvalidArgumentException
     */
    public function __construct(
        $kit_token,
        $build_id,
        $status = null,
        $url = null,
    ) {
        if (!is_string($kit_token) || $kit_token === "") {
            throw new \InvalidArgumentException(
                "kit_token must be a non-empty string",
            );
        }

        $this->kit_token = $kit_token;

        if (!is_string($build_id) || $build_id === "") {
            throw new \InvalidArgumentException(
                "build_id must be a non-empty string",
            );
        }

        $this->build_id = $build_id;

        if (
            !in_array(
                $status,
                [self::STATUS_READY, self::STATUS_FAILED, self::STATUS_PENDING],
                true,
            )
        ) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }

        $this->status = $status;
        $this->url = $url;
    }

    /**
     * Get the build ID.
     *
     * @return string
     */
    public function get_build_id(): string
    {
        return $this->build_id;
    }

    /**
     * Get the kit download's status.
     *
     * @return string one of READY, FAILED, PENDING
     */
    public function get_status(): string
    {
        return $this->status;
    }

    /**
     * Get the kit download's status. When status is READY, this is the download URL.
     * Otherwise, it is null.
     *
     * @return string one of READY, FAILED, PENDING
     */
    public function get_url(): ?string
    {
        return $this->url;
    }

    /**
     * Get the kit token associated with this download.
     * @return string
     */
    public function get_kit_token(): string
    {
        return $this->kit_token;
    }

    /**
     * Convenience method for checking that the status is READY.
     * @return bool
     */
    public function is_ready(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Convenience method for checking that the status is FAILED.
     * @return bool
     */
    public function is_failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Convenience method for checking that the status is PENDING.
     * @return bool
     */
    public function is_pending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Create a new Kit_Download by querying the Font Awesome metadata server
     * for the Font Awesome Kit corresponding to the given kit token.
     *
     * @param Query_Resolver_Base $query_resolver
     * @param Auth_Token_Provider_Base $auth_token_provider
     * @param string $kit_token
     * @return KitDownload | WP_Error
     */
    public static function create_kit_download(
        $query_resolver,
        $auth_token_provider,
        $kit_token,
    ): self|WP_Error {
        if (!is_string($kit_token) || $kit_token === "") {
            return new WP_Error(
                "fontawesome_invalid_kit_token",
                __(
                    "kit_token must be a non-empty string",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        $query = <<<EOT
        mutation {
           	createKitDownload(buildType: WEB, kitToken: "$kit_token" ) {
          		buildId
          		status
          		url
           	}
        }
        EOT;

        $decoded_body = self::handle_query(
            $query,
            $query_resolver,
            $auth_token_provider,
        );

        if (
            !isset($decoded_body["data"]) ||
            !isset($decoded_body["data"]["createKitDownload"]) ||
            !isset($decoded_body["data"]["createKitDownload"]["buildId"]) ||
            !isset($decoded_body["data"]["createKitDownload"]["status"]) ||
            !array_key_exists("url", $decoded_body["data"]["createKitDownload"])
        ) {
            return new WP_Error(
                "fontawesome_api_query_unexpected_response",
                "The response from the Font Awesome API server did not contain the expected data.",
                $decoded_body,
            );
        }

        return new self(
            $kit_token,
            $decoded_body["data"]["createKitDownload"]["buildId"],
            $decoded_body["data"]["createKitDownload"]["status"],
            $decoded_body["data"]["createKitDownload"]["url"],
        );
    }

    /**
     * Fetch the Kit_Download status from the Font Awesome API server.
     * @param Query_Resolver_Base $query_resolver
     * @param Auth_Token_Provider_Base $auth_token_provider
     * @return bool|WP_Error true if the resulting status is READY, WP_Error on error.
     */
    public function poll($query_resolver, $auth_token_provider): bool|WP_Error
    {
        if ($this->is_ready()) {
            return true;
        }

        $query = <<<EOT
        query {
           	getKitDownload(buildId: "$this->build_id", buildType: WEB, kitToken: "$this->kit_token") {
          		buildId
          		status
          		url
           	}
        }
        EOT;

        $decoded_body = self::handle_query(
            $query,
            $query_resolver,
            $auth_token_provider,
        );

        if (
            !isset($decoded_body["data"]) ||
            !isset($decoded_body["data"]["getKitDownload"]) ||
            !isset($decoded_body["data"]["getKitDownload"]["buildId"]) ||
            !isset($decoded_body["data"]["getKitDownload"]["status"]) ||
            !array_key_exists("url", $decoded_body["data"]["getKitDownload"])
        ) {
            return new WP_Error(
                "fontawesome_api_query_unexpected_response",
                "The response from the Font Awesome API server did not contain the expected data.",
                $decoded_body,
            );
        }
        $this->status = $decoded_body["data"]["getKitDownload"]["status"];
        $this->url = $decoded_body["data"]["getKitDownload"]["url"];
        return $this->is_ready();
    }

    /**
     * Handle a query to the Font Awesome API server.
     * @param string $query
     * @param Query_Resolver_Base $query_resolver
     * @param Auth_Token_Provider_Base $auth_token_provider
     * @return array|WP_Error
     */
    private static function handle_query(
        $query,
        $query_resolver,
        $auth_token_provider,
    ): array|WP_Error {
        $response = $query_resolver->query(
            ["query" => $query],
            $auth_token_provider,
        );

        if ($response instanceof WP_Error) {
            $response->add(
                "fontawesome_api_request_error",
                __(
                    "Received an error response when sending a request to the Font Awesome API server.",
                    "wordpress-fontawesome-lib",
                ),
            );

            return $response;
        }

        if (200 !== $response["response"]["code"]) {
            return new WP_Error(
                "fontawesome_api_response_not_ok",
                __(
                    "The response from the Font Awesome API server has an HTTP status other than 200.",
                    "wordpress-fontawesome-lib",
                ),
                $response,
            );
        }

        $decoded_body = json_decode($response["body"], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                "fontawesome_api_response_json_parse_error",
                __(
                    "The response from the Font Awesome API server could not be parsed as JSON.",
                    "wordpress-fontawesome-lib",
                ),
                $decoded_body,
            );
        }

        if (Query_Resolver_Base::has_authorization_error($decoded_body)) {
            return new WP_Error(
                "fontawesome_api_unauthorized_query",
                "This API token is not authorized to create a kit download.",
                $decoded_body,
            );
        }

        if (Query_Resolver_Base::has_any_error($decoded_body)) {
            return new WP_Error(
                "fontawesome_api_query_error",
                "An error occurred while querying the Font Awesome API.",
                $decoded_body,
            );
        }

        return $decoded_body;
    }

    /**
     * Download the kit zip file to a temporary directory.
     * @return string|WP_Error The path to the temporary directory containing the downloaded zip file, or WP_Error on error.
     * The caller is responsible for cleaning up the temporary directory.
     */
    public function download(): string|WP_Error
    {
        if (!$this->is_ready() || !$this->url === null) {
            return new WP_Error(
                "fontawesome_api_kit_download_not_ready",
                __(
                    "The kit download is not ready. Cannot download.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        $base_temp_dir = apply_filters(
            "fontawesome_lib_temp_dir",
            get_temp_dir(),
        );

        $temp_dir = $base_temp_dir . "fontawesome-" . wp_generate_uuid4() . "/";

        $was_temp_dir_created = wp_mkdir_p($temp_dir);

        if (!$was_temp_dir_created) {
            return new WP_Error(
                "fontawesome_api_temp_dir_creation_failed",
                "Failed to create temporary directory.",
                ["temp_dir" => $temp_dir],
            );
        }

        if (!is_dir($temp_dir) || !is_writable($temp_dir)) {
            return new WP_Error(
                "fontawesome_api_invalid_temp_dir",
                "Temporary directory is not writable.",
                ["temp_dir" => $temp_dir],
            );
        }

        $zip_file_path = trailingslashit($temp_dir) . "kit.zip";

        $timeout_seconds = apply_filters(
            "fontawesome_lib_kit_download_timeout_seconds",
            30,
        );

        $response = wp_remote_get($this->url, [
            "timeout" => $timeout_seconds,
            "stream" => true,
            "filename" => $zip_file_path,
        ]);

        if (is_wp_error($response)) {
            $response->add(
                "fontawesome_api_kit_download_request_error",
                __(
                    "HTTP request to download Font Awesome kit zip file failed.",
                    "wordpress-fontawesome-lib",
                ),
            );

            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if (200 !== $code) {
            return new WP_Error(
                "fontawesome_api_kit_download_response_not_ok",
                sprintf(
                    /* translators: 1: HTTP response code */
                    __(
                        'Unexpected HTTP response code when downloading Font Awesome kit zip: %1$s',
                        "font-awesome",
                    ),
                    $code,
                ),
            );
        }

        if (!file_exists($zip_file_path) || filesize($zip_file_path) === 0) {
            return new WP_Error(
                "fontawesome_api_kit_download_response_not_ok",
                __(
                    "Downloaded Font Awesome kit zip file is not valid.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        return $temp_dir;
    }
}
