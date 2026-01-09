<?php
declare(strict_types=1);

namespace FontAwesomeLib;

if (!defined("ABSPATH")) {
    exit(); // Exit if accessed directly.
}

class Svg_Icon
{
    protected $view_box_width = 0;
    protected $view_box_height = 0;
    protected $primary_path = null;
    protected $secondary_path = null;

    /**
     * Constructs a new Svg_Icon object from the given icon data.
     * @param array $icon_data An associative array containing the icon data.
     *
     * Expected keys:
     *  - width: int. The width of the icon's view box.
     *  - height: int. The height of the icon's view box.
     *  - path: string|array. The SVG path data. If an array, paths are in descending layer order.
     *    So if it's a duotone icon with two paths, then the secondary path is first (at index 0),
     *    then the primary path at index 1.
     *
     * It's valid for a duotone icon to have only a primary path, or only a secondary path. In such cases,
     * the missing path must be an empty string.
     *
     * This is significant because an icon with a null secondary layer is considered a monotone icon,
     * whereas an icon with a secondary layer that is an empty string is considered a duotone icon with
     * an empty secondary layer.
     */
    public function __construct($icon_data)
    {
        if (!is_array($icon_data)) {
            return;
        }

        if (
            !isset($icon_data["width"]) ||
            !is_integer($icon_data["width"]) ||
            !isset($icon_data["height"]) ||
            !is_integer($icon_data["height"])
        ) {
            return "";
        }

        $this->view_box_width = $icon_data["width"];
        $this->view_box_height = $icon_data["height"];

        $path_data = $icon_data["path"] ?? null;

        if (is_string($path_data)) {
            $this->primary_path = $path_data;
        } elseif (is_array($path_data)) {
            if (isset($path_data[0]) && is_string($path_data[0])) {
                $this->secondary_path = $path_data[0];
            }

            if (isset($path_data[1]) && is_string($path_data[1])) {
                $this->primary_path = $path_data[1];
            }
        }
    }

    /**
     * Returns the SVG string representation of this icon.
     * @param array $opts (optional) options for stringification.
     *  - class: string (optional). An optional CSS class to add to the SVG element.
     * @return string The SVG string representation of this icon.
     */
    public function stringify($opts = []): string
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d"',
            \esc_attr($this->view_box_width),
            \esc_attr($this->view_box_height),
        );

        $class = null;

        if (
            is_array($opts) &&
            isset($opts["class"]) &&
            is_string($opts["class"])
        ) {
            $svg .= sprintf(' class="%s"', \esc_attr($opts["class"]));
        }

        $svg .= ">";

        if ($this->is_duotone()) {
            $svg .= sprintf(
                '<path style="fill:var(--fa-secondary-color,currentColor);opacity:var(--fa-secondary-opacity,.4)" d="%s"/>',
                \esc_attr($this->secondary_path),
            );
        }

        $svg .= sprintf(
            '<path style="fill:var(--fa-primary-color,currentColor);opacity:var(--fa-primary-opacity,1)" d="%s"/>',
            \esc_attr($this->primary_path),
        );

        $svg .= "</svg>";

        return $svg;
    }

    /**
     * Returns true if this icon is a duotone icon (i.e., has both primary and secondary paths), false otherwise.
     * @return bool
     */
    public function is_duotone(): bool
    {
        return $this->secondary_path !== null;
    }
}
