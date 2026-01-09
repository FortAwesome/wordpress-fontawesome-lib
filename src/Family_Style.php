<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

class Family_Style
{
    protected $family = "";
    protected $style = "";
    protected $short_prefix_id = "";

    /**
     * Construct a new Family_Style object.
     * Family and style names must be lower-case and must match the actual values used in the Font Awesome system.
     *
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * This must be a valid family in the Font Awesome system, as would be found when selecting a [FamilyStyle](See https://docs.fontawesome.com/apis/graphql/objects#familystyle)
     * object from the GraphQL API. Notable special cases:
     *
     * 1. The legacy styles like "solid", "regular", and "brands" formally belong to the "classic" family.
     * 2. The legacy style known as simply "duotone" is now the "solid" style in the "duotone" family.
     * 3. As of Font Awesome 7, the style originally known simply as "brands", is also a member of the "classic" family.
     * 4. As of Font Awesome 7, the family for monotone custom icons is "kit", and the family for duotone custom icons is "kit-duotone". The style for both is "custom".
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @param string $short_prefix_id The short prefix ID (e.g., "fas", "far", "fasl", "fak").
     */
    public function __construct($family, $style, $short_prefix_id)
    {
        $this->family = $family;
        $this->style = $style;
        $this->short_prefix_id = $short_prefix_id;
    }

    /**
     * Get the Font Awesome family (e.g., "classic", "sharp", "notdog-duo").
     * @@return string family
     */
    public function family(): string
    {
        return $this->family;
    }

    /**
     * Get the Font Awesome style (e.g., "solid", "regular", "semibold").
     * @@return string style
     */
    public function style(): string
    {
        return $this->style;
    }

    /**
     * Get the Font Awesome short_prefix_id (aka "prefix") (e.g., "fas", "fasl", "fak").
     * @@return string short_prefix_id
     */
    public function short_prefix_id(): string
    {
        return $this->short_prefix_id;
    }

    /**
     * Get the Font Awesome family style shorthand (e.g., "sharp-solid", "solid", "duotone").
     * @see Family_Style::map_family_style_to_shorthand()
     * @return string shorthand
     */
    public function shorthand(): string
    {
        return self::map_family_and_style_to_shorthand(
            $this->family,
            $this->style,
        );
    }

    /**
     * Get a asset file stem that would corresopnd to this Family_Style.
     * @see Family_Style::map_family_style_to_asset_file_stem()
     * @return string asset file stem
     */
    public function asset_file_stem(): string
    {
        return self::map_family_and_style_to_asset_file_stem(
            $this->family,
            $this->style,
        );
    }

    /**
     * Get the human readable label for this Font Awesome family style (e.g., "Sharp Solid", "Duotone Regular", "Brands").
     * @see Family_Style::map_family_style_to_label()
     * @return string label
     */
    public function label(): string
    {
        return self::map_family_and_style_to_label($this->family, $this->style);
    }

    /**
     * Convert this Family_Style to an associative array.
     * @return array{family: string, style: string, prefix: string, shorthand: string, asset_file_stem: string, label: string}
     */
    public function to_array(): array
    {
        return [
            "family" => $this->family,
            "style" => $this->style,
            "prefix" => $this->short_prefix_id,
            "shorthand" => $this->shorthand(),
            "asset_file_stem" => $this->asset_file_stem(),
            "label" => $this->label(),
        ];
    }

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
    public static function map_family_and_style_to_shorthand(
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
    public static function map_family_and_style_to_label(
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

    /**
     * Map a Font Awesome family style to the corresponding asset file stem.
     *
     * This handles special cases for classic, duotone, and custom icons.
     *
     * @param string $family The Font Awesome family (e.g., "sharp", "classic").
     * @param string $style The Font Awesome style (e.g., "solid", "regular").
     * @return string The asset file stem for the given family style (e.g., "sharp-solid", "solid", "duotone", "custom-icons", "custom-icons-duotone").
     */
    public static function map_family_and_style_to_asset_file_stem(
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

    /**
     * A static helper to get the family style info for monotone kit custom icons.
     * @return Family_Style
     */
    public static function kit_custom_family_style()
    {
        return new Family_Style("kit", "custom", "fak");
    }

    /**
     * A static helper to get the family style info for duotone kit custom icons.
     * @return Family_Style
     */
    public static function kit_duotone_custom_family_style()
    {
        return new Family_Style("kit-duotone", "custom", "fakd");
    }
}
