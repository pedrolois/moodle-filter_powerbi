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
 * A Power BI report placed in content: where it is, and how to show it.
 *
 * Content stores it as an ordinary link to the report with the class "powerbi-embed". The display options travel
 * in the link's query string (mdl* parameters), which Power BI ignores, so the link still works without the filter.
 *
 * @package   filter_powerbi
 * @copyright 2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed {
    /** @var string Shown with the viewer's own Microsoft sign-in and Power BI licence. */
    public const MODE_SECURE = 'secure';

    /** @var string Shown with an embed token from the site's app registration: no sign-in or licence needed. */
    public const MODE_EMBEDDED = 'embedded';

    /** @var string No filter. */
    public const FILTER_NONE = 'none';

    /** @var string Filter the report by the course the content is in. */
    public const FILTER_COURSE = 'course';

    /** @var string Filter the report by the user viewing it. */
    public const FILTER_USER = 'user';

    /** @var string Filter the report by the category of the course. */
    public const FILTER_CATEGORY = 'category';

    /** @var string Filter the report by the viewer's groups in the course. */
    public const FILTER_GROUP = 'group';

    /** @var string Filter the report by the viewer's cohorts. */
    public const FILTER_COHORT = 'cohort';

    /** @var string Prefix of a filter by a field of the viewer's profile, for example "profile:department". */
    public const FILTER_PROFILE = 'profile:';

    /** @var string[] Standard user fields that can be used as a profile filter. */
    public const PROFILE_FIELDS = ['department', 'institution', 'city', 'country', 'idnumber'];

    /** @var string GUID pattern used by Power BI ids. */
    private const GUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** @var string Workspace id, or "me" for My workspace. */
    public string $groupid = 'me';

    /** @var string Report id. */
    public string $reportid = '';

    /** @var string Page (section) name, empty for the report's default page. */
    public string $pagename = '';

    /** @var string Microsoft Entra tenant id from the link, if any. */
    public string $tenantid = '';

    /** @var string Title shown to screen readers and in the fallback link. */
    public string $title = '';

    /** @var int Height in pixels. */
    public int $height = 600;

    /** @var string One of the MODE_* constants, empty for the site default. */
    public string $mode = '';

    /** @var string One of the FILTER_* constants. */
    public string $filterby = self::FILTER_NONE;

    /** @var string Table of the report model the filter applies to. */
    public string $filtertable = '';

    /** @var string Column of that table. */
    public string $filtercolumn = '';

    /** @var string Row-level security role (embedded mode). */
    public string $rlsrole = '';

    /** @var bool Show the page navigation. */
    public bool $navpane = true;

    /** @var bool Show the filter pane. */
    public bool $filterpane = false;

    /**
     * Read a Power BI report link.
     *
     * Accepts the report address (https://app.powerbi.com/groups/{workspace}/reports/{report}/{page}) and the
     * secure embed address (https://app.powerbi.com/reportEmbed?reportId=…&groupId=…).
     *
     * @param string $url
     * @return self|null Null when the link is not a Power BI report.
     */
    public static function from_url(string $url): ?self {
        $parts = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5));
        if (empty($parts['host']) || !preg_match('/(^|\.)powerbi\.(com|cn|us|de)$/i', $parts['host'])) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        $path = $parts['path'] ?? '';
        $guid = self::GUID;

        $embed = new self();
        if (preg_match("~/groups/(me|{$guid})/reports/({$guid})(?:/([^/?#]+))?~i", $path, $m)) {
            $embed->groupid = strtolower($m[1]);
            $embed->reportid = strtolower($m[2]);
            $embed->pagename = $m[3] ?? '';
        } else if (preg_match('~/reportEmbed$~i', $path) && preg_match("~^{$guid}$~i", (string) ($query['reportId'] ?? ''))) {
            $embed->reportid = strtolower($query['reportId']);
            $group = (string) ($query['groupId'] ?? '');
            $embed->groupid = preg_match("~^{$guid}$~i", $group) ? strtolower($group) : 'me';
            $embed->pagename = (string) ($query['pageName'] ?? '');
        } else {
            return null;
        }
        // Power BI adds pages like "ReportSection" plus suffixes; anything else is not a page name.
        if (!preg_match('/^[A-Za-z0-9_]{1,100}$/', $embed->pagename)) {
            $embed->pagename = '';
        }
        if (preg_match("~^{$guid}$~i", (string) ($query['ctid'] ?? ''))) {
            $embed->tenantid = strtolower($query['ctid']);
        }

        $embed->height = min(2000, max(200, (int) ($query['mdlheight'] ?? 600)));
        $embed->mode = in_array($query['mdlmode'] ?? '', [self::MODE_SECURE, self::MODE_EMBEDDED], true) ? $query['mdlmode'] : '';
        $filterby = (string) ($query['mdlfilter'] ?? '');
        $embed->filterby = self::is_filter($filterby) ? $filterby : self::FILTER_NONE;
        $embed->filtertable = self::clean_name((string) ($query['mdltable'] ?? ''));
        $embed->filtercolumn = self::clean_name((string) ($query['mdlcolumn'] ?? ''));
        if ($embed->filtertable === '' || $embed->filtercolumn === '') {
            $embed->filterby = self::FILTER_NONE;
        }
        $embed->rlsrole = self::clean_name((string) ($query['mdlrole'] ?? ''));
        $embed->navpane = ($query['mdlnav'] ?? '1') !== '0';
        $embed->filterpane = ($query['mdlfilters'] ?? '0') === '1';
        return $embed;
    }

    /**
     * Keep a table, column or role name safe: letters, digits, spaces and a few punctuation marks.
     *
     * @param string $name
     * @return string
     */
    private static function clean_name(string $name): string {
        return trim(preg_replace('/[^\p{L}\p{N} _\-.]/u', '', \core_text::substr($name, 0, 100)));
    }

    /**
     * Display mode, falling back to the site default.
     *
     * @return string
     */
    public function get_mode(): string {
        if ($this->mode !== '') {
            return $this->mode;
        }
        return get_config('filter_powerbi', 'defaultmode') === self::MODE_EMBEDDED ? self::MODE_EMBEDDED : self::MODE_SECURE;
    }

    /**
     * Tenant id: the one in the link, else the site's.
     *
     * @return string
     */
    public function get_tenantid(): string {
        return $this->tenantid ?: (string) get_config('filter_powerbi', 'tenantid');
    }

    /**
     * Whether a value is a known filter.
     *
     * @param string $filterby
     * @return bool
     */
    public static function is_filter(string $filterby): bool {
        $fixed = [self::FILTER_COURSE, self::FILTER_USER, self::FILTER_CATEGORY, self::FILTER_GROUP, self::FILTER_COHORT];
        return in_array($filterby, $fixed, true) || preg_match('/^profile:[a-z0-9_]{1,100}$/', $filterby);
    }

    /**
     * Filters offered in the editor.
     *
     * @return array Filter value => label.
     */
    public static function get_filter_options(): array {
        global $DB;
        $component = 'filter_powerbi';
        $options = [
            self::FILTER_NONE => get_string('filterby:none', $component),
            self::FILTER_COURSE => get_string('filterby:course', $component),
            self::FILTER_CATEGORY => get_string('filterby:category', $component),
            self::FILTER_GROUP => get_string('filterby:group', $component),
            self::FILTER_COHORT => get_string('filterby:cohort', $component),
            self::FILTER_USER => get_string('filterby:user', $component),
        ];
        foreach (self::PROFILE_FIELDS as $field) {
            $options[self::FILTER_PROFILE . $field] = get_string('filterby:profile', $component, get_string($field));
        }
        foreach ($DB->get_records('user_info_field', null, 'sortorder', 'id, shortname, name') as $field) {
            $shortname = strtolower($field->shortname);
            if (preg_match('/^[a-z0-9_]{1,100}$/', $shortname)) {
                $options[self::FILTER_PROFILE . $shortname] = get_string(
                    'filterby:profile',
                    $component,
                    format_string($field->name, true, ['context' => \core\context\system::instance()])
                );
            }
        }
        return $options;
    }

    /**
     * Values the report is filtered by for this viewer, worked out on the server.
     *
     * A viewer can match several values (for example several groups). A viewer who matches none gets an empty list,
     * which shows no data rather than all of it.
     *
     * @param \context $context Context the content is shown in.
     * @param \stdClass $user Viewer.
     * @return string[]|null Null when the report is not filtered for this viewer.
     */
    public function get_filter_values(\context $context, \stdClass $user): ?array {
        global $CFG;
        $config = get_config('filter_powerbi');
        $coursecontext = $context->get_course_context(false);
        $course = $coursecontext ? get_course($coursecontext->instanceid) : null;

        switch ($this->filterby) {
            case self::FILTER_NONE:
                return null;
            case self::FILTER_COURSE:
                if (!$course) {
                    return null;
                }
                $field = $config->coursefield ?? 'shortname';
                return [(string) ($course->{$field} ?? $course->shortname)];
            case self::FILTER_CATEGORY:
                if (!$course) {
                    return null;
                }
                $category = \core_course_category::get($course->category, IGNORE_MISSING, true);
                $field = $config->categoryfield ?? 'name';
                return $category ? [(string) ($category->{$field} ?? $category->name)] : [];
            case self::FILTER_GROUP:
                if (!$course) {
                    return null;
                }
                // People who see every group (teachers, managers) see the whole report.
                if (has_capability('moodle/site:accessallgroups', $coursecontext, $user)) {
                    return null;
                }
                $field = $config->groupfield ?? 'name';
                return array_values(array_map(fn($g) => (string) $g->{$field}, groups_get_all_groups($course->id, $user->id)));
            case self::FILTER_COHORT:
                require_once($CFG->dirroot . '/cohort/lib.php');
                $field = $config->cohortfield ?? 'name';
                return array_values(array_map(fn($c) => (string) $c->{$field}, cohort_get_user_cohorts($user->id)));
            case self::FILTER_USER:
                return [self::get_user_value($user)];
        }

        if (str_starts_with($this->filterby, self::FILTER_PROFILE)) {
            $field = substr($this->filterby, strlen(self::FILTER_PROFILE));
            if (in_array($field, self::PROFILE_FIELDS, true)) {
                $record = \core_user::get_user($user->id, implode(',', self::PROFILE_FIELDS));
                $value = (string) ($record->{$field} ?? '');
            } else {
                require_once($CFG->dirroot . '/user/profile/lib.php');
                $value = (string) (profile_user_record($user->id, false)->{$field} ?? '');
            }
            return $value === '' ? [] : [$value];
        }
        return null;
    }

    /**
     * The user field matched in Power BI (filters and row-level security).
     *
     * @param \stdClass $user
     * @return string
     */
    public static function get_user_value(\stdClass $user): string {
        $field = get_config('filter_powerbi', 'userfield') ?: 'email';
        return (string) ($user->{$field} ?? $user->email);
    }

    /**
     * Address of the secure embed (the viewer signs in with Microsoft).
     *
     * @param \context $context
     * @param \stdClass $user
     * @return \moodle_url
     */
    public function get_secure_url(\context $context, \stdClass $user): \moodle_url {
        $params = ['reportId' => $this->reportid, 'autoAuth' => 'true'];
        if ($this->groupid !== 'me') {
            $params['groupId'] = $this->groupid;
        }
        if ($this->get_tenantid() !== '') {
            $params['ctid'] = $this->get_tenantid();
        }
        if ($this->pagename !== '') {
            $params['pageName'] = $this->pagename;
        }
        $params['navContentPaneEnabled'] = $this->navpane ? 'true' : 'false';
        $params['filterPaneEnabled'] = $this->filterpane ? 'true' : 'false';
        $values = $this->get_filter_values($context, $user);
        if ($values !== null) {
            $field = self::escape_name($this->filtertable) . '/' . self::escape_name($this->filtercolumn);
            $quoted = array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $values ?: ['']);
            $params['filter'] = count($quoted) === 1
                ? "{$field} eq {$quoted[0]}"
                : "{$field} in (" . implode(',', $quoted) . ')';
        }
        return new \moodle_url('https://app.powerbi.com/reportEmbed', $params);
    }

    /**
     * Escape a table or column name for a Power BI URL filter (a space becomes _x0020_, and so on).
     *
     * @param string $name
     * @return string
     */
    public static function escape_name(string $name): string {
        return preg_replace_callback('/[^A-Za-z0-9_]/u', function ($m) {
            $code = mb_ord($m[0], 'UTF-8');
            return '_x' . sprintf('%04x', $code) . '_';
        }, $name);
    }

    /**
     * Data needed to show the report, signed for the context it is shown in.
     *
     * The token web service only accepts signed references, so nobody can ask for a token of a report that is not
     * placed in content they can see.
     *
     * @param \context $context
     * @return string
     */
    public function get_reference(\context $context): string {
        $data = json_encode([
            'c' => $context->id,
            'g' => $this->groupid,
            'r' => $this->reportid,
            'p' => $this->pagename,
            'f' => $this->filterby,
            't' => $this->filtertable,
            'k' => $this->filtercolumn,
            'l' => $this->rlsrole,
            'n' => $this->navpane,
            'q' => $this->filterpane,
        ]);
        $payload = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        return $payload . '.' . self::sign($payload);
    }

    /**
     * Read a reference made by get_reference().
     *
     * @param string $reference
     * @param \context $context Context the caller says the content is in.
     * @return self|null Null when the signature or the context do not match.
     */
    public static function from_reference(string $reference, \context $context): ?self {
        [$payload, $signature] = array_pad(explode('.', $reference, 2), 2, '');
        if ($payload === '' || !hash_equals(self::sign($payload), $signature)) {
            return null;
        }
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($data) || (int) ($data['c'] ?? 0) !== (int) $context->id) {
            return null;
        }
        $embed = new self();
        $embed->groupid = (string) $data['g'];
        $embed->reportid = (string) $data['r'];
        $embed->pagename = (string) $data['p'];
        $embed->filterby = (string) $data['f'];
        $embed->filtertable = (string) $data['t'];
        $embed->filtercolumn = (string) $data['k'];
        $embed->rlsrole = (string) $data['l'];
        $embed->navpane = (bool) $data['n'];
        $embed->filterpane = (bool) $data['q'];
        $embed->mode = self::MODE_EMBEDDED;
        return $embed;
    }

    /**
     * HMAC of a payload with the site's signing key.
     *
     * @param string $payload
     * @return string
     */
    private static function sign(string $payload): string {
        $key = (string) get_config('filter_powerbi', 'signingkey');
        if ($key === '') {
            throw new \coding_exception('filter_powerbi signing key missing');
        }
        return hash_hmac('sha256', $payload, $key);
    }
}
