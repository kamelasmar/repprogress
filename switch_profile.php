<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_auth();

$to = $_GET['to'] ?? '';

if ($to === 'self' || $to === '') {
    unset($_SESSION['viewing_as']);
} else {
    $target_id = (int)$to;
    // Verify access
    $st = db()->prepare("SELECT 1 FROM shared_access WHERE owner_id=? AND granted_to=?");
    $st->execute([$target_id, current_user_id()]);
    if ($st->fetch()) {
        $_SESSION['viewing_as'] = $target_id;
    } else {
        flash('You do not have access to that account.', 'error');
    }
}

// Redirect back to referring page or dashboard
$ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $ref");
exit;
