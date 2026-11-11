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

namespace filter_powerbi\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use filter_powerbi\local\api;
use filter_powerbi\local\embed;

/**
 * Embed token and settings of a report placed in content the user can see.
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_embed extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context the content with the report is shown in'),
            'reference' => new external_value(PARAM_RAW, 'Signed reference written by the filter'),
        ]);
    }

    /**
     * Get the embed token.
     *
     * @param int $contextid
     * @param string $reference
     * @return array
     */
    public static function execute(int $contextid, string $reference): array {
        global $USER;

        ['contextid' => $contextid, 'reference' => $reference] = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'reference' => $reference]
        );
        $context = \core\context::instance_by_id($contextid);
        self::validate_context($context);

        $embed = embed::from_reference($reference, $context);
        if (!$embed) {
            throw new \moodle_exception('error:badreference', 'filter_powerbi');
        }

        $report = api::get_report($embed->groupid, $embed->reportid);
        $identity = $embed->rlsrole !== '' ? embed::get_user_value($USER) : '';
        $token = api::generate_token($report, $identity, $embed->rlsrole);

        $filtervalues = $embed->get_filter_values($context, $USER);
        return [
            'reportid' => (string) $report['id'],
            'embedurl' => (string) $report['embedUrl'],
            'token' => $token['token'],
            'expiration' => $token['expiration'],
            'pagename' => $embed->pagename,
            'navpane' => $embed->navpane,
            'filterpane' => $embed->filterpane,
            'filtertable' => $filtervalues !== null ? $embed->filtertable : '',
            'filtercolumn' => $filtervalues !== null ? $embed->filtercolumn : '',
            // No match shows no data, never all of it.
            'filtervalues' => $filtervalues === null ? [] : ($filtervalues ?: ['']),
            'language' => self::get_locale(current_language()),
        ];
    }

    /**
     * Power BI locale of a Moodle language: "es" stays "es", "pt_br" becomes "pt-BR".
     *
     * @param string $lang
     * @return string
     */
    public static function get_locale(string $lang): string {
        $parts = explode('_', clean_param($lang, PARAM_ALPHANUMEXT));
        $locale = strtolower($parts[0]);
        if (isset($parts[1]) && preg_match('/^[a-z]{2}$/i', $parts[1])) {
            $locale .= '-' . strtoupper($parts[1]);
        }
        return $locale;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reportid' => new external_value(PARAM_ALPHANUMEXT, 'Report id'),
            'embedurl' => new external_value(PARAM_URL, 'Embed URL of the report'),
            'token' => new external_value(PARAM_RAW, 'Embed token'),
            'expiration' => new external_value(PARAM_INT, 'When the token expires (timestamp)'),
            'pagename' => new external_value(PARAM_ALPHANUMEXT, 'Page to open, empty for the default'),
            'navpane' => new external_value(PARAM_BOOL, 'Show the page navigation'),
            'filterpane' => new external_value(PARAM_BOOL, 'Show the filter pane'),
            'filtertable' => new external_value(PARAM_TEXT, 'Table filtered for this viewer, empty for none'),
            'filtercolumn' => new external_value(PARAM_TEXT, 'Column filtered for this viewer'),
            'filtervalues' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Value'),
                'Values of the filter for this viewer'
            ),
            'language' => new external_value(PARAM_TEXT, 'Locale of the viewer, for example es or pt-BR'),
        ]);
    }
}
