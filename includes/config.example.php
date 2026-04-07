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

// ── Session Security ──────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// ── Database ──────────────────────────────────────────────────────────────────
function db(): PDO {
    if (!DB_HOST || !DB_NAME || !DB_USER) {
        die('<div style="font-family:sans-serif;padding:2rem;background:#1a1a1a;color:#f08080;border-radius:8px;margin:2rem auto;max-width:500px">
            <strong>Database not configured.</strong><br><br>
            Copy <code>includes/config.example.php</code> to <code>includes/config.php</code>
            and fill in your credentials, or run <a href="install.php" style="color:#4dd8a7">install.php</a>.
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

// ── Flash Messages ────────────────────────────────────────────────────────────
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ── CSRF Protection ───────────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}

// ── Auth Helpers ──────────────────────────────────────────────────────────────
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $uid = current_user_id();
        if ($uid) {
            $st = db()->prepare("SELECT id, name, email, phone, email_verified, is_admin, pending_email, date_of_birth, country, created_at, last_login FROM users WHERE id = ?");
            $st->execute([$uid]);
            $user = $st->fetch() ?: null;
        } else {
            $user = null;
        }
    }
    return $user;
}

function require_auth(): void {
    if (!current_user_id()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    $user = current_user();
    if ($user && !$user['email_verified']) {
        header('Location: verify.php');
        exit;
    }
}

function is_logged_in(): bool {
    return current_user_id() !== null;
}

function is_admin(): bool {
    $user = current_user();
    return $user && $user['is_admin'];
}

function active_user_id(): int {
    $viewing = $_SESSION['viewing_as'] ?? null;
    if ($viewing && (int)$viewing !== current_user_id()) {
        $st = db()->prepare("SELECT 1 FROM shared_access WHERE owner_id=? AND granted_to=?");
        $st->execute([(int)$viewing, current_user_id()]);
        if ($st->fetch()) return (int)$viewing;
        unset($_SESSION['viewing_as']);
    }
    return current_user_id();
}

function viewing_other_profile(): bool {
    $viewing = $_SESSION['viewing_as'] ?? null;
    return $viewing && (int)$viewing !== current_user_id();
}

function viewed_user(): ?array {
    if (!viewing_other_profile()) return null;
    static $vu = false;
    if ($vu === false) {
        $st = db()->prepare("SELECT id, name, email FROM users WHERE id=?");
        $st->execute([active_user_id()]);
        $vu = $st->fetch() ?: null;
    }
    return $vu;
}
