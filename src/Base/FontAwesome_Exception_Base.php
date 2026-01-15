<?php
namespace FontAwesomeLib\Base;

// require_once ABSPATH . "wp-includes/class-wp-error.php";

use Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

/**
 * An abstract class defining common exception behavior.
 */
class FontAwesome_Exception_Base extends Exception {

	/**
	 * A WP_Error object that was the occasion for this exception.
	 */
	protected $wp_error = null;

	/**
	 * An HTTP response array that is the occasion for this exception.
	 * Array keys should be like an array that would be returned from wp_remote_post().
	 */
	protected $wp_response = null;

	/**
	 * Construct an exception that includes a WP_Error that is the cause of the exception.
	 *
	 * @since 0.1.0
	 */
	public static function with_wp_error( $wp_error ): self {
		// This is how we invoke the derived class's constructor from an inherited static method.
		$obj = new static();

		if ( ! is_null( $wp_error ) && is_a( $wp_error, 'WP_Error' ) ) {
			$obj->wp_error = $wp_error;
		}

		return $obj;
	}

	/**
	 * Construct an exception with an associated HTTP response, the cause of the exception.
	 *
	 * @param $wp_reponse a response array as would be returned by wp_remote_post()
	 *   with keys like: 'headers', 'body', 'response'
	 */
	public static function with_wp_response( $wp_response ): self {
		// This is how we invoke the derived class's constructor from an inherited static method.
		$obj = new static();

		if (
			! is_null( $wp_response ) &&
			is_array( $wp_response ) &&
			isset( $wp_response['headers'] ) &&
			isset( $wp_response['body'] ) &&
			isset( $wp_response['response'] )
		) {
			$obj->wp_response = $wp_response;
		}

		return $obj;
	}

	/**
	 * Construct an exception with a previously thrown Error or Exception.
	 *
	 * @param $e Error or Exception
	 */
	public static function with_thrown( $e ): self {
		return new static( null, 0, $e );
	}

	/**
	 * The WP_Error associated with this exception, if any.
	 *
	 * @since 0.1.0
	 * @return null|WP_Error
	 */
	public function get_wp_error(): ?WP_Error {
		return $this->wp_error;
	}

	/**
	 * The response object associated with this exception, if any.
	 *
	 * @since 4.0.0
	 * @return null|array a response array as would be returned by wp_remote_post()
	 *   with keys like: 'headers', 'body', 'response'.
	 */
	public function get_wp_response() {
		return $this->wp_response;
	}
}
