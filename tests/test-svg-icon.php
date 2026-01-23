<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;
use FontAwesomeLib\Svg_Icon;

class Svg_IconTest extends TestCase
{
    const VALID_MONOTONE_ICON_DATA = [
        'width' => 512,
        'height' => 512,
        'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
    ];

    const VALID_DUOTONE_ICON_DATA = [
        'width' => 640,
        'height' => 512,
        'path' => [
            'M224 256c70.7 0 128-57.3 128-128S294.7 0 224 0 96 57.3 96 128s57.3 128 128 128z',
            'M313.6 304h-16.7c-22.2 10.2-46.9 16-72.9 16s-50.6-5.8-72.9-16h-16.7C60.2 304 0 364.2 0 438.4V464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48v-25.6c0-74.2-60.2-134.4-134.4-134.4z'
        ]
    ];

    const VALID_DUOTONE_WITH_EMPTY_SECONDARY = [
        'width' => 512,
        'height' => 512,
        'path' => [
            '',
            'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
        ]
    ];

    const VALID_DUOTONE_WITH_EMPTY_PRIMARY = [
        'width' => 512,
        'height' => 512,
        'path' => [
            'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z',
            ''
        ]
    ];

    public function test_constructor_with_valid_monotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $this->assertTrue($icon instanceof Svg_Icon);
    }

    public function test_constructor_with_valid_duotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_ICON_DATA);
        $this->assertTrue($icon instanceof Svg_Icon);
    }

    public function test_constructor_with_non_array()
    {
        $icon = new Svg_Icon("not an array");
        $this->assertTrue($icon instanceof Svg_Icon);
        // Object should be created but with default values (zeros)
        $svg = $icon->stringify();
        $this->assertStringContainsString('viewBox="0 0 0 0"', $svg);
    }

    public function test_constructor_with_missing_width()
    {
        $icon_data = [
            'height' => 512,
            'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        $this->assertStringContainsString('viewBox="0 0 0 0"', $svg);
    }

    public function test_constructor_with_missing_height()
    {
        $icon_data = [
            'width' => 512,
            'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        $this->assertStringContainsString('viewBox="0 0 0 0"', $svg);
    }

    public function test_constructor_with_non_integer_width()
    {
        $icon_data = [
            'width' => "512",
            'height' => 512,
            'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        $this->assertStringContainsString('viewBox="0 0 0 0"', $svg);
    }

    public function test_constructor_with_non_integer_height()
    {
        $icon_data = [
            'width' => 512,
            'height' => "512",
            'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z'
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        $this->assertStringContainsString('viewBox="0 0 0 0"', $svg);
    }

    public function test_constructor_with_missing_path()
    {
        $icon_data = [
            'width' => 512,
            'height' => 512
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        // Should create an icon with viewBox but empty path
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg);
    }

    public function test_constructor_with_non_string_non_array_path()
    {
        $icon_data = [
            'width' => 512,
            'height' => 512,
            'path' => 12345
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();
        // Path should not be set
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg);
        $this->assertStringContainsString('d=""', $svg);
    }

    public function test_stringify_monotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify();

        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg);
        $this->assertStringContainsString(self::VALID_MONOTONE_ICON_DATA['path'], $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('fill:var(--fa-primary-color,currentColor)', $svg);
    }

    public function test_stringify_duotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_ICON_DATA);
        $svg = $icon->stringify();

        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 640 512"', $svg);
        $this->assertStringContainsString(self::VALID_DUOTONE_ICON_DATA['path'][0], $svg);
        $this->assertStringContainsString(self::VALID_DUOTONE_ICON_DATA['path'][1], $svg);
        $this->assertStringContainsString('fill:var(--fa-secondary-color,currentColor)', $svg);
        $this->assertStringContainsString('opacity:var(--fa-secondary-opacity,.4)', $svg);
        $this->assertStringContainsString('fill:var(--fa-primary-color,currentColor)', $svg);
        $this->assertStringContainsString('opacity:var(--fa-primary-opacity,1)', $svg);
    }

    public function test_stringify_with_class_option()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify(['class' => 'my-custom-class']);

        $this->assertStringContainsString('class="my-custom-class"', $svg);
    }

    public function test_stringify_with_empty_class_option()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify(['class' => '']);

        $this->assertStringNotContainsString('class=', $svg);
    }

    public function test_stringify_with_non_string_class_option()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify(['class' => 12345]);

        $this->assertStringNotContainsString('class=', $svg);
    }

    public function test_stringify_with_non_array_opts()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify("not an array");

        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_stringify_without_opts()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify();

        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('class=', $svg);
    }

    public function test_stringify_escapes_path_data()
    {
        $icon_data = [
            'width' => 512,
            'height' => 512,
            'path' => 'M256 <script>alert("xss")</script>'
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();

        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
    }

    public function test_stringify_escapes_class_attribute()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify(['class' => '"><script>alert("xss")</script><div class="']);

        $this->assertStringNotContainsString('<script>', $svg);
    }

    public function test_is_duotone_returns_false_for_monotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $this->assertFalse($icon->is_duotone());
    }

    public function test_is_duotone_returns_true_for_duotone_icon()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_ICON_DATA);
        $this->assertTrue($icon->is_duotone());
    }

    public function test_is_duotone_returns_true_for_duotone_with_empty_secondary()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_WITH_EMPTY_SECONDARY);
        $this->assertTrue($icon->is_duotone());
    }

    public function test_is_duotone_returns_true_for_duotone_with_empty_primary()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_WITH_EMPTY_PRIMARY);
        $this->assertTrue($icon->is_duotone());
    }

    public function test_duotone_icon_with_empty_secondary_renders_correctly()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_WITH_EMPTY_SECONDARY);
        $svg = $icon->stringify();

        $this->assertStringContainsString('fill:var(--fa-secondary-color,currentColor)', $svg);
        $this->assertStringContainsString('d=""', $svg);
        $this->assertStringContainsString(self::VALID_DUOTONE_WITH_EMPTY_SECONDARY['path'][1], $svg);
    }

    public function test_duotone_icon_with_empty_primary_renders_correctly()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_WITH_EMPTY_PRIMARY);
        $svg = $icon->stringify();

        $this->assertStringContainsString('fill:var(--fa-secondary-color,currentColor)', $svg);
        $this->assertStringContainsString(self::VALID_DUOTONE_WITH_EMPTY_PRIMARY['path'][0], $svg);
        // Primary path should be empty
        $this->assertStringContainsString('fill:var(--fa-primary-color,currentColor)', $svg);
    }

    public function test_constructor_with_array_path_containing_non_strings()
    {
        $icon_data = [
            'width' => 512,
            'height' => 512,
            'path' => [12345, 67890]
        ];
        $icon = new Svg_Icon($icon_data);
        $svg = $icon->stringify();

        // Neither path should be set since they're not strings
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg);
        $this->assertFalse($icon->is_duotone());
        // Should have empty path attributes
        $this->assertStringContainsString('d=""', $svg);
    }

    public function test_constructor_with_array_path_with_only_first_element()
    {
        $icon_data = [
            'width' => 512,
            'height' => 512,
            'path' => ['M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z']
        ];
        $icon = new Svg_Icon($icon_data);
        $this->assertTrue($icon->is_duotone());
        
        $svg = $icon->stringify();
        // Should render secondary path but no primary path
        $this->assertStringContainsString('fill:var(--fa-secondary-color,currentColor)', $svg);
        $this->assertStringContainsString($icon_data['path'][0], $svg);
    }

    public function test_multiple_icons_independence()
    {
        $icon1 = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $icon2 = new Svg_Icon(self::VALID_DUOTONE_ICON_DATA);

        $this->assertFalse($icon1->is_duotone());
        $this->assertTrue($icon2->is_duotone());

        $svg1 = $icon1->stringify(['class' => 'icon-1']);
        $svg2 = $icon2->stringify(['class' => 'icon-2']);

        $this->assertStringContainsString('class="icon-1"', $svg1);
        $this->assertStringContainsString('class="icon-2"', $svg2);
        $this->assertStringContainsString('viewBox="0 0 512 512"', $svg1);
        $this->assertStringContainsString('viewBox="0 0 640 512"', $svg2);
    }

    public function test_stringify_returns_valid_svg_structure()
    {
        $icon = new Svg_Icon(self::VALID_DUOTONE_ICON_DATA);
        $svg = $icon->stringify();

        // Test that the SVG has proper structure
        $this->assertMatchesRegularExpression('/<svg[^>]*>.*<\/svg>/', $svg);
        $this->assertMatchesRegularExpression('/<path[^>]*\/>/', $svg);
        
        // Count the number of path elements (should be 2 for duotone)
        $this->assertEquals(2, substr_count($svg, '<path'));
    }

    public function test_stringify_monotone_has_single_path()
    {
        $icon = new Svg_Icon(self::VALID_MONOTONE_ICON_DATA);
        $svg = $icon->stringify();

        // Count the number of path elements (should be 1 for monotone)
        $this->assertEquals(1, substr_count($svg, '<path'));
    }
}