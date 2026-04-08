<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

$plan_id = (int)($_GET['plan_id'] ?? 0);
if (!$plan_id) { header("Location: plan_manager.php"); exit; }

$plan = $db->prepare("SELECT * FROM plans WHERE id=? AND user_id=?");
$plan->execute([$plan_id, $uid]);
$plan = $plan->fetch();
if (!$plan) { header("Location: plan_manager.php"); exit; }

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Re-verify plan ownership (defense in depth)
    if (!$plan) { header("Location: plan_manager.php"); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_exercise') {
        $ex_id = (int)($_POST['exercise_id'] ?? 0);
        $day   = trim($_POST['day_label'] ?? '');
        $sec   = trim($_POST['section'] ?? 'Main Work');
        if (!$ex_id || !$day) {
            flash('Please select an exercise.', 'error');
            header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($day ?: 'Day 1')); exit;
        }
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
        $pe_id = (int)($_POST['pe_id'] ?? 0);
        $db->prepare("UPDATE plan_exercises SET sets_target=?,reps_target=?,sets_left=?,reps_left_bonus=?,is_left_priority=?,both_sides=?,section=?,notes=? WHERE id=? AND plan_id=?")
           ->execute([$_POST['sets_target'],$_POST['reps_target'],(int)$_POST['sets_left'],
             (int)$_POST['reps_left_bonus'],isset($_POST['is_left_priority'])?1:0,
             isset($_POST['both_sides'])?1:0,$_POST['section'],$_POST['notes']??null,
             $pe_id,$plan_id]);
        flash('Exercise updated.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }

    if ($action === 'remove_exercise') {
        $pe_id = (int)($_POST['pe_id'] ?? 0);
        $db->prepare("DELETE FROM plan_exercises WHERE id=? AND plan_id=?")->execute([$pe_id,$plan_id]);
        flash('Exercise removed from plan.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".$_POST['day_label']); exit;
    }

    if ($action === 'add_alternative') {
        $pe_id = (int)($_POST['pe_id'] ?? 0);
        $alt_id = (int)($_POST['alt_exercise_id'] ?? 0);
        if ($pe_id && $alt_id) {
            // Check max 2 alternatives
            $count = $db->prepare("SELECT COUNT(*) FROM exercise_alternatives WHERE plan_exercise_id=?");
            $count->execute([$pe_id]);
            if ($count->fetchColumn() < 2) {
                $db->prepare("INSERT IGNORE INTO exercise_alternatives (plan_exercise_id, alternative_exercise_id, sort_order) VALUES (?,?,?)")
                   ->execute([$pe_id, $alt_id, $count->fetchColumn() + 1]);
                flash('Alternative added.');
            } else {
                flash('Maximum 2 alternatives per exercise.', 'error');
            }
        }
        header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($_POST['day_label'] ?? $active_day)); exit;
    }

    if ($action === 'remove_alternative') {
        $alt_id = (int)($_POST['alt_id'] ?? 0);
        $db->prepare("DELETE FROM exercise_alternatives WHERE id=? AND plan_exercise_id IN (SELECT id FROM plan_exercises WHERE plan_id=?)")
           ->execute([$alt_id, $plan_id]);
        flash('Alternative removed.');
        header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($_POST['day_label'] ?? $active_day)); exit;
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

    if ($action === 'quick_add_exercise') {
        $name   = trim($_POST['new_ex_name'] ?? '');
        $muscle = trim($_POST['new_ex_muscle'] ?? '');
        $yt_url = trim($_POST['new_ex_youtube'] ?? '');
        $is_class = isset($_POST['is_class']) ? 1 : 0;
        if ($name && $muscle) {
            $yt = $yt_url ?: 'https://www.youtube.com/results?search_query=' . urlencode($name . ' tutorial form');
            $st = $db->prepare("INSERT INTO exercises (name, muscle_group, youtube_url, created_by, status, is_suggested, is_class) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$name, $muscle, $yt, $uid, is_admin() ? 'approved' : 'pending', is_admin() ? 0 : 1, $is_class]);
            flash("\"$name\" added! It's now available in the picker.");
        } else {
            flash('Name and muscle group are required.', 'error');
        }
        header("Location: plan_builder.php?plan_id=$plan_id&day=".urlencode($_POST['day_label'] ?? $active_day)); exit;
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

// Load alternatives for each plan exercise
$alternatives = [];
$alt_st = $db->prepare("SELECT ea.*, e.name, e.muscle_group, e.youtube_url FROM exercise_alternatives ea JOIN exercises e ON ea.alternative_exercise_id = e.id WHERE ea.plan_exercise_id = ? ORDER BY ea.sort_order");
foreach ($plan_exs as $pe) {
    $alt_st->execute([$pe['id']]);
    $alts = $alt_st->fetchAll();
    if ($alts) $alternatives[$pe['id']] = $alts;
}

// Group by section
$by_section = [];
foreach ($plan_exs as $e) $by_section[$e['section']][] = $e;

// Current day config
$day_config = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? AND day_label=?");
$day_config->execute([$plan_id, $active_day]);
$day_config = $day_config->fetch();

// All exercises for the add form (approved + user's own pending)
$all_ex_st = $db->prepare("SELECT id, name, muscle_group, is_mobility, is_core, is_functional, cardio_type, youtube_url, is_class FROM exercises WHERE status='approved' OR created_by=? ORDER BY muscle_group, name");
$all_ex_st->execute([$uid]);
$all_ex = $all_ex_st->fetchAll();
$ex_by_mg = [];
foreach ($all_ex as $e) $ex_by_mg[$e['muscle_group']][] = $e;

$muscle_groups = ['Chest','Back','Shoulders','Biceps','Triceps','Core','Quads','Hamstrings','Glutes','Calves','Hips','Full Body','Cardio','Mobility'];
$sections = ['Cardio Warm-Up','Mobility','Stretching','Core Block A','Activation','Main Work','Functional','Finisher','Core Block B','Cool-Down','Reset'];
$section_orders = ['Cardio Warm-Up'=>1,'Mobility'=>2,'Stretching'=>3,'Core Block A'=>4,'Activation'=>5,'Main Work'=>6,'Functional'=>7,'Finisher'=>8,'Core Block B'=>9,'Cool-Down'=>10,'Reset'=>11];
// Day pill numbers derived dynamically from label

render_head('Plan Builder — '.htmlspecialchars($plan['name']),'plans', false, 'Build and customise your training plan. Add exercises, set targets, and organise your workout days.');
?>
<script>
window.__exData = <?= json_encode(array_values($all_ex)) ?>;
window.__secOrders = <?= json_encode($section_orders) ?>;
window.__muscleGroups = <?= json_encode($muscle_groups) ?>;
</script>

<div class="flex items-center gap-3 mb-5 flex-wrap">
  <a href="plan_manager.php" class="text-muted text-sm">← Plans</a>
  <div class="flex-1">
    <div class="page-title"><?= htmlspecialchars($plan['name']) ?></div>
    <div class="page-sub">Phase <?= $plan['phase_number'] ?> · <?= $plan['weeks_duration'] ?> weeks
      <?= $plan['is_active'] ? ' · <span class="text-accent-text font-semibold">ACTIVE</span>' : '' ?>
    </div>
  </div>
  <div class="flex gap-2">
    <button type="button" class="btn btn-ghost btn-sm" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText('<?= get_share_url($db, $plan_id) ?>'); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? '✓ Copied!' : '🔗 Share'">🔗 Share</button>
    <?php if (!$plan['is_active']): ?>
    <form method="post" action="plan_manager.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="activate">
      <input type="hidden" name="plan_id" value="<?= $plan_id ?>">
      <button class="btn btn-primary btn-sm">▶ Activate This Plan</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Day tabs -->
<div class="flex gap-2 flex-wrap mb-5">
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

<div class="max-w-[720px]">

<!-- Exercises -->
  <!-- Day header config (collapsible) -->
  <?php if ($day_config): ?>
  <div class="card mb-5" x-data="{ open: false }">
    <div class="flex justify-between items-center cursor-pointer" x-on:click="open = !open">
      <div>
        <div class="card-title mb-0">Day Settings</div>
        <div class="text-xs text-muted mt-1">
          <?= htmlspecialchars($day_config['day_title']) ?>
          <?= $day_config['week_day'] ? ' · ' . $day_config['week_day'] : '' ?>
          <?= $day_config['cardio_type'] !== 'none' ? ' · ' . ucfirst(str_replace('_', ' ', $day_config['cardio_type'])) : '' ?>
        </div>
      </div>
      <span class="btn btn-ghost btn-sm" x-text="open ? 'Close' : 'Edit'">Edit</span>
    </div>
    <div x-show="open" x-transition x-cloak class="mt-4">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_day">
        <input type="hidden" name="day_label" value="<?= $active_day ?>">
        <div class="form-row form-row-3">
          <div><label>Day Title</label><input type="text" name="day_title" value="<?= htmlspecialchars($day_config['day_title']) ?>"></div>
          <div>
            <label>Recommended Day</label>
            <select name="week_day">
              <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
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
  </div>
  <?php endif; ?>

  <!-- Exercise list -->
  <div class="card">
    <div class="card-title flex justify-between">
      <span><?= htmlspecialchars($active_day) ?> · <?= count($plan_exs) ?> exercises</span>
      <span class="font-normal normal-case tracking-normal text-xs text-muted">
        <?= $day_config ? htmlspecialchars($day_config['day_title']) : '' ?>
      </span>
    </div>

    <?php if ($by_section): ?>
    <?php foreach ($by_section as $section => $exs): ?>
    <div class="section-hdr"><?= htmlspecialchars($section) ?></div>
    <?php foreach ($exs as $e): ?>
    <div class="grid grid-cols-[1fr_auto] gap-3 py-2.5 border-b border-border-app items-start">
      <div>
        <div class="flex items-center gap-2 flex-wrap mb-0.5">
          <span class="font-semibold text-sm"><?= htmlspecialchars($e['name']) ?></span>
          <span class="text-xs text-muted"><?= $e['muscle_group'] ?></span>
          <?php if ($e['both_sides']): ?><span class="badge bg-bg text-muted border border-border-app">Both sides</span><?php endif; ?>
          <?php if ($e['ex_cardio']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
          <?php if ($e['ex_cardio']==='steady_state'): ?><span class="badge badge-ss">Steady State</span><?php endif; ?>
          <?php if ($e['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
          <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
        </div>
        <div class="text-xs text-muted">
          <?= $e['sets_target'] ?> sets · <?= htmlspecialchars($e['reps_target']) ?>
          <?php if ($e['notes']): ?>
          · <em><?= htmlspecialchars($e['notes']) ?></em>
          <?php endif; ?>
        </div>
        <?php $pe_alts = $alternatives[$e['id']] ?? []; ?>
        <?php if ($pe_alts): ?>
        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
          <span class="text-[10px] text-muted uppercase font-semibold">or:</span>
          <?php foreach ($pe_alts as $alt): ?>
          <span class="text-xs bg-bg3 px-2 py-0.5 rounded text-[var(--text)] inline-flex items-center gap-1">
            <?= htmlspecialchars($alt['name']) ?>
            <?php if ($alt['youtube_url']): ?>
            <a href="<?= htmlspecialchars($alt['youtube_url']) ?>" target="_blank" class="btn-yt" style="font-size:9px;padding:1px 5px">▶</a>
            <?php endif; ?>
            <form method="post" class="inline"><input type="hidden" name="action" value="remove_alternative"><input type="hidden" name="alt_id" value="<?= $alt['id'] ?>"><input type="hidden" name="day_label" value="<?= $active_day ?>"><?= csrf_field() ?><button class="text-muted hover:text-red-text cursor-pointer" style="background:none;border:none;padding:0;font-size:11px">×</button></form>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (count($pe_alts) < 2): ?>
        <div x-data="{ showAlt: false }" class="mt-1">
          <button type="button" class="text-[10px] text-accent-text cursor-pointer" style="background:none;border:none;padding:0" x-show="!showAlt" x-on:click="showAlt = true">+ Add alternative</button>
          <form method="post" x-show="showAlt" x-cloak class="flex gap-1.5 items-end mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_alternative">
            <input type="hidden" name="pe_id" value="<?= $e['id'] ?>">
            <input type="hidden" name="day_label" value="<?= $active_day ?>">
            <select name="alt_exercise_id" required class="text-xs" style="padding:4px 8px;min-height:30px">
              <option value="">— pick alternative —</option>
              <?php foreach ($all_ex as $ae): ?>
              <?php if ($ae['id'] != $e['exercise_id']): ?>
              <option value="<?= $ae['id'] ?>"><?= htmlspecialchars($ae['name']) ?> (<?= $ae['muscle_group'] ?>)</option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" style="padding:3px 8px;font-size:11px;min-height:30px">Add</button>
            <button type="button" class="btn btn-ghost btn-sm" style="padding:3px 8px;font-size:11px;min-height:30px" x-on:click="showAlt = false">×</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <div class="flex gap-1 items-center flex-wrap justify-end">
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="pe_id" value="<?= $e['id'] ?>"><input type="hidden" name="dir" value="up"><input type="hidden" name="day_label" value="<?= $active_day ?>"><button class="btn btn-ghost btn-sm px-2 py-1">↑</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="pe_id" value="<?= $e['id'] ?>"><input type="hidden" name="dir" value="down"><input type="hidden" name="day_label" value="<?= $active_day ?>"><button class="btn btn-ghost btn-sm px-2 py-1">↓</button></form>
        <button onclick="toggleEdit(<?= $e['id'] ?>)" class="btn btn-ghost btn-sm">Edit</button>
        <form method="post" class="inline" x-data x-on:submit="if (!confirm('Remove from plan?')) $event.preventDefault()">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove_exercise">
          <input type="hidden" name="pe_id" value="<?= $e['id'] ?>">
          <input type="hidden" name="day_label" value="<?= $active_day ?>">
          <button class="btn btn-danger btn-sm">×</button>
        </form>
        <?php if ($e['youtube_url']): ?>
        <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt">▶</a>
        <?php endif; ?>
      </div>
    </div>
    <!-- Inline edit form (hidden by default) -->
    <div id="edit-<?= $e['id'] ?>" style="display:none" class="bg-bg rounded-app p-3 mb-2 border border-border-app">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_exercise">
        <input type="hidden" name="pe_id" value="<?= $e['id'] ?>">
        <input type="hidden" name="day_label" value="<?= $active_day ?>">
        <div class="form-row form-row-4 mb-2">
          <div><label class="text-[11px]">Sets</label><input type="number" name="sets_target" value="<?= $e['sets_target'] ?>" min="1"></div>
          <div><label class="text-[11px]">Reps / Duration</label><input type="text" name="reps_target" value="<?= htmlspecialchars($e['reps_target']) ?>"></div>
          <div><label class="text-[11px]">Extra L Sets</label><input type="number" name="sets_left" value="<?= $e['sets_left'] ?>" min="0"></div>
          <div><label class="text-[11px]">Extra L Reps</label><input type="number" name="reps_left_bonus" value="<?= $e['reps_left_bonus'] ?>" min="0"></div>
        </div>
        <div class="form-row form-row-2 mb-2">
          <div>
            <label class="text-[11px]">Section</label>
            <select name="section">
              <?php foreach ($sections as $s): ?><option value="<?= $s ?>" <?= $e['section']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="text-[11px]">Notes</label><input type="text" name="notes" value="<?= htmlspecialchars($e['notes']??'') ?>"></div>
        </div>
        <div class="flex gap-2 mb-1">
          <label class="text-xs flex items-center gap-1.5 font-medium">
            <input type="checkbox" name="is_left_priority" <?= $e['is_left_priority']?'checked':'' ?> style="-webkit-appearance:checkbox;appearance:checkbox;width:auto"> Left priority
          </label>
          <label class="text-xs flex items-center gap-1.5 font-medium">
            <input type="checkbox" name="both_sides" <?= $e['both_sides']?'checked':'' ?> style="-webkit-appearance:checkbox;appearance:checkbox;width:auto"> Both sides
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" onclick="toggleEdit(<?= $e['id'] ?>)" class="btn btn-ghost btn-sm">Cancel</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty">
      <div class="empty-icon">💪</div>
      <p>This day is empty. Add exercises below to start building.</p>
      <?php if (openai_api_key_configured()): ?>
      <a href="ai_builder.php" class="btn btn-ghost btn-sm mt-2">Or generate a full plan with AI →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

<!-- Add exercise panel -->
  <div class="card mt-5" x-data="{
    exercises: window.__exData || [],
    categories: [],
    category: '',
    search: '',
    selectedId: '',
    selectedName: '',
    selectedIsClass: false,
    section: 'Main Work',
    sectionOrder: 6,
    sectionOrders: window.__secOrders || {},
    filtered: [],
    init() {
      this.categories = window.__muscleGroups || [];
      this.doFilter();
      this.$watch('category', () => this.doFilter());
      this.$watch('search', () => this.doFilter());
    },
    doFilter() {
      this.filtered = this.exercises.filter(e => {
        const matchCat = !this.category || e.muscle_group === this.category;
        const matchSearch = !this.search || e.name.toLowerCase().includes(this.search.toLowerCase());
        return matchCat && matchSearch;
      });
    },
    selectExercise(ex) { this.selectedId = String(ex.id); this.selectedName = ex.name; this.selectedIsClass = !!parseInt(ex.is_class); },
    clearSelection() { this.selectedId = ''; this.selectedName = ''; this.selectedIsClass = false; this.search = ''; this.category = ''; this.doFilter(); }
  }">
    <div class="card-title">Add Exercise</div>
    <form method="post" x-ref="addForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_exercise">
      <input type="hidden" name="day_label" value="<?= $active_day ?>">
      <input type="hidden" name="exercise_id" x-model="selectedId">
      <input type="hidden" name="section_order" x-model="sectionOrder">

      <!-- Exercise picker -->
      <div class="form-group">
        <label>Exercise</label>

        <!-- Selected exercise display -->
        <template x-if="selectedId">
          <div class="flex items-center justify-between bg-accent-dim border border-accent rounded-app px-3 py-2 mb-2">
            <span class="text-sm font-semibold text-accent-text" x-text="selectedName"></span>
            <button type="button" class="text-accent-text text-lg leading-none cursor-pointer" style="background:none;border:none" x-on:click="clearSelection()">×</button>
          </div>
        </template>

        <!-- Picker (shown when no selection) -->
        <div x-show="!selectedId">
          <!-- Category pills -->
          <div class="flex gap-1.5 flex-wrap mb-3">
            <button type="button" class="btn btn-sm" :class="category === '' ? 'btn-primary' : 'btn-ghost'" x-on:click="category = ''">All</button>
            <template x-for="cat in categories" :key="cat">
              <button type="button" class="btn btn-sm" :class="category === cat ? 'btn-primary' : 'btn-ghost'" x-on:click="category = cat" x-text="cat"></button>
            </template>
          </div>

          <!-- Search -->
          <input type="text" x-model="search" placeholder="Search exercises..." class="mb-2">

          <!-- Exercise list -->
          <div class="max-h-64 overflow-y-auto border border-border-app rounded-app">
            <template x-for="ex in filtered" :key="ex.id">
              <div class="px-3 py-2 border-b border-border-app cursor-pointer hover:bg-bg3 transition-colors" x-on:click="selectExercise(ex)">
                <div class="flex items-center justify-between gap-2">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="text-sm font-semibold" x-text="ex.name"></span>
                      <span class="text-[11px] text-muted" x-text="ex.muscle_group"></span>
                    </div>
                    <div class="flex gap-1 mt-0.5" x-show="ex.is_core || ex.is_functional || ex.cardio_type !== 'none' || parseInt(ex.is_class)">
                      <span class="badge badge-core" x-show="ex.is_core" style="font-size:10px;padding:1px 5px">Core</span>
                      <span class="badge badge-func" x-show="ex.is_functional" style="font-size:10px;padding:1px 5px">Functional</span>
                      <span class="badge badge-hiit" x-show="ex.cardio_type === 'hiit'" style="font-size:10px;padding:1px 5px">HIIT</span>
                      <span class="badge badge-ss" x-show="ex.cardio_type === 'steady_state'" style="font-size:10px;padding:1px 5px">Steady</span>
                      <span class="badge badge-mob" x-show="parseInt(ex.is_class)" style="font-size:10px;padding:1px 5px">Class</span>
                    </div>
                  </div>
                  <div class="flex gap-1.5 items-center flex-shrink-0">
                    <a x-show="ex.youtube_url" :href="ex.youtube_url" target="_blank" class="btn-yt" style="font-size:11px;padding:2px 7px" x-on:click.stop>▶</a>
                    <span class="btn btn-primary btn-sm" style="padding:2px 10px;font-size:11px">+ Add</span>
                  </div>
                </div>
              </div>
            </template>
            <div x-show="filtered.length === 0" class="px-3 py-4 text-center text-xs text-muted">No exercises found</div>
          </div>
          <!-- Quick add new exercise -->
          <div x-data="{ showNew: false }" class="mt-3">
            <button type="button" class="text-xs text-accent-text hover:underline cursor-pointer" style="background:none;border:none;padding:0" x-show="!showNew" x-on:click="showNew = true">Can't find it? Add a new exercise +</button>
            <div x-show="showNew" x-transition x-cloak class="bg-bg3 rounded-app p-3 border border-border-app">
              <div class="text-xs font-bold text-[var(--text)] mb-2">Quick Add Exercise</div>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="quick_add_exercise">
                <input type="hidden" name="day_label" value="<?= $active_day ?>">
                <div class="form-group mb-2">
                  <label class="text-[11px]">Exercise Name</label>
                  <input type="text" name="new_ex_name" placeholder="e.g. Incline Dumbbell Press" required>
                </div>
                <div class="form-group mb-2">
                  <label class="text-[11px]">Muscle Group</label>
                  <select name="new_ex_muscle" required>
                    <option value="">— select —</option>
                    <?php foreach (['Chest','Back','Shoulders','Biceps','Triceps','Core','Quads','Hamstrings','Glutes','Calves','Hips','Full Body','Cardio','Mobility'] as $mg): ?>
                    <option value="<?= $mg ?>"><?= $mg ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group mb-2">
                  <label class="text-[11px]">YouTube Link <span class="font-normal text-muted2">(optional)</span></label>
                  <input type="url" name="new_ex_youtube" placeholder="https://youtube.com/...">
                </div>
                <label class="flex items-center gap-2 text-xs font-normal text-[var(--text)] cursor-pointer mb-3" style="text-transform:none;letter-spacing:0">
                  <input type="checkbox" name="is_class" value="1" style="width:auto;-webkit-appearance:checkbox;appearance:checkbox"> This is a class (duration only, no reps)
                </label>
                <div class="flex gap-2">
                  <button type="submit" class="btn btn-primary btn-sm">Add Exercise</button>
                  <button type="button" class="btn btn-ghost btn-sm" x-on:click="showNew = false">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Rest of form (shown after selection) -->
      <div x-show="selectedId" x-transition>
        <div class="form-group">
          <label>Section</label>
          <select name="section" x-model="section" x-on:change="sectionOrder = sectionOrders[section] || 5">
            <?php foreach ($sections as $s): ?>
            <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div x-show="!selectedIsClass" class="form-row form-row-2">
          <div><label>Sets</label><input type="number" name="sets_target" value="3" min="1" :disabled="selectedIsClass"></div>
          <div><label>Reps / Duration</label><input type="text" name="reps_target" value="10-12" :disabled="selectedIsClass"></div>
        </div>
        <div x-show="selectedIsClass" x-cloak class="form-group">
          <label>Duration</label>
          <input type="text" name="reps_target" value="60 min" placeholder="e.g. 60 min, 45 min">
          <input type="hidden" name="sets_target" value="1">
        </div>

        <!-- Weak side emphasis (hidden toggle) -->
        <div x-data="{ showWeak: false }" class="mb-4">
          <button type="button" class="text-xs text-muted hover:text-[var(--text)] cursor-pointer" style="background:none;border:none;padding:0" x-show="!showWeak" x-on:click="showWeak = true">+ Add weak side emphasis</button>
          <div x-show="showWeak" x-transition class="bg-left-dim border border-[rgba(91,159,214,0.25)] rounded-app p-3 mt-2">
            <div class="text-xs font-bold text-left-text mb-2">Weak Side Emphasis</div>
            <div class="form-row form-row-2 mb-2">
              <div><label class="text-[11px]">Extra Sets</label><input type="number" name="sets_left" value="1" min="0"></div>
              <div><label class="text-[11px]">Extra Reps</label><input type="number" name="reps_left_bonus" value="2" min="0"></div>
            </div>
            <div class="flex gap-3">
              <label class="text-xs flex items-center gap-1.5">
                <input type="checkbox" name="is_left_priority" checked style="-webkit-appearance:checkbox;appearance:checkbox;width:auto"> Priority side
              </label>
              <label class="text-xs flex items-center gap-1.5">
                <input type="checkbox" name="both_sides" checked style="-webkit-appearance:checkbox;appearance:checkbox;width:auto"> Both sides
              </label>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Notes (optional)</label>
          <input type="text" name="notes" placeholder="e.g. 6 rounds, 20s/40s">
        </div>
        <button type="submit" class="btn btn-primary w-full" x-on:click="$el.disabled=true;$refs.addForm.submit()">Add to <?= $active_day ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleEdit(id) {
  var el = document.getElementById('edit-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php render_foot(); ?>
