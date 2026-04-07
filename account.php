<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name'] ?? '');
        $phone   = preg_replace('/[^0-9+\-\s()]/', '', trim($_POST['phone'] ?? ''));
        $dob     = $_POST['date_of_birth'] ?? '';
        $country = $_POST['country'] ?? '';
        if ($name && strlen($phone) >= 7) {
            $db->prepare("UPDATE users SET name = ?, phone = ?, date_of_birth = ?, country = ? WHERE id = ?")
               ->execute([$name, $phone, $dob ?: null, $country ?: null, $uid]);
            flash('Profile updated.');
        } else {
            flash('Name and a valid phone number are required.', 'error');
        }
        header("Location: account.php"); exit;
    }

    if ($action === 'change_email') {
        $new_email = trim($_POST['new_email'] ?? '');
        $result = request_email_change($db, $uid, $new_email);
        if ($result['ok']) {
            flash('Verification email sent to ' . htmlspecialchars($new_email) . '. Click the link to confirm.');
        } else {
            flash($result['error'], 'error');
        }
        header("Location: account.php"); exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        if ($new !== $confirm) {
            flash('New passwords do not match.', 'error');
        } else {
            $result = change_password($db, $uid, $current, $new);
            if ($result['ok']) {
                flash('Password changed successfully.');
            } else {
                flash($result['error'], 'error');
            }
        }
        header("Location: account.php"); exit;
    }
}

// Reload user data after potential updates
$st = $db->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$uid]);
$user = $st->fetch();

render_head('Account Settings', 'account');
?>

<div class="page-header">
  <div class="page-title">Account Settings</div>
  <div class="page-sub">Manage your profile, email, and password</div>
</div>

<div class="grid-2">
<!-- Profile -->
<div class="card">
  <div class="card-title">Profile</div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required placeholder="Your name">
    </div>
    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Date of Birth</label>
        <input type="date" name="date_of_birth" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Country</label>
        <select name="country">
          <option value="">-- Select --</option>
          <?php foreach (get_countries() as $code => $name): ?>
          <option value="<?= $code ?>" <?= ($user['country'] ?? '')===$code?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
             style="opacity:0.6;cursor:not-allowed">
      <div style="font-size:12px;color:var(--muted);margin-top:4px">Use the section below to change your email</div>
    </div>
    <div class="form-group">
      <label>Member Since</label>
      <div style="font-size:14px;color:var(--muted)"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
  </form>
</div>

<div>
<!-- Change Email -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Change Email</div>
  <?php if ($user['pending_email']): ?>
  <div class="info-box" style="margin-bottom:1rem">
    Verification email sent to <strong><?= htmlspecialchars($user['pending_email']) ?></strong>. Check your inbox and click the link to confirm.
  </div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_email">
    <div class="form-group">
      <label>Current Email</label>
      <div style="font-size:14px;color:var(--text);margin-bottom:8px"><?= htmlspecialchars($user['email']) ?></div>
    </div>
    <div class="form-group">
      <label>New Email Address</label>
      <input type="email" name="new_email" required placeholder="new@example.com">
    </div>
    <button type="submit" class="btn btn-ghost btn-sm">Send Verification Email</button>
  </form>
</div>

<!-- Change Password -->
<div class="card">
  <div class="card-title">Change Password</div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="form-group">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new_password" required minlength="8" placeholder="At least 8 characters">
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="new_password_confirm" required minlength="8">
    </div>
    <button type="submit" class="btn btn-ghost btn-sm">Change Password</button>
  </form>
</div>
</div>
</div>

<?php render_foot(); ?>
