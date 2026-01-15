<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Family_Style_Collection {

	protected $family_styles_by_prefix = [];

	/**
	 * Construct a new Family_Style_Collection object, using the given array of Family_Style objects or associative arrays.
	 *
	 * @param Family_Style[]|array[] $family_styles An optional array of Family_Style objects or associative arrays with keys 'family', 'style', and 'prefix'
	 */
	public function __construct( $family_styles = [] ) {
		if ( ! is_array( $family_styles ) ) {
			return;
		}

		foreach ( $family_styles as $family_style ) {
			$this->add_family_style( $family_style );
		}
	}
	/**
	 * @param Family_Style|array $family_style A Family_Style object or an associative array with keys 'family', 'style', and 'prefix'
	 * that are valid for creating a new Family_Style object.
	 * @see Family_Style::__construct
	 */
	public function add_family_style( $family_style ): void {
		if ( $family_style instanceof Family_Style ) {
			$this->family_styles_by_prefix[ $family_style->short_prefix_id() ] = $family_style;
		}

		if ( self::is_valid_family_style_array( $family_style ) ) {
			$family_style_object = new Family_Style(
				$family_style['family'],
				$family_style['style'],
				$family_style['prefix'],
			);

			$this->family_styles_by_prefix[ $family_style_object->short_prefix_id() ] = $family_style_object;
		}
	}

	/**
	 * Removes a Family_Style from the collection.
	 *
	 * @param Family_Style|array|string $family_style A Family_Style object, an associative array with the key 'prefix',
	 * or a string representing the short prefix ID of the Family_Style to remove.
	 */
	public function remove_family_style( $family_style ): void {
		if ( $family_style instanceof Family_Style ) {
			$short_prefix_id = $family_style->short_prefix_id();
		} elseif ( self::is_valid_family_style_array( $family_style ) ) {
			$short_prefix_id = $family_style['prefix'];
		} elseif ( is_string( $family_style ) ) {
			$short_prefix_id = $family_style;
		} else {
			return;
		}

		unset( $this->family_styles_by_prefix[ $short_prefix_id ] );
	}

	/**
	 * @return Family_Style[] An array of all Family_Style objects in the collection.
	 */
	public function family_styles(): array {
		return array_values( $this->family_styles_by_prefix );
	}

	/**
	 * @return array an array of associative arrays representing all Family_Style objects in the collection,
	 * ready for JSON serialization.
	 */
	public function family_styles_for_json(): array {
		return array_map(function ( Family_Style $family_style ) {
			return $family_style->to_array();
		}, $this->family_styles());
	}

	/**
	 * @param array $family_style an item to test for whether it is an associative array with keys 'family', 'style', and 'prefix'.
	 * @return bool true if given array is a valid family style array, that can be used to construct a Family_Style. false otherwise.
	 */
	private static function is_valid_family_style_array( $family_style ): bool {
		if ( ! is_array( $family_style ) ) {
			return false;
		}

		$required_keys = [ 'family', 'style', 'prefix' ];

		foreach ( $required_keys as $key ) {
			if (
				! array_key_exists( $key, $family_style ) ||
				! is_string( $family_style[ $key ] ) ||
				$family_style[ $key ] === ''
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $short_prefix_id The short prefix ID of the Family_Style to retrieve.
	 * @return Family_Style|null The Family_Style object with the given short prefix ID, or null if not found.
	 */
	public function get_by_short_prefix_id( $short_prefix_id ): ?Family_Style {
		if ( ! is_string( $short_prefix_id ) ) {
			return null;
		}

		return $this->family_styles_by_prefix[ $short_prefix_id ] ?? null;
	}
}
