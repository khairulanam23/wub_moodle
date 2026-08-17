# WUB Special Login Permission Manager (`local_wub_special_permission`)

## Overview

The **WUB Special Login Permission Manager** plugin (`local_wub_special_permission`) provides a safe, auditable administrative interface for Moodle administrators to grant temporary login permissions (`wub_permission`) to students.

When a student has outstanding university fees (> 100 BDT), the automated authentication plugin (`auth_wub_auth_penalty`) blocks login. However, students who have received temporary payment extensions or financial arrangements can be granted a special permission date. While valid, this permission bypasses the automated financial due check during authentication.

---

## Architectural Principles & Separation of Concerns

To guarantee platform safety and avoid duplicate business logic, this plugin follows strict architectural boundaries:

```text
            ADMINISTRATOR
                 │
                 ▼
 ┌──────────────────────────────┐
 │ local_wub_special_permission │
 │                              │
 │ Search Student               │
 │ View Current Status          │
 │ Grant / Revoke Expiry Date   │
 └───────────────┬──────────────┘
                 │
                 ▼ (Single Source of Truth)
   Moodle User Preference: `wub_permission`
                 │
                 ▼
 ┌──────────────────────────────┐
 │    auth_wub_auth_penalty     │
 │                              │
 │ Authenticate Student         │
 │ Check wub_permission         │
 │ Check UMS Dues (> 100 BDT)   │
 │ Enforce Dashboard Access     │
 └──────────────────────────────┘
```

1. **No Duplicate Permission System**: Reuses Moodle's native user preference store (`name = 'wub_permission'`) evaluated by `auth_wub_auth_penalty`.
2. **No Due Calculation**: This admin plugin does NOT query financial APIs or calculate student dues.
3. **No Login Decisions**: Authentication and access decisions remain 100% owned by `auth_wub_auth_penalty`.

---

## Expiration Evaluation

Special login permissions are stored in `YYYY-MM-DD` string format (e.g. `2026-08-25`). Expiration is calculated up to 23:59:59 of the specified date:

- **Active**: `time() <= strtotime(trim($permission) . ' 23:59:59')` -> Dues check bypassed.
- **Expired**: `time() > strtotime(trim($permission) . ' 23:59:59')` -> Automated dues check executed.
- **None**: Preference empty -> Automated dues check executed.

---

## File Structure & Responsibilities

```text
local/wub_special_permission/
├── classes/
│   ├── event/
│   │   └── permission_updated.php  → Moodle event logger for audit history
│   ├── form/
│   │   └── permission_form.php     → QuickForm for date selection & actions
│   └── local/
│       └── permission_manager.php  → Core service manager for search & preference management
├── db/
│   └── access.php                  → Capability definition (local/wub_special_permission:manage)
├── lang/
│   └── en/
│       └── local_wub_special_permission.php → English language strings
├── index.php                       → Primary admin interface controller
├── settings.php                    → Site Administration navigation tree registration
├── version.php                     → Plugin metadata specification
└── README.md                       → Technical documentation
```

---

## Security & Auditing

- **Capability Enforcement**: Protected by `local/wub_special_permission:manage`.
- **CSRF Protection**: All form submissions enforce `require_sesskey()`.
- **Event Audit Logging**: Every grant, update, or revocation triggers `\local_wub_special_permission\event\permission_updated`.
