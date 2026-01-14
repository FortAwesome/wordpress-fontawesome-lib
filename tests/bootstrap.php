<?php
use Yoast\WPTestUtils\WPIntegration;

if (getenv("WP_PLUGIN_DIR") !== false) {
    define("WP_PLUGIN_DIR", getenv("WP_PLUGIN_DIR"));
} else {
    define("WP_PLUGIN_DIR", dirname(__DIR__, 3) . "/plugins");
}

$relative_yoast_bootstrap_path =
    "yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php";

$absolute_yoast_bootstrap_path =
    dirname(__DIR__) . "/vendor/" . $relative_yoast_bootstrap_path;

if (!file_exists($absolute_yoast_bootstrap_path)) {
    echo PHP_EOL,
        "ERROR: no yoast/wp-test-utils boostrap module could be found here: $absolute_yoast_bootstrap_path",
        PHP_EOL;
    exit(1);
}

require_once $absolute_yoast_bootstrap_path;

/*
 * Bootstrap WordPress. This will also load the Composer autoload file, the PHPUnit Polyfills
 * and the custom autoloader for the TestCase and the mock object classes.
 */
WPIntegration\bootstrap_it();

if (
    !defined("WP_PLUGIN_DIR") ||
    file_exists(WP_PLUGIN_DIR . "/index.php") === false
) {
    echo PHP_EOL,
        'ERROR: Please check whether the WP_PLUGIN_DIR environment variable is set and set to the correct value. The integration test suite won\'t be able to run without it.',
        PHP_EOL;
    exit(1);
}
