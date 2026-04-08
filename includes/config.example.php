<?php
// ── Database Configuration ────────────────────────────────────────────────────
// Copy this file to config.php and fill in your credentials.
// config.php is gitignored — never commit real credentials.

define('DB_HOST',    'localhost');
define('DB_NAME',    'repprogress');
define('DB_USER',    '');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── Email / App Configuration ─────────────────────────────────────────────────
define('MAIL_FROM',      'noreply@repprogress.com');
define('MAIL_FROM_NAME', 'Repprogress');
define('APP_URL',        'https://repprogress.com'); // no trailing slash
define('SENDGRID_API_KEY', '');

// ── OpenAI (AI Workout Builder) ──────────────────────────────────────────────
define('OPENAI_API_KEY', '');
define('OPENAI_MODEL',  'gpt-4o');

// ── Google OAuth ─────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  APP_URL . '/auth_google.php');

// ── Frontend Build ───────────────────────────────────────────────────────────
define('VITE_DEV', false);  // Set to true during local development with `npm run dev`

// ── Load all functions (committed to git — never edit below this line) ────────
require_once __DIR__ . '/functions.php';
