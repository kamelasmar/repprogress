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
        flash('Email verified! You can now sign in.', 'success');
        header('Location: login.php');
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
  <div style="text-align:center;margin-bottom:2rem">
    <div style="font-size:22px;font-weight:700;letter-spacing:-0.3px">FitTracker</div>
    <div style="color:var(--muted);font-size:14px;margin-top:4px">Verify your email</div>
  </div>

  <div class="card" style="text-align:center">
    <div style="font-size:48px;margin-bottom:1rem">&#9993;</div>
    <p style="font-size:15px;line-height:1.6;margin-bottom:1rem">
      We sent a verification link to<br>
      <strong style="color:var(--accent-text)"><?= htmlspecialchars($user['email'] ?? '') ?></strong>
    </p>
    <p style="font-size:13px;color:var(--muted);margin-bottom:1.5rem">
      Click the link in your email to activate your account.<br>
      The link expires in 24 hours.
    </p>

    <form method="post" style="margin-bottom:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="btn btn-ghost" style="width:100%;justify-content:center">
        Resend Verification Email
      </button>
    </form>

    <a href="logout.php" style="font-size:13px;color:var(--muted)">Sign out</a>
  </div>
</div>

<?php render_foot(); ?>
