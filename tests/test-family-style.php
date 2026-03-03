<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Family_Style;

class Family_StyleTest extends TestCase {

	public function test_constructor_and_getters_return_expected_values() {
		$family_style = new Family_Style( 'sharp', 'solid', 'fass' );

		$this->assertInstanceOf( Family_Style::class, $family_style );
		$this->assertSame( 'sharp', $family_style->family() );
		$this->assertSame( 'solid', $family_style->style() );
		$this->assertSame( 'fass', $family_style->short_prefix_id() );
	}

	// =========================================================================
	// map_family_and_style_to_shorthand()
	// =========================================================================

	public function test_map_family_and_style_to_shorthand_classic_family_returns_style_only() {
		$this->assertSame(
			'solid',
			Family_Style::map_family_and_style_to_shorthand( 'classic', 'solid' )
		);

		$this->assertSame(
			'regular',
			Family_Style::map_family_and_style_to_shorthand( 'classic', 'regular' )
		);

		$this->assertSame(
			'brands',
			Family_Style::map_family_and_style_to_shorthand( 'classic', 'brands' )
		);
	}

	public function test_map_family_and_style_to_shorthand_duotone_family_with_solid_style_maps_to_duotone() {
		$this->assertSame(
			'duotone',
			Family_Style::map_family_and_style_to_shorthand( 'duotone', 'solid' )
		);
	}

	public function test_map_family_and_style_to_shorthand_non_classic_non_special_case_is_hyphenated() {
		$this->assertSame(
			'sharp-solid',
			Family_Style::map_family_and_style_to_shorthand( 'sharp', 'solid' )
		);

		$this->assertSame(
			'notdog-duo-regular',
			Family_Style::map_family_and_style_to_shorthand( 'notdog-duo', 'regular' )
		);
	}

	// =========================================================================
	// map_family_and_style_to_label()
	// =========================================================================

	public function test_map_family_and_style_to_label_classic_family_is_just_style_ucfirst() {
		$this->assertSame(
			'Solid',
			Family_Style::map_family_and_style_to_label( 'classic', 'solid' )
		);

		$this->assertSame(
			'Regular',
			Family_Style::map_family_and_style_to_label( 'classic', 'regular' )
		);
	}

	public function test_map_family_and_style_to_label_brands_style_is_brands_ucfirst_even_when_family_is_not_classic() {
		$this->assertSame(
			'Brands',
			Family_Style::map_family_and_style_to_label( 'classic', 'brands' )
		);
	}

	public function test_map_family_and_style_to_label_invalid_family_style() {
		$this->assertSame(
			'Foo Bar',
			Family_Style::map_family_and_style_to_label( 'foo', 'bar' )
		);
	}

	public function test_map_family_and_style_to_label_non_classic_splits_family_on_hyphens_and_appends_style_ucfirst() {
		$this->assertSame(
			'Sharp Solid',
			Family_Style::map_family_and_style_to_label( 'sharp', 'solid' )
		);

		$this->assertSame(
			'Kit Duotone Custom',
			Family_Style::map_family_and_style_to_label( 'kit-duotone', 'custom' )
		);

		$this->assertSame(
			'Notdog Duo Regular',
			Family_Style::map_family_and_style_to_label( 'notdog-duo', 'regular' )
		);
	}

	// =========================================================================
	// map_family_and_style_to_asset_file_stem()
	// =========================================================================

	public function test_map_family_and_style_to_asset_file_stem_classic_family_returns_style_only() {
		$this->assertSame(
			'solid',
			Family_Style::map_family_and_style_to_asset_file_stem( 'classic', 'solid' )
		);

		$this->assertSame(
			'brands',
			Family_Style::map_family_and_style_to_asset_file_stem( 'classic', 'brands' )
		);
	}

	public function test_map_family_and_style_to_asset_file_stem_duotone_family_with_solid_style_maps_to_duotone() {
		$this->assertSame(
			'duotone',
			Family_Style::map_family_and_style_to_asset_file_stem( 'duotone', 'solid' )
		);
	}

	public function test_map_family_and_style_to_asset_file_stem_when_family_style_is_invalid() {
		$this->assertSame(
			'foo-bar',
			Family_Style::map_family_and_style_to_asset_file_stem( 'foo', 'bar' )
		);
	}

	public function test_map_family_and_style_to_asset_file_stem_kit_custom_icons_mapped_to_custom_icons_stems() {
		$this->assertSame(
			'custom-icons',
			Family_Style::map_family_and_style_to_asset_file_stem( 'kit', 'custom' )
		);

		$this->assertSame(
			'custom-icons-duotone',
			Family_Style::map_family_and_style_to_asset_file_stem( 'kit-duotone', 'custom' )
		);
	}

	public function test_map_family_and_style_to_asset_file_stem_non_special_case_is_hyphenated() {
		$this->assertSame(
			'sharp-solid',
			Family_Style::map_family_and_style_to_asset_file_stem( 'sharp', 'solid' )
		);
	}

	// =========================================================================
	// Instance helpers: shorthand(), asset_file_stem(), label(), to_array()
	// =========================================================================

	public function test_instance_helpers_use_mapping_helpers() {
		$family_style = new Family_Style( 'sharp', 'solid', 'fass' );

		$this->assertSame( 'sharp-solid', $family_style->shorthand() );
		$this->assertSame( 'sharp-solid', $family_style->asset_file_stem() );
		$this->assertSame( 'Sharp Solid', $family_style->label() );
	}

	public function test_to_array_includes_expected_keys_and_values() {
		$family_style = new Family_Style( 'classic', 'solid', 'fas' );

		$this->assertSame(
			[
				'family' => 'classic',
				'style' => 'solid',
				'prefix' => 'fas',
				'shorthand' => 'solid',
				'asset_file_stem' => 'solid',
				'label' => 'Solid',
			],
			$family_style->to_array()
		);
	}

	// =========================================================================
	// Convenience constructors for kit custom icons
	// =========================================================================

	public function test_kit_custom_family_style_returns_expected_family_style_and_prefix() {
		$family_style = Family_Style::kit_custom_family_style();

		$this->assertInstanceOf( Family_Style::class, $family_style );
		$this->assertSame( 'kit', $family_style->family() );
		$this->assertSame( 'custom', $family_style->style() );
		$this->assertSame( 'fak', $family_style->short_prefix_id() );

		$this->assertSame( 'kit-custom', $family_style->shorthand() );
		$this->assertSame( 'custom-icons', $family_style->asset_file_stem() );
		$this->assertSame( 'Kit Custom', $family_style->label() );
	}

	public function test_kit_duotone_custom_family_style_returns_expected_family_style_and_prefix() {
		$family_style = Family_Style::kit_duotone_custom_family_style();

		$this->assertInstanceOf( Family_Style::class, $family_style );
		$this->assertSame( 'kit-duotone', $family_style->family() );
		$this->assertSame( 'custom', $family_style->style() );
		$this->assertSame( 'fakd', $family_style->short_prefix_id() );

		$this->assertSame( 'kit-duotone-custom', $family_style->shorthand() );
		$this->assertSame( 'custom-icons-duotone', $family_style->asset_file_stem() );
		$this->assertSame( 'Kit Duotone Custom', $family_style->label() );
	}
}
