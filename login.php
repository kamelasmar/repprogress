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

render_head('Sign In to Repprogress', '', true);
?>

<div class="auth-box">
  <div class="text-center mb-8">
    <div class="text-2xl font-bold -tracking-0.3">Repprogress</div>
    <div class="text-accent-text text-sm font-medium mt-1.5">Track every rep. Own every side. See the progress.</div>
    <div class="text-muted text-xs mt-2">Sign in to your account</div>
  </div>

  <?php if ($error): ?>
  <div class="flash flash-error mb-4"><?= htmlspecialchars($error) ?></div>
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
