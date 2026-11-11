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

use core\http_client;
use core_external\external_api;
use filter_powerbi\local\embed;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * Tests of the embed token web service.
 *
 * @package   filter_powerbi
 * @category  test
 * @covers    \filter_powerbi\external\get_embed
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_embed::class)]
final class get_embed_test extends \advanced_testcase {
    /**
     * Set up the app registration and the answers of Power BI.
     */
    private function mock_powerbi(): void {
        set_config('tenantid', '99999999-8888-7777-6666-555555555555', 'filter_powerbi');
        set_config('clientid', '12345678-1234-1234-1234-123456789012', 'filter_powerbi');
        set_config('clientsecret', 'secret', 'filter_powerbi');
        $json = fn(array $body) => new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
        \core\di::set(http_client::class, new http_client(['mock' => new MockHandler([
            $json(['access_token' => 'AAA', 'expires_in' => 3600]),
            $json(['id' => 'r1', 'datasetId' => 'd1', 'embedUrl' => 'https://app.powerbi.com/reportEmbed?reportId=r1']),
            $json(['token' => 'EMBED', 'expiration' => '2030-01-01T10:00:00Z']),
        ])]));
    }

    /**
     * A signed report in a course the student is in: token, filter values and language come back.
     *
     * @covers \filter_powerbi\external\get_embed
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->mock_powerbi();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['shortname' => 'LEAD200']);
        $student = $generator->create_and_enrol($course, 'student');
        $context = \core\context\course::instance($course->id);
        $embed = embed::from_url('https://app.powerbi.com/groups/11111111-2222-3333-4444-555555555555/reports/' .
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee?mdlmode=embedded&mdlfilter=course&mdltable=Courses&mdlcolumn=Code');
        $this->setUser($student);

        $result = get_embed::execute($context->id, $embed->get_reference($context));
        $result = external_api::clean_returnvalue(get_embed::execute_returns(), $result);
        $this->assertSame('EMBED', $result['token']);
        $this->assertSame('Courses', $result['filtertable']);
        $this->assertSame(['LEAD200'], $result['filtervalues']);
        $this->assertSame('en', $result['language']);
    }

    /**
     * A reference that was not signed by the site, or used in another context, is refused.
     *
     * @covers \filter_powerbi\external\get_embed
     */
    public function test_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);
        $embed = embed::from_url('https://app.powerbi.com/groups/me/reports/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $reference = $embed->get_reference(\core\context\system::instance());

        $this->expectExceptionMessage(get_string('error:badreference', 'filter_powerbi'));
        get_embed::execute($context->id, $reference);
    }

    /**
     * Moodle languages become Power BI locales.
     *
     * @covers \filter_powerbi\external\get_embed
     */
    public function test_get_locale(): void {
        $this->assertSame('es', get_embed::get_locale('es'));
        $this->assertSame('pt-BR', get_embed::get_locale('pt_br'));
        $this->assertSame('zh-CN', get_embed::get_locale('zh_cn'));
        $this->assertSame('en', get_embed::get_locale('en'));
    }
}
