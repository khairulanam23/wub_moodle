# WUB Bulk Enrolment & UMS Integration (`local_bulk_enrolment`)

Production Moodle local plugin implementing the **August 2026 UMS Student Synchronization and Advanced Bulk Enrolment System** for World University of Bangladesh (WUB). Merges authoritative WUB University Management System (UMS) REST API integration with bulk course enrolment, course-scoped unenrolment, CSV processing, student metadata synchronization, interactive pre-enrolment verification matrix, and chunked execution.

---

## 1. Architectural Principles

1. **August 2026 Unified Workflow**:
   - **UMS Transport Layer (`\local_bulk_enrolment\api_client`)**: Handles HTTP transport, cURL Digest authentication, dynamic `X-API-KEY` placement, transient retry backoff, SSL validation, and MUC caching.
   - **Identity Resolution (`\local_bulk_enrolment\service\student_identity_service`)**: Unambiguously resolves student identifiers across numeric registration numbers, Moodle usernames, institutional emails, and idnumbers. Rejects ambiguous duplicate matches.
   - **Student Synchronization (`\local_bulk_enrolment\service\sync_service`)**: Compares authoritative UMS student rosters against local Moodle user database. Generates KPI comparison matrix (`SYNCED`, `CAN_UPDATE`, `PROVISION_DEFERRED`, `AMBIGUOUS_MATCH`) and executes safe metadata updates (`department`, `institution`, `idnumber`) via core `user_update_user()`.
   - **Course Groups (`\local_bulk_enrolment\service\group_service`)**: Course-scoped group resolution and creation via native Moodle core APIs (`groups_create_group`, `groups_add_member`).
   - **CSV Enrolment (`\local_bulk_enrolment\service\csv_enrolment_service`)**: Native CSV ingestion using `csv_import_reader`, validating each row's permissions, instances, and identity before previewing and executing.
   - **Bulk Unenrolment (`\local_bulk_enrolment\service\unenrolment_service`)**: Course-scoped bulk unenrolment strictly calling `enrol_manual_plugin::unenrol_user()` with preview matrix and audit logging.
   - **Enrolment Execution (`\local_bulk_enrolment\service\enrolment_dispatcher`)**: Manages batch enrolment execution in controlled 50-student transaction chunks with rollback on critical failure.
   - **Native Data Export (`\local_bulk_enrolment\service\export_service`)**: Streams CSV reports and sample templates without third-party libraries (zero `vendor/` tree).

2. **Authoritative Native Moodle Enrolment**:
   - Never writes directly to `mdl_user_enrolments` or `mdl_role_assignments`.
   - Delegates all enrolments to native Moodle `enrol_manual_plugin::enrol_user()` inside delegated database transactions.

3. **Course-Context Authorization**:
   - Enforces capabilities at `context_course::instance($courseid)` (`enrol/manual:enrol`, `enrol/manual:unenrol`, `moodle/role:assign`).
   - A user with system-level access cannot bypass course-level restrictions.

4. **Dynamic Role Resolution & Instance Policies**:
   - Resolves the Student role dynamically via `role_resolver`. Hardcoded role IDs (e.g., `5`) are strictly prohibited.
   - Requires existing, active manual enrolment instances via `instance_resolver`. Missing or disabled instances are flagged; instances are NEVER automatically created (`add_default_instance()` is prohibited).

5. **No Synthetic Student Provisioning**:
   - Unmatched UMS students are surfaced in the sync comparison and preview matrix as `PROVISION_DEFERRED` / `NOT_MATCHED` (`id=0`).
   - Automatic account creation is formally marked as **DEFERRED** per architectural guidelines. No phantom or synthetic accounts are ever inserted into `mdl_user`.

6. **Security & Zero Secret Exposure**:
   - Credentials (`api_username`, `api_password`, `api_key`) are configured through Moodle administration UI and masked via `admin_setting_configpasswordunmask`.
   - Zero secrets are passed to JavaScript, logged, exported, or displayed in UI consoles.

7. **Database Integrity**:
   - Strictly Moodle-native schema. Zero custom database tables created (legacy `mdl_enrol_ums_user` is deprecated and prohibited).

---

## 2. Supported Workflows

```text
1. August 2026 Advanced Bulk Enrolment Workflow:
   Programs UI (Search/Filter/Select)
         ↓
   Batches Filter (Loaded dynamically from UMS)
         ↓
   Student Roster (Name, ID, RegID, Email, Program, Batch, Previous/Current Courses)
         ↓
   Select Student(s)
         ↓
   Select Moodle Course(s)
         ↓
   Verification Matrix (READY, ALREADY_ENROLLED, IDENTITY_NOT_FOUND, NO_MANUAL_INSTANCE)
         ↓
   Confirm Enrolment
         ↓
   Execute Bulk Enrolment (50-student transaction chunks)
         ↓
   Result Report & CSV Export

2. August 2026 UMS Student Synchronization Workflow:
   Connect to UMS API
         ↓
   Retrieve Program & Batch Roster
         ↓
   Resolve Student Identities in Local Moodle DB
         ↓
   Generate Comparison Matrix (Synced, Can Update, Provision Deferred, Ambiguous)
         ↓
   Select Students for Update
         ↓
   Execute Safe Metadata Update (department, institution, idnumber) via user_update_user()
         ↓
   Execution Report (Updated, Skipped, Blocked, Errors)

3. CSV Bulk Enrolment Workflow:
   Upload CSV → Parse & Validate → Resolve Users & Courses → Course Permissions Check → Preview Matrix → Confirmation → Chunked Execution → Execution Report

4. Bulk Unenrolment Workflow:
   Select Target Course → Provide Student Identifiers (or CSV) → Course Capability Verification → Preview Matrix → Confirmation → Chunked Unenrolment → Audit Log
```

---

## 3. UMS API contracts (verified live from Moodle on 2026-09-14)

Base URL `https://api.e-dhrubo.com`; HTTP Digest authentication + `X-API-KEY`. For **GET** endpoints the key goes in the
query string; for **POST** endpoints it **must** be a form-body field (query placement is answered with HTTP 403). Every
success response is wrapped as `{"status":"success","message":...}`; HTTP 404 always means "no records for these
identifiers" and is raised as `ums_not_found_exception`. Credentials live only in Moodle settings and are scrubbed from
every error message.

| API | Method | Path | Request | Real response keys | Notes |
| :-- | :-- | :-- | :-- | :-- | :-- |
| #1 | POST | `/students/multiple_username_wise_std_details` | `email=<usernames csv>` (≤50 per call) | `message.StudentDetails[]`: `id`, `stud_id` (= registration id `WUB03/26/74/5565`), `ugc_stud_id` (16-digit UGC id, *not* the login), `username` (= university e-mail; local part = 10-digit login), `full_name`, `batch_id` (= batch **title**), `program_id`, `shift`, `program_type`, `is_active` … | Also returns sensitive fields (password hash, dues, phones) that the model deliberately drops. Used only to enrich the visible roster page. |
| #2 | GET | `/students/programs` | – | `message[]`: `id`, `title`, `short_title`, `short_name`, `code`, `is_active` | 35 programs. MUC 30 min. |
| #3 | GET | `/students/batches/{program_id}` | – | `message[]`: `id`, `batch_title`, `shift`, `program_type`, `total_no_of_students`, `is_active`, `is_open`, `program_id` | 172 batches for CSE. MUC 15 min. `id` and `batch_title` are both kept; **only the title is accepted by #4**. |
| #4 | GET | `/students/enroll_student_list_program_batch_wise/{program_id}/{batch_title\|0}` | – | `message[]`: `username` (10-digit), `regId`, `full_name`, `mother_batch`, `current_batch`, `program_name`, `program_id`, `enrollCourseDetails[{title, courseCode, credit}]` | `0`/`all` = whole program (1,187 for CSE). A numeric batch id → 404. A valid batch with no registered students → 404 (= empty). **An unknown title returns the whole program**, so titles are validated against #3 first and rows are filtered by `mother_batch`. Roster MUC 10 min. |
| #5 | POST | `/students/reg_id_wise_multi_student_info` | `ids=<10-digit usernames csv>` (≤50) | `message[]`: `stud_id` (= registration id), `university_email` (= the 10-digit username, despite its name), `email`, `department_id`, `program_id`, `batch_id` (= title) | 404 = none of the ids is known. |

### Service contract

```
UMS API ──► api_client (transport, retries ×3 with backoff, timeouts, TLS, typed exceptions with error codes)
        ──► model\ums_program / ums_batch / ums_student / ums_course / ums_student_details (whitelisted, normalised)
        ──► service\roster_service (batch-title validation, MUC caching, mother_batch guard, bulk identity matching,
                                     search/filter/pagination, API #1 enrichment of the visible page only)
        ──► service\preview_matrix_service (READY | ALREADY_ENROLLED | ENROLMENT_SUSPENDED | IDENTITY_NOT_FOUND |
                                             AMBIGUOUS_MATCH | NO_MANUAL_INSTANCE | MANUAL_INSTANCE_DISABLED |
                                             INVALID_COURSE | NOT_AUTHORIZED)
        ──► service\enrolment_dispatcher (≤50 users per transaction, enrol_manual only, idempotent, optional group)
        ──► external\get_programs / get_batches / get_roster / preview_matrix / execute_chunk
             (envelope {ok, error_code, error_message, ...}; error codes: UMS_AUTH, UMS_FORBIDDEN, UMS_TIMEOUT,
              UMS_CONNECTION, UMS_HTTP_4XX, UMS_HTTP_5XX, UMS_BAD_JSON, UMS_SCHEMA, UMS_NOT_CONFIGURED,
              UMS_NOT_FOUND, INVALID_BATCH)
        ──► amd/src/workspace.js (programs → batches → paginated roster → courses → matrix → chunked execution)
        ──► Moodle: enrol_manual_plugin::enrol_user(), role resolved by archetype/shortname, groups_* APIs
```

Identity matching (`student_identity_service`), in priority order: Moodle `username` == UMS username; Moodle `idnumber`
== registration id or username; `<username>@student.wub.edu.bd`. One account → MATCHED; several (including accounts that
share the matched account's idnumber) → AMBIGUOUS_MATCH (blocked); none → NOT_FOUND / PROVISION_DEFERRED. No account is
ever created by the enrolment workflow.

---

## 4. Student Synchronization Mapping

Authoritative UMS data maps to local Moodle user fields as follows:

| UMS Field | Moodle User Field | Safety Policy |
| :--- | :--- | :--- |
| `program_name` | `department` | Safe update via `user_update_user()` if different. |
| `mother_batch` / `batch_name` | `institution` | Safe update via `user_update_user()` if different. |
| `regId` | `idnumber` | Safe update via `user_update_user()` if different and not conflicting. |
| `username` | `username` | Authoritative primary matching key. Read-only. |
| `university_email` | `email` | Fallback matching key. Never overwritten if external auth is active. |

---

## 5. CSV File Specifications

### 5.1 Bulk Enrolment CSV
Required headers: `username,course`  
Optional headers: `role,group,timestart,timeend`

Sample:
```csv
username,course,role,group
0323643804,CSE101,student,Section 64A
0323643806,CSE101,student,Section 64A
```

### 5.2 Bulk Unenrolment CSV
Required headers: `username,course`

Sample:
```csv
username,course
0323643804,CSE101
0323643806,CSE101
```

---

## 6. Audit Logging & Events

The plugin triggers standard Moodle Core events for full auditing:
- `\local_bulk_enrolment\event\bulk_enrolment_executed`: Dispatched on successful batch enrolment chunk execution, recording course context, mode, enrolled count, and conflicts.
- `\local_bulk_enrolment\event\bulk_unenrolment_executed`: Dispatched on bulk unenrolment execution, recording course context, unenrolled count, and failures.

---

## 7. Verification & Testing

The v3 rebuild was verified on 2026-09-14 with live UMS data and a controlled, cleaned-up Moodle fixture
(hidden test course + temporary account for one real UMS student): programs/batches/roster/details/ids APIs,
roster pagination (1,187 students), invalid-batch refusal, verification matrix statuses, one real manual enrolment
(`is_enrolled()`, student role, batch-named group), idempotent re-run (`ALREADY_ENROLLED`, no duplicate rows),
50-user chunk cap, permission denial, and the sync workflow (`CAN_UPDATE` → `UPDATED` → `SYNCED`, ambiguity blocked,
UMS-only students `PROVISION_DEFERRED`). See `PROJECT_STATE.md` (Completed Work #15) for the detailed record.
The earlier `scratch/` test scripts referenced by previous versions are not part of this repository.
