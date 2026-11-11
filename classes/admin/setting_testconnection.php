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

namespace filter_powerbi\admin;

use filter_powerbi\local\api;

/**
 * "Save and test the connection" button on the settings page.
 *
 * The button submits the settings form like "Save changes", so the values just typed are saved first; the page then
 * shows the result of the test next to the button.
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_testconnection extends \admin_setting {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'filter_powerbi/testconnection',
            get_string('testconnection', 'filter_powerbi'),
            get_string('testconnection_desc', 'filter_powerbi'),
            ''
        );
    }

    /**
     * Nothing is stored.
     *
     * @return string
     */
    public function get_setting() {
        return '';
    }

    /**
     * The button was clicked: test once the settings are saved and the page is shown again.
     *
     * @param mixed $data
     * @return string Empty: never an error.
     */
    public function write_setting($data) {
        global $SESSION;
        if ($data === '1') {
            $SESSION->filter_powerbi_testconnection = true;
        }
        return '';
    }

    /**
     * The button, and the result of the last test.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $SESSION;

        $html = \html_writer::tag('button', get_string('testconnection:button', 'filter_powerbi'), [
            'type' => 'submit',
            'name' => $this->get_full_name(),
            'value' => '1',
            'class' => 'btn btn-secondary',
        ]);

        if (!empty($SESSION->filter_powerbi_testconnection)) {
            unset($SESSION->filter_powerbi_testconnection);
            $html .= \html_writer::div($this->render_result(), 'mt-3');
        }
        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }

    /**
     * Run the test and render its result.
     *
     * @return string
     */
    private function render_result(): string {
        global $OUTPUT;
        $config = get_config('filter_powerbi');
        $missing = array_keys(array_filter([
            'settings:tenantid' => empty($config->tenantid),
            'settings:clientid' => empty($config->clientid),
            'settings:clientsecret' => empty($config->clientsecret),
        ]));
        if ($missing) {
            $names = array_map(fn($key) => get_string($key, 'filter_powerbi'), $missing);
            return $OUTPUT->notification(
                get_string('testconnection:missing', 'filter_powerbi', implode(', ', $names)),
                'warning',
                false
            );
        }

        $result = api::check_connection();
        $html = $OUTPUT->notification(s($result['message']), $result['ok'] ? 'success' : 'error', false);
        if ($result['workspaces']) {
            $items = array_map(fn($w) => \html_writer::tag('strong', s($w['name'])) .
                \html_writer::alist(array_map('s', $w['reports'])), $result['workspaces']);
            $html .= \html_writer::alist($items);
        }
        return $html;
    }
}
