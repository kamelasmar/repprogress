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

render_head('Create Your Repprogress Account', '', true);
?>

<div class="auth-box">
  <div class="text-center mb-8">
    <div class="text-2xl font-bold -tracking-0.3">Repprogress</div>
    <div class="text-muted text-sm mt-1">Create your account</div>
  </div>

  <?php if ($errors): ?>
  <div class="flash flash-error mb-4">
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

      <button type="submit" class="btn btn-primary w-full justify-center mt-2">
        Create Account
      </button>
    </form>
  </div>

  <div class="text-center mt-5 text-sm text-muted">
    Already have an account? <a href="login.php">Sign in</a>
  </div>
</div>

<?php render_foot(); ?>
