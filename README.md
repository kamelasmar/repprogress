# Kamel's Workout Tracker

A personal PHP/MySQL workout tracker built around a 5-day neuro-functional programme, with a focus on left-side muscle reconnection, machine-based lower body work (cervical stenosis safe), and progressive tracking.

## Features

- **Dashboard** — weight trend, volume by muscle group, weekly frequency, recent sessions
- **Workout logger** — session and set logging with left/right side tracking
- **Weight tracker** — daily log with trend chart
- **Exercise programme** — 89 pre-seeded exercises across 5 days with YouTube links and coach tips
- **Schedule** — weekly split, cardio guide, core protocol, 4-week calendar, 12-week roadmap
- **Dark theme** — mobile-first with bottom tab navigation

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- A web server (Apache / Nginx)

## Setup

### 1. Create the database

In your hosting control panel (cPanel, Plesk, etc.), create a MySQL database and user. Note the database name, username, password, and hostname — shared hosts often use a prefix like `if0_41598676_fittrack`.

### 2. Run the installer

Upload all files to your server, then visit:

```
https://yourdomain.com/fittrack/install.php
```

Fill in your credentials. The installer creates all tables and seeds the exercise library automatically. If it can't write `includes/config.php` (permissions), it shows the PHP to paste in manually.

### 3. Delete install.php

After successful installation, remove `install.php` from your server.

### 4. Local development

```bash
cp includes/config.example.php includes/config.php
# Edit config.php with local credentials
# Then import setup.sql into your local MySQL:
mysql -u root -p your_db_name < setup.sql
```

## File structure

```
fittrack/
├── index.php              Dashboard
├── log.php                Workout logger
├── weight.php             Weight tracker
├── exercises.php          Exercise programme
├── exercise_detail.php    Per-exercise history + progress chart
├── schedule.php           Weekly schedule + roadmap
├── install.php            Browser-based installer
├── setup.sql              Database schema + seed data
├── CHANGELOG.md
└── includes/
    ├── config.php         ← gitignored, created by installer
    ├── config.example.php ← committed template
    ├── layout.php         Shared HTML/CSS/navigation
    └── migrate.php        Auto-migration for schema updates
```

## Versioning

This project uses [Semantic Versioning](https://semver.org/):
- `MAJOR.MINOR.PATCH`
- See [CHANGELOG.md](CHANGELOG.md) for the full history

## Deployment workflow

```bash
# Make changes locally, test, then:
git add -A
git commit -m "feat: describe what changed"
git push origin main

# On the server (via SSH or git deployment):
git pull origin main
```

For shared hosts without SSH/git, use FTP to upload changed files and reference the git diff to know exactly what to upload.
