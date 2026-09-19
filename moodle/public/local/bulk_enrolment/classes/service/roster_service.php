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
 * Roster service: UMS programs/batches/students normalised for the enrolment workspace.
 *
 * Data flow: api_client (raw UMS) -> models -> this service (validation, caching, identity matching,
 * search/filter/pagination, API #1 enrichment of the visible page) -> external functions -> AMD workspace.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use cache;
use local_bulk_enrolment\api_client;
use local_bulk_enrolment\exception\ums_exception;
use local_bulk_enrolment\exception\ums_not_found_exception;
use local_bulk_enrolment\model\ums_student;

/**
 * Roster service.
 */
class roster_service {
    /** @var string Row match status: exactly one Moodle account. */
    public const MATCHED = 'MATCHED';
    /** @var string Row match status: no Moodle account. */
    public const NOT_FOUND = 'NOT_FOUND';
    /** @var string Row match status: several Moodle accounts. */
    public const AMBIGUOUS = 'AMBIGUOUS';
    /** @var int Default page size. */
    public const DEFAULT_PER_PAGE = 25;
    /** @var int Maximum page size (bounded to keep the DOM and API #1 calls small). */
    public const MAX_PER_PAGE = 100;

    /** @var api_client */
    protected api_client $client;
    /** @var student_identity_service */
    protected student_identity_service $identity;

    /**
     * Constructor.
     *
     * @param api_client|null $client
     * @param student_identity_service|null $identity
     */
    public function __construct(?api_client $client = null, ?student_identity_service $identity = null) {
        $this->client = $client ?: new api_client();
        $this->identity = $identity ?: new student_identity_service();
    }

    /**
     * Programs as plain arrays, active first, sorted by title.
     *
     * @param bool $refresh Bypass the cache.
     * @return array
     * @throws ums_exception
     */
    public function list_programs(bool $refresh = false): array {
        $programs = array_map(fn($p) => $p->to_array(), $this->client->get_programs($refresh));
        usort($programs, fn($a, $b) => [$b['is_active'], $a['title']] <=> [$a['is_active'], $b['title']]);
        return $programs;
    }

    /**
     * Batches for several programs, keyed by program id, newest batch title first.
     *
     * @param string[] $programids
     * @param bool $refresh
     * @return array program id => batch arrays
     * @throws ums_exception
     */
    public function list_batches(array $programids, bool $refresh = false): array {
        $out = [];
        foreach (array_values(array_unique(array_filter(array_map('trim', $programids)))) as $pid) {
            $batches = array_map(fn($b) => $b->to_array(), $this->client->get_batches($pid, $refresh));
            usort($batches, fn($a, $b) => strnatcasecmp($b['title'], $a['title']));
            $out[$pid] = $batches;
        }
        return $out;
    }

    /**
     * Load and merge rosters for a set of program/batch selections.
     *
     * @param array $selections [['program_id' => '300', 'batch_titles' => ['74F', ...]], ...]; empty titles = whole program.
     * @param bool $refresh Bypass the roster cache.
     * @return array ['students' => ums_student[] keyed by username, 'sources' => [...], 'warnings' => string[]]
     * @throws ums_exception Propagated UMS failures (never converted to an empty roster).
     */
    public function load_roster(array $selections, bool $refresh = false): array {
        $cache = cache::make('local_bulk_enrolment', 'roster');
        $students = [];
        $sources = [];
        $warnings = [];
        foreach ($selections as $sel) {
            $pid = trim((string)($sel['program_id'] ?? ''));
            if ($pid === '') {
                continue;
            }
            $titles = array_values(array_unique(array_filter(array_map('trim', (array)($sel['batch_titles'] ?? [])))));
            $targets = empty($titles) ? [null] : $titles;
            foreach ($targets as $title) {
                $validtitle = null;
                if ($title !== null) {
                    $batch = $this->client->find_batch($pid, $title);
                    if (!$batch) {
                        // Unknown titles make UMS return the whole program: refuse instead of enrolling everyone.
                        throw new ums_exception('error_invalid_batch', "{$title} (program {$pid})", '', 'INVALID_BATCH');
                    }
                    $validtitle = $batch->title;
                }
                $key = 'roster_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pid . '_' . ($validtitle ?? 'all'));
                $rows = $refresh ? false : $cache->get($key);
                $status = 'OK';
                if (!is_array($rows)) {
                    try {
                        $models = $this->client->get_roster($pid, $validtitle);
                    } catch (ums_not_found_exception $e) {
                        // The batch title was validated against API #3, so 404 here means "no registered students".
                        $models = [];
                    }
                    if ($validtitle !== null) {
                        $kept = [];
                        foreach ($models as $m) {
                            if (strcasecmp($m->get_batch(), $validtitle) === 0 || strcasecmp($m->mother_batch, $validtitle) === 0) {
                                $kept[] = $m;
                            }
                        }
                        if (count($kept) !== count($models)) {
                            $warnings[] = get_string('warn_batch_mismatch', 'local_bulk_enrolment',
                                (object)['batch' => $validtitle, 'dropped' => count($models) - count($kept)]);
                        }
                        $models = $kept;
                    }
                    $rows = array_map(fn($m) => $m->to_array(), $models);
                    $cache->set($key, $rows);
                }
                if (empty($rows)) {
                    $status = 'EMPTY';
                }
                foreach ($rows as $r) {
                    $s = ums_student::from_array($r);
                    if (!isset($students[$s->username])) {
                        $students[$s->username] = $s;
                    }
                }
                $sources[] = ['program_id' => $pid, 'batch_title' => $validtitle ?? '', 'count' => count($rows), 'status' => $status];
            }
        }
        return ['students' => $students, 'sources' => $sources, 'warnings' => $warnings];
    }

    /**
     * Query a roster page for the workspace.
     *
     * @param array $selections See load_roster().
     * @param array $filters ['search' => '', 'batch' => '', 'match' => ''|MATCHED|NOT_FOUND|AMBIGUOUS, 'program' => '']
     * @param int $page 1-based page.
     * @param int $perpage
     * @param bool $refresh
     * @return array
     * @throws ums_exception
     */
    public function query(array $selections, array $filters = [], int $page = 1, int $perpage = self::DEFAULT_PER_PAGE, bool $refresh = false): array {
        $perpage = max(5, min(self::MAX_PER_PAGE, $perpage));
        $page = max(1, $page);

        $loaded = $this->load_roster($selections, $refresh);
        $students = $loaded['students'];

        // Identity resolution for the whole roster (one SQL round-trip per 400 students).
        $resolved = $this->identity->bulk_resolve(array_map(fn($s) => ['username' => $s->username, 'reg_id' => $s->reg_id], $students));

        $programs = [];
        try {
            foreach ($this->client->get_programs() as $p) {
                $programs[$p->id] = $p;
            }
        } catch (ums_exception $e) {
            $programs = []; // Labels are cosmetic; the roster itself already loaded.
        }

        $rows = [];
        $facetbatch = [];
        $facetmatch = [self::MATCHED => 0, self::NOT_FOUND => 0, self::AMBIGUOUS => 0];
        foreach ($students as $s) {
            $res = $resolved[strtolower($s->username)] ?? ['status' => student_identity_service::NOT_FOUND, 'user' => null, 'matched_field' => '', 'candidates' => 0];
            $match = $res['status'] === student_identity_service::RESOLVED ? self::MATCHED
                : ($res['status'] === student_identity_service::AMBIGUOUS ? self::AMBIGUOUS : self::NOT_FOUND);
            $u = $res['user'];
            $batch = $s->get_batch();
            $row = [
                'key' => $s->username,
                'username' => $s->username,
                'reg_id' => $s->reg_id,
                'full_name' => $s->full_name !== '' ? $s->full_name : ($u ? fullname($u) : $s->username),
                'program_id' => $s->program_id,
                'program_name' => $s->program_name,
                'program_short' => isset($programs[$s->program_id]) ? $programs[$s->program_id]->short_title : '',
                'batch' => $batch,
                'batch_name' => $batch,
                'mother_batch' => $s->mother_batch,
                'current_batch' => $s->current_batch,
                'university_email' => '',
                'shift' => '',
                'courses' => array_map(fn($c) => $c->to_array(), $s->courses),
                'course_count' => count($s->courses),
                'course_codes' => $s->get_course_codes_summary(),
                'match_status' => $match,
                'matched_field' => (string)$res['matched_field'],
                'candidates' => (int)$res['candidates'],
                'is_existing' => ($u !== null),
                'moodle_user_id' => $u ? (int)$u->id : 0,
                'moodle_username' => $u ? $u->username : '',
                'moodle_fullname' => $u ? fullname($u) : '',
                'moodle_email' => $u ? $u->email : '',
                'moodle_idnumber' => $u ? (string)$u->idnumber : '',
                'suspended' => $u ? (bool)$u->suspended : false,
                'selectable' => true,
            ];
            $facetbatch[$batch] = ($facetbatch[$batch] ?? 0) + 1;
            $facetmatch[$match]++;
            $rows[] = $row;
        }

        // Filters.
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        $fbatch = trim((string)($filters['batch'] ?? ''));
        $fmatch = strtoupper(trim((string)($filters['match'] ?? '')));
        $fprogram = trim((string)($filters['program'] ?? ''));
        $filtered = array_values(array_filter($rows, function($r) use ($search, $fbatch, $fmatch, $fprogram) {
            if ($fbatch !== '' && strcasecmp($r['batch'], $fbatch) !== 0) {
                return false;
            }
            if ($fmatch !== '' && $r['match_status'] !== $fmatch) {
                return false;
            }
            if ($fprogram !== '' && $r['program_id'] !== $fprogram) {
                return false;
            }
            if ($search !== '') {
                $titles = implode(' ', array_map(fn($c) => $c['title'], $r['courses']));
                $hay = strtolower($r['username'] . ' ' . $r['reg_id'] . ' ' . $r['full_name'] . ' ' . $r['moodle_email'] . ' ' . $r['course_codes'] . ' ' . $titles);
                if (!str_contains($hay, $search)) {
                    return false;
                }
            }
            return true;
        }));
        usort($filtered, fn($a, $b) => [strnatcasecmp($a['batch'], $b['batch']), strcasecmp($a['full_name'], $b['full_name'])] <=> [0, 0]);

        $total = count($filtered);
        $pages = max(1, (int)ceil($total / $perpage));
        $page = min($page, $pages);
        $pagerows = array_slice($filtered, ($page - 1) * $perpage, $perpage);

        // Enrich only the visible page with API #1 (e-mail, shift); failures here must not hide the roster.
        $enrichwarning = '';
        try {
            $details = $this->get_details_cached(array_map(fn($r) => $r['username'], $pagerows));
            foreach ($pagerows as &$r) {
                if (isset($details[$r['username']])) {
                    $d = $details[$r['username']];
                    $r['university_email'] = $d['university_email'];
                    $r['shift'] = $d['shift'];
                    if ($r['reg_id'] === '' && $d['reg_id'] !== '') {
                        $r['reg_id'] = $d['reg_id'];
                    }
                }
            }
            unset($r);
        } catch (ums_exception $e) {
            $enrichwarning = get_string('warn_enrich_failed', 'local_bulk_enrolment', $e->get_error_code());
        }
        $warnings = $loaded['warnings'];
        if ($enrichwarning !== '') {
            $warnings[] = $enrichwarning;
        }
        ksort($facetbatch, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'total' => $total,
            'total_unfiltered' => count($rows),
            'page' => $page,
            'pages' => $pages,
            'perpage' => $perpage,
            'rows' => $pagerows,
            'selectable_keys' => array_values(array_map(fn($r) => ['key' => $r['key'], 'reg_id' => $r['reg_id'], 'full_name' => $r['full_name'], 'batch' => $r['batch']],
                $filtered)),
            'facets' => [
                'batches' => array_map(fn($k, $v) => ['title' => (string)$k, 'count' => $v], array_keys($facetbatch), $facetbatch),
                'matched' => $facetmatch[self::MATCHED],
                'not_found' => $facetmatch[self::NOT_FOUND],
                'ambiguous' => $facetmatch[self::AMBIGUOUS],
            ],
            'sources' => $loaded['sources'],
            'warnings' => $warnings,
        ];
    }

    /**
     * API #1 details for a small set of usernames, cached 10 minutes per username.
     *
     * @param string[] $usernames
     * @return array username => details array
     * @throws ums_exception
     */
    public function get_details_cached(array $usernames): array {
        $cache = cache::make('local_bulk_enrolment', 'student_details');
        $out = [];
        $missing = [];
        foreach (array_values(array_unique(array_filter($usernames))) as $un) {
            $hit = $cache->get('d_' . $un);
            if (is_array($hit)) {
                $out[$un] = $hit;
            } else {
                $missing[] = $un;
            }
        }
        if (!empty($missing)) {
            foreach ($this->client->get_student_details_by_usernames($missing) as $un => $d) {
                $out[$un] = $d->to_array();
                $cache->set('d_' . $un, $out[$un]);
            }
        }
        return $out;
    }

    /**
     * Purge cached rosters and details (programs/batches caches are purged from the diagnostics tab).
     */
    public static function purge_roster_caches(): void {
        cache::make('local_bulk_enrolment', 'roster')->purge();
        cache::make('local_bulk_enrolment', 'student_details')->purge();
    }
}
