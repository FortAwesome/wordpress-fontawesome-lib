<?php
declare(strict_types=1);

namespace FontAwesomeLib\Base;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

use FontAwesomeLib\Base\Auth_Token_Provider_Base;
use FontAwesomeLib\Exceptions\ApiRequestException;
use FontAwesomeLib\Exceptions\ApiResponseException;

class Query_Resolver_Base
{
    const DEFAULT_API_BASE_URL = "https://api.fontawesome.com";

    protected $api_base_url = self::DEFAULT_API_BASE_URL;

    /**
     * Construct a new Query_Resolver_Base object, using the given Font Awesome API base URL.
     *
     * @param string $api_base_url The base URL for the Font Awesome API.
     * @throws \InvalidArgumentException
     */
    public function __construct($api_base_url = self::DEFAULT_API_BASE_URL)
    {
        if (!is_string($api_base_url) || $api_base_url === "") {
            throw new \InvalidArgumentException(
                "api_base_url must be a non-empty string",
            );
        }

        $this->api_base_url = \untrailingslashit($api_base_url);
    }

    /**
     * @param array $query_params
     *  - query: string. The GraphQL query string.
     *  - variables: array (optional). The variables for the GraphQL query.
     * @param Auth_Token_Provider_Base $auth_token_provider
     * @param array $opts
     * @throws ApiRequestException
     * @throws ApiResponseException
     * @throws \InvalidArgumentException
     * @return array|WP_Error The response from the Font Awesome API server, or WP_Error on failure.
     * See WP_Http::request() for information on return value.
     */
    public function query(
        $query_params,
        $auth_token_provider,
        $opts = ["ignore_auth" => false, "timeout_seconds" => 10],
    ): array {
        if (
            !is_array($query_params) ||
            !array_key_exists("query", $query_params) ||
            !is_string($query_params["query"]) ||
            $query_params["query"] === ""
        ) {
            throw new \InvalidArgumentException(
                "query_params must be an array with a non-empty 'query' string key",
            );
        }

        $body = "";

        $filtered_query_array = [];
        $filtered_query_array["query"] = $query_params["query"];
        if (array_key_exists("variables", $query_params)) {
            $filtered_query_array["variables"] = $query_params["variables"];
        }

        $body = \wp_json_encode($filtered_query_array);

        $timeout_seconds = $opts["timeout_seconds"] ?? 10;

        $args = [
            "method" => "POST",
            "headers" => [
                "Content-Type" => "application/json",
            ],
            "body" => $body,
            "timeout" => $timeout_seconds,
        ];

        $ignore_auth = $opts["ignore_auth"] ?? false;

        if (!$ignore_auth) {
            $access_token = $auth_token_provider->get_access_token();
            $args["headers"]["authorization"] = "Bearer $access_token";
        }

        return \wp_remote_post($this->api_base_url, $args);
    }

    /**
     * Determine if the given decoded JSON body from a Font Awesome API response
     * indicates an authorization error.
     * @param mixed $decoded_body The decoded JSON body from the Font Awesome API response.
     * @return bool true if the response indicates an authorization error, false otherwise.
     */
    public static function has_authorization_error($decoded_body): bool
    {
        if (!is_array($decoded_body)) {
            return false;
        }

        if (!array_key_exists("errors", $decoded_body)) {
            return false;
        }

        foreach ($decoded_body["errors"] as $error) {
            if (
                is_array($error) &&
                array_key_exists("message", $error) &&
                $error["message"] === "unauthorized"
            ) {
                return true;
            }
        }

        return false;
    }

    public static function has_any_error($decoded_body): bool
    {
        if (!is_array($decoded_body)) {
            return true;
        }

        if (
            !array_key_exists("errors", $decoded_body) ||
            !is_array($decoded_body["errors"])
        ) {
            return false;
        }

        if (count($decoded_body["errors"]) > 0) {
            return true;
        }

        return false;
    }
}
