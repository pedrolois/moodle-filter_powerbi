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

/**
 * Install steps.
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create the key that signs embedded reports, and turn the filter on.
 *
 * @return bool
 */
function xmldb_filter_powerbi_install() {
    global $CFG;
    require_once($CFG->libdir . '/filterlib.php');

    if (!get_config('filter_powerbi', 'signingkey')) {
        set_config('signingkey', random_string(64), 'filter_powerbi');
    }
    filter_set_global_state('powerbi', TEXTFILTER_ON);
    return true;
}
