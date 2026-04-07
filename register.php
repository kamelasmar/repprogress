<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';

// Already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old = ['email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $old = ['email' => $email, 'phone' => $phone];

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = db();
        $result = register_user($db, $email, $phone, $password);

        if ($result['ok']) {
            send_verification_email($result['email'], $result['token']);
            // Log the user in so they can access verify.php
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['user_id'];
            flash('Account created! Please check your email to verify your address.', 'success');
            header('Location: verify.php');
            exit;
        } else {
            $errors[] = $result['error'];
        }
    }
}

render_head('Create Account', '', true);
?>

<div class="auth-box">
  <div style="text-align:center;margin-bottom:2rem">
    <div style="font-size:22px;font-weight:700;letter-spacing:-0.3px">Repprogress</div>
    <div style="color:var(--muted);font-size:14px;margin-top:4px">Create your account</div>
  </div>

  <?php if ($errors): ?>
  <div class="flash flash-error" style="margin-bottom:1rem">
    <?php foreach ($errors as $e): ?>
      <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus
               value="<?= htmlspecialchars($old['email']) ?>"
               placeholder="you@example.com">
      </div>

      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" required
               value="<?= htmlspecialchars($old['phone']) ?>"
               placeholder="+1 (555) 123-4567">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8"
               placeholder="At least 8 characters">
      </div>

      <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
               placeholder="Re-enter your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">
        Create Account
      </button>
    </form>
  </div>

  <div style="text-align:center;margin-top:1.25rem;font-size:14px;color:var(--muted)">
    Already have an account? <a href="login.php">Sign in</a>
  </div>
</div>

<?php render_foot(); ?>
