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
     * It can always be used as part of a CSS class name when prepending the appropriate prefix (e.g., "fa-"
     * can be prepended to the shorthand "sharp-solid" to form the valid Font Awesome classname "fa-sharp-solid").
     *
     * Shorthands are also used in naming files and directories that contain assets for a given family style.
     *
     * Some noteworthy historical idiosyncrasies:
     *
     * - Some shorthands name only a style, and omit the family name. Those normally have a family name of "classic".
     *   For example, the "solid" shorthand refers to the "classic" family with the "solid" style.
     *
     * - The shorthand "duotone" refers to the "duotone" family with the "solid" style.
     *
     * - For custom icons (aka "icon uploads"), the family for monotone is "kit" and the family for duotone is "kit-duotone". The style for both is "custom".
     *   However, these don't have shorthands that follow the same convensions as the official family styles.
     *   In a kit download zip, for example, the monotone custom icons are referred to as "custom-icons", and the duotone custom icons are referrred to as "custom-icons-duotone".
     *   And the corresponding CSS classes are "fa-kit" and "fa-kit-duotone".
     */
    public static function normalize_family_style_shorthand(
        $family_styles,
        $family,
        $style,
    ): ?string {
        foreach ($family_styles as $fs) {
            if (
                isset($fs["family"]) &&
                isset($fs["style"]) &&
                $fs["family"] === $family &&
                $fs["style"] === $style
            ) {
                if ("classic" === $family) {
                    return $style;
                }

                if ("duotone" === $family && "solid" === $style) {
                    return "duotone";
                }

                return "$family-$style";
            }
        }

        if (
            ("kit" === $family || "kit-duotone" === $family) &&
            "custom" === $style
        ) {
            return "$family-$style";
        }

        return null;
    }

    /**
     * Convert a Font Awesome family style shorthand into a human readable label.
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @return string The human readable label for the given family style (e.g., "Thin", "Sharp Solid", "Duotone Regular", "Brands").
     */
    public static function convert_family_style_to_label(
        $family,
        $style,
    ): string {
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
}
