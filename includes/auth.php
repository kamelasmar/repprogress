<?php
/**
 * auth.php — Registration, login, email verification, and auth helpers.
 */

function register_user(PDO $db, string $email, string $phone, string $password): array {
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
        $st = $db->prepare("INSERT INTO users (email, phone, password_hash, verification_token, verification_expires) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$email, $phone, $hash, $token, $expires]);
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

    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n"
             . "Reply-To: " . MAIL_FROM . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "X-Mailer: PHP/" . phpversion();

    // For production, consider replacing mail() with PHPMailer for SMTP support.
    return @mail($email, $subject, $body, $headers);
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
