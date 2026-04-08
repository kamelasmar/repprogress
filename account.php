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

    if ($action === 'share_access') {
        $share_email = strtolower(trim($_POST['share_email'] ?? ''));
        if (!$share_email) {
            flash('Please enter an email address.', 'error');
        } else {
            $target = $db->prepare("SELECT id, name, email FROM users WHERE email=?");
            $target->execute([$share_email]);
            $target = $target->fetch();
            if (!$target) {
                flash('No account found with that email.', 'error');
            } elseif ($target['id'] == $uid) {
                flash('You cannot share with yourself.', 'error');
            } else {
                try {
                    $db->prepare("INSERT INTO shared_access (owner_id, granted_to) VALUES (?, ?)")
                       ->execute([$uid, $target['id']]);
                    flash('Access granted to ' . htmlspecialchars($target['name'] ?: $target['email']) . '.');
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        flash('Already shared with this user.', 'error');
                    } else { throw $e; }
                }
            }
        }
        header("Location: account.php"); exit;
    }

    if ($action === 'revoke_access') {
        $db->prepare("DELETE FROM shared_access WHERE id=? AND owner_id=?")->execute([$_POST['share_id'], $uid]);
        flash('Access revoked.');
        header("Location: account.php"); exit;
    }
}

// Reload user data after potential updates
$st = $db->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$uid]);
$user = $st->fetch();

// Shared access data
$shared_with = $db->prepare("SELECT sa.id, u.name, u.email FROM shared_access sa JOIN users u ON sa.granted_to=u.id WHERE sa.owner_id=? ORDER BY sa.created_at");
$shared_with->execute([$uid]);
$shared_with = $shared_with->fetchAll();

$shared_to_me = $db->prepare("SELECT sa.id, u.id AS user_id, u.name, u.email FROM shared_access sa JOIN users u ON sa.owner_id=u.id WHERE sa.granted_to=? ORDER BY sa.created_at");
$shared_to_me->execute([$uid]);
$shared_to_me = $shared_to_me->fetchAll();

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
             class="opacity-60 cursor-not-allowed">
      <div class="text-xs text-muted mt-1">Use the section below to change your email</div>
    </div>
    <div class="form-group">
      <label>Member Since</label>
      <div class="text-sm text-muted"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
  </form>
</div>

<div>
<!-- Change Email -->
<div class="card mb-5">
  <div class="card-title">Change Email</div>
  <?php if ($user['pending_email']): ?>
  <div class="info-box mb-4">
    Verification email sent to <strong><?= htmlspecialchars($user['pending_email']) ?></strong>. Check your inbox and click the link to confirm.
  </div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_email">
    <div class="form-group">
      <label>Current Email</label>
      <div class="text-sm mb-2"><?= htmlspecialchars($user['email']) ?></div>
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

<!-- Shared Access -->
<div class="mt-6">
  <div class="page-title text-lg mb-4">Shared Access</div>

  <div class="grid-2">
    <!-- Share your account -->
    <div class="card">
      <div class="card-title">Share Your Account</div>
      <p class="text-[13px] text-muted mb-4 leading-normal">
        Grant someone full access to your plans, sessions, and body comp data. They can view and log on your behalf.
      </p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="share_access">
        <div class="form-group">
          <label>Email of person to share with</label>
          <input type="email" name="share_email" required placeholder="trainer@example.com">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Grant Access</button>
      </form>

      <?php if ($shared_with): ?>
      <div class="mt-5 border-t border-border-app pt-4">
        <div class="text-xs font-bold uppercase tracking-wide text-muted mb-2">People with access</div>
        <?php foreach ($shared_with as $sw): ?>
        <div class="flex justify-between items-center py-2 border-b border-border-app">
          <div>
            <div class="text-[13px] font-semibold"><?= htmlspecialchars($sw['name'] ?: $sw['email']) ?></div>
            <?php if ($sw['name']): ?><div class="text-[11px] text-muted"><?= htmlspecialchars($sw['email']) ?></div><?php endif; ?>
          </div>
          <form method="post" class="inline" x-data x-on:submit="if(!confirm('Revoke access for this user?')) $event.preventDefault()">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="revoke_access">
            <input type="hidden" name="share_id" value="<?= $sw['id'] ?>">
            <button class="btn btn-danger btn-sm">Revoke</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Accounts shared with you -->
    <div class="card">
      <div class="card-title">Accounts Shared With You</div>
      <?php if ($shared_to_me): ?>
      <p class="text-[13px] text-muted mb-4 leading-normal">
        Use the profile switcher in the sidebar to view and manage these accounts.
      </p>
      <?php foreach ($shared_to_me as $sm): ?>
      <div class="flex justify-between items-center py-2.5 border-b border-border-app">
        <div>
          <div class="text-sm font-semibold"><?= htmlspecialchars($sm['name'] ?: $sm['email']) ?></div>
          <?php if ($sm['name']): ?><div class="text-xs text-muted"><?= htmlspecialchars($sm['email']) ?></div><?php endif; ?>
        </div>
        <a href="switch_profile.php?to=<?= $sm['user_id'] ?>" class="btn btn-primary btn-sm">View</a>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="empty"><p>No one has shared their account with you yet.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php render_foot(); ?>
