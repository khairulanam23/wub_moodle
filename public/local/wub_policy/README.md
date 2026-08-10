# WUB Policy Plugin (`local_wub_policy`)

A custom Moodle 5.1 local plugin providing role-specific Terms & Conditions / Policy agreements for the World University of Bangladesh (WUB) eLearning portal.

## Features

- **Session-Based State**: Policy acceptance is stored purely in memory in Moodle's `$SESSION` object (`$SESSION->wub_policy_accepted[$role]`). It is **never** saved to a database and disappears when the session is destroyed or expires.
- **Role-Specific Tracking**: Accepts policy independently per role (`student`, `teacher`, `admin`).
- **CSRF & Security**: Form submission protected with `require_sesskey()`, role parameter whitelist validation, and proper Moodle output escaping.
- **Visual Design**: Uses WUB's custom visual identity (Navy Blue gradient, clear background image, 60% opacity glassmorphism headers & footers).

## Requirements

- Moodle 5.1+
- PHP 8.1+
