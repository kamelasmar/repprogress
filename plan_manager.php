<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
$db = db();

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Activate a plan
    if ($action === 'activate') {
        $db->prepare("UPDATE plans SET is_active=0")->execute();
        $db->prepare("UPDATE plans SET is_active=1 WHERE id=?")->execute([$_POST['plan_id']]);
        flash('Plan activated. New sessions will be logged under this plan.');
        header("Location: plan_manager.php"); exit;
    }

    // Create new plan (blank)
    if ($action === 'create') {
        $start = $_POST['start_date'] ?: date('Y-m-d');
        $end   = date('Y-m-d', strtotime($start . ' + ' . (int)$_POST['weeks_duration'] . ' weeks'));
        $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active) VALUES (?,?,?,?,?,?,0)")
           ->execute([$_POST['name'], $_POST['description'], $_POST['phase_number'], $_POST['weeks_duration'], $start, $end]);
        $new_id = $db->lastInsertId();
        // Seed blank days
        $days = [['Day 1','Lower Body',1,'Tue'],['Day 2','Push',2,'Wed'],['Day 3','Pull',3,'Fri'],['Day 4','Arms & Functional',4,'Sat'],['Day 5','Full Body + Mobility',5,'Mon']];
        $dst = $db->prepare("INSERT INTO plan_days (plan_id,day_label,day_title,day_order,week_day) VALUES (?,?,?,?,?)");
        foreach ($days as [$dl,$dt,$do,$wd]) $dst->execute([$new_id,$dl,$dt,$do,$wd]);
        flash('Plan created! Use the builder to add exercises.');
        header("Location: plan_builder.php?plan_id=$new_id"); exit;
    }

    // Clone existing plan
    if ($action === 'clone') {
        $src_id = (int)$_POST['source_plan_id'];
        $start  = $_POST['start_date'] ?: date('Y-m-d');
        $weeks  = (int)$_POST['weeks_duration'];
        $end    = date('Y-m-d', strtotime("$start + $weeks weeks"));

        $src = $db->prepare("SELECT * FROM plans WHERE id=?");
        $src->execute([$src_id]);
        $src = $src->fetch();

        $db->prepare("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active) VALUES (?,?,?,?,?,?,0)")
           ->execute([$_POST['name'], $_POST['description'] ?: $src['description'], $_POST['phase_number'], $weeks, $start, $end]);
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
        $has_sessions = $db->prepare("SELECT COUNT(*) FROM sessions WHERE plan_id=?")->execute([$pid]) ? $db->query("SELECT COUNT(*) FROM sessions WHERE plan_id=$pid")->fetchColumn() : 0;
        $is_active    = $db->prepare("SELECT is_active FROM plans WHERE id=?")->execute([$pid]) ? $db->query("SELECT is_active FROM plans WHERE id=$pid")->fetchColumn() : 1;
        if ($is_active) { flash('Cannot delete the active plan.', 'error'); }
        elseif ($has_sessions) { flash('Cannot delete a plan with logged sessions. Deactivate it instead.', 'error'); }
        else {
            $db->prepare("DELETE FROM plans WHERE id=?")->execute([$pid]);
            flash('Plan deleted.');
        }
        header("Location: plan_manager.php"); exit;
    }
}

$plans = $db->query("SELECT p.*, (SELECT COUNT(*) FROM sessions s WHERE s.plan_id=p.id) AS session_count FROM plans p ORDER BY p.created_at DESC")->fetchAll();
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
<div class="card" style="margin-bottom:1rem;<?= $p['is_active'] ? 'border:2px solid var(--accent)' : '' ?>">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
        <span style="font-size:17px;font-weight:700;color:var(--text)"><?= htmlspecialchars($p['name']) ?></span>
        <?php if ($p['is_active']): ?>
        <span style="background:var(--accent);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px">● ACTIVE</span>
        <?php else: ?>
        <span style="background:var(--bg);color:var(--muted);font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;border:1px solid var(--border)">Inactive</span>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--muted)">Phase <?= $p['phase_number'] ?> · <?= $p['weeks_duration'] ?> weeks</span>
      </div>
      <?php if ($p['description']): ?>
      <div style="font-size:13px;color:var(--muted);margin-bottom:8px;line-height:1.5"><?= htmlspecialchars($p['description']) ?></div>
      <?php endif; ?>
      <div style="display:flex;gap:16px;font-size:12px;color:var(--muted);flex-wrap:wrap">
        <?php if ($p['start_date']): ?>
        <span>📅 <?= date('M j, Y', strtotime($p['start_date'])) ?> → <?= $p['end_date'] ? date('M j, Y',strtotime($p['end_date'])) : '?' ?></span>
        <span>📊 Week <?= $week_num ?> of <?= $p['weeks_duration'] ?><?= is_numeric($weeks_left) ? " · $weeks_left weeks left" : '' ?></span>
        <?php endif; ?>
        <span>🏋️ <?= $p['session_count'] ?> sessions logged</span>
      </div>

      <?php if ($p['is_active'] && $p['weeks_duration']): ?>
      <div style="margin-top:10px">
        <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
          <div style="height:100%;width:<?= $progress_pct ?>%;background:var(--accent);border-radius:3px;transition:width 0.3s"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $progress_pct ?>% through this plan</div>
      </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
      <a href="plan_builder.php?plan_id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit Plan</a>
      <?php if (!$p['is_active']): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="activate">
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
        <button class="btn btn-primary btn-sm">▶ Activate</button>
      </form>
      <?php endif; ?>
      <?php if (!$p['is_active'] && !$p['session_count']): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete this plan?')">
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
<div class="grid-2" style="margin-top:1.5rem">

  <!-- New blank plan -->
  <div class="card">
    <div class="card-title">Create New Plan</div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Plan Name</label>
        <input type="text" name="name" placeholder="Phase 2 — Loading" required>
      </div>
      <div class="form-row form-row-2">
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
      <button type="submit" class="btn btn-primary btn-sm">Create &amp; Open Builder →</button>
    </form>
  </div>

  <!-- Clone existing plan -->
  <div class="card">
    <div class="card-title">Clone from Existing Plan</div>
    <?php if ($plans): ?>
    <p style="font-size:13px;color:var(--muted);margin-bottom:1rem;line-height:1.5">Copies all days and exercises from the source plan. Then customise in the builder — add, remove or swap exercises without touching your historical data.</p>
    <form method="post">
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

<div class="info-box" style="margin-top:1rem">
  <strong style="color:var(--text)">How it works:</strong>
  When you activate a new plan, all future sessions are logged under it. Old sessions remain permanently linked to the plan they were logged under — nothing is ever deleted.
  You can view history filtered by plan on the dashboard and exercise detail pages.
</div>

<?php render_foot(); ?>
