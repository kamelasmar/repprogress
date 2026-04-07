<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';

// Already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old_email = $email;

    $db = db();
    $result = login_user($db, $email, $password);

    if ($result['ok']) {
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);

        // If unverified, go to verify page
        if (!$result['user']['email_verified']) {
            header('Location: verify.php');
        } else {
            header('Location: ' . $redirect);
        }
        exit;
    } else {
        $error = $result['error'];
    }
}

render_head('Sign In', '', true);
?>

<div class="auth-box">
  <div style="text-align:center;margin-bottom:2rem">
    <div style="font-size:22px;font-weight:700;letter-spacing:-0.3px">FitTracker</div>
    <div style="color:var(--muted);font-size:14px;margin-top:4px">Sign in to your account</div>
  </div>

  <?php if ($error): ?>
  <div class="flash flash-error" style="margin-bottom:1rem"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus
               value="<?= htmlspecialchars($old_email) ?>"
               placeholder="you@example.com">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required
               placeholder="Your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">
        Sign In
      </button>
    </form>
  </div>

  <div style="text-align:center;margin-top:1.25rem;font-size:14px;color:var(--muted)">
    Don't have an account? <a href="register.php">Create one</a>
  </div>
</div>

<?php render_foot(); ?>
