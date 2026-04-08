<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();

if (!is_admin()) {
    flash('Admin access only.', 'error');
    header('Location: index.php');
    exit;
}

$db = db();
$uid = current_user_id();
$tab = $_GET['tab'] ?? 'users';

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_exercise') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE exercises SET status='approved', is_suggested=0 WHERE id=?")->execute([$id]);
        flash('Exercise approved.');
        header('Location: admin.php?tab=exercises'); exit;
    }

    if ($action === 'reject_exercise') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM exercises WHERE id=? AND status='pending'")->execute([$id]);
        flash('Exercise rejected and removed.');
        header('Location: admin.php?tab=exercises'); exit;
    }

    if ($action === 'toggle_admin') {
        $target = (int)($_POST['user_id'] ?? 0);
        if ($target !== $uid) {
            $user = $db->prepare("SELECT is_admin FROM users WHERE id=?")->execute([$target]);
            $user = $db->prepare("SELECT is_admin FROM users WHERE id=?");
            $user->execute([$target]);
            $u = $user->fetch();
            if ($u) {
                $new_val = $u['is_admin'] ? 0 : 1;
                $db->prepare("UPDATE users SET is_admin=? WHERE id=?")->execute([$new_val, $target]);
                flash($new_val ? 'User promoted to admin.' : 'Admin access removed.');
            }
        } else {
            flash('You cannot change your own admin status.', 'error');
        }
        header('Location: admin.php?tab=users'); exit;
    }

    if ($action === 'delete_user') {
        $target = (int)($_POST['user_id'] ?? 0);
        if ($target !== $uid) {
            $db->prepare("DELETE FROM sets_log WHERE user_id=?")->execute([$target]);
            $db->prepare("DELETE FROM sessions WHERE user_id=?")->execute([$target]);
            $db->prepare("DELETE FROM weight_log WHERE user_id=?")->execute([$target]);
            $db->prepare("DELETE FROM plan_exercises WHERE plan_id IN (SELECT id FROM plans WHERE user_id=?)")->execute([$target]);
            $db->prepare("DELETE FROM plan_days WHERE plan_id IN (SELECT id FROM plans WHERE user_id=?)")->execute([$target]);
            $db->prepare("DELETE FROM plans WHERE user_id=?")->execute([$target]);
            $db->prepare("DELETE FROM shared_access WHERE owner_id=? OR granted_to=?")->execute([$target, $target]);
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$target]);
            flash('User and all their data deleted.');
        } else {
            flash('You cannot delete yourself.', 'error');
        }
        header('Location: admin.php?tab=users'); exit;
    }
}

// ── Load data ────────────────────────────────────────────────────────────────
$users = $db->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM plans WHERE user_id=u.id) AS plan_count,
        (SELECT COUNT(*) FROM sessions WHERE user_id=u.id) AS session_count
    FROM users u ORDER BY u.created_at DESC
")->fetchAll();

$pending = $db->query("
    SELECT e.*, u.name AS suggested_by_name, u.email AS suggested_by_email
    FROM exercises e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.status = 'pending'
    ORDER BY e.created_at DESC
")->fetchAll();

render_head('Admin Panel', '', false, 'Manage users and exercise approvals.');
?>

<div class="page-header">
  <div class="page-title">Admin Panel</div>
  <div class="page-sub"><?= count($users) ?> users · <?= count($pending) ?> pending exercises</div>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-5">
  <a href="admin.php?tab=users" class="btn btn-sm <?= $tab === 'users' ? 'btn-primary' : 'btn-ghost' ?>">Users (<?= count($users) ?>)</a>
  <a href="admin.php?tab=exercises" class="btn btn-sm <?= $tab === 'exercises' ? 'btn-primary' : 'btn-ghost' ?>">Pending Exercises (<?= count($pending) ?>)</a>
</div>

<?php if ($tab === 'users'): ?>
<!-- ── USERS ──────────────────────────────────────────────────────────────── -->
<?php foreach ($users as $u): ?>
<div class="card mb-3">
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div>
      <div class="flex items-center gap-2 flex-wrap mb-1">
        <span class="font-bold text-sm text-[var(--text)]"><?= htmlspecialchars($u['name'] ?: 'No name') ?></span>
        <?php if ($u['is_admin']): ?><span class="badge badge-admin">Admin</span><?php endif; ?>
        <?php if (!$u['email_verified']): ?><span class="badge badge-pending">Unverified</span><?php endif; ?>
      </div>
      <div class="text-xs text-muted"><?= htmlspecialchars($u['email']) ?></div>
      <div class="flex gap-3 text-xs text-muted mt-1">
        <span>Joined: <?= date('M j, Y', strtotime($u['created_at'])) ?></span>
        <?php if ($u['last_login']): ?><span>Last login: <?= date('M j, Y', strtotime($u['last_login'])) ?></span><?php endif; ?>
        <span><?= $u['plan_count'] ?> plans</span>
        <span><?= $u['session_count'] ?> sessions</span>
      </div>
    </div>
    <?php if ($u['id'] !== $uid): ?>
    <div class="flex gap-1.5 items-center">
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_admin">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <button class="btn btn-ghost btn-sm"><?= $u['is_admin'] ? 'Remove Admin' : 'Make Admin' ?></button>
      </form>
      <form method="post" class="inline" x-data x-on:submit="if (!confirm('Delete this user and ALL their data?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>
    </div>
    <?php else: ?>
    <span class="text-xs text-muted">You</span>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php elseif ($tab === 'exercises'): ?>
<!-- ── PENDING EXERCISES ──────────────────────────────────────────────────── -->
<?php if (!$pending): ?>
<div class="card"><div class="empty"><p class="text-sm">No pending exercises. All clear.</p></div></div>
<?php else: ?>
<?php foreach ($pending as $p): ?>
<div class="card mb-3 border-warn">
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div>
      <div class="flex items-center gap-2 flex-wrap mb-1">
        <span class="font-bold text-sm text-[var(--text)]"><?= htmlspecialchars($p['name']) ?></span>
        <span class="text-xs text-muted"><?= htmlspecialchars($p['muscle_group']) ?></span>
        <span class="badge badge-pending">Pending</span>
        <?php if ($p['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
        <?php if ($p['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
        <?php if ($p['is_class']): ?><span class="badge badge-mob">Class</span><?php endif; ?>
      </div>
      <?php if ($p['coach_tip']): ?>
      <div class="coach-tip mb-1"><?= htmlspecialchars($p['coach_tip']) ?></div>
      <?php endif; ?>
      <?php if ($p['youtube_url']): ?>
      <a href="<?= htmlspecialchars($p['youtube_url']) ?>" target="_blank" class="btn-yt" style="font-size:11px;padding:2px 7px">▶ Video</a>
      <?php endif; ?>
      <div class="text-xs text-muted mt-1">
        Suggested by <strong><?= htmlspecialchars($p['suggested_by_name'] ?: $p['suggested_by_email'] ?: 'Unknown') ?></strong>
        <?php if ($p['created_at']): ?> on <?= date('M j, Y', strtotime($p['created_at'])) ?><?php endif; ?>
      </div>
    </div>
    <div class="flex gap-1.5">
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve_exercise">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-primary btn-sm">Approve</button>
      </form>
      <form method="post" class="inline" x-data x-on:submit="if (!confirm('Reject and delete this exercise?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_exercise">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-danger btn-sm">Reject</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php render_foot(); ?>
