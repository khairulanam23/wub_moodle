# WUB Landing Page Plugin

A custom Moodle 5.1 local plugin that provides a WUB eLearning landing page as the public entry point to Moodle.

## Features

- **Role-based Entry Points**: Student, Teacher, and Administration buttons that integrate with Moodle's native authentication
- **Public Course Catalog**: Browse and search courses without logging in
- **Course Details**: View course information, instructors, and summaries publicly
- **Secure Role Verification**: Post-authentication capability checking ensures users can only access areas appropriate to their actual Moodle roles
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Configurable Settings**: Admin settings for titles, button toggles, URLs, and more
- **Moodle-native**: Uses Mustache templates, renderable classes, language strings, and Moodle APIs throughout

## Requirements

- Moodle 5.1 or later
- PHP 8.1 or later

## Installation

1. Copy the `wub_landing` directory to `/local/wub_landing/` within your Moodle installation:

```bash
# If Moodle's dirroot is /var/www/moodle/public
cp -r wub_landing /var/www/moodle/public/local/wub_landing
```

2. Set file permissions:

```bash
sudo chown -R www-data:www-data /var/www/moodle/public/local/wub_landing
```

3. Visit **Site administration → Notifications** to trigger the plugin installation.

4. Configure settings at **Site administration → Plugins → Local plugins → WUB Landing**.

## Usage

- **Landing Page**: Visit `/local/wub_landing/` to see the landing page
- **Course Catalog**: Visit `/local/wub_landing/catalog.php` to browse courses
- **Course Details**: Visit `/local/wub_landing/course.php?id=X` for course information

## Architecture

### Authentication Flow

```
Landing Page → Role Button → auth.php → Moodle Login → postlogin.php → Role Verification → Destination
```

The role selection button is an **entry point only** — it does NOT assign or change any Moodle role. After Moodle authenticates the user, `postlogin.php` verifies the user's actual roles/capabilities before redirecting.

### Security

- Role buttons never grant unauthorized access
- `is_siteadmin()` for admin verification
- `has_capability()` for teacher verification
- `enrol_get_users_courses()` for student verification
- Course visibility enforced via `core_course_category::can_view_course_info()`
- All output escaped via Mustache auto-escaping
- URLs constructed via `moodle_url`
- No custom database tables — no personal data storage

## Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| Enable WUB Landing | Enable/disable the plugin | Enabled |
| Landing Page Title | Main heading text | "Welcome to WUB" |
| Landing Page Subtitle | Subtitle text | Click below to access... |
| Student/Teacher/Admin Buttons | Show/hide individual role buttons | All enabled |
| Course Catalog | Show/hide catalog section | Enabled |
| Contact Us URL | Link for Contact Us | Empty |
| How-to Guides URL | Link for How-to Guides | Empty |
| Courses Per Page | Pagination size for catalog | 12 |

## License

GNU GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
