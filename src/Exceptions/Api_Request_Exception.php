<?php
namespace FontAwesomeLib\Exceptions;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

/**
 * Thrown when the WordPress server fails to issue a request to the main query
 * endpoint on Font Awesome API server.
 *
 * @since 0.1.0
 */
class Api_Request_Exception extends
    \FontAwesomeLib\Base\FontAwesome_Exception_Base {}
