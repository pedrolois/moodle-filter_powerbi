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

use core\http_client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests of the Power BI REST client, with the HTTP calls mocked.
 *
 * @package   filter_powerbi
 * @category  test
 * @covers    \filter_powerbi\local\api
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /** @var array Requests sent, recorded by the mock. */
    private array $sent = [];

    /**
     * Configure the app registration and answer the HTTP calls with these responses.
     *
     * @param array $responses
     */
    private function mock(array $responses): void {
        set_config('tenantid', '99999999-8888-7777-6666-555555555555', 'filter_powerbi');
        set_config('clientid', '12345678-1234-1234-1234-123456789012', 'filter_powerbi');
        set_config('clientsecret', 'secret', 'filter_powerbi');
        $mock = new MockHandler($responses);
        $client = new http_client(['mock' => $mock]);
        $client->getConfig('handler')->push(Middleware::history($this->sent));
        \core\di::set(http_client::class, $client);
    }

    /**
     * A JSON response.
     *
     * @param array $body
     * @param int $status
     * @return Response
     */
    private function json(array $body, int $status = 200): Response {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * Not configured: no request is made.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_not_configured(): void {
        $this->resetAfterTest();
        $this->assertFalse(api::is_configured());
        $this->expectExceptionMessage(get_string('error:notconfigured', 'filter_powerbi'));
        api::get_access_token();
    }

    /**
     * The access token is asked for once and then cached.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_access_token_cached(): void {
        $this->resetAfterTest();
        $this->mock([
            $this->json(['access_token' => 'AAA', 'expires_in' => 3600]),
            $this->json(['value' => [['id' => 'g1', 'name' => 'Moodle reports']]]),
            $this->json(['value' => [['id' => 'g1', 'name' => 'Moodle reports']]]),
        ]);
        $this->assertSame([['id' => 'g1', 'name' => 'Moodle reports']], api::list_workspaces());
        api::list_workspaces();

        $this->assertCount(3, $this->sent);
        /** @var Request $token */
        $token = $this->sent[0]['request'];
        $this->assertStringContainsString('/99999999-8888-7777-6666-555555555555/oauth2/v2.0/token', (string) $token->getUri());
        $this->assertStringContainsString('grant_type=client_credentials', (string) $token->getBody());
        $this->assertSame('Bearer AAA', $this->sent[2]['request']->getHeaderLine('Authorization'));
    }

    /**
     * The embed token carries the viewer's identity only with a row-level security role.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_generate_token(): void {
        $this->resetAfterTest();
        $this->mock([
            $this->json(['access_token' => 'AAA', 'expires_in' => 3600]),
            $this->json(['token' => 'T1', 'expiration' => '2030-01-01T10:00:00Z']),
            $this->json(['token' => 'T2', 'expiration' => '2030-01-01T10:00:00Z']),
        ]);
        $report = ['id' => 'r1', 'datasetId' => 'd1'];

        $token = api::generate_token($report);
        $this->assertSame('T1', $token['token']);
        $this->assertSame(strtotime('2030-01-01T10:00:00Z'), $token['expiration']);
        $body = json_decode((string) $this->sent[1]['request']->getBody(), true);
        $this->assertArrayNotHasKey('identities', $body);

        api::generate_token($report, 'ana@example.com', 'Learner');
        $body = json_decode((string) $this->sent[2]['request']->getBody(), true);
        $this->assertSame(
            [['username' => 'ana@example.com', 'roles' => ['Learner'], 'datasets' => ['d1']]],
            $body['identities']
        );
    }

    /**
     * Errors from Microsoft become readable messages.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_errors(): void {
        $this->resetAfterTest();
        $this->mock([
            $this->json(['error' => 'invalid_client', 'error_description' => 'Invalid client secret'], 401),
        ]);
        $result = api::check_connection();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Invalid client secret', $result['message']);
    }

    /**
     * A server without trusted certificates gets advice instead of a cURL error.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_certificate_error(): void {
        $this->resetAfterTest();
        $request = new Request('POST', 'https://login.microsoftonline.com/x');
        $this->mock([new ConnectException('cURL error 60: SSL certificate problem', $request)]);
        $result = api::check_connection();
        $this->assertFalse($result['ok']);
        $this->assertSame(get_string('error:certificates', 'filter_powerbi'), $result['message']);
    }

    /**
     * The connection check lists workspaces with their reports.
     *
     * @covers \filter_powerbi\local\api
     */
    public function test_check_connection(): void {
        $this->resetAfterTest();
        $this->mock([
            $this->json(['access_token' => 'AAA', 'expires_in' => 3600]),
            $this->json(['value' => [['id' => 'g1', 'name' => 'Moodle reports']]]),
            $this->json(['value' => [['id' => 'r1', 'name' => 'Enrolments']]]),
        ]);
        $result = api::check_connection();
        $this->assertTrue($result['ok']);
        $this->assertSame([['name' => 'Moodle reports', 'reports' => ['Enrolments']]], $result['workspaces']);
    }
}
