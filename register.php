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
$old = ['name' => '', 'email' => '', 'phone' => '', 'dob' => '', 'country' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $dob      = $_POST['date_of_birth'] ?? '';
    $country  = $_POST['country'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $old = ['name' => $name, 'email' => $email, 'phone' => $phone, 'dob' => $dob, 'country' => $country];

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = db();
        $result = register_user($db, $email, $phone, $password, $name);

        if ($result['ok']) {
            // Save DOB and country
            if ($dob || $country) {
                $db->prepare("UPDATE users SET date_of_birth=?, country=? WHERE id=?")
                   ->execute([$dob ?: null, $country ?: null, $result['user_id']]);
            }
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
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required autofocus
               value="<?= htmlspecialchars($old['name']) ?>"
               placeholder="Your name">
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required
               value="<?= htmlspecialchars($old['email']) ?>"
               placeholder="you@example.com">
      </div>

      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" required
               value="<?= htmlspecialchars($old['phone']) ?>"
               placeholder="+1 (555) 123-4567">
      </div>

      <div class="form-row form-row-2">
        <div class="form-group">
          <label for="dob">Date of Birth</label>
          <input type="date" id="dob" name="date_of_birth"
                 value="<?= htmlspecialchars($old['dob']) ?>">
        </div>
        <div class="form-group">
          <label for="country">Country</label>
          <select id="country" name="country">
            <option value="">-- Select --</option>
            <?php foreach (get_countries() as $code => $name): ?>
            <option value="<?= $code ?>" <?= ($old['country'] ?? '')===$code?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
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
