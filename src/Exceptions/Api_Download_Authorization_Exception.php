<?php
namespace FontAwesomeLib\Exceptions;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

/**
 * Thrown when a request to create a kit download fails because the API token lacks
 * permission for that feature.
 *
 * @since 0.1.0
 */
class Api_Download_Authorization_Exception extends
    \FontAwesomeLib\Base\FontAwesome_Exception_Base {}
