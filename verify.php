<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';

$db = db();

// Handle token verification from email link
$token = $_GET['token'] ?? '';
if ($token) {
    $result = verify_email($db, $token);
    if ($result['ok']) {
        if (!empty($result['email_changed'])) {
            flash('Email address updated successfully!', 'success');
            header('Location: account.php');
        } else {
            flash('Email verified! You can now sign in.', 'success');
            header('Location: login.php');
        }
    } else {
        flash($result['error'], 'error');
        header('Location: login.php');
    }
    exit;
}

// Must be logged in to see the "check your email" page
if (!current_user_id()) {
    header('Location: login.php');
    exit;
}

$user = current_user();

// Already verified — go to dashboard
if ($user && $user['email_verified']) {
    header('Location: index.php');
    exit;
}

// Handle resend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    verify_csrf();
    $result = resend_verification($db, current_user_id());
    if ($result['ok']) {
        flash('Verification email sent! Check your inbox.', 'success');
    } else {
        flash($result['error'] ?? 'Failed to send email.', 'error');
    }
    header('Location: verify.php');
    exit;
}

render_head('Verify Email', '', true);
?>

<div class="auth-box">
  <div class="text-center mb-8">
    <div class="text-2xl font-bold -tracking-0.3">Repprogress</div>
    <div class="text-muted text-sm mt-1">Verify your email</div>
  </div>

  <div class="card text-center">
    <div class="text-6xl mb-4">&#9993;</div>
    <p class="text-base leading-relaxed mb-4">
      We sent a verification link to<br>
      <strong class="text-accent-text"><?= htmlspecialchars($user['email'] ?? '') ?></strong>
    </p>
    <p class="text-xs text-muted mb-6">
      Click the link in your email to activate your account.<br>
      The link expires in 24 hours.
    </p>

    <form method="post" class="mb-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="btn btn-ghost w-full justify-center">
        Resend Verification Email
      </button>
    </form>

    <a href="logout.php" class="text-xs text-muted">Sign out</a>
  </div>
</div>

<?php render_foot(); ?>
