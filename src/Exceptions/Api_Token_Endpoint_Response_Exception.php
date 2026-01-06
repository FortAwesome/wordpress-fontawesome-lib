<?php
namespace FontAwesomeLib\Exceptions;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

/**
 * Thrown when the Font Awesome API server returns a response error from its token endpoint.
 * This probably indicates an invalid API token or some other authentication issue.
 *
 * @since 0.1.0
 */
class Api_Token_Endpoint_Response_Exception extends
    \FontAwesomeLib\Base\FontAwesome_Exception_Base {}
