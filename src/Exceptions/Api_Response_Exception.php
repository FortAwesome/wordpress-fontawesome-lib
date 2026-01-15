<?php
namespace FontAwesomeLib\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

/**
 * Thrown when a response from the Font Awesome API server is not successful.
 *
 * @since 0.1.0
 */
class Api_Response_Exception extends \FontAwesomeLib\Base\FontAwesome_Exception_Base {}
