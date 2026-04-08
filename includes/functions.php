<?php
/**
 * functions.php — All app functions. This file is committed to git.
 * config.php only holds credentials and requires this file.
 */

// ── Session Security ──────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

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

// ── Vite Asset Loading ───────────────────────────────────────────────────────
function vite_assets(): string {
    $dev = defined('VITE_DEV') && VITE_DEV;

    if ($dev) {
        return '<script type="module" src="http://localhost:5173/@vite/client"></script>'
             . '<script type="module" src="http://localhost:5173/src/js/app.js"></script>';
    }

    $manifest_path = __DIR__ . '/../dist/.vite/manifest.json';
    if (!file_exists($manifest_path)) return '';

    $manifest = json_decode(file_get_contents($manifest_path), true);
    $entry = $manifest['src/js/app.js'] ?? null;
    if (!$entry) return '';

    $html = '';
    foreach (($entry['css'] ?? []) as $css) {
        $html .= '<link rel="stylesheet" href="/dist/' . $css . '">';
    }
    $html .= '<script type="module" src="/dist/' . $entry['file'] . '"></script>';

    return $html;
}

// ── AI Workout Builder ───────────────────────────────────────────────────────
function openai_api_key_configured(): bool {
    return defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
}

function contains_profanity(string $text): bool {
    $words = ['fuck','shit','ass','bitch','damn','crap','dick','pussy','cock',
              'bastard','slut','whore','nigger','faggot','retard','cunt'];
    foreach ($words as $w) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $text)) return true;
    }
    return false;
}

function sanitize_ai_text(string $text): string {
    return trim(strip_tags($text));
}

function call_openai(string $system_prompt, string $user_prompt): ?array {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'       => OPENAI_MODEL,
            'messages'    => [
                ['role' => 'system',  'content' => $system_prompt],
                ['role' => 'user',    'content' => $user_prompt],
            ],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
        ]),
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        error_log('call_openai failed: HTTP ' . $http_code . ' curl_error=' . $curl_error);
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? null;
    if (!$content) return null;

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) return null;

    return $parsed;
}

function match_exercise(PDO $db, string $name, string $muscle_group, ?string $coach_tip, int $user_id): int {
    $name = sanitize_ai_text($name);
    $muscle_group = sanitize_ai_text($muscle_group);
    $coach_tip = $coach_tip ? sanitize_ai_text($coach_tip) : null;

    // 1. Exact match (case-insensitive)
    $st = $db->prepare("SELECT id FROM exercises WHERE LOWER(name) = LOWER(?) AND status='approved' LIMIT 1");
    $st->execute([$name]);
    $row = $st->fetch();
    if ($row) return (int)$row['id'];

    // 2. Fuzzy match — partial LIKE, pick shortest name
    $st = $db->prepare("SELECT id, name FROM exercises WHERE LOWER(name) LIKE LOWER(?) AND status='approved' ORDER BY CHAR_LENGTH(name) ASC LIMIT 1");
    $st->execute(['%' . $name . '%']);
    $row = $st->fetch();
    if ($row) return (int)$row['id'];

    // 3. No match — auto-create as public exercise
    $yt_url = 'https://www.youtube.com/results?search_query=' . urlencode($name . ' tutorial form');
    $st = $db->prepare("INSERT INTO exercises (name, muscle_group, youtube_url, coach_tip, created_by, status, is_suggested) VALUES (?,?,?,?,?,'approved',0)");
    $st->execute([$name, $muscle_group, $yt_url, $coach_tip, $user_id]);
    return (int)$db->lastInsertId();
}
