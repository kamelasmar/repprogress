<?php
/**
 * auth.php — Registration, login, email verification, and auth helpers.
 */

function register_user(PDO $db, string $email, string $phone, string $password, string $name = ''): array {
    $name = trim($name);
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $phone = preg_replace('/[^0-9+\-\s()]/', '', trim($phone));
    if (strlen($phone) < 7) {
        return ['ok' => false, 'error' => 'Please enter a valid phone number.'];
    }

    // Check duplicate email
    $st = $db->prepare("SELECT id FROM users WHERE email = ?");
    $st->execute([$email]);
    if ($st->fetch()) {
        return ['ok' => false, 'error' => 'An account with this email already exists.'];
    }

    $hash    = password_hash($password, PASSWORD_DEFAULT);
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    try {
        $st = $db->prepare("INSERT INTO users (email, phone, password_hash, verification_token, verification_expires, name) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$email, $phone, $hash, $token, $expires, $name ?: null]);
    } catch (PDOException $e) {
        // Unique constraint race condition
        if ($e->getCode() == 23000) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }
        throw $e;
    }

    return ['ok' => true, 'user_id' => (int)$db->lastInsertId(), 'token' => $token, 'email' => $email];
}

function login_user(PDO $db, string $email, string $password): array {
    $email = strtolower(trim($email));
    $st = $db->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    // Rehash if algorithm has been upgraded
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    return ['ok' => true, 'user' => $user];
}

function verify_email(PDO $db, string $token): array {
    $st = $db->prepare("SELECT * FROM users WHERE verification_token = ? AND verification_expires > NOW()");
    $st->execute([$token]);
    $user = $st->fetch();

    if (!$user) {
        return ['ok' => false, 'error' => 'Invalid or expired verification link.'];
    }

    // Handle email change verification
    if (!empty($user['pending_email'])) {
        $db->prepare("UPDATE users SET email = ?, pending_email = NULL, email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?")
           ->execute([$user['pending_email'], $user['id']]);
        return ['ok' => true, 'user_id' => (int)$user['id'], 'email_changed' => true];
    }

    $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?")
       ->execute([$user['id']]);

    return ['ok' => true, 'user_id' => (int)$user['id']];
}

function send_verification_email(string $email, string $token): bool {
    $link    = APP_URL . '/verify.php?token=' . urlencode($token);
    $subject = 'Verify your Repprogress account';
    $body    = "Welcome to Repprogress!\n\n"
             . "Click the link below to verify your email address:\n\n"
             . $link . "\n\n"
             . "This link expires in 24 hours.\n\n"
             . "If you didn't create this account, you can ignore this email.";

    return sendgrid_send($email, $subject, $body);
}

function sendgrid_send(string $to, string $subject, string $text_body): bool {
    if (!defined('SENDGRID_API_KEY') || !SENDGRID_API_KEY) {
        error_log('[Repprogress] SendGrid API key not configured');
        return false;
    }

    $payload = [
        'personalizations' => [['to' => [['email' => $to]]]],
        'from'    => ['email' => MAIL_FROM, 'name' => MAIL_FROM_NAME],
        'subject' => $subject,
        'content' => [['type' => 'text/plain', 'value' => $text_body]],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response  = curl_exec($ch);
    $code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("[Repprogress] SendGrid cURL error: $curl_err");
        return false;
    }

    if ($code < 200 || $code >= 300) {
        error_log("[Repprogress] SendGrid HTTP $code — to: $to — response: $response");
        return false;
    }

    return true;
}

function resend_verification(PDO $db, int $user_id): array {
    $st = $db->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$user_id]);
    $user = $st->fetch();

    if (!$user) return ['ok' => false, 'error' => 'User not found.'];
    if ($user['email_verified']) return ['ok' => false, 'error' => 'Email already verified.'];

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $db->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?")
       ->execute([$token, $expires, $user_id]);

    $sent = send_verification_email($user['email'], $token);
    return ['ok' => $sent, 'error' => $sent ? null : 'Failed to send email. Please try again.'];
}

function request_email_change(PDO $db, int $user_id, string $new_email): array {
    $new_email = strtolower(trim($new_email));
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }

    // Check if already taken
    $st = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $st->execute([$new_email, $user_id]);
    if ($st->fetch()) {
        return ['ok' => false, 'error' => 'This email is already in use.'];
    }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $db->prepare("UPDATE users SET pending_email = ?, verification_token = ?, verification_expires = ? WHERE id = ?")
       ->execute([$new_email, $token, $expires, $user_id]);

    $link    = APP_URL . '/verify.php?token=' . urlencode($token);
    $subject = 'Confirm your new email — Repprogress';
    $body    = "You requested to change your email to this address.\n\n"
             . "Click the link below to confirm:\n\n"
             . $link . "\n\n"
             . "This link expires in 24 hours.\n\n"
             . "If you didn't request this, you can ignore this email.";

    $sent = sendgrid_send($new_email, $subject, $body);
    return ['ok' => $sent, 'error' => $sent ? null : 'Failed to send verification email.'];
}

function change_password(PDO $db, int $user_id, string $current, string $new_password): array {
    if (strlen($new_password) < 8) {
        return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    $st = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $st->execute([$user_id]);
    $user = $st->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Current password is incorrect.'];
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);

    return ['ok' => true];
}
