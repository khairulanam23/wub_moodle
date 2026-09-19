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
 * Resolves UMS student identities to Moodle accounts without ever guessing.
 *
 * Priority of strong identifiers: (1) Moodle username == UMS 10-digit username, (2) Moodle idnumber == UMS
 * registration id or username, (3) institutional e-mail <username>@student.wub.edu.bd. A student that maps to more
 * than one Moodle account is AMBIGUOUS_MATCH and is blocked; a student with no account is NOT_FOUND.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Identity resolution service.
 */
class student_identity_service {
    /** @var string */
    public const RESOLVED = 'RESOLVED';
    /** @var string */
    public const AMBIGUOUS = 'AMBIGUOUS_MATCH';
    /** @var string */
    public const NOT_FOUND = 'NOT_FOUND';
    /** @var string Institutional e-mail domain used only as a matching candidate, never as displayed data. */
    public const EMAIL_DOMAIN = 'student.wub.edu.bd';
    /** @var string Fields selected for matched users. */
    protected const USER_FIELDS = 'id, username, email, idnumber, firstname, lastname, department, institution, suspended, auth';

    /**
     * Resolve many UMS students at once.
     *
     * @param array $identities List of ['username' => string, 'reg_id' => string]. reg_id optional.
     * @return array Keyed by username: ['status' => RESOLVED|AMBIGUOUS_MATCH|NOT_FOUND, 'user' => stdClass|null,
     *               'matched_field' => string, 'candidates' => int]
     */
    public function bulk_resolve(array $identities): array {
        global $DB, $CFG;

        $byusername = [];
        foreach ($identities as $row) {
            $un = strtolower(trim((string)($row['username'] ?? '')));
            if ($un === '') {
                continue;
            }
            $byusername[$un] = trim((string)($row['reg_id'] ?? ''));
        }
        $results = [];
        foreach (array_chunk(array_keys($byusername), 400) as $chunk) {
            $usernames = $chunk;
            $emails = array_map(fn($u) => $u . '@' . self::EMAIL_DOMAIN, $chunk);
            $idnumbers = $chunk;
            foreach ($chunk as $u) {
                if ($byusername[$u] !== '') {
                    $idnumbers[] = $byusername[$u];
                }
            }
            [$s1, $p1] = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED, 'un');
            [$s2, $p2] = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'em');
            [$s3, $p3] = $DB->get_in_or_equal(array_values(array_unique($idnumbers)), SQL_PARAMS_NAMED, 'idn');
            $sql = "SELECT " . self::USER_FIELDS . "
                      FROM {user}
                     WHERE deleted = 0 AND mnethostid = :mnet
                       AND (username $s1 OR LOWER(email) $s2 OR idnumber $s3)";
            $users = $DB->get_records_sql($sql, array_merge($p1, $p2, $p3, ['mnet' => $CFG->mnet_localhost_id]));

            $byun = [];
            $byemail = [];
            $byidn = [];
            foreach ($users as $u) {
                $byun[strtolower($u->username)][] = $u;
                if ($u->email !== '') {
                    $byemail[strtolower($u->email)][] = $u;
                }
                if ((string)$u->idnumber !== '') {
                    $byidn[(string)$u->idnumber][] = $u;
                }
            }
            // Duplicate-idnumber guard: if a matched account's idnumber is shared by another account, the identity is
            // ambiguous even when the caller did not supply the registration id.
            $matchedidn = [];
            foreach ($users as $m) {
                if ((string)$m->idnumber !== '') {
                    $matchedidn[(string)$m->idnumber] = true;
                }
            }
            if (!empty($matchedidn)) {
                [$s4, $p4] = $DB->get_in_or_equal(array_keys($matchedidn), SQL_PARAMS_NAMED, 'dup');
                $dups = $DB->get_records_sql("SELECT " . self::USER_FIELDS . " FROM {user} WHERE deleted = 0 AND mnethostid = :mnet AND idnumber $s4",
                    $p4 + ['mnet' => $CFG->mnet_localhost_id]);
                foreach ($dups as $m) {
                    if (!isset($users[$m->id])) {
                        $users[$m->id] = $m;
                    }
                    $byidn[(string)$m->idnumber][$m->id] = $m;
                }
                foreach ($byidn as $k => $list) {
                    $uniq = [];
                    foreach ($list as $m) {
                        $uniq[$m->id] = $m;
                    }
                    $byidn[$k] = array_values($uniq);
                }
            }
            foreach ($chunk as $u) {
                $cands = [];
                $field = '';
                foreach ($byun[$u] ?? [] as $m) {
                    $cands[$m->id] = $m;
                    $field = $field ?: 'username';
                }
                foreach ($byidn[$u] ?? [] as $m) {
                    $cands[$m->id] = $m;
                    $field = $field ?: 'idnumber';
                }
                if ($byusername[$u] !== '') {
                    foreach ($byidn[$byusername[$u]] ?? [] as $m) {
                        $cands[$m->id] = $m;
                        $field = $field ?: 'idnumber';
                    }
                }
                foreach ($byemail[$u . '@' . self::EMAIL_DOMAIN] ?? [] as $m) {
                    $cands[$m->id] = $m;
                    $field = $field ?: 'email';
                }
                // Expand with accounts sharing the idnumber of any candidate.
                foreach (array_values($cands) as $m) {
                    if ((string)$m->idnumber !== '') {
                        foreach ($byidn[(string)$m->idnumber] ?? [] as $dup) {
                            $cands[$dup->id] = $dup;
                        }
                    }
                }
                $n = count($cands);
                $results[$u] = [
                    'status' => $n === 1 ? self::RESOLVED : ($n > 1 ? self::AMBIGUOUS : self::NOT_FOUND),
                    'user' => $n === 1 ? reset($cands) : null,
                    'matched_field' => $n === 1 ? $field : '',
                    'candidates' => $n,
                ];
            }
        }
        return $results;
    }

    /**
     * Resolve a single free-text identifier (registration id, username, institutional e-mail or idnumber).
     *
     * @param string $identifier
     * @return array ['user' => stdClass|null, 'status' => RESOLVED|AMBIGUOUS_MATCH|NOT_FOUND|INVALID_INPUT,
     *               'identifier' => string, 'matched_field' => string]
     */
    public function resolve_identifier(string $identifier): array {
        global $DB, $CFG;
        $clean = trim($identifier);
        if ($clean === '') {
            return ['user' => null, 'status' => 'INVALID_INPUT', 'identifier' => $identifier, 'matched_field' => ''];
        }
        $lower = strtolower($clean);
        $local = explode('@', $lower)[0];
        $digits = preg_replace('/[^0-9]/', '', $local);
        $usernames = array_values(array_unique(array_filter([$lower, $local, $digits])));
        $emails = array_values(array_unique(array_filter([str_contains($lower, '@') ? $lower : '', $local . '@' . self::EMAIL_DOMAIN, $digits !== '' ? $digits . '@' . self::EMAIL_DOMAIN : ''])));
        $idnumbers = array_values(array_unique(array_filter([$clean, $digits])));
        [$s1, $p1] = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED, 'un');
        [$s2, $p2] = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'em');
        [$s3, $p3] = $DB->get_in_or_equal($idnumbers, SQL_PARAMS_NAMED, 'idn');
        $sql = "SELECT " . self::USER_FIELDS . " FROM {user}
                 WHERE deleted = 0 AND mnethostid = :mnet AND (username $s1 OR LOWER(email) $s2 OR idnumber $s3)";
        $users = $DB->get_records_sql($sql, array_merge($p1, $p2, $p3, ['mnet' => $CFG->mnet_localhost_id]));
        $idns = array_values(array_unique(array_filter(array_map(fn($m) => (string)$m->idnumber, $users))));
        if (!empty($idns)) {
            [$s4, $p4] = $DB->get_in_or_equal($idns, SQL_PARAMS_NAMED, 'dup');
            foreach ($DB->get_records_sql("SELECT " . self::USER_FIELDS . " FROM {user} WHERE deleted = 0 AND mnethostid = :mnet AND idnumber $s4",
                    $p4 + ['mnet' => $CFG->mnet_localhost_id]) as $m) {
                $users[$m->id] = $m;
            }
        }
        $n = count($users);
        if ($n === 1) {
            $u = reset($users);
            $field = in_array(strtolower($u->username), $usernames, true) ? 'username'
                : (in_array((string)$u->idnumber, $idnumbers, true) ? 'idnumber' : 'email');
            return ['user' => $u, 'status' => self::RESOLVED, 'identifier' => $clean, 'matched_field' => $field];
        }
        return ['user' => null, 'status' => $n > 1 ? self::AMBIGUOUS : self::NOT_FOUND, 'identifier' => $clean, 'matched_field' => ''];
    }

    /**
     * Resolve several free-text identifiers.
     *
     * @param string[] $identifiers
     * @return array Keyed by identifier.
     */
    public function batch_resolve(array $identifiers): array {
        $results = [];
        foreach (array_values(array_unique(array_filter(array_map('trim', $identifiers)))) as $ident) {
            $results[$ident] = $this->resolve_identifier($ident);
        }
        return $results;
    }
}
