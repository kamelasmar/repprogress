<?php
// ── Database Configuration ────────────────────────────────────────────────────
// Copy this file to config.php and fill in your credentials.
// config.php is gitignored — never commit real credentials.

define('DB_HOST',    '');   // e.g. localhost or sql123.example.com
define('DB_NAME',    '');   // exact database name from your host
define('DB_USER',    '');   // database username
define('DB_PASS',    '');   // database password
define('DB_CHARSET', 'utf8mb4');

// ── Email / App Configuration ─────────────────────────────────────────────────
define('MAIL_FROM',      'noreply@repprogress.com');
define('MAIL_FROM_NAME', 'Repprogress');
define('APP_URL',        'https://repprogress.com'); // no trailing slash
define('SENDGRID_API_KEY', '');  // SendGrid API key for transactional emails

// ── Load all functions (committed to git — never edit below this line) ────────
require_once __DIR__ . '/functions.php';
