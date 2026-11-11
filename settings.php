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
 * Admin settings.
 *
 * @package   filter_powerbi
 * @category  admin
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use filter_powerbi\local\embed;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/defaultmode',
        new lang_string('settings:defaultmode', 'filter_powerbi'),
        new lang_string('settings:defaultmode_desc', 'filter_powerbi'),
        embed::MODE_SECURE,
        [
            embed::MODE_SECURE => new lang_string('mode:secure', 'filter_powerbi'),
            embed::MODE_EMBEDDED => new lang_string('mode:embedded', 'filter_powerbi'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'filter_powerbi/appheading',
        new lang_string('settings:appheading', 'filter_powerbi'),
        new lang_string('settings:appheading_desc', 'filter_powerbi')
    ));
    $settings->add(new admin_setting_configtext(
        'filter_powerbi/tenantid',
        new lang_string('settings:tenantid', 'filter_powerbi'),
        new lang_string('settings:tenantid_desc', 'filter_powerbi'),
        '',
        '/^([0-9a-fA-F-]{36}|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?$/'
    ));
    $settings->add(new admin_setting_configtext(
        'filter_powerbi/clientid',
        new lang_string('settings:clientid', 'filter_powerbi'),
        new lang_string('settings:clientid_desc', 'filter_powerbi'),
        '',
        '/^([0-9a-fA-F-]{36})?$/'
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'filter_powerbi/clientsecret',
        new lang_string('settings:clientsecret', 'filter_powerbi'),
        new lang_string('settings:clientsecret_desc', 'filter_powerbi'),
        ''
    ));
    // Saves the form, then tests the connection with the values just saved.
    $settings->add(new \filter_powerbi\admin\setting_testconnection());

    $settings->add(new admin_setting_heading(
        'filter_powerbi/filterheading',
        new lang_string('settings:filterheading', 'filter_powerbi'),
        new lang_string('settings:filterheading_desc', 'filter_powerbi')
    ));
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/coursefield',
        new lang_string('settings:coursefield', 'filter_powerbi'),
        new lang_string('settings:coursefield_desc', 'filter_powerbi'),
        'shortname',
        [
            'shortname' => new lang_string('shortnamecourse'),
            'idnumber' => new lang_string('idnumbercourse'),
            'id' => 'ID',
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/categoryfield',
        new lang_string('settings:categoryfield', 'filter_powerbi'),
        new lang_string('settings:categoryfield_desc', 'filter_powerbi'),
        'name',
        [
            'name' => new lang_string('categoryname'),
            'idnumber' => new lang_string('idnumbercoursecategory'),
            'id' => 'ID',
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/groupfield',
        new lang_string('settings:groupfield', 'filter_powerbi'),
        new lang_string('settings:groupfield_desc', 'filter_powerbi'),
        'name',
        [
            'name' => new lang_string('groupname', 'group'),
            'idnumber' => new lang_string('idnumbergroup'),
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/cohortfield',
        new lang_string('settings:cohortfield', 'filter_powerbi'),
        new lang_string('settings:cohortfield_desc', 'filter_powerbi'),
        'name',
        [
            'name' => new lang_string('name', 'cohort'),
            'idnumber' => new lang_string('idnumber', 'cohort'),
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'filter_powerbi/userfield',
        new lang_string('settings:userfield', 'filter_powerbi'),
        new lang_string('settings:userfield_desc', 'filter_powerbi'),
        'email',
        [
            'email' => new lang_string('email'),
            'username' => new lang_string('username'),
            'idnumber' => new lang_string('idnumber'),
        ]
    ));
}
