# WUB Moodle — UI surface inventory (2026-09-14, updated for `local_bulk_enrolment`)

Presentation-layer inventory of the currently implemented UI, gathered from the live site
(`http://localhost:8080`, theme `academi` v2026042900, Moodle 5.2.2+). Used to scope the
UI-only modernization pass; kept for the later dedicated UI redesign phase.

All UI-only changes in this pass live in **one place**: the `theme_academi/customcss` setting,
whose source of truth is `theme-custom/academi-customcss.css` (apply with `theme-custom/apply-customcss.sh`).
No template, SCSS, PHP or JS file was changed.

| Page / screen | URL | PHP entry point | Layout / templates | Styling | AMD/JS | Purpose | UI-only changes applied |
|---|---|---|---|---|---|---|---|
| Login | `/login/index.php` | `public/login/index.php` | Academi `layout/login.php` → `templates/login.mustache` → `templates/core/login_layout.mustache`; core `core/loginform` | Academi `scss/login.scss` + customcss §13 | Boost loader | Site login (username/password, forgot password) | Generic Moodle "500M users" panel hidden via `theme_academi/showdefaultloginpanel=0`; form as a centred white card over the campus photo with a dark overlay; full-width primary button |
| Dashboard | `/my/` | `public/my/index.php` | Academi `layout/drawers.php` → `templates/drawers.mustache` (Boost drawers); blocks `block_myoverview`, `block_timeline`, `block_calendar_month` … | Boost + Academi `blocks.scss` + customcss §5–6 | Boost drawers, moremenu | User landing page (course overview, timeline) — currently the real empty state (no courses) | Page shell surface, block cards, headings, empty-state icon colour |
| Front page | `/?redirect=0` | `public/index.php` | Academi `layout/frontpage.php` / `templates/frontpage.mustache` | Academi `frontpage.scss` | Academi slideshow AMD | Site home (forcelogin=1 → same as dashboard for logged-in users) | Shared shell styles only |
| Course index | `/course/index.php` | `public/course/index.php` | drawers layout; core `core_course/…` | customcss §5–8 | core course search | Category browsing, course search | Shell, tabs, search input/button |
| Users list | `/admin/user.php` | `public/admin/user.php` | drawers; core report builder table (`core_reportbuilder`) | customcss §9 (tables), §7 (buttons) | reportbuilder dynamic table, filters dropdown | Browse/manage user accounts | Table header/rows, filters button, bulk-action controls, tabs |
| Edit profile | `/user/edit.php` | `public/user/edit.php` | drawers; core `mform` (`core_form/element-*`) | customcss §8 | mform JS, sticky footer | Profile editing form | Inputs/labels/help text, sticky-footer submit (`#id_submitbutton`) and cancel styling |
| Preferences | `/user/preferences.php` | `public/user/preferences.php` | drawers; `core/preferences_groups` cards | customcss §6 | – | Preference link groups | Card radius/border/shadow |
| Public profile | `/user/profile.php` | `public/user/profile.php` | drawers; core user profile | customcss | – | Profile view | Shell, headings |
| Calendar | `/calendar/view.php?view=month` | `public/calendar/view.php` | drawers; `core_calendar/month_detailed` | customcss | calendar AMD (modal event form) | Month view + "New event" modal | Buttons, modal chrome |
| Site administration | `/admin/search.php` | `public/admin/search.php` | drawers; admin settings tree | customcss §5, §12 | admin search | Admin index/search | Tabs, search field, headings |
| Admin settings pages | `/admin/settings.php?section=…` | `public/admin/settings.php` | drawers; `admin_setting_*` forms | customcss §8, §12 | – | Settings forms | Form control/label styling (no behaviour change) |
| Grade scales | `/grade/edit/scale/index.php` | `public/grade/edit/scale/index.php` | drawers; `generaltable` | customcss §9 | – | Scales list | Table styling |
| **WUB Bulk Enrolment — Bulk Enrolment tab** | `/local/bulk_enrolment/index.php?tab=enrol` | `public/local/bulk_enrolment/index.php` | admin layout; `local_bulk_enrolment/enrolment_page`, `preview_matrix`, `result_report` | BS4-style utility classes in templates; customcss §11b (scoped to `#page-local-bulk_enrolment-index`) | `local_bulk_enrolment/selector`, `enrolment_controller` | Course/roster selection, pre-enrolment preview, execution (other agent, **active development**) | Tabs, step cards in primary shades (600/700/800), badges, selects, buttons (`btn-success`→p-700, `btn-info`→p-500, `btn-light`/`badge-light`→p-100), stat tiles; **no plugin file touched**. Pre-existing JS exception on load not addressed |
| **WUB Bulk Enrolment — Bulk Unenrolment tab** | `…?tab=unenrol` | same | `unenrolment_page` | customcss §11b | – | Course-scoped bulk unenrolment | Cards/labels/help text; `btn-danger` kept red (destructive action); full-width `custom-select`, mobile wrapping of intro action |
| **WUB Bulk Enrolment — Student Synchronization tab** | `…?tab=sync` | same | `sync_page` | customcss §11b | `sync_workspace` | UMS→Moodle identity sync (other agent) | Cards, mapping table (now scrolls on mobile), code chips in primary shades. **Functional bug (not UI):** plain GET returns "A required parameter (sesskey) was missing" (`index.php:72` calls `confirm_sesskey()` unconditionally) |
| **WUB Bulk Enrolment — UMS Integration & Diagnostics tab** | `…?tab=ums` | same | `ums_diagnostics` | customcss §11b | plugin JS (live test) | Connection status, live test, API contract table | Status table label column no-wrap, long `<code>` URLs wrap, endpoint table; decorative icons in shades, status badges keep semantic colours |
| UMS/Bulk settings | `/admin/settings.php?section=local_bulk_enrolment` | `public/admin/settings.php` | admin settings | customcss §8, §12 | – | Plugin configuration | Generic form styling |
| CSV export | `/local/bulk_enrolment/export.php` | `public/local/bulk_enrolment/export.php` | – (returns `text/csv`) | – | – | Template/report download | n/a (no HTML) |
| Footer (all pages) | – | Academi `layout/includes/footer.php` → `templates/footer.mustache` | Academi `footnote` HTML setting (WUB 4-column footer) | customcss §16 | Boost footer popover | Site footer + copyright bar | Unchanged in this pass (already WUB-branded) |

Global components restyled (all pages): top bar (`#header`), logo bar (`.header-main`), primary navigation, secondary
navigation tabs, page shell (`#page.drawers .main-inner`), page headings, cards/blocks, dropdowns, modals, drawers,
buttons (primary/secondary/link/icon/submit inputs), form controls, tables, alerts, badges, pagination, focus rings,
reduced-motion handling, drawer-toggle position, back-to-top button.
