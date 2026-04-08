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

render_head('Sign In | Repprogress', '', true);
?>

<div class="auth-box">
  <div class="text-center mb-8">
    <div class="text-2xl font-bold -tracking-0.3">Rep<span class="text-accent-text">progress</span></div>
    <div class="text-[var(--text)] text-sm font-medium mt-2">Track your reps. See your progress.</div>
    <div class="text-muted text-xs mt-1.5 leading-relaxed">Build training plans, log every set, and track your results.<br>Trainer? Manage and track your clients' progress too.</div>
  </div>

  <?php if ($error): ?>
  <div class="flash flash-error mb-4"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <a href="auth_google.php" class="btn w-full justify-center mb-4" style="background:#fff;color:#333;border:1px solid var(--border2);font-weight:600">
      <svg width="18" height="18" viewBox="0 0 24 24" style="flex-shrink:0"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
      Continue with Google
    </a>

    <div class="flex items-center gap-3 mb-4">
      <div class="flex-1 h-px bg-border-app"></div>
      <span class="text-xs text-muted">or sign in with email</span>
      <div class="flex-1 h-px bg-border-app"></div>
    </div>

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

      <button type="submit" class="btn btn-primary w-full justify-center mt-2">
        Sign In
      </button>
    </form>
  </div>

  <div class="text-center mt-5 text-sm text-muted">
    Don't have an account? <a href="register.php">Create one</a>
  </div>
</div>

<?php render_foot(); ?>
