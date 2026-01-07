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
     */
    public function __construct($kit_token, $build_id)
    {
        $this->kit_token = $kit_token;
        $this->build_id = $build_id;
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

        $kit_download = new self(
            $kit_token,
            $decoded_body["data"]["createKitDownload"]["buildId"],
        );

        $kit_download->status =
            $decoded_body["data"]["createKitDownload"]["status"];
        $kit_download->url = $decoded_body["data"]["createKitDownload"]["url"];

        return $kit_download;
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
     * The zip file will be saved as "kit.zip" in the temporary directory.
     * @param WP_Filesystem_Base $wp_filesystem The WordPress filesystem object.
     * @return string|WP_Error The path to the temporary directory containing the downloaded zip file, or WP_Error on error.
     * The caller is responsible for cleaning up the temporary directory.
     */
    protected function download($wp_filesystem): string|WP_Error
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

        $temp_dir =
            $base_temp_dir .
            "fontawesome-kit-download-" .
            wp_generate_uuid4() .
            "/";

        $was_temp_dir_created = $wp_filesystem->mkdir($temp_dir);

        if (!$was_temp_dir_created) {
            return new WP_Error(
                "fontawesome_api_temp_dir_creation_failed",
                "Failed to create temporary directory.",
                ["temp_dir" => $temp_dir],
            );
        }

        if (
            !$wp_filesystem->is_dir($temp_dir) ||
            !$wp_filesystem->is_writable($temp_dir)
        ) {
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

        if (
            !$wp_filesystem->exists($zip_file_path) ||
            $wp_filesystem->size($zip_file_path) === 0
        ) {
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

    /**
     * Download the kit zip file, extract the relevant files to the given upload directory.
     * Cleans up temporary files after extraction.
     * If the kit assets for the given build ID already exists relative to the given destination directory,
     * they will be overwritten if the "overwrite" option is true (the default). Otherwise, downloading
     * will be skipped and the existing directory path will be returned.
     * @param string $destination_base_dir The destination base directory for kit assets to be written into. For example the basedir from `wp_upload_dir()`.
     * @return string|WP_Error on success returns the path to the directory containing the kit's assets for self-hosting. WP_Error on error.
     */
    public function download_and_prepare_selfhosting(
        $destination_base_dir,
        $opts = ["overwrite" => true],
    ): string|WP_Error {
        if (!function_exists("WP_Filesystem")) {
            require_once ABSPATH . "wp-admin/includes/file.php";
        }

        if (!WP_Filesystem(false)) {
            return new WP_Error(
                "fontawesome_kit_download_filesystem_init_failed",
                __(
                    "Failed to initialize WordPress filesystem API.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        global $wp_filesystem;

        $kit_assets_selfhosting_dir_path = $this->kit_assets_selfhosting_dir_path(
            $destination_base_dir,
        );

        $should_overwrite = true;

        if (
            is_array($opts) &&
            isset($opts["overwrite"]) &&
            !boolval($opts["overwrite"])
        ) {
            $should_overwrite = false;
        }

        if (
            !$should_overwrite &&
            $wp_filesystem->is_dir($kit_assets_selfhosting_dir_path) &&
            $wp_filesystem->is_readable($kit_assets_selfhosting_dir_path)
        ) {
            return $kit_assets_selfhosting_dir_path;
        }

        $zip_temp_dir = $this->download($wp_filesystem);

        error_log("ZIP_TEMP_DIR: $zip_temp_dir\n");

        if (is_wp_error($zip_temp_dir)) {
            return $zip_temp_dir;
        }

        $extraction_result = self::prepare_selfhosting(
            $wp_filesystem,
            $zip_temp_dir,
            $destination_base_dir,
        );

        if (is_wp_error($extraction_result)) {
            return $extraction_result;
        }

        // try {
        //     $wp_filesystem->delete($zip_temp_dir, true);
        // } catch (\Exception $e) {
        //     error_log(
        //         "Failed to clean up temporary directory $zip_temp_dir: " .
        //             $e->getMessage(),
        //     );
        // }

        return $kit_assets_selfhosting_dir_path;
    }

    public function kit_assets_selfhosting_dir_path(
        $destination_base_dir,
    ): string|WP_Error {
        if (!is_string($destination_base_dir) || $destination_base_dir == "") {
            return new WP_Error(
                "fontawesome_invalid_kit_assets_selfhosting_dir_path",
                "The provided destination base directory is not valid. It must be a non-empty string.",
            );
        }

        return trailingslashit($destination_base_dir) .
            "fontawesome-kit/" .
            $this->get_kit_token() .
            "/" .
            $this->get_build_id() .
            "/";
    }

    private function prepare_selfhosting(
        $wp_filesystem,
        $zip_temp_dir,
        $destination_dir,
    ): true|WP_Error {
        if (
            !$wp_filesystem->is_dir($zip_temp_dir) ||
            !$wp_filesystem->is_readable($zip_temp_dir)
        ) {
            return new WP_Error(
                "fontawesome_invalid_zip_temp_dir",
                __(
                    "The provided zip temporary directory is not valid.",
                    "wordpress-fontawesome-lib",
                ),
                ["zip_temp_dir" => $zip_temp_dir],
            );
        }

        $mkdir_result = $wp_filesystem->mkdir($destination_dir);

        if (is_wp_error($mkdir_result)) {
            $mkdir_result->add(
                "fontawesome_kit_assets_destination_dir_creation_failed",
                __(
                    "Failed to create the Font Awesome kit assets destination directory.",
                    "wordpress-fontawesome-lib",
                ),
                ["destination_dir" => $destination_dir],
            );
            return $mkdir_result;
        }

        $zip_file_path = trailingslashit($zip_temp_dir) . "kit.zip";

        if (!class_exists("ZipArchive")) {
            return new WP_Error(
                "fontawesome_ziparchive_class_not_found",
                __(
                    "The PHP ZipArchive class is not available. Please ensure the PHP zip extension is installed and enabled.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        $zip = new \ZipArchive();
        $dirs_for_extraction = ["css", "webfonts", "metadata"];

        if (
            !$wp_filesystem->is_readable($zip_file_path) ||
            $zip->open($zip_file_path) !== true
        ) {
            return new WP_Error(
                "fontawesome_invalid_zip_file",
                __(
                    "The Font Awesome kit zip file is not readable.",
                    "wordpress-fontawesome-lib",
                ),
                ["zip_file_path" => $zip_file_path],
            );
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            foreach ($dirs_for_extraction as $dir) {
                if (strpos($entry, trailingslashit($dir)) === 0) {
                    if (!$zip->extractTo($zip_temp_dir, [$entry])) {
                        return new WP_Error(
                            "fontawesome_zip_extraction_failed",
                            __(
                                "Failed to extract entry from Font Awesome kit zip file.",
                                "wordpress-fontawesome-lib",
                            ),
                            [
                                "entry" => $entry,
                                "zip_file_path" => $zip_file_path,
                            ],
                        );
                    }
                    break;
                }
            }
        }

        $zip->close();

        $staging_dir = trailingslashit($zip_temp_dir) . "staging/";

        $mkdir_result = $wp_filesystem->mkdir($staging_dir);

        if (is_wp_error($mkdir_result)) {
            $mkdir_result->add(
                "fontawesome_kit_staging_dir_creation_failed",
                __(
                    "Failed to create the Font Awesome kit assets staging directory.",
                    "wordpress-fontawesome-lib",
                ),
                ["staging_dir" => $staging_dir],
            );
            return $mkdir_result;
        }

        foreach (["css", "webfonts"] as $dir) {
            $source = trailingslashit($zip_temp_dir) . $dir;
            $destination = trailingslashit($staging_dir) . $dir;
            $move_result = \move_dir($source, $destination);
            if (is_wp_error($move_result)) {
                return $move_result;
            }
        }

        /*
        build_metadata_json_assets(
            $upload_dir,
            $fa_version,
            trailingslashit($temp_dir) . "$prefix/metadata/icon-families.json",
        );

        $wp_filesystem->delete($temp_dir, true);
        */
        return true;
    }

    function build_metadata_json_assets(
        $wp_filesystem,
        $source_metadata_dir,
        $assets_staging_dir,
    ) {
        $icon_families_json_path =
            trailingslashit($source_metadata_dir) . "icon-families.json";

        if (
            !$wp_filesystem->is_readable($icon_families_json_path) ||
            !$wp_filesystem->is_file($icon_families_json_path)
        ) {
            return new WP_Error(
                "fontawesome_invalid_icon_families_json_file",
                __(
                    "The metadata/icon-families.json file from the Font Awesome kit download is not readable.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        $icon_families_json_str = $wp_filesystem->get_contents(
            $icon_families_json_path,
        );

        if (!$icon_families_json_str) {
            return new WP_Error(
                "fontawesome_failed_reading_icon_families_json_file",
                __(
                    "Failed reading the metadata/icon-families.json file from the Font Awesome kit download.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        $svg_objects_dir =
            trailingslashit($assets_staging_dir) . "/svg-objects";

        $icon_families_data = json_decode($icon_families_json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                "fontawesome_icon_families_json_parse_error",
                __(
                    "Failed parsing the metadata/icon-families.json file from the Font Awesome kit download.",
                    "wordpress-fontawesome-lib",
                ),
            );
        }

        /*
        $icons_by_shorthand = [];

        foreach ($icon_families_data as $icon_name => $icon_data) {
            foreach ($icon_data["svgs"] as $family => $style_map) {
                foreach ($style_map as $style => $svg_data) {
                    $svg_object = [
                        "width" => $svg_data["width"],
                        "height" => $svg_data["height"],
                        "path" => $svg_data["path"],
                    ];

                    $style_shorthand = get_style_shorthand($family, $style);
                    $svg_object_json = json_encode($svg_object);
                    $family_style_dir =
                        trailingslashit($svg_objects_dir) . $style_shorthand;

                    if (!wp_mkdir_p($family_style_dir)) {
                        throw new Exception(
                            esc_html__(
                                "Failed creating a directory for self-hosted assets. Contact your WordPress server administrator.",
                                "font-awesome",
                            ),
                        );
                    }

                    $icon_file_path =
                        trailingslashit($family_style_dir) . "$icon_name.json";

                    if (
                        !$wp_filesystem->put_contents(
                            $icon_file_path,
                            $svg_object_json,
                            FS_CHMOD_FILE,
                        )
                    ) {
                        throw new Exception(
                            esc_html__(
                                "Failed creating an svg-objects file.",
                                "font-awesome",
                            ),
                        );
                    }

                    if (!isset($icons_by_shorthand[$style_shorthand])) {
                        $icons_by_shorthand[$style_shorthand] = [];
                    }

                    // must quote icon names in case some are numeric.
                    $icons_by_shorthand[$style_shorthand][] = "$icon_name";
                }
            }
        }

        foreach ($icons_by_shorthand as $style_shorthand => $icon_names) {
            $icon_names_json = json_encode(["icons" => $icon_names]);
            $file_name = "$style_shorthand.js";
            $metadata_file_path = build_metadata_disk_path(
                $upload_dir,
                $fa_version,
                $file_name,
            );

            if (
                !$wp_filesystem->put_contents(
                    $metadata_file_path,
                    $icon_names_json,
                    FS_CHMOD_FILE,
                )
            ) {
                throw new Exception(
                    "failed creating metadata file: $metadata_file_path",
                );
            }
        }
        */
    }
}
