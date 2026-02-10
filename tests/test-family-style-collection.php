<?php

declare(strict_types=1);

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Family_Style;
use FontAwesomeLib\Family_Style_Collection;

class Family_Style_CollectionTest extends TestCase {

	public function test_construct_with_non_array_is_noop_and_collection_is_empty(): void {
		$collection = new Family_Style_Collection( 'not-an-array' );

		$this->assertSame( [], $collection->family_styles() );
		$this->assertSame( [], $collection->family_styles_for_json() );
	}

	public function test_construct_with_family_style_objects_populates_collection_keyed_by_prefix(): void {
		$fs1 = new Family_Style( 'classic', 'solid', 'fas' );
		$fs2 = new Family_Style( 'sharp', 'solid', 'fass' );

		$collection = new Family_Style_Collection( [ $fs1, $fs2 ] );

		$this->assertCount( 2, $collection->family_styles() );

		$this->assertSame( $fs1, $collection->get_by_short_prefix_id( 'fas' ) );
		$this->assertSame( $fs2, $collection->get_by_short_prefix_id( 'fass' ) );
		$this->assertNull( $collection->get_by_short_prefix_id( 'does-not-exist' ) );
	}

	public function test_construct_with_associative_arrays_creates_family_style_objects(): void {
		$collection = new Family_Style_Collection(
			[
				[
					'family' => 'classic',
					'style'  => 'solid',
					'prefix' => 'fas',
				],
				[
					'family' => 'sharp',
					'style'  => 'solid',
					'prefix' => 'fass',
				],
			]
		);

		$this->assertInstanceOf( Family_Style::class, $collection->get_by_short_prefix_id( 'fas' ) );
		$this->assertInstanceOf( Family_Style::class, $collection->get_by_short_prefix_id( 'fass' ) );

		$this->assertSame( 'classic', $collection->get_by_short_prefix_id( 'fas' )->family() );
		$this->assertSame( 'solid', $collection->get_by_short_prefix_id( 'fas' )->style() );
		$this->assertSame( 'fas', $collection->get_by_short_prefix_id( 'fas' )->short_prefix_id() );

		$this->assertSame( 'sharp', $collection->get_by_short_prefix_id( 'fass' )->family() );
		$this->assertSame( 'solid', $collection->get_by_short_prefix_id( 'fass' )->style() );
		$this->assertSame( 'fass', $collection->get_by_short_prefix_id( 'fass' )->short_prefix_id() );
	}

	public function test_add_family_style_accepts_object_and_replaces_existing_with_same_prefix(): void {
		$collection = new Family_Style_Collection();

		$original = new Family_Style( 'classic', 'solid', 'fas' );
		$replacement = new Family_Style( 'classic', 'regular', 'fas' );

		$collection->add_family_style( $original );
		$this->assertSame( $original, $collection->get_by_short_prefix_id( 'fas' ) );

		$collection->add_family_style( $replacement );
		$this->assertSame( $replacement, $collection->get_by_short_prefix_id( 'fas' ) );

		$this->assertCount( 1, $collection->family_styles() );
	}

	public function test_add_family_style_accepts_valid_array_and_ignores_invalid_arrays(): void {
		$collection = new Family_Style_Collection();

		$collection->add_family_style(
			[
				'family' => 'classic',
				'style'  => 'solid',
				'prefix' => 'fas',
			]
		);

		$this->assertInstanceOf( Family_Style::class, $collection->get_by_short_prefix_id( 'fas' ) );
		$this->assertCount( 1, $collection->family_styles() );

		// Missing key.
		$collection->add_family_style(
			[
				'family' => 'classic',
				'style'  => 'solid',
			]
		);

		// Empty string values not allowed.
		$collection->add_family_style(
			[
				'family' => '',
				'style'  => 'solid',
				'prefix' => 'fa-empty',
			]
		);

		// Wrong types not allowed.
		$collection->add_family_style(
			[
				'family' => 'classic',
				'style'  => 'solid',
				'prefix' => 123,
			]
		);

		$this->assertCount( 1, $collection->family_styles() );
		$this->assertNull( $collection->get_by_short_prefix_id( 'fa-empty' ) );
	}

	public function test_remove_family_style_accepts_object_array_or_prefix_string(): void {
		$fs1 = new Family_Style( 'classic', 'solid', 'fas' );
		$fs2 = new Family_Style( 'sharp', 'solid', 'fass' );

		$collection = new Family_Style_Collection( [ $fs1, $fs2 ] );
		$this->assertCount( 2, $collection->family_styles() );

		$collection->remove_family_style( $fs1 );
		$this->assertNull( $collection->get_by_short_prefix_id( 'fas' ) );
		$this->assertSame( $fs2, $collection->get_by_short_prefix_id( 'fass' ) );
		$this->assertCount( 1, $collection->family_styles() );

		$collection->remove_family_style(
			[
				'family' => 'sharp',
				'style'  => 'solid',
				'prefix' => 'fass',
			]
		);
		$this->assertNull( $collection->get_by_short_prefix_id( 'fass' ) );
		$this->assertCount( 0, $collection->family_styles() );

		// Removing a non-existent prefix should be harmless.
		$collection->remove_family_style( 'does-not-exist' );
		$this->assertCount( 0, $collection->family_styles() );
	}

	public function test_get_by_short_prefix_id_returns_null_for_non_string_input(): void {
		$collection = new Family_Style_Collection(
			[
				[
					'family' => 'classic',
					'style'  => 'solid',
					'prefix' => 'fas',
				],
			]
		);

		$this->assertNull( $collection->get_by_short_prefix_id( null ) );
		$this->assertNull( $collection->get_by_short_prefix_id( 123 ) );
		$this->assertNull( $collection->get_by_short_prefix_id( [ 'fas' ] ) );

		$this->assertInstanceOf( Family_Style::class, $collection->get_by_short_prefix_id( 'fas' ) );
	}

	public function test_family_styles_for_json_returns_arrays_from_family_style_to_array(): void {
		$collection = new Family_Style_Collection(
			[
				[
					'family' => 'classic',
					'style'  => 'solid',
					'prefix' => 'fas',
				],
				[
					'family' => 'sharp',
					'style'  => 'solid',
					'prefix' => 'fass',
				],
			]
		);

		$json_ready = $collection->family_styles_for_json();

		$this->assertIsArray( $json_ready );
		$this->assertCount( 2, $json_ready );

		foreach ( $json_ready as $item ) {
			$this->assertIsArray( $item );

			$this->assertArrayHasKey( 'family', $item );
			$this->assertArrayHasKey( 'style', $item );
			$this->assertArrayHasKey( 'prefix', $item );

			$this->assertArrayHasKey( 'shorthand', $item );
			$this->assertArrayHasKey( 'asset_file_stem', $item );
			$this->assertArrayHasKey( 'label', $item );

			$this->assertIsString( $item['family'] );
			$this->assertIsString( $item['style'] );
			$this->assertIsString( $item['prefix'] );
		}

		$prefixes = array_values(
			array_filter(
				array_map(
					static function ( array $item ): string {
						return $item['prefix'];
					},
					$json_ready
				)
			)
		);

		$this->assertContains( 'fas', $prefixes );
		$this->assertContains( 'fass', $prefixes );
	}
}