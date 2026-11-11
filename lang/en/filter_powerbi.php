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
 * English strings.
 *
 * @package   filter_powerbi
 * @category  string
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_accesstoken'] = 'Microsoft Entra access token of the Power BI app registration';
$string['defaulttitle'] = 'Power BI report';
$string['error:auth'] = 'Microsoft Entra refused the app registration: {$a}';
$string['error:badreference'] = 'This report cannot be shown here.';
$string['error:certificates'] = 'The server cannot check Microsoft\'s security certificate because PHP has no up-to-date list of trusted certificates. Ask your server administrator to download https://curl.se/ca/cacert.pem and set curl.cainfo to it in php.ini.';
$string['error:notconfigured'] = 'This report needs the Power BI app registration, which is not set up on this site yet.';
$string['error:request'] = 'Power BI returned an error: {$a}';
$string['filterby:category'] = 'The course category';
$string['filterby:cohort'] = 'The viewer\'s cohorts';
$string['filterby:course'] = 'This course';
$string['filterby:group'] = 'The viewer\'s groups in this course';
$string['filterby:none'] = 'No filter';
$string['filterby:profile'] = 'The viewer\'s {$a}';
$string['filterby:user'] = 'The user viewing it';
$string['filtername'] = 'Power BI reports';
$string['fullscreen'] = 'Full screen';
$string['loading'] = 'Loading report…';
$string['mode:embedded'] = 'Embedded: shown with the site\'s app registration, no Power BI sign-in or licence needed';
$string['mode:secure'] = 'Secure embed: viewers sign in with Microsoft and need access to the report';
$string['privacy:metadata:powerbi'] = 'When a report filters by user or uses row-level security, the user field chosen in the settings is sent to Microsoft Power BI to show only that user\'s data.';
$string['privacy:metadata:powerbi:identity'] = 'The e-mail address, username or ID number of the user, as set in the plugin settings.';
$string['settings:appheading'] = 'App registration (embedded mode)';
$string['settings:appheading_desc'] = 'The embedded mode shows reports to every user, without a Power BI sign-in or licence. It needs an app registration in Microsoft Entra ID with a client secret, added as a Member of the Power BI workspaces, and a Power BI administrator must turn on "Service principals can call Fabric public APIs" in the tenant settings.';
$string['settings:categoryfield'] = 'Category field';
$string['settings:categoryfield_desc'] = 'Category value sent when a report is filtered by the course category.';
$string['settings:clientid'] = 'Application (client) ID';
$string['settings:clientid_desc'] = 'From the app registration\'s Overview page in Microsoft Entra ID.';
$string['settings:clientsecret'] = 'Client secret';
$string['settings:clientsecret_desc'] = 'The value of a client secret of the app registration (Certificates &amp; secrets).';
$string['settings:cohortfield'] = 'Cohort field';
$string['settings:cohortfield_desc'] = 'Cohort value sent when a report is filtered by the viewer\'s cohorts. A viewer in several cohorts sees the data of all of them.';
$string['settings:coursefield'] = 'Course field';
$string['settings:coursefield_desc'] = 'Course value sent when a report is filtered by course.';
$string['settings:defaultmode'] = 'Default mode';
$string['settings:defaultmode_desc'] = 'How reports are shown when the editor does not choose a mode.';
$string['settings:filterheading'] = 'Filters';
$string['settings:filterheading_desc'] = 'A report can open filtered by the course it is placed in, its category, the viewer\'s groups, cohorts or profile fields, or the viewer. A viewer who matches no value sees no data. These filters make reports easier to read but are not a security boundary: to make sure each user only sees their own data, use row-level security in the embedded mode.';
$string['settings:groupfield'] = 'Group field';
$string['settings:groupfield_desc'] = 'Group value sent when a report is filtered by the viewer\'s groups. People who can see every group (such as teachers) see the whole report.';
$string['settings:tenantid'] = 'Directory (tenant) ID';
$string['settings:tenantid_desc'] = 'Your organisation\'s Microsoft Entra tenant ID or domain. Also used by the secure embed to send users to the right sign-in page.';
$string['settings:userfield'] = 'User field';
$string['settings:userfield_desc'] = 'User value sent when a report is filtered by user, and as the identity for row-level security.';
$string['testconnection'] = 'Test the Power BI connection';
$string['testconnection:button'] = 'Save and test the connection';
$string['testconnection:missing'] = 'Fill in and save these settings first: {$a}.';
$string['testconnection:noworkspaces'] = 'Connected, but the app registration is not a member of any workspace yet. Add it to a workspace as a Member.';
$string['testconnection:ok'] = 'Connected to Power BI. Workspaces and reports the app registration can use:';
$string['testconnection_desc'] = 'Saves the settings above, signs in to Microsoft with them, and lists the workspaces and reports the app registration can use.';
