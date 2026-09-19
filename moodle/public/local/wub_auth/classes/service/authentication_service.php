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

use local_wub_auth\model\auth_result;
use local_wub_auth\model\identity_result;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Central Authentication and Institutional Access Control Orchestrator.
 *
 * Coordinates identity resolution -> native authentication -> UMS fallback
 * -> account state validation -> authoritative role resolution -> financial clearance
 * -> policy requirement checking -> session establishment.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class authentication_service {
    protected identity_resolver $identityresolver;
    protected ums_authenticator $umsauthenticator;
    protected role_resolver $roleresolver;
    protected financial_clearance_service $clearanceservice;
    protected policy_service $policyservice;
    protected session_service $sessionservice;
    protected audit_service $auditservice;

    public function __construct(
        ?identity_resolver $identityresolver = null,
        ?ums_authenticator $umsauthenticator = null,
        ?role_resolver $roleresolver = null,
        ?financial_clearance_service $clearanceservice = null,
        ?policy_service $policyservice = null,
        ?session_service $sessionservice = null,
        ?audit_service $auditservice = null
    ) {
        $this->identityresolver = $identityresolver ?? new identity_resolver();
        $this->umsauthenticator = $umsauthenticator ?? new ums_authenticator();
        $this->roleresolver = $roleresolver ?? new role_resolver();
        $this->clearanceservice = $clearanceservice ?? new financial_clearance_service();
        $this->policyservice = $policyservice ?? new policy_service();
        $this->sessionservice = $sessionservice ?? new session_service();
        $this->auditservice = $auditservice ?? new audit_service();
    }

    /**
     * Authenticate credentials and evaluate institutional access rules.
     *
     * @param string $identifier Student ID, email, username, or registration ID.
     * @param string $password Submitted plaintext password.
     * @param array $options Optional flags (e.g. 'returnurl' => string).
     * @return auth_result
     */
    public function authenticate(string $identifier, string $password, array $options = []): auth_result {
        global $CFG;

        $rawidentifier = trim($identifier);
        $rawpassword = (string)$password;

        if ($rawidentifier === '' || $rawpassword === '') {
            return auth_result::invalid_credentials(get_string('error_empty_credentials', 'local_wub_auth'));
        }

        // 1. Identity Resolution
        $idresult = $this->identityresolver->resolve($rawidentifier);

        if ($idresult->is_ambiguous()) {
            $this->auditservice->log(0, 'AMBIGUOUS_IDENTITY', 'BLOCKED', [], $rawidentifier);
            return auth_result::ambiguous(get_string('error_ambiguous_identity', 'local_wub_auth'));
        }

        // 2. User Not Found in Local Moodle
        if (!$idresult->is_found()) {
            // Check if user exists in UMS and can authenticate externally
            if ($this->umsauthenticator->is_enabled()) {
                $umsauth = $this->umsauthenticator->authenticate(
                    $rawidentifier,
                    $rawpassword,
                    $idresult->get_ums_data()
                );

                if ($umsauth['authenticated']) {
                    // Valid UMS student credentials, but NO Moodle account provisioned
                    $this->auditservice->log(0, 'ACCOUNT_NOT_PROVISIONED', 'REJECTED', [], $rawidentifier);
                    return auth_result::not_provisioned(get_string('error_account_not_provisioned', 'local_wub_auth'));
                }
            }

            // Unknown account or failed credentials
            $this->auditservice->log(0, 'LOGIN_FAILURE', 'INVALID_CREDENTIALS', [], $rawidentifier);
            return auth_result::invalid_credentials(get_string('error_invalid_credentials', 'local_wub_auth'));
        }

        // 3. User Found in Local Moodle
        $user = $idresult->get_user();

        // Check account state
        if (!empty($user->suspended)) {
            $this->auditservice->log((int)$user->id, 'LOGIN_FAILURE', 'SUSPENDED', [], $rawidentifier);
            return auth_result::suspended(get_string('error_account_suspended', 'local_wub_auth'));
        }

        if (($user->auth ?? '') === 'nologin') {
            $this->auditservice->log((int)$user->id, 'LOGIN_FAILURE', 'NOLOGIN', [], $rawidentifier);
            return auth_result::error(get_string('error_account_nologin', 'local_wub_auth'));
        }

        // 4. Native Moodle Password Verification
        require_once($CFG->dirroot . '/user/lib.php');
        $authenticated = false;
        $umsfallbackused = false;

        if (!empty($user->password) && $user->password !== 'not cached' && validate_internal_user_password($user, $rawpassword)) {
            $authenticated = true;
        }

        // 5. UMS Fallback Authentication
        if (!$authenticated && $this->umsauthenticator->is_enabled()) {
            $umsauth = $this->umsauthenticator->authenticate(
                $rawidentifier,
                $rawpassword,
                $idresult->get_ums_data()
            );

            if ($umsauth['authenticated']) {
                $authenticated = true;
                $umsfallbackused = true;

                // Safely update Moodle internal password hash
                $autosync = (bool)get_config('local_wub_auth', 'sync_password_on_ums_login');
                if ($autosync || !defined('PHPUNIT_TEST')) {
                    update_internal_user_password($user, $rawpassword);
                }

                $this->auditservice->log((int)$user->id, 'UMS_AUTH_SUCCESS', 'SUCCESS', [
                    'strategy' => 'ums_fallback',
                ]);
            }
        }

        if (!$authenticated) {
            $this->auditservice->log((int)$user->id, 'LOGIN_FAILURE', 'INVALID_CREDENTIALS', [], $rawidentifier);
            return auth_result::invalid_credentials(get_string('error_invalid_credentials', 'local_wub_auth'));
        }

        // 6. Determine Authoritative Role & Verify Persona Authorization
        $role = $this->roleresolver->resolve_primary_role($user);

        $requestedrole = !empty($options['role']) ? trim((string)$options['role']) : (!empty($options['persona']) ? trim((string)$options['persona']) : null);
        if ($requestedrole !== null && !$this->roleresolver->can_act_as_role($user, $requestedrole)) {
            $this->auditservice->log((int)$user->id, 'UNAUTHORIZED_PERSONA', 'REJECTED', [
                'requested_role' => $requestedrole,
                'authoritative_role' => $role,
            ], $rawidentifier);
            $msg = get_string('error_unauthorized_persona', 'local_wub_auth', (object)[
                'role' => ucfirst($requestedrole),
            ]);
            return auth_result::unauthorized_persona($msg);
        }

        // 7. Check Institutional Financial Access Control
        $clearance = $this->clearanceservice->check_clearance($user);
        if (!$clearance->is_allowed()) {
            $this->auditservice->log((int)$user->id, 'ACCESS_RESTRICTED', 'RESTRICTED', $clearance->to_array());
            return auth_result::restricted($user, $clearance, $role);
        }

        // 8. Check Policy Acknowledgement
        $policyrequired = false;
        if ($this->policyservice->is_enabled()) {
            $policyrequired = !$this->policyservice->is_policy_accepted($role, (int)$user->id);
        }

        // 9. Success Audit
        $this->auditservice->log((int)$user->id, 'LOGIN_SUCCESS', 'SUCCESS', [
            'role' => $role,
            'ums_fallback' => $umsfallbackused,
            'policy_required' => $policyrequired,
        ]);

        $returnurl = $options['returnurl'] ?? null;
        return auth_result::success($user, $role, $returnurl, $policyrequired, $umsfallbackused);
    }
}
