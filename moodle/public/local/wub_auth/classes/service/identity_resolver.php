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

namespace local_wub_auth\service;

use local_wub_auth\api_client;
use local_wub_auth\model\identity_result;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Deterministic Student & Institutional Identity Resolver.
 *
 * Implements strict resolution hierarchy with multi-match collision guard:
 * 1. Exact Moodle username match
 * 2. Exact Moodle email match
 * 3. Exact Moodle idnumber (Registration ID) match
 * 4. External UMS student identity probe (unprovisioned detection)
 *
 * Rejects ambiguous matches to prevent credential collision.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_resolver {
    protected ?api_client $apiclient;

    public function __construct(?api_client $apiclient = null) {
        $this->apiclient = $apiclient ?? new api_client();
    }

    /**
     * Resolve a user identifier deterministically.
     *
     * @param string $identifier Student ID, institutional email, username, or registration ID.
     * @return identity_result
     */
    public function resolve(string $identifier): identity_result {
        global $DB;

        $raw = trim($identifier);
        if ($raw === '') {
            return identity_result::invalid($identifier, 'Identifier cannot be empty.');
        }

        // Canonical forms
        $lowercase = \core_text::strtolower($raw);
        $shortpart = explode('@', $lowercase)[0];
        $digits = preg_replace('/[^0-9]/', '', $shortpart);
        $cleanid = !empty($digits) ? $digits : $shortpart;
        $studentemail = !empty($digits) ? $digits . '@student.wub.edu.bd' : $lowercase;
        $cleanreg = \core_text::strtoupper(preg_replace('/\s+/', '', $raw));

        $cleanids = [$raw, $cleanid, $cleanreg];
        $cleanemails = [$raw, $lowercase, $studentemail];

        if (!empty($digits)) {
            if (strlen($digits) === 9) {
                $cleanids[] = '0' . $digits;
                $cleanemails[] = '0' . $digits . '@student.wub.edu.bd';
            } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                $cleanids[] = substr($digits, 1);
                $cleanemails[] = substr($digits, 1) . '@student.wub.edu.bd';
            }
        }
        $cleanids = array_values(array_unique(array_filter($cleanids)));
        $cleanemails = array_values(array_unique(array_filter($cleanemails)));

        // 1. Moodle username
        $matchedusers = [];
        $matchtype = null;

        list($uninsql, $unparams) = $DB->get_in_or_equal($cleanids, SQL_PARAMS_NAMED, 'un');
        $unmatches = $DB->get_records_select(
            'user',
            "deleted = 0 AND LOWER(username) $uninsql",
            $unparams
        );
        foreach ($unmatches as $u) {
            $matchedusers[$u->id] = ['user' => $u, 'type' => identity_result::MATCH_USERNAME];
        }

        // 2. Moodle institutional email
        list($eminsql, $emparams) = $DB->get_in_or_equal($cleanemails, SQL_PARAMS_NAMED, 'em');
        $emailmatches = $DB->get_records_select(
            'user',
            "deleted = 0 AND LOWER(email) $eminsql",
            $emparams
        );
        foreach ($emailmatches as $u) {
            if (!isset($matchedusers[$u->id])) {
                $matchedusers[$u->id] = ['user' => $u, 'type' => identity_result::MATCH_EMAIL];
            }
        }

        // 3. Moodle idnumber (authoritative UMS registration ID, e.g. WUB03/26/74/5530)
        list($idinsql1, $idparams1) = $DB->get_in_or_equal($cleanids, SQL_PARAMS_NAMED, 'id1');
        list($idinsql2, $idparams2) = $DB->get_in_or_equal($cleanids, SQL_PARAMS_NAMED, 'id2');
        $idparams = array_merge($idparams1, $idparams2);
        $idmatches = $DB->get_records_select(
            'user',
            "deleted = 0 AND (LOWER(idnumber) $idinsql1 OR LOWER(REPLACE(idnumber, ' ', '')) $idinsql2)",
            $idparams
        );
        foreach ($idmatches as $u) {
            if (!isset($matchedusers[$u->id])) {
                $matchedusers[$u->id] = ['user' => $u, 'type' => identity_result::MATCH_IDNUMBER];
            }
        }

        // Ambiguity Collision Guard: Multiple distinct Moodle users matched!
        if (count($matchedusers) > 1) {
            // Check if exactly one has an exact match on username or idnumber
            $exactmatches = [];
            foreach ($matchedusers as $uid => $match) {
                $u = $match['user'];
                if (\core_text::strtolower($u->username) === $lowercase || \core_text::strtolower(str_replace(' ', '', (string)$u->idnumber)) === \core_text::strtolower($cleanreg) || \core_text::strtolower($u->email) === $lowercase) {
                    $exactmatches[$uid] = $match;
                }
            }
            if (count($exactmatches) === 1) {
                $match = reset($exactmatches);
                return identity_result::found($match['user'], $match['type'], $raw);
            }

            return identity_result::ambiguous(
                $raw,
                'Ambiguous identity: multiple user accounts match this identifier.'
            );
        }

        // Single unique local Moodle user resolved
        if (count($matchedusers) === 1) {
            $match = reset($matchedusers);
            return identity_result::found($match['user'], $match['type'], $raw);
        }

        // 4. If not found in local Moodle, probe external UMS
        if ($this->apiclient->is_configured()) {
            try {
                $umsstudent = $this->apiclient->get_student_details($cleanid);
                if (!$umsstudent && $cleanid !== $raw) {
                    $umsstudent = $this->apiclient->get_student_details($raw);
                }

                if ($umsstudent && !empty($umsstudent['stud_id'])) {
                    // Check if a local Moodle user has this authoritative registration ID
                    $studid = (string)$umsstudent['stud_id'];
                    $localbyregid = $DB->get_record('user', ['deleted' => 0, 'idnumber' => $studid]);
                    if ($localbyregid) {
                        return identity_result::found(
                            $localbyregid,
                            identity_result::MATCH_IDNUMBER,
                            $raw,
                            $umsstudent
                        );
                    }

                    // Student exists in UMS, but has not been provisioned in Moodle yet
                    return identity_result::not_found($raw, $umsstudent);
                }
            } catch (\Exception $e) {
                // UMS probe failed or timed out — treat as local not found safely
            }
        }

        return identity_result::not_found($raw);
    }
}
