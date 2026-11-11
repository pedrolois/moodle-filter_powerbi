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

namespace filter_powerbi\local;

/**
 * Tests of reading report links, filters and signed references.
 *
 * @package   filter_powerbi
 * @category  test
 * @covers    \filter_powerbi\local\embed
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(embed::class)]
final class embed_test extends \advanced_testcase {
    /** @var string Workspace id used in the tests. */
    private const GROUP = '11111111-2222-3333-4444-555555555555';

    /** @var string Report id used in the tests. */
    private const REPORT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    /** @var string Tenant id used in the tests. */
    private const TENANT = '99999999-8888-7777-6666-555555555555';

    /**
     * Address of a report, with optional display options.
     *
     * @param string $options Query string of display options.
     * @return string
     */
    private function link(string $options = ''): string {
        return 'https://app.powerbi.com/groups/' . self::GROUP . '/reports/' . self::REPORT . '/ReportSection2a1b?ctid=' .
            self::TENANT . $options;
    }

    /**
     * The report address and the secure embed address are both read; other links are not.
     */
    public function test_from_url(): void {
        $embed = embed::from_url($this->link('&mdlheight=480&mdlmode=embedded&mdlnav=0&mdlfilters=1'));
        $this->assertSame(self::GROUP, $embed->groupid);
        $this->assertSame(self::REPORT, $embed->reportid);
        $this->assertSame('ReportSection2a1b', $embed->pagename);
        $this->assertSame(self::TENANT, $embed->tenantid);
        $this->assertSame(480, $embed->height);
        $this->assertSame(embed::MODE_EMBEDDED, $embed->mode);
        $this->assertFalse($embed->navpane);
        $this->assertTrue($embed->filterpane);

        $secure = embed::from_url('https://app.powerbi.com/reportEmbed?reportId=' . self::REPORT . '&autoAuth=true');
        $this->assertSame('me', $secure->groupid);
        $this->assertSame(self::REPORT, $secure->reportid);

        $this->assertNull(embed::from_url('https://example.com/groups/me/reports/' . self::REPORT));
        $this->assertNull(embed::from_url('https://app.powerbi.com/groups/me/dashboards/' . self::REPORT));
        $this->assertNull(embed::from_url('not a link'));
    }

    /**
     * Display options are kept within safe limits; unknown values fall back to the defaults.
     */
    public function test_from_url_limits(): void {
        $embed = embed::from_url($this->link('&mdlheight=99999&mdlmode=evil&mdlfilter=evil&mdltable=T&mdlcolumn=C'));
        $this->assertSame(2000, $embed->height);
        $this->assertSame('', $embed->mode);
        $this->assertSame(embed::FILTER_NONE, $embed->filterby);

        // A filter without its table and column is no filter.
        $this->assertSame(embed::FILTER_NONE, embed::from_url($this->link('&mdlfilter=course'))->filterby);

        // Table names cannot carry markup.
        $embed = embed::from_url($this->link('&mdlfilter=course&mdltable=%3Cscript%3EX&mdlcolumn=C'));
        $this->assertSame('scriptX', $embed->filtertable);
    }

    /**
     * Names are escaped the way Power BI URL filters need.
     */
    public function test_escape_name(): void {
        $this->assertSame('Course_x0020_Info', embed::escape_name('Course Info'));
        $this->assertSame('Short_x002d_name', embed::escape_name('Short-name'));
        $this->assertSame('Plain', embed::escape_name('Plain'));
    }

    /**
     * The secure embed address carries the report, page, panes and the filter by course.
     */
    public function test_secure_url_filtered_by_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => "Bob's course"]);
        $context = \core\context\course::instance($course->id);
        $embed = embed::from_url($this->link('&mdlfilter=course&mdltable=Course%20Info&mdlcolumn=Short%20name&mdlnav=0'));

        $url = $embed->get_secure_url($context, get_admin());
        $this->assertSame('https://app.powerbi.com/reportEmbed', $url->out_omit_querystring());
        $this->assertSame(self::REPORT, $url->get_param('reportId'));
        $this->assertSame(self::GROUP, $url->get_param('groupId'));
        $this->assertSame(self::TENANT, $url->get_param('ctid'));
        $this->assertSame('ReportSection2a1b', $url->get_param('pageName'));
        $this->assertSame('false', $url->get_param('navContentPaneEnabled'));
        // Quotes are doubled inside the value.
        $this->assertSame("Course_x0020_Info/Short_x0020_name eq 'Bob''s course'", $url->get_param('filter'));
    }

    /**
     * Each kind of filter works out the viewer's values on the server.
     */
    public function test_get_filter_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $category = $generator->create_category(['name' => 'Leadership']);
        $course = $generator->create_course(['category' => $category->id, 'shortname' => 'LEAD200']);
        $student = $generator->create_user(['email' => 'ana@example.com', 'department' => 'Finance']);
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        foreach (['Team A', 'Team B'] as $name) {
            $group = $generator->create_group(['courseid' => $course->id, 'name' => $name]);
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        }
        $cohort = $generator->create_cohort(['name' => 'Staff 2026']);
        cohort_add_member($cohort->id, $student->id);
        $field = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'station', 'name' => 'Station']);
        profile_save_data((object) ['id' => $student->id, 'profile_field_station' => 'Geneva']);

        $context = \core\context\course::instance($course->id);
        $values = fn(string $filter, \stdClass $user) => embed::from_url(
            $this->link('&mdltable=T&mdlcolumn=C&mdlfilter=' . urlencode($filter))
        )->get_filter_values($context, $user);

        $this->assertSame(['LEAD200'], $values('course', $student));
        $this->assertSame(['Leadership'], $values('category', $student));
        $this->assertEqualsCanonicalizing(['Team A', 'Team B'], $values('group', $student));
        $this->assertSame(['Staff 2026'], $values('cohort', $student));
        $this->assertSame(['ana@example.com'], $values('user', $student));
        $this->assertSame(['Finance'], $values('profile:department', $student));
        $this->assertSame(['Geneva'], $values('profile:' . $field->shortname, $student));

        // People who see every group are not filtered by group.
        $this->assertNull($values('group', $teacher));
        // No match is an empty list, which shows no data.
        $this->assertSame([], $values('cohort', $teacher));
        $this->assertSame([], $values('profile:department', $teacher));
    }

    /**
     * Several values become an "in" filter; no value filters by an empty value.
     */
    public function test_secure_url_several_values(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        foreach (['Team A', "Team O'Brien"] as $name) {
            $group = $generator->create_group(['courseid' => $course->id, 'name' => $name]);
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        $context = \core\context\course::instance($course->id);

        $embed = embed::from_url($this->link('&mdlfilter=group&mdltable=T&mdlcolumn=C'));
        $filter = $embed->get_secure_url($context, $user)->get_param('filter');
        $this->assertStringStartsWith('T/C in (', $filter);
        $this->assertStringContainsString("'Team O''Brien'", $filter);

        $loner = $generator->create_and_enrol($course, 'student');
        $this->assertSame("T/C eq ''", $embed->get_secure_url($context, $loner)->get_param('filter'));
    }

    /**
     * A reference is accepted only with its signature and in the context it was made for.
     */
    public function test_reference(): void {
        $this->resetAfterTest();
        set_config('signingkey', 'test-key', 'filter_powerbi');
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        $embed = embed::from_url($this->link('&mdlfilter=course&mdltable=T&mdlcolumn=C&mdlrole=Learner'));

        $reference = $embed->get_reference($context);
        $read = embed::from_reference($reference, $context);
        $this->assertSame(self::REPORT, $read->reportid);
        $this->assertSame('Learner', $read->rlsrole);
        $this->assertSame(embed::FILTER_COURSE, $read->filterby);
        $this->assertSame(embed::MODE_EMBEDDED, $read->mode);

        // Another context.
        $this->assertNull(embed::from_reference($reference, \core\context\system::instance()));

        // A changed report id with the old signature.
        [$payload, $signature] = explode('.', $reference);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $data['r'] = '00000000-0000-0000-0000-000000000000';
        $forged = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=') . '.' . $signature;
        $this->assertNull(embed::from_reference($forged, $context));

        // Another site key.
        set_config('signingkey', 'other-key', 'filter_powerbi');
        $this->assertNull(embed::from_reference($reference, $context));
    }

    /**
     * The editor is offered the fixed filters plus every custom profile field.
     */
    public function test_get_filter_options(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'station',
            'name' => 'Station']);
        $options = embed::get_filter_options();
        foreach (['none', 'course', 'category', 'group', 'cohort', 'user', 'profile:department', 'profile:station'] as $key) {
            $this->assertArrayHasKey($key, $options);
        }
    }
}
