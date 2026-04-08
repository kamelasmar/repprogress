<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
$db = db();

$token = trim($_GET['token'] ?? '');
if (!$token) { header("Location: login.php"); exit; }

// Find the shared plan
$plan = $db->prepare("SELECT p.*, u.name AS owner_name FROM plans p JOIN users u ON p.user_id = u.id WHERE p.share_token = ?");
$plan->execute([$token]);
$plan = $plan->fetch();
if (!$plan) {
    render_head('Plan Not Found', '', true);
    echo '<div class="auth-box"><div class="card text-center"><div class="text-3xl mb-3">🔗</div><p class="text-muted text-sm mb-4">This plan link is invalid or has been removed.</p><a href="login.php" class="btn btn-primary btn-sm">Go to Login</a></div></div>';
    render_foot(true);
    exit;
}

// Load plan days and exercises
$days = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_order");
$days->execute([$plan['id']]);
$days = $days->fetchAll();

$exercises_by_day = [];
$ex_st = $db->prepare("
    SELECT pe.day_label, pe.section, pe.section_order, pe.sort_order, pe.sets_target, pe.reps_target,
           e.name, e.muscle_group, e.is_core, e.is_functional, e.cardio_type
    FROM plan_exercises pe
    JOIN exercises e ON pe.exercise_id = e.id
    WHERE pe.plan_id = ?
    ORDER BY pe.day_label, pe.section_order, pe.sort_order
");
$ex_st->execute([$plan['id']]);
foreach ($ex_st->fetchAll() as $row) {
    $exercises_by_day[$row['day_label']][] = $row;
}

// Handle import
$logged_in = is_logged_in();
$uid = $logged_in ? active_user_id() : null;
$is_own_plan = $logged_in && $plan['user_id'] == $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'import') {
        // Clone the plan into the user's account
        $start = date('Y-m-d');
        $weeks = $plan['weeks_duration'] ?: 8;
        $end = date('Y-m-d', strtotime("+{$weeks} weeks"));

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active, user_id) VALUES (?,?,1,?,?,?,0,?)")
               ->execute([$plan['name'], 'Imported from ' . ($plan['owner_name'] ?: 'shared link'), $weeks, $start, $end, $uid]);
            $new_id = (int)$db->lastInsertId();

            // Clone days
            $dst_day = $db->prepare("INSERT INTO plan_days (plan_id, day_label, day_title, day_order, week_day, cardio_type, cardio_description) VALUES (?,?,?,?,?,?,?)");
            foreach ($days as $d) {
                $dst_day->execute([$new_id, $d['day_label'], $d['day_title'], $d['day_order'], $d['week_day'], $d['cardio_type'], $d['cardio_description']]);
            }

            // Clone exercises
            $src_exs = $db->prepare("SELECT * FROM plan_exercises WHERE plan_id=?");
            $src_exs->execute([$plan['id']]);
            $dst_ex = $db->prepare("INSERT INTO plan_exercises (plan_id, day_label, exercise_id, section, section_order, sort_order, sets_target, reps_target, sets_left, reps_left_bonus, is_left_priority, both_sides, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($src_exs->fetchAll() as $e) {
                $dst_ex->execute([$new_id, $e['day_label'], $e['exercise_id'], $e['section'], $e['section_order'], $e['sort_order'], $e['sets_target'], $e['reps_target'], $e['sets_left'], $e['reps_left_bonus'], $e['is_left_priority'], $e['both_sides'], $e['notes']]);
            }

            $db->commit();
            flash('Plan imported! Customise it in the builder.');
            header("Location: plan_builder.php?plan_id=$new_id");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash('Failed to import plan. Please try again.', 'error');
            header("Location: share.php?token=" . urlencode($token));
            exit;
        }
    }
}

$total_exercises = 0;
foreach ($exercises_by_day as $exs) $total_exercises += count($exs);

render_head(htmlspecialchars($plan['name']) . ' — Shared Training Plan', '', false, htmlspecialchars($plan['name']) . ' — ' . count($days) . '-day training plan with ' . $total_exercises . ' exercises. Import it to your Repprogress account.');
?>

<div class="mb-6">
  <div class="page-title"><?= htmlspecialchars($plan['name']) ?></div>
  <div class="page-sub">
    Shared by <strong><?= htmlspecialchars($plan['owner_name'] ?: 'a user') ?></strong>
    · <?= count($days) ?> day<?= count($days) !== 1 ? 's' : '' ?>
    · <?= $total_exercises ?> exercise<?= $total_exercises !== 1 ? 's' : '' ?>
    · <?= $plan['weeks_duration'] ?> weeks
  </div>
</div>

<?php if ($logged_in && !$is_own_plan): ?>
<div class="mb-5">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="import">
    <button type="submit" class="btn btn-primary">📥 Import to My Plans</button>
  </form>
</div>
<?php elseif ($is_own_plan): ?>
<div class="mb-5 text-sm text-muted">This is your own plan. Share this link with others so they can import it.</div>
<?php else: ?>
<div class="card mb-5">
  <div class="text-center py-2">
    <p class="text-sm text-muted mb-3">Sign in to import this plan to your account.</p>
    <a href="login.php" class="btn btn-primary btn-sm">Log In</a>
    <a href="register.php" class="btn btn-ghost btn-sm">Sign Up</a>
  </div>
</div>
<?php endif; ?>

<?php foreach ($days as $d):
  $pn = (int)preg_replace('/\D/', '', $d['day_label']);
  $day_exs = $exercises_by_day[$d['day_label']] ?? [];
?>
<div class="card mb-3">
  <div class="flex items-center gap-2.5 mb-3">
    <?= day_pill($d['day_label']) ?>
    <span class="font-bold text-[var(--text)]"><?= htmlspecialchars($d['day_title']) ?></span>
    <span class="text-xs text-muted"><?= count($day_exs) ?> exercise<?= count($day_exs) !== 1 ? 's' : '' ?></span>
    <?php if ($d['week_day']): ?>
    <span class="text-xs text-muted">· <?= $d['week_day'] ?></span>
    <?php endif; ?>
  </div>

  <?php if ($day_exs):
    $current_section = '';
    foreach ($day_exs as $ex):
      if ($ex['section'] !== $current_section):
        $current_section = $ex['section'];
  ?>
  <div class="section-hdr"><?= htmlspecialchars($current_section) ?></div>
  <?php endif; ?>
  <div class="flex justify-between items-center py-1.5 border-b border-border-app">
    <div class="flex items-center gap-1.5 flex-wrap">
      <span class="text-sm font-semibold"><?= htmlspecialchars($ex['name']) ?></span>
      <span class="text-xs text-muted"><?= htmlspecialchars($ex['muscle_group']) ?></span>
      <?php if ($ex['is_core']): ?><span class="badge badge-core" style="font-size:10px;padding:1px 5px">Core</span><?php endif; ?>
      <?php if ($ex['is_functional']): ?><span class="badge badge-func" style="font-size:10px;padding:1px 5px">Functional</span><?php endif; ?>
    </div>
    <span class="text-xs text-muted"><?= $ex['sets_target'] ?> × <?= htmlspecialchars($ex['reps_target']) ?></span>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="text-sm text-muted">No exercises assigned yet.</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($logged_in && !$is_own_plan): ?>
<div class="mt-5 text-center">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="import">
    <button type="submit" class="btn btn-primary">📥 Import to My Plans</button>
  </form>
</div>
<?php endif; ?>

<?php render_foot(); ?>
