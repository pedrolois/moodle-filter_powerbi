<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_powerbi;

use filter_powerbi\local\embed;

/**
 * Turns links to Power BI reports marked with the class "powerbi-embed" into live, interactive reports.
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /** @var bool Whether the page already loads the script for embedded reports. */
    private static bool $jsloaded = false;

    /** @var bool Whether the page already loads the full screen button. */
    private static bool $fullscreenloaded = false;

    /**
     * Replace the marked links.
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    public function filter($text, array $options = []) {
        if (!is_string($text) || stripos($text, 'powerbi-embed') === false) {
            return $text;
        }
        $text = preg_replace_callback(
            '~<a\s[^>]*\bclass\s*=\s*["\'][^"\']*\bpowerbi-embed\b[^"\']*["\'][^>]*>(.*?)</a>~is',
            fn(array $match) => $this->render_link($match[0], $match[1]) ?? $match[0],
            $text
        );
        // A report alone in its paragraph replaces the paragraph (a figure cannot be inside a <p>).
        return preg_replace('~<p\b[^>]*>\s*(<figure class="filter-powerbi".*?</figure>)\s*</p>~is', '$1', $text);
    }

    /**
     * Render one marked link as a report.
     *
     * @param string $tag The whole <a> element.
     * @param string $label Its content.
     * @return string|null Null to leave the link as it is.
     */
    private function render_link(string $tag, string $label): ?string {
        global $OUTPUT, $PAGE, $USER;

        if (!preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $href)) {
            return null;
        }
        $embed = embed::from_url($href[2]);
        if (!$embed) {
            return null;
        }
        $title = trim(strip_tags($label)) ?: get_string('defaulttitle', 'filter_powerbi');
        $data = [
            'title' => $title,
            'height' => $embed->height,
            'href' => html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5),
        ];

        if ($embed->get_mode() === embed::MODE_EMBEDDED) {
            if (!local\api::is_configured()) {
                $data['error'] = get_string('error:notconfigured', 'filter_powerbi');
            } else {
                $data['embedded'] = true;
                $data['contextid'] = $this->context->id;
                $data['reference'] = $embed->get_reference($this->context);
                if (!self::$jsloaded) {
                    $PAGE->requires->js_call_amd('filter_powerbi/embed', 'init');
                    self::$jsloaded = true;
                }
            }
        } else {
            $data['src'] = $embed->get_secure_url($this->context, $USER)->out(false);
        }
        if (empty($data['error']) && !self::$fullscreenloaded) {
            $PAGE->requires->js_call_amd('filter_powerbi/fullscreen', 'init');
            self::$fullscreenloaded = true;
        }
        return $OUTPUT->render_from_template('filter_powerbi/embed', $data);
    }
}
