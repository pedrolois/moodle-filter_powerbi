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

/**
 * Tests of the text filter.
 *
 * @package   filter_powerbi
 * @category  test
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(text_filter::class)]
final class text_filter_test extends \advanced_testcase {
    /** @var string A report link. */
    private const LINK = 'https://app.powerbi.com/groups/me/reports/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    /**
     * Run the filter on some HTML in a course.
     *
     * @param string $html
     * @return string
     */
    private function filter(string $html): string {
        $course = $this->getDataGenerator()->create_course();
        $filter = new text_filter(\core\context\course::instance($course->id), []);
        return $filter->filter($html);
    }

    /**
     * A marked link becomes a secure embed, and replaces its paragraph; other links are left alone.
     */
    public function test_secure_embed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $html = '<p><a class="powerbi-embed" href="' . self::LINK . '?mdlheight=450">Completion</a></p>' .
            '<p><a href="https://app.powerbi.com/groups/me/reports/x">Plain link</a></p>';

        $out = $this->filter($html);
        $this->assertStringContainsString('<figure class="filter-powerbi"', $out);
        $this->assertStringNotContainsString('<p><figure', $out);
        $this->assertStringContainsString('--filter-powerbi-height: 450px', $out);
        $this->assertStringContainsString('src="https://app.powerbi.com/reportEmbed?reportId=aaaaaaaa', $out);
        $this->assertStringContainsString('title="Completion"', $out);
        $this->assertStringContainsString('data-action="filter-powerbi-fullscreen"', $out);
        $this->assertStringContainsString('>Plain link</a>', $out);
    }

    /**
     * Text without marked links is returned as it is.
     */
    public function test_nothing_to_do(): void {
        $this->resetAfterTest();
        $html = '<p>No reports here, only <a href="https://moodle.org">a link</a>.</p>';
        $this->assertSame($html, $this->filter($html));
    }

    /**
     * The embedded mode without an app registration explains what is missing; with one, it writes a signed reference.
     */
    public function test_embedded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $html = '<a class="powerbi-embed" href="' . self::LINK . '?mdlmode=embedded">Completion</a>';

        $out = $this->filter($html);
        $this->assertStringContainsString(get_string('error:notconfigured', 'filter_powerbi'), $out);
        $this->assertStringNotContainsString('<iframe', $out);

        set_config('tenantid', '99999999-8888-7777-6666-555555555555', 'filter_powerbi');
        set_config('clientid', '12345678-1234-1234-1234-123456789012', 'filter_powerbi');
        set_config('clientsecret', 'secret', 'filter_powerbi');
        $out = $this->filter($html);
        $this->assertStringContainsString('data-embed="1"', $out);
        $this->assertMatchesRegularExpression('/data-reference="[A-Za-z0-9_-]+\.[0-9a-f]{64}"/', $out);
        // Nothing secret reaches the page.
        $this->assertStringNotContainsString('secret', $out);
    }
}
