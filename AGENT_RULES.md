# Universal Agent Rules

This document defines mandatory operating rules for any autonomous or semi-autonomous AI coding assistant, DevOps agent, or engineer working in this repository.

Before exploring the repository or modifying any files, all agents MUST read:
1. `AGENT_RULES.md` (this file)
2. `PROJECT_STATE.md` (current repository state, architectural decisions, and tasks)

---

### 1. Project State First
* **Always read `PROJECT_STATE.md` first.**
* Do not perform broad file tree inspections, recursive greps, or repository-wide exploration when the necessary information is already documented in `PROJECT_STATE.md`.
* Only inspect specific files when directly required by the current assigned task.

### 2. Incremental Work
* Work on one logical task at a time.
* Do not perform unrelated refactoring, styling changes, dependency upgrades, or architecture redesigns unless explicitly requested.
* Keep edits minimal, precise, and scoped to the task.

### 3. Preserve Existing Work
* Never overwrite, reset, delete, or recreate working configuration, persistent data, or application sources.
* **Strictly protect**:
  * Database contents (`db/`)
  * Moodle persistent data (`moodledata/`)
  * Runtime credentials (`.env`)
  * Existing Moodle source and configuration (`moodle/config.php`)
  * Backups (`backups/`)
  * Installed plugins and themes
* Never run destructive Docker commands such as `docker compose down -v`.

### 4. No Secrets
* Never print, expose, log, or commit secret credentials.
* This includes `MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD`, `MOODLE_ADMIN_PASSWORD`, API tokens, private keys, and session secrets.
* Always read credentials from environment variables or `.env` at runtime using shell substitution rather than hardcoding.
* Never commit `.env` or send raw secrets in conversation messages or documentation.

### 5. Git Rules
* `PROJECT_STATE.md` and `AGENT_RULES.md` are critical project infrastructure and **must remain tracked by Git**.
* Never add `PROJECT_STATE.md` or `AGENT_RULES.md` to `.gitignore`.
* Do not add untracked build artifacts, caches, logs, or persistent data to Git.
* Do not commit or push to GitHub or any remote repository unless the user explicitly requests it.
* Always run `git status` before finishing to ensure working tree cleanliness.

### 6. No Core Modifications Without Need
* Moodle core source (`moodle/lib/`, `moodle/admin/`, `moodle/course/`, etc.) must not be edited to resolve configuration, deployment, or plugin issues.
* Prefer supported Moodle configuration mechanisms, administration APIs (`admin_setting_*`), CLI scripts (`admin/cli/*`), hooks, or official plugins.
* If a core modification is ever strictly required, stop and obtain user approval first.

### 7. Docker Awareness
* Understand the multi-container Docker Compose architecture:
  * Services: `db` (MariaDB 11.8), `php` (PHP 8.4-FPM), `nginx` (Nginx 1.28-Alpine), `cron` (PHP CLI cron daemon).
  * Webroot: `/var/www/html/public` (host: `moodle/public`).
  * Non-public source: `/var/www/html` (host: `moodle`).
  * Moodle data: `/var/www/moodledata` (host: `moodledata`).
* Distinguish host permissions (user `phant0m`, UID 1000) from container permissions (user `www-data`, UID 33).
* **Never use `chmod 777` as a generic fix.** Apply targeted permissions using user/group ownership and SGID (`2775` / `664`) at the narrowest appropriate scope.

### 8. Real Verification
* Never declare a task complete merely because a command exited with status code 0.
* Verify the actual functional behavior:
  * If updating permissions, verify actual file creation/read as `www-data`.
  * If altering Nginx, run `docker compose exec nginx nginx -t` and verify HTTP response headers.
  * If configuring Moodle settings, verify via Moodle CLI and web responses.
  * If modifying database configuration, query MariaDB directly.
  * If testing persistence, verify state survives container restart and tear-down/re-launch.

### 9. Diagnose Before Changing
* When encountering an error:
  1. Read the full error message and stack trace.
  2. Identify the root cause before attempting fixes.
  3. Formulate the smallest appropriate change.
  4. Test and verify the fix.
* Never randomly change versions, reinstall packages, or reset configurations hoping it resolves the issue.

### 10. Keep Agent Output Efficient
* Leverage `PROJECT_STATE.md` to avoid redundant investigations.
* At the conclusion of any meaningful task:
  * Update `PROJECT_STATE.md` to reflect new components, completed tasks, and architectural decisions.
  * Record any unresolved issues and identify the next logical step.

### 11. No Fake or Placeholder Production Data
* Never introduce dummy, hardcoded, or mock data into production/persistent databases to simulate completion.
* If a dependency or piece of information is missing, report the gap clearly to the user.

### 12. Follow Existing Project Decisions
* Respect already-settled decisions documented in `PROJECT_STATE.md`.
* Do not re-evaluate or undo architecture decisions (e.g. database choice, port forwarding setup, public webroot separation) unless the task specifically calls for it or technical constraints require reconsideration.

### 13. User-Controlled Destructive Operations
* If any operation could cause data loss, schema resets, volume deletion, or unrecoverable changes, stop and explain the situation to the user and request explicit confirmation before executing.

### 14. Universal Compatibility
* These instructions apply universally across all engineering environments and coding tools.
* Maintain clean documentation, standard shell scripts, and native Docker Compose conventions.
