<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

class Metadata
{
    /**
     * A "shorthand" is a human readable representation of a Font Awesome family style. It may be hyphenated,
     * but never contains spaces. It uniquely identifies a family style within any given Font Awesome release.
     *
     * Shorthands are used in naming files and directories that contain assets for a given family style.
     *
     * Some noteworthy historical idiosyncrasies:
     *
     * - Some shorthands name only a style, and omit the family name. In those cases, the implied family is "classic".
     *   For example, the "solid" shorthand implies the "classic" family.
     *
     * - The shorthand "duotone" refers to the "duotone" family with the "solid" style.
     *
     * - For custom icons (aka "icon uploads"), the family for monotone is "kit" and the family for duotone is "kit-duotone". The style for both is "custom".
     *   However, these don't have shorthands that follow the same conventions as the official family styles.
     *   In a kit download zip, for example, the monotone custom icons are referred to as "custom-icons", and the duotone custom icons are referrred to as "custom-icons-duotone".
     *   And the corresponding CSS classes are "fa-kit" and "fa-kit-duotone".
     *
     * This function does not validate the existence of the given family style in any particular Font Awesome release.
     * It merely constructs the shorthand according to the established conventions.
     *
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @return string The normalized family style shorthand (e.g., "sharp-solid", "solid", "duotone")
     */
    public static function map_family_style_to_shorthand(
        $family,
        $style,
    ): string {
        if ("classic" === $family) {
            return $style;
        }

        if ("duotone" === $family && "solid" === $style) {
            return "duotone";
        }

        return "$family-$style";
    }

    /**
     * Map a Font Awesome family style shorthand into a human readable label.
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @return string The human readable label for the given family style (e.g., "Thin", "Sharp Solid", "Duotone Regular", "Brands").
     */
    public static function map_family_style_to_label($family, $style): string
    {
        if ("classic" === $family) {
            return ucfirst($style);
        }

        if ("brands" === $style) {
            return ucfirst($style);
        }

        $family_parts = explode("-", $family);
        $family_label_parts = array_map(function ($part) {
            return ucfirst($part);
        }, $family_parts);

        return implode(" ", $family_label_parts) . " " . ucfirst($style);
    }

    /**
     * Map a Font Awesome family style to the corresponding asset file stem.
     *
     * This handles special cases for classic, duotone, and custom icons.
     *
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @return string The asset file stem for the given family style (e.g., "sharp-solid", "solid", "duotone", "custom-icons", "custom-icons-duotone").
     */
    public static function map_family_style_to_asset_file_stem(
        $family,
        $style,
    ): string {
        if ("classic" === $family) {
            return $style;
        }

        if ("duotone" === $family && "solid" === $style) {
            return "duotone";
        }

        if ("kit" === $family) {
            return "custom-icons";
        }

        if ("kit-duotone" === $family) {
            return "custom-icons-duotone";
        }

        return "$family-$style";
    }

    public static function map_short_prefix_id_to_family_style(
        $family_styles,
        $short_prefix_id,
    ): array {
        if ($short_prefix_id === "fak") {
            return self::kit_custom_family_style();
        }

        if ($short_prefix_id === "fakd") {
            return self::kit_duotone_custom_family_style();
        }

        foreach ($family_styles as $family_style) {
            if (
                isset($family_style["prefix"]) &&
                $family_style["prefix"] === $short_prefix_id
            ) {
                return $family_style;
            }
        }
    }

    public static function kit_custom_family_style()
    {
        return self::family_style_from_family_and_style_and_prefix(
            "kit",
            "custom",
            "fak",
        );
    }

    public static function kit_duotone_custom_family_style()
    {
        return self::family_style_from_family_and_style_and_prefix(
            "kit-duotone",
            "custom",
            "fakd",
        );
    }

    private static function family_style_from_family_and_style_and_prefix(
        $family,
        $style,
        $prefix,
    ) {
        return [
            "family" => $family,
            "style" => $style,
            "shorthand" => "$family-$style",
            "label" => Metadata::map_family_style_to_label($family, $style),
            "prefix" => $prefix,
        ];
    }
}
