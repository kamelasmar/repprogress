<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Activate a plan
    if ($action === 'activate') {
        $db->prepare("UPDATE plans SET is_active=0 WHERE user_id=?")->execute([$uid]);
        $db->prepare("UPDATE plans SET is_active=1 WHERE id=? AND user_id=?")->execute([$_POST['plan_id'], $uid]);
        flash('Plan activated. New sessions will be logged under this plan.');
        header("Location: plan_manager.php"); exit;
    }

    // Create new plan (blank)
    if ($action === 'create') {
        $plan_name = trim($_POST['name'] ?? '');
        if (!$plan_name) {
            flash('Plan name is required.', 'error');
            header("Location: plan_manager.php"); exit;
        }
        $start = $_POST['start_date'] ?: date('Y-m-d');
        $weeks = max(1, (int)($_POST['weeks_duration'] ?? 8));
        $end   = date('Y-m-d', strtotime("$start + $weeks weeks"));
        $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active, user_id) VALUES (?,?,?,?,?,?,0,?)")
           ->execute([$plan_name, $_POST['description'] ?? '', $_POST['phase_number'] ?? 1, $weeks, $start, $end, $uid]);
        $new_id = $db->lastInsertId();
        // Seed days based on selected count
        $num_days = max(1, min(7, (int)($_POST['num_days'] ?? 3)));
        $dst = $db->prepare("INSERT INTO plan_days (plan_id,day_label,day_title,day_order) VALUES (?,?,?,?)");
        for ($i = 1; $i <= $num_days; $i++) {
            $dst->execute([$new_id, "Day $i", "Training Day $i", $i]);
        }
        flash('Plan created! Use the builder to add exercises.');
        header("Location: plan_builder.php?plan_id=$new_id"); exit;
    }

    // Clone existing plan
    if ($action === 'clone') {
        $src_id = (int)$_POST['source_plan_id'];
        $start  = $_POST['start_date'] ?: date('Y-m-d');
        $weeks  = (int)$_POST['weeks_duration'];
        $end    = date('Y-m-d', strtotime("$start + $weeks weeks"));

        $src = $db->prepare("SELECT * FROM plans WHERE id=? AND user_id=?");
        $src->execute([$src_id, $uid]);
        $src = $src->fetch();
        if (!$src) { flash('Plan not found.', 'error'); header("Location: plan_manager.php"); exit; }

        $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active, user_id) VALUES (?,?,?,?,?,?,0,?)")
           ->execute([$_POST['name'], $_POST['description'] ?: $src['description'], $_POST['phase_number'], $weeks, $start, $end, $uid]);
        $new_id = $db->lastInsertId();

        // Clone plan_days
        $src_days = $db->prepare("SELECT * FROM plan_days WHERE plan_id=?");
        $src_days->execute([$src_id]);
        $dst = $db->prepare("INSERT INTO plan_days (plan_id,day_label,day_title,day_order,week_day,cardio_type,cardio_description) VALUES (?,?,?,?,?,?,?)");
        foreach ($src_days->fetchAll() as $d) $dst->execute([$new_id,$d['day_label'],$d['day_title'],$d['day_order'],$d['week_day'],$d['cardio_type'],$d['cardio_description']]);

        // Clone plan_exercises
        $src_ex = $db->prepare("SELECT * FROM plan_exercises WHERE plan_id=?");
        $src_ex->execute([$src_id]);
        $dex = $db->prepare("INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,sets_left,reps_left_bonus,is_left_priority,both_sides,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($src_ex->fetchAll() as $e) $dex->execute([$new_id,$e['day_label'],$e['exercise_id'],$e['section'],$e['section_order'],$e['sort_order'],$e['sets_target'],$e['reps_target'],$e['sets_left'],$e['reps_left_bonus'],$e['is_left_priority'],$e['both_sides'],$e['notes']]);

        flash('Plan cloned from "'.htmlspecialchars($src['name']).'". Customise it in the builder, then activate when ready.');
        header("Location: plan_builder.php?plan_id=$new_id"); exit;
    }

    // Delete plan (only if not active and has no sessions)
    if ($action === 'delete') {
        $pid = (int)$_POST['plan_id'];
        $chk = $db->prepare("SELECT is_active, (SELECT COUNT(*) FROM sessions WHERE plan_id=? AND user_id=?) AS session_count FROM plans WHERE id=? AND user_id=?");
        $chk->execute([$pid, $uid, $pid, $uid]);
        $info = $chk->fetch();
        if (!$info) { flash('Plan not found.', 'error'); }
        elseif ($info['is_active']) { flash('Cannot delete the active plan.', 'error'); }
        elseif ($info['session_count']) { flash('Cannot delete a plan with logged sessions. Deactivate it instead.', 'error'); }
        else {
            $db->prepare("DELETE FROM plans WHERE id=? AND user_id=?")->execute([$pid, $uid]);
            flash('Plan deleted.');
        }
        header("Location: plan_manager.php"); exit;
    }
}

$st = $db->prepare("SELECT p.*, (SELECT COUNT(*) FROM sessions s WHERE s.plan_id=p.id AND s.user_id=?) AS session_count FROM plans p WHERE p.user_id=? ORDER BY p.created_at DESC");
$st->execute([$uid, $uid]);
$plans = $st->fetchAll();
$all_plans = $plans;

render_head('Plans', 'plans');
?>

<div class="page-header">
  <div class="page-title">Training Plans</div>
  <div class="page-sub">Switch plans every 8 weeks — all historical data is always preserved</div>
</div>

<!-- ── Plans list ─────────────────────────────────────────────────────────── -->
<?php foreach ($plans as $p):
  $week_num = $p['start_date'] ? max(1,(int)ceil((time()-strtotime($p['start_date']))/604800)) : '—';
  $weeks_left = $p['end_date'] ? max(0,(int)ceil((strtotime($p['end_date'])-time())/604800)) : '—';
  $progress_pct = ($p['weeks_duration'] && $p['start_date'])
    ? min(100, round(((time()-strtotime($p['start_date']))/604800) / $p['weeks_duration'] * 100))
    : 0;
?>
<div class="card mb-3" <?= $p['is_active'] ? 'style="border:2px solid var(--accent)"' : '' ?>>
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div class="flex-1">
      <div class="flex items-center gap-2.5 flex-wrap mb-1">
        <span class="text-[17px] font-bold text-[var(--text)]"><?= htmlspecialchars($p['name']) ?></span>
        <?php if ($p['is_active']): ?>
        <span class="bg-accent text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full">● ACTIVE</span>
        <?php else: ?>
        <span class="bg-bg text-muted text-[11px] font-semibold px-2.5 py-0.5 rounded-full border border-border-app">Inactive</span>
        <?php endif; ?>
        <span class="text-xs text-muted">Phase <?= $p['phase_number'] ?> · <?= $p['weeks_duration'] ?> weeks</span>
      </div>
      <?php if ($p['description']): ?>
      <div class="text-[13px] text-muted mb-2 leading-relaxed"><?= htmlspecialchars($p['description']) ?></div>
      <?php endif; ?>
      <div class="flex gap-4 text-xs text-muted flex-wrap">
        <?php if ($p['start_date']): ?>
        <span>📅 <?= date('M j, Y', strtotime($p['start_date'])) ?> → <?= $p['end_date'] ? date('M j, Y',strtotime($p['end_date'])) : '?' ?></span>
        <span>📊 Week <?= $week_num ?> of <?= $p['weeks_duration'] ?><?= is_numeric($weeks_left) ? " · $weeks_left weeks left" : '' ?></span>
        <?php endif; ?>
        <span>🏋️ <?= $p['session_count'] ?> sessions logged</span>
      </div>

      <?php if ($p['is_active'] && $p['weeks_duration']): ?>
      <div class="mt-2.5">
        <div class="h-1.5 bg-[var(--border)] rounded-sm overflow-hidden">
          <div class="h-full bg-accent rounded-sm transition-all duration-300" style="width:<?= $progress_pct ?>%"></div>
        </div>
        <div class="text-[11px] text-muted mt-1"><?= $progress_pct ?>% through this plan</div>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex flex-col gap-1.5 items-end">
      <a href="plan_builder.php?plan_id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit Plan</a>
      <?php if (!$p['is_active']): ?>
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="activate">
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
        <button class="btn btn-primary btn-sm">▶ Activate</button>
      </form>
      <?php endif; ?>
      <?php if (!$p['is_active'] && !$p['session_count']): ?>
      <form method="post" class="inline" x-data x-on:submit="if (!confirm('Delete this plan?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$plans): ?>
<div class="card"><div class="empty"><div class="empty-icon">🗂️</div><p>No plans yet. Create your first one below.</p></div></div>
<?php endif; ?>

<!-- ── Create / Clone ─────────────────────────────────────────────────────── -->
<div class="grid-2 mt-6">

  <!-- New plan — choose type -->
  <div class="card" x-data="{ mode: '<?= openai_api_key_configured() ? 'choose' : 'form' ?>' }">
    <div class="card-title">Create New Plan</div>

    <?php if (openai_api_key_configured()): ?>
    <div x-show="mode === 'choose'">
      <p class="text-[13px] text-muted mb-4 leading-relaxed">Choose how to start your new plan:</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:1.25rem">
        <button type="button" class="btn btn-ghost" style="padding:14px;flex-direction:column;justify-content:center;text-align:center;height:auto;white-space:normal" x-on:click="mode = 'form'">
          <span style="font-size:20px;display:block;margin-bottom:4px">&#128221;</span>
          <span style="font-weight:700;display:block">Blank Plan</span>
          <span style="font-size:12px;color:var(--muted);display:block;margin-top:2px">Start from scratch</span>
        </button>
        <a href="ai_builder.php" class="btn btn-ghost" style="padding:14px;flex-direction:column;justify-content:center;text-align:center;height:auto;white-space:normal;text-decoration:none">
          <span style="font-size:20px;display:block;margin-bottom:4px">&#129302;</span>
          <span style="font-weight:700;display:block">AI Generated</span>
          <span style="font-size:12px;color:var(--muted);display:block;margin-top:2px">Answer questions, get a plan</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div x-show="mode === 'form'" x-transition x-cloak>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Plan Name</label>
        <input type="text" name="name" placeholder="Phase 2 — Loading" required>
      </div>
      <div class="form-row form-row-3">
        <div>
          <label>Training Days</label>
          <select name="num_days">
            <?php for ($d=1; $d<=7; $d++): ?>
            <option value="<?= $d ?>" <?= $d===3?'selected':'' ?>><?= $d ?> day<?= $d>1?'s':'' ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label>Phase #</label>
          <input type="number" name="phase_number" value="<?= count($plans)+1 ?>" min="1">
        </div>
        <div>
          <label>Duration (weeks)</label>
          <input type="number" name="weeks_duration" value="8" min="1" max="52">
        </div>
      </div>
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea name="description" rows="2" placeholder="Focus, goals, key differences from last phase..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Create &amp; Open Builder &rarr;</button>
    </form>
    </div>
  </div>

  <!-- Clone existing plan -->
  <div class="card">
    <div class="card-title">Clone from Existing Plan</div>
    <?php if ($plans): ?>
    <p class="text-[13px] text-muted mb-4 leading-relaxed">Copies all days and exercises from the source plan. Then customise in the builder — add, remove or swap exercises without touching your historical data.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clone">
      <div class="form-group">
        <label>Copy from</label>
        <select name="source_plan_id" required>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>New Plan Name</label>
        <input type="text" name="name" placeholder="Phase 2 — Loading" required>
      </div>
      <div class="form-row form-row-2">
        <div><label>Phase #</label><input type="number" name="phase_number" value="<?= count($plans)+1 ?>" min="1"></div>
        <div><label>Duration (weeks)</label><input type="number" name="weeks_duration" value="8" min="1" max="52"></div>
      </div>
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea name="description" rows="2" placeholder="What's different this phase?"></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Clone &amp; Open Builder →</button>
    </form>
    <?php else: ?>
    <div class="empty"><p>Create your first plan to enable cloning.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="info-box mt-4">
  <strong class="text-[var(--text)]">How it works:</strong>
  When you activate a new plan, all future sessions are logged under it. Old sessions remain permanently linked to the plan they were logged under — nothing is ever deleted.
  You can view history filtered by plan on the dashboard and exercise detail pages.
</div>

<?php render_foot(); ?>
