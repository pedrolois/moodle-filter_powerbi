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
use GuzzleHttp\Exception\GuzzleException;

/**
 * Power BI REST API client, authenticated with the site's Microsoft Entra app registration (service principal).
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string Power BI REST API root. */
    private const API = 'https://api.powerbi.com/v1.0/myorg/';

    /** @var string Scope of the Power BI REST API. */
    private const SCOPE = 'https://analysis.windows.net/powerbi/api/.default';

    /**
     * Whether the app registration is configured, so the embedded mode can work.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $config = get_config('filter_powerbi');
        return !empty($config->tenantid) && !empty($config->clientid) && !empty($config->clientsecret);
    }

    /**
     * Access token of the app registration (client credentials), cached until shortly before it expires.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_access_token(): string {
        $config = get_config('filter_powerbi');
        if (!self::is_configured()) {
            throw new \moodle_exception('error:notconfigured', 'filter_powerbi');
        }
        $cache = \cache::make('filter_powerbi', 'accesstoken');
        $key = sha1($config->tenantid . '|' . $config->clientid . '|' . $config->clientsecret);
        $cached = $cache->get($key);
        if ($cached && $cached['expires'] > time() + 120) {
            return $cached['token'];
        }

        $data = self::send('POST', 'https://login.microsoftonline.com/' . rawurlencode($config->tenantid) . '/oauth2/v2.0/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $config->clientid,
                'client_secret' => $config->clientsecret,
                'scope' => self::SCOPE,
            ],
        ]);
        if (empty($data['access_token'])) {
            throw new \moodle_exception('error:auth', 'filter_powerbi', '', $data['error_description'] ?? '');
        }
        $cache->set($key, ['token' => $data['access_token'], 'expires' => time() + (int) ($data['expires_in'] ?? 3600)]);
        return $data['access_token'];
    }

    /**
     * Call the Power BI REST API.
     *
     * @param string $method
     * @param string $path Path under /v1.0/myorg/.
     * @param array|null $json Body.
     * @return array
     * @throws \moodle_exception
     */
    public static function call(string $method, string $path, ?array $json = null): array {
        $options = ['headers' => ['Authorization' => 'Bearer ' . self::get_access_token()]];
        if ($json !== null) {
            $options['json'] = $json;
        }
        return self::send($method, self::API . $path, $options);
    }

    /**
     * Send a request and decode the JSON answer.
     *
     * @param string $method
     * @param string $url
     * @param array $options Guzzle options.
     * @return array
     * @throws \moodle_exception
     */
    private static function send(string $method, string $url, array $options): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        // Use the certificate bundle Moodle uses for its own HTTPS calls: Windows PHP has none by default.
        $cacert = \curl::get_cacert();
        if ($cacert) {
            $options['verify'] = $cacert;
        }
        $client = \core\di::get(http_client::class);
        try {
            $response = $client->request($method, $url, $options + ['http_errors' => false, 'timeout' => 30]);
        } catch (GuzzleException $e) {
            // Error 60 of cURL: PHP has no up-to-date list of trusted certificates (common on Windows servers).
            if (str_contains($e->getMessage(), 'cURL error 60')) {
                throw new \moodle_exception('error:certificates', 'filter_powerbi');
            }
            throw new \moodle_exception('error:request', 'filter_powerbi', '', $e->getMessage());
        }
        $body = json_decode((string) $response->getBody(), true) ?: [];
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $message = $body['error']['message'] ?? $body['error_description'] ?? $body['error']['code'] ?? "HTTP {$status}";
            throw new \moodle_exception('error:request', 'filter_powerbi', '', is_string($message) ? $message : "HTTP {$status}");
        }
        return $body;
    }

    /**
     * Check the app registration: sign in and list the workspaces and reports it can use.
     *
     * @return array ['ok' => bool, 'message' => string, 'workspaces' => [['name' => string, 'reports' => string[]]]]
     */
    public static function check_connection(): array {
        try {
            $workspaces = [];
            foreach (self::list_workspaces() as $workspace) {
                $workspaces[] = [
                    'name' => $workspace['name'],
                    'reports' => array_column(self::list_reports($workspace['id']), 'name'),
                ];
            }
        } catch (\moodle_exception $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'workspaces' => []];
        }
        $message = get_string($workspaces ? 'testconnection:ok' : 'testconnection:noworkspaces', 'filter_powerbi');
        return ['ok' => true, 'message' => $message, 'workspaces' => $workspaces];
    }

    /**
     * Report details: name, embed URL and dataset.
     *
     * @param string $groupid
     * @param string $reportid
     * @return array
     */
    public static function get_report(string $groupid, string $reportid): array {
        return self::call('GET', self::group_path($groupid) . 'reports/' . rawurlencode($reportid));
    }

    /**
     * Embed token for one report, with the viewer's identity when row-level security is used.
     *
     * @param array $report From get_report().
     * @param string $identity User name sent to row-level security, empty for none.
     * @param string $role Row-level security role.
     * @return array ['token' => string, 'expiration' => int timestamp]
     */
    public static function generate_token(array $report, string $identity = '', string $role = ''): array {
        $body = [
            'reports' => [['id' => $report['id']]],
            'datasets' => [['id' => $report['datasetId']]],
        ];
        if ($identity !== '' && $role !== '') {
            $body['identities'] = [[
                'username' => $identity,
                'roles' => [$role],
                'datasets' => [$report['datasetId']],
            ]];
        }
        $data = self::call('POST', 'GenerateToken', $body);
        return ['token' => (string) $data['token'], 'expiration' => (int) strtotime($data['expiration'])];
    }

    /**
     * Workspaces the app registration can see.
     *
     * @return array [['id' => string, 'name' => string]]
     */
    public static function list_workspaces(): array {
        return array_map(fn($g) => ['id' => $g['id'], 'name' => $g['name']], self::call('GET', 'groups')['value'] ?? []);
    }

    /**
     * Reports of a workspace.
     *
     * @param string $groupid
     * @return array [['id' => string, 'name' => string]]
     */
    public static function list_reports(string $groupid): array {
        $reports = self::call('GET', self::group_path($groupid) . 'reports')['value'] ?? [];
        return array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name']], $reports);
    }

    /**
     * Pages of a report, in order.
     *
     * @param string $groupid
     * @param string $reportid
     * @return array [['id' => string (page name), 'name' => string (display name)]]
     */
    public static function list_pages(string $groupid, string $reportid): array {
        $pages = self::call('GET', self::group_path($groupid) . 'reports/' . rawurlencode($reportid) . '/pages')['value'] ?? [];
        usort($pages, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return array_map(fn($p) => ['id' => $p['name'], 'name' => $p['displayName']], $pages);
    }

    /**
     * Path of a workspace ("me" is the caller's own workspace, which a service principal does not have).
     *
     * @param string $groupid
     * @return string
     */
    private static function group_path(string $groupid): string {
        return $groupid === 'me' ? '' : 'groups/' . rawurlencode($groupid) . '/';
    }
}
