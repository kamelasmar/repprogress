<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = $_GET['error'] ?? '';
$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// Error from Google
if ($error) {
    flash('Google sign-in was cancelled or failed.', 'error');
    header('Location: login.php');
    exit;
}

// No code — redirect to Google
if (!$code) {
    header('Location: ' . google_auth_url());
    exit;
}

// Verify state to prevent CSRF
if (!$state || $state !== ($_SESSION['google_oauth_state'] ?? '')) {
    flash('Invalid request. Please try again.', 'error');
    header('Location: login.php');
    exit;
}
unset($_SESSION['google_oauth_state']);

// Exchange code for user info
$google_user = google_exchange_code($code);
if (!$google_user) {
    flash('Failed to verify your Google account. Please try again.', 'error');
    header('Location: login.php');
    exit;
}

// Login or register
$db = db();
$result = google_login_or_register($db, $google_user);

if (!$result['ok']) {
    flash($result['error'] ?? 'Sign-in failed.', 'error');
    header('Location: login.php');
    exit;
}

if ($result['is_new']) {
    flash('Welcome to Repprogress! Your account has been created with Google.');
} else {
    flash('Signed in with Google.');
}

$redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit;
