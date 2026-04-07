<?php
// ── Database Configuration ────────────────────────────────────────────────────
// Copy this file to config.php and fill in your credentials.
// config.php is gitignored — never commit real credentials.

define('DB_HOST',    '');   // e.g. localhost or sql123.example.com
define('DB_NAME',    '');   // exact database name from your host
define('DB_USER',    '');   // database username
define('DB_PASS',    '');   // database password
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    if (!DB_HOST || !DB_NAME || !DB_USER) {
        die('<div style="font-family:sans-serif;padding:2rem;background:#1a1a1a;color:#f08080;border-radius:8px;margin:2rem auto;max-width:500px">
            <strong>⚠️ Database not configured.</strong><br><br>
            Copy <code>includes/config.example.php</code> to <code>includes/config.php</code>
            and fill in your credentials, or run <a href="/fittrack/install.php" style="color:#4dd8a7">install.php</a>.
        </div>');
    }
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        require_once __DIR__ . '/migrate.php';
        run_migrations($pdo);
    }
    return $pdo;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

session_start();
