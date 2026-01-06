<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

use FontAwesomeLib\Base\FontAwesome_Exception_Base as FontAwesome_Exception;
use FontAwesomeLib\Exceptions\Api_Request_Exception;
use FontAwesomeLib\Exceptions\Api_Response_Exception;
use FontAwesomeLib\Exceptions\Api_Download_Authorization_Exception;
use FontAwesomeLib\Base\Query_Resolver_Base;
use \WP_Error;

class Kit_Download
{
    public const STATUS_READY = "READY";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_PENDING = "PENDING";

    protected $_build_id = null;
    protected $_status = null;
    protected $_url = null;

    /**
     * Construct a new Kit_Download object.
     *
     * @param string $build_id
     * @param string $status
     * @param string|null $url
     * @throws \InvalidArgumentException
     */
    public function __construct($build_id, $status, $url)
    {
        if (!is_string($build_id) || $build_id === "") {
            throw new \InvalidArgumentException(
                "build_id must be a non-empty string",
            );
        }

        $this->_build_id = $build_id;

        if (
            !in_array(
                $status,
                [self::STATUS_READY, self::STATUS_FAILED, self::STATUS_PENDING],
                true,
            )
        ) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }

        $this->_status = $status;
        $this->_url = $url;
    }

    /**
     * Get the build ID.
     *
     * @return string
     */
    public function get_build_id(): string
    {
        return $this->_build_id;
    }

    /**
     * Get the kit download's status.
     *
     * @return string one of READY, FAILED, PENDING
     */
    public function get_status(): string
    {
        return $this->_status;
    }

    /**
     * Get the kit download's status. When status is READY, this is the download URL.
     * Otherwise, it is null.
     *
     * @return string one of READY, FAILED, PENDING
     */
    public function get_url(): ?string
    {
        return $this->_url;
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

        if (
            !isset($decoded_body["data"]) ||
            !isset($decoded_body["data"]["createKitDownload"]) ||
            !isset($decoded_body["data"]["createKitDownload"]["buildId"]) ||
            !isset($decoded_body["data"]["createKitDownload"]["status"]) ||
            !isset($decoded_body["data"]["createKitDownload"]["url"])
        ) {
            return new WP_Error(
                "fontawesome_api_query_unexpected_response",
                "The response from the Font Awesome API server did not contain the expected data.",
                $decoded_body,
            );
        }

        return new self(
            $decoded_body["data"]["createKitDownload"]["buildId"],
            $decoded_body["data"]["createKitDownload"]["status"],
            $decoded_body["data"]["createKitDownload"]["url"],
        );
    }

    /**
     * Fetch the Kit_Download status from the Font Awesome API server.
     */
    function get_kit_download($build_id, $kit_token) {}
}
