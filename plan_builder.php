<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();

$plan_id = (int)($_GET['plan_id'] ?? 0);
if (!$plan_id) { header("Location: plan_manager.php"); exit; }

$plan = $db->prepare("SELECT * FROM plans WHERE id=? AND user_id=?");
$plan->execute([$plan_id, $uid]);
$plan = $plan->fetch();
if (!$plan) { header("Location: plan_manager.php"); exit; }

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_exercise') {
        $ex_id = (int)$_POST['exercise_id'];
        $day   = $_POST['day_label'];
        $sec   = $_POST['section'];
        // Prevent duplicate: check if this exercise is already in this day+section
        $dup = $db->prepare("SELECT COUNT(*) FROM plan_exercises WHERE plan_id=? AND day_label=? AND exercise_id=? AND section=?");
        $dup->execute([$plan_id, $day, $ex_id, $sec]);
        if ($dup->fetchColumn() > 0) {
            flash('This exercise is already in this section.', 'error');
            header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($day)); exit;
        }
        // Get current max sort_order for this section
        $max = $db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM plan_exercises WHERE plan_id=? AND day_label=? AND section=?");
        $max->execute([$plan_id, $day, $sec]);
        $next = $max->fetchColumn() + 1;
        $db->prepare("INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,sets_left,reps_left_bonus,is_left_priority,both_sides,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$plan_id,$day,$ex_id,$sec,
             (int)$_POST['section_order'],$next,$_POST['sets_target'],$_POST['reps_target'],
             (int)($_POST['sets_left']??0),(int)($_POST['reps_left_bonus']??0),
             isset($_POST['is_left_priority'])?1:0,isset($_POST['both_sides'])?1:0,
             $_POST['notes']??null]);
        flash('Exercise added.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($day)); exit;
    }

    if ($action === 'update_exercise') {
        $db->prepare("UPDATE plan_exercises SET sets_target=?,reps_target=?,sets_left=?,reps_left_bonus=?,is_left_priority=?,both_sides=?,section=?,notes=? WHERE id=? AND plan_id=?")
           ->execute([$_POST['sets_target'],$_POST['reps_target'],(int)$_POST['sets_left'],
             (int)$_POST['reps_left_bonus'],isset($_POST['is_left_priority'])?1:0,
             isset($_POST['both_sides'])?1:0,$_POST['section'],$_POST['notes']??null,
             $_POST['pe_id'],$plan_id]);
        flash('Exercise updated.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }

    if ($action === 'remove_exercise') {
        $db->prepare("DELETE FROM plan_exercises WHERE id=? AND plan_id=?")->execute([$_POST['pe_id'],$plan_id]);
        flash('Exercise removed from plan.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }

    if ($action === 'update_day') {
        $db->prepare("UPDATE plan_days SET day_title=?,week_day=?,cardio_type=?,cardio_description=? WHERE plan_id=? AND day_label=?")
           ->execute([$_POST['day_title'],$_POST['week_day'],$_POST['cardio_type'],$_POST['cardio_description'],$plan_id,$_POST['day_label']]);
        flash('Day updated.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }

    if ($action === 'move') {
        // Swap sort_order with adjacent exercise
        $pe_id = (int)$_POST['pe_id'];
        $dir   = $_POST['dir']; // 'up' or 'down'
        $curr  = $db->prepare("SELECT * FROM plan_exercises WHERE id=?");
        $curr->execute([$pe_id]); $curr = $curr->fetch();
        if ($curr) {
            $comp = $db->prepare("SELECT * FROM plan_exercises WHERE plan_id=? AND day_label=? AND section=? AND sort_order ".($dir==='up'?'<':'>')."? ORDER BY sort_order ".($dir==='up'?'DESC':'ASC')." LIMIT 1");
            $comp->execute([$plan_id,$curr['day_label'],$curr['section'],$curr['sort_order']]);
            $comp = $comp->fetch();
            if ($comp) {
                $db->prepare("UPDATE plan_exercises SET sort_order=? WHERE id=?")->execute([$comp['sort_order'],$pe_id]);
                $db->prepare("UPDATE plan_exercises SET sort_order=? WHERE id=?")->execute([$curr['sort_order'],$comp['id']]);
            }
        }
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }
}

// ── Load data ────────────────────────────────────────────────────────────────
$active_day = $_GET['day'] ?? 'Day 1';
$plan_days  = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_order");
$plan_days->execute([$plan_id]);
$plan_days  = $plan_days->fetchAll();

$plan_exs = $db->prepare("
  SELECT pe.*, e.name, e.muscle_group, e.youtube_url, e.coach_tip, e.is_mobility, e.is_core, e.is_functional, e.cardio_type AS ex_cardio
  FROM plan_exercises pe JOIN exercises e ON pe.exercise_id=e.id
  WHERE pe.plan_id=? AND pe.day_label=?
  ORDER BY pe.section_order, pe.sort_order
");
$plan_exs->execute([$plan_id, $active_day]);
$plan_exs = $plan_exs->fetchAll();

// Group by section
$by_section = [];
foreach ($plan_exs as $e) $by_section[$e['section']][] = $e;

// Current day config
$day_config = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? AND day_label=?");
$day_config->execute([$plan_id, $active_day]);
$day_config = $day_config->fetch();

// All exercises for the add form (approved + user's own pending)
$all_ex_st = $db->prepare("SELECT id, name, muscle_group, is_mobility, is_core, is_functional, cardio_type FROM exercises WHERE status='approved' OR created_by=? ORDER BY muscle_group, name");
$all_ex_st->execute([$uid]);
$all_ex = $all_ex_st->fetchAll();
$ex_by_mg = [];
foreach ($all_ex as $e) $ex_by_mg[$e['muscle_group']][] = $e;

$sections = ['Cardio Warm-Up','Hip Mobility','Core Block A','Activation','Main Work','Functional','Finisher','Core Block B','Cool-Down','Reset'];
// Day pill numbers derived dynamically from label

render_head('Plan Builder — '.$plan['name'], 'plans');
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:1.25rem;flex-wrap:wrap">
  <a href="plan_manager.php" style="color:var(--muted);font-size:14px">← Plans</a>
  <div style="flex:1">
    <div class="page-title"><?= htmlspecialchars($plan['name']) ?></div>
    <div class="page-sub">Phase <?= $plan['phase_number'] ?> · <?= $plan['weeks_duration'] ?> weeks
      <?= $plan['is_active'] ? ' · <span style="color:var(--accent);font-weight:600">ACTIVE</span>' : '' ?>
    </div>
  </div>
  <?php if (!$plan['is_active']): ?>
  <form method="post" action="plan_manager.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="activate">
    <input type="hidden" name="plan_id" value="<?= $plan_id ?>">
    <button class="btn btn-primary btn-sm">&#9654; Activate This Plan</button>
  </form>
  <?php endif; ?>
</div>

<!-- Day tabs -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.25rem">
  <?php foreach ($plan_days as $pd):
    $pn = (int)preg_replace('/\D/', '', $pd['day_label']);
    $isActive = $pd['day_label'] === $active_day;
  ?>
  <a href="plan_builder.php?plan_id=<?= $plan_id ?>&day=<?= urlencode($pd['day_label']) ?>"
     class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-ghost' ?>">
    <?= $pd['day_label'] ?> · <?= htmlspecialchars($pd['day_title']) ?>
  </a>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

<!-- Left: current day exercises -->
<div>
  <!-- Day header config -->
  <?php if ($day_config): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-title">Day Settings</div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_day">
      <input type="hidden" name="day_label" value="<?= $active_day ?>">
      <div class="form-row form-row-3">
        <div><label>Day Title</label><input type="text" name="day_title" value="<?= htmlspecialchars($day_config['day_title']) ?>"></div>
        <div>
          <label>Recommended Day</label>
          <select name="week_day">
            <?php foreach (['Tue','Wed','Thu','Fri','Sat','Sun','Mon'] as $d): ?>
            <option value="<?= $d ?>" <?= $day_config['week_day']===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Cardio Type</label>
          <select name="cardio_type">
            <option value="none" <?= $day_config['cardio_type']==='none'?'selected':'' ?>>None</option>
            <option value="steady_state" <?= $day_config['cardio_type']==='steady_state'?'selected':'' ?>>Steady State</option>
            <option value="hiit" <?= $day_config['cardio_type']==='hiit'?'selected':'' ?>>HIIT</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Cardio Description</label>
        <input type="text" name="cardio_description" value="<?= htmlspecialchars($day_config['cardio_description']??'') ?>" placeholder="e.g. Rowing 10 min Zone 2">
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Save Day Settings</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Exercise list -->
  <div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between">
      <span><?= htmlspecialchars($active_day) ?> — <?= count($plan_exs) ?> exercises</span>
      <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:12px;color:var(--muted)">
        <?= $day_config ? htmlspecialchars($day_config['day_title']) : '' ?>
      </span>
    </div>

    <?php if ($by_section): ?>
    <?php foreach ($by_section as $section => $exs): ?>
    <div class="section-hdr"><?= htmlspecialchars($section) ?></div>
    <?php foreach ($exs as $e): ?>
    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);align-items:start">
      <div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px">
          <span style="font-weight:600;font-size:14px"><?= htmlspecialchars($e['name']) ?></span>
          <span style="font-size:12px;color:var(--muted)"><?= $e['muscle_group'] ?></span>
          <?php if ($e['is_left_priority']): ?><span class="badge badge-left">Left+</span><?php endif; ?>
          <?php if ($e['both_sides']): ?><span class="badge" style="background:var(--bg);color:var(--muted);border:1px solid var(--border)">Both sides</span><?php endif; ?>
          <?php if ($e['ex_cardio']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
          <?php if ($e['ex_cardio']==='steady_state'): ?><span class="badge badge-ss">Steady State</span><?php endif; ?>
          <?php if ($e['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
          <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--muted)">
          <?= $e['sets_target'] ?> sets · <?= htmlspecialchars($e['reps_target']) ?>
          <?php if ($e['is_left_priority'] && ($e['sets_left'] || $e['reps_left_bonus'])): ?>
          <span style="color:var(--left)"> · Left: +<?= $e['sets_left'] ?> sets, +<?= $e['reps_left_bonus'] ?> reps</span>
          <?php endif; ?>
          <?php if ($e['notes']): ?>
          · <em><?= htmlspecialchars($e['notes']) ?></em>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
        <!-- Move up/down -->
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="pe_id" value="<?= $e['id'] ?>"><input type="hidden" name="dir" value="up"><input type="hidden" name="day_label" value="<?= $active_day ?>"><button class="btn btn-ghost btn-sm" style="padding:4px 8px">&uarr;</button></form>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="pe_id" value="<?= $e['id'] ?>"><input type="hidden" name="dir" value="down"><input type="hidden" name="day_label" value="<?= $active_day ?>"><button class="btn btn-ghost btn-sm" style="padding:4px 8px">&darr;</button></form>
        <!-- Edit inline -->
        <button onclick="toggleEdit(<?= $e['id'] ?>)" class="btn btn-ghost btn-sm">Edit</button>
        <!-- Remove -->
        <form method="post" style="display:inline" onsubmit="return confirm('Remove from plan?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove_exercise">
          <input type="hidden" name="pe_id" value="<?= $e['id'] ?>">
          <input type="hidden" name="day_label" value="<?= $active_day ?>">
          <button class="btn btn-danger btn-sm">&times;</button>
        </form>
        <?php if ($e['youtube_url']): ?>
        <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt">▶</a>
        <?php endif; ?>
      </div>
    </div>
    <!-- Inline edit form (hidden by default) -->
    <div id="edit-<?= $e['id'] ?>" style="display:none;background:var(--bg);border-radius:8px;padding:12px;margin-bottom:8px;border:1px solid var(--border)">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_exercise">
        <input type="hidden" name="pe_id" value="<?= $e['id'] ?>">
        <input type="hidden" name="day_label" value="<?= $active_day ?>">
        <div class="form-row form-row-4" style="margin-bottom:8px">
          <div><label style="font-size:11px">Sets</label><input type="number" name="sets_target" value="<?= $e['sets_target'] ?>" min="1"></div>
          <div><label style="font-size:11px">Reps / Duration</label><input type="text" name="reps_target" value="<?= htmlspecialchars($e['reps_target']) ?>"></div>
          <div><label style="font-size:11px">Extra L Sets</label><input type="number" name="sets_left" value="<?= $e['sets_left'] ?>" min="0"></div>
          <div><label style="font-size:11px">Extra L Reps</label><input type="number" name="reps_left_bonus" value="<?= $e['reps_left_bonus'] ?>" min="0"></div>
        </div>
        <div class="form-row form-row-2" style="margin-bottom:8px">
          <div>
            <label style="font-size:11px">Section</label>
            <select name="section">
              <?php foreach ($sections as $s): ?><option value="<?= $s ?>" <?= $e['section']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label style="font-size:11px">Notes</label><input type="text" name="notes" value="<?= htmlspecialchars($e['notes']??'') ?>"></div>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:4px">
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="checkbox" name="is_left_priority" <?= $e['is_left_priority']?'checked':'' ?>> Left priority
          </label>
          <label style="font-size:12px;display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="checkbox" name="both_sides" <?= $e['both_sides']?'checked':'' ?>> Both sides
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" onclick="toggleEdit(<?= $e['id'] ?>)" class="btn btn-ghost btn-sm">Cancel</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty"><div class="empty-icon">➕</div><p>No exercises yet for <?= $active_day ?>. Add from the panel on the right.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Right: add exercise panel -->
<div>
  <div class="card" style="position:sticky;top:1rem">
    <div class="card-title">Add Exercise</div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_exercise">
      <input type="hidden" name="day_label" value="<?= $active_day ?>">
      <div class="form-group">
        <label>Exercise</label>
        <select name="exercise_id" required>
          <option value="">— select from library —</option>
          <?php foreach ($ex_by_mg as $mg => $exs): ?>
          <optgroup label="<?= htmlspecialchars($mg) ?>">
            <?php foreach ($exs as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?><?= $e['is_core']?' (Core)':($e['is_functional']?' (Functional)':($e['cardio_type']!=='none'?' ('.ucfirst(str_replace('_',' ',$e['cardio_type'])).')':'')) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Section</label>
        <select name="section" id="section-sel" onchange="updateSectionOrder(this.value)">
          <?php
          $section_orders = ['Cardio Warm-Up'=>1,'Hip Mobility'=>2,'Core Block A'=>3,'Activation'=>4,'Main Work'=>5,'Functional'=>6,'Finisher'=>7,'Core Block B'=>8,'Cool-Down'=>9,'Reset'=>10];
          foreach ($sections as $s):
          ?>
          <option value="<?= $s ?>"><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="section_order" id="section-order" value="5">
      </div>
      <div class="form-row form-row-2">
        <div><label>Sets</label><input type="number" name="sets_target" value="3" min="1"></div>
        <div><label>Reps / Duration</label><input type="text" name="reps_target" value="10-12"></div>
      </div>
      <div id="left-options" style="background:var(--left-light);border-radius:8px;padding:10px;margin-bottom:1rem">
        <div style="font-size:12px;font-weight:700;color:#0C447C;margin-bottom:8px">Left Side Emphasis</div>
        <div class="form-row form-row-2" style="margin-bottom:8px">
          <div><label style="font-size:11px">Extra L Sets</label><input type="number" name="sets_left" value="1" min="0"></div>
          <div><label style="font-size:11px">Extra L Reps</label><input type="number" name="reps_left_bonus" value="2" min="0"></div>
        </div>
        <div style="display:flex;gap:12px">
          <label style="font-size:12px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="is_left_priority" checked> Left priority
          </label>
          <label style="font-size:12px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="both_sides" checked> Both sides
          </label>
        </div>
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <input type="text" name="notes" placeholder="e.g. 6 rounds, 20s/40s">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" onclick="this.disabled=true;this.form.submit()">Add to <?= $active_day ?></button>
    </form>
  </div>
</div>
</div>

<script>
function toggleEdit(id) {
  var el = document.getElementById('edit-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
const sectionOrders = <?= json_encode($section_orders) ?>;
function updateSectionOrder(val) {
  document.getElementById('section-order').value = sectionOrders[val] || 5;
}
</script>

<?php render_foot(); ?>
