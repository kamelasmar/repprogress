# Repprogress

A multi-user PHP/MySQL workout tracker with body composition tracking, exercise programme management, and progressive overload monitoring.

## Features

- **Dashboard** — weight trend, body composition, volume by muscle group, weekly frequency, recent sessions
- **Workout logger** — session and set logging with left/right side tracking
- **Body composition** — weight, body fat %, and muscle mass % tracking with trend charts
- **Exercise library** — shared exercise library with user suggestions and admin approval workflow
- **Plan builder** — create and customise training plans with per-exercise targets
- **Schedule** — weekly split, cardio guide, core protocol, 4-week calendar, 12-week roadmap
- **Multi-user auth** — registration, email verification, per-user data isolation
- **Dark theme** — mobile-first with bottom tab navigation

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- A web server (Apache / Nginx)

## Setup

### 1. Create the database

In your hosting control panel (cPanel, Plesk, etc.), create a MySQL database and user.

### 2. Run the installer

Upload all files to your server, then visit:

```
https://yourdomain.com/repprogress/install.php
```

The installer creates all tables, seeds the exercise library, and sets up your admin account.

### 3. Delete install.php

After successful installation, remove `install.php` from your server.

### 4. Local development

```bash
cp includes/config.example.php includes/config.php
# Edit config.php with local credentials
mysql -u root -p your_db_name < setup.sql
```

## File structure

```
repprogress/
├── index.php              Dashboard
├── log.php                Workout logger
├── weight.php             Body composition tracker
├── exercises.php          Exercise library
├── exercise_detail.php    Per-exercise history + progress chart
├── schedule.php           Weekly schedule + roadmap
├── plan_manager.php       Training plan management
├── plan_builder.php       Plan exercise editor
├── register.php           User registration
├── login.php              User login
├── logout.php             Session logout
├── verify.php             Email verification
├── install.php            Browser-based installer
├── setup.sql              Database schema + seed data
├── CHANGELOG.md
└── includes/
    ├── config.php         ← gitignored, created by installer
    ├── config.example.php ← committed template
    ├── auth.php           Authentication functions
    ├── layout.php         Shared HTML/CSS/navigation
    └── migrate.php        Auto-migration for schema updates
```
