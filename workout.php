<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();
$ap  = active_plan();
$colors = day_colors();
$today = date('Y-m-d');

if (!$ap) {
    render_head('Today's Workout — Log Sets & Track Progress','workout', false, 'Log your workout sets, track weights and reps, and monitor your progress in real time.');
    echo '<div class="card"><div class="empty"><p>No active plan. Create and activate a plan first.</p><a href="plan_manager.php" class="btn btn-primary btn-sm">Go to Plans</a></div></div>';
    render_foot();
    exit;
}

// Load plan days
$pdq = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_order");
$pdq->execute([$ap['id']]);
$plan_days = $pdq->fetchAll();

// Auto-detect today's day from weekday, or use ?day= param
$today_dow = date('D'); // Mon, Tue, Wed...
$active_day = $_GET['day'] ?? '';
if (!$active_day) {
    foreach ($plan_days as $pd) {
        if ($pd['week_day'] === $today_dow) {
            $active_day = $pd['day_label'];
            break;
        }
    }
}
// Fallback to first day if no match (rest day)
$is_rest_day = empty($active_day);
if (!$active_day && $plan_days) {
    $active_day = $plan_days[0]['day_label'];
}

// Get current day config
$day_config = null;
foreach ($plan_days as $pd) {
    if ($pd['day_label'] === $active_day) { $day_config = $pd; break; }
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'log_set') {
        // Double-submit prevention
        $submit_token = $_POST['submit_token'] ?? '';
        if ($submit_token && ($submit_token === ($_SESSION['last_submit_token'] ?? ''))) {
            header("Location: workout.php?day=".urlencode($active_day)."#ex-".$_POST['exercise_id']); exit;
        }
        $_SESSION['last_submit_token'] = $submit_token;

        // Validate required fields
        $ex_id = (int)($_POST['exercise_id'] ?? 0);
        $set_num = (int)($_POST['set_number'] ?? 0);
        if (!$ex_id || !$set_num) {
            flash('Exercise and set number are required.', 'error');
            header("Location: workout.php?day=".urlencode($active_day)); exit;
        }

        // Get or create session
        $session_id = (int)($_POST['session_id'] ?? 0);
        if (!$session_id) {
            $dt = $day_config ? $day_config['day_title'] : $active_day;
            $db->prepare("INSERT INTO sessions (session_date, day_label, title, plan_id, user_id) VALUES (?,?,?,?,?)")
               ->execute([$today, $active_day, $dt, $ap['id'], $uid]);
            $session_id = (int)$db->lastInsertId();
        } else {
            // Verify ownership
            $own = $db->prepare("SELECT id FROM sessions WHERE id=? AND user_id=?");
            $own->execute([$session_id, $uid]);
            if (!$own->fetch()) {
                header("Location: workout.php?day=".urlencode($active_day)); exit;
            }
        }

        $db->prepare("INSERT INTO sets_log (session_id, exercise_id, set_number, reps, weight_kg, duration_sec, side, notes, difficulty, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$session_id, $ex_id, $set_num,
                      $_POST['reps'] ?: null, $_POST['weight_kg'] ?: null,
                      $_POST['duration_sec'] ?: null, $_POST['side'] ?: 'both',
                      $_POST['notes'] ?: null, $_POST['difficulty'] ?: null, $uid]);
        flash('Set logged!');
        header("Location: workout.php?day=".urlencode($active_day)."#ex-".$_POST['exercise_id']); exit;
    }

    if ($action === 'update_set') {
        $set_id = (int)($_POST['set_id'] ?? 0);
        $db->prepare("UPDATE sets_log SET reps=?, weight_kg=?, side=?, difficulty=?, duration_sec=? WHERE id=? AND user_id=?")
           ->execute([$_POST['reps'] ?: null, $_POST['weight_kg'] ?: null, $_POST['side'] ?: 'both', $_POST['difficulty'] ?: null, $_POST['duration_sec'] ?: null, $set_id, $uid]);
        flash('Set updated.');
        header("Location: workout.php?day=".urlencode($active_day)."#ex-".$_POST['exercise_id']); exit;
    }
    if ($action === 'delete_set') {
        $set_id = (int)($_POST['set_id'] ?? 0);
        $db->prepare("DELETE FROM sets_log WHERE id=? AND user_id=?")->execute([$set_id, $uid]);
        flash('Set removed.');
        header("Location: workout.php?day=".urlencode($active_day)); exit;
    }
}

// Get today's session (if exists)
$session = $db->prepare("SELECT * FROM sessions WHERE session_date=? AND day_label=? AND user_id=? LIMIT 1");
$session->execute([$today, $active_day, $uid]);
$session = $session->fetch();
$session_id = $session ? (int)$session['id'] : 0;

// Get exercises for this day
$exs = $db->prepare("
    SELECT pe.*, e.name, e.muscle_group, e.youtube_url, e.coach_tip,
           e.is_mobility, e.is_core, e.is_functional, e.cardio_type AS ex_cardio,
           pe.is_left_priority, pe.both_sides, pe.sets_target, pe.reps_target,
           pe.sets_left, pe.reps_left_bonus, pe.section
    FROM plan_exercises pe JOIN exercises e ON pe.exercise_id=e.id
    WHERE pe.plan_id=? AND pe.day_label=?
    ORDER BY pe.section_order, pe.sort_order
");
$exs->execute([$ap['id'], $active_day]);
$exercises = $exs->fetchAll();

// Get logged sets for this session, grouped by exercise
$logged_sets = [];
$total_logged = 0;
if ($session_id) {
    $sl = $db->prepare("SELECT * FROM sets_log WHERE session_id=? ORDER BY exercise_id, set_number");
    $sl->execute([$session_id]);
    foreach ($sl->fetchAll() as $s) {
        $logged_sets[$s['exercise_id']][] = $s;
        $total_logged++;
    }
}

// Get last session data per exercise (for weight suggestions)
$last_session_data = [];
$ls_st = $db->prepare("
    SELECT sl.exercise_id, sl.weight_kg, sl.reps, sl.side, sl.difficulty
    FROM sets_log sl
    JOIN sessions s ON sl.session_id = s.id
    WHERE sl.exercise_id = ? AND s.user_id = ? AND s.session_date < CURDATE()
    ORDER BY s.session_date DESC, sl.set_number ASC
    LIMIT 10
");
foreach ($exercises as $e) {
    $ls_st->execute([$e['exercise_id'], $uid]);
    $rows = $ls_st->fetchAll();
    if ($rows) $last_session_data[$e['exercise_id']] = $rows;
}

// Calculate total target sets
$total_target = 0;
foreach ($exercises as $e) {
    $total_target += (int)$e['sets_target'];
    if ($e['is_left_priority'] && $e['sets_left']) {
        $total_target += (int)$e['sets_left'];
    }
}

// Group by section
$by_section = [];
foreach ($exercises as $e) $by_section[$e['section']][] = $e;

$pn = (int)preg_replace('/\D/', '', $active_day);

render_head('Today's Workout — Log Sets & Track Progress','workout', false, 'Log your workout sets, track weights and reps, and monitor your progress in real time.');
?>

<div class="page-header">
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div>
      <div class="page-title">
        <?php if ($is_rest_day && !isset($_GET['day'])): ?>
          Rest Day
        <?php else: ?>
          Today's Workout
        <?php endif; ?>
      </div>
      <div class="page-sub">
        <?php if ($day_config): ?>
          <span class="day-pill day-pill-<?= $pn ?>" style="font-size:11px"><?= htmlspecialchars($active_day) ?></span>
          <?= htmlspecialchars($day_config['day_title']) ?> &middot; <?= date('l, M j') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($session): ?>
      <span class="badge badge-admin">Session active</span>
      <?php endif; ?>
      <span class="text-sm font-semibold text-accent-text"><?= $total_logged ?>/<?= $total_target ?> sets</span>
    </div>
  </div>
</div>

<?php if ($is_rest_day && !isset($_GET['day'])): ?>
<div class="card mb-5 border-accent">
  <div class="text-center py-4">
    <div class="text-3xl mb-2">😴</div>
    <div class="text-[15px] font-semibold text-[var(--text)] mb-1">No workout scheduled for today</div>
    <div class="text-[13px] text-muted">Pick a day below to train anyway, or enjoy your rest.</div>
  </div>
</div>
<?php endif; ?>

<!-- Day tabs -->
<div class="flex gap-1.5 flex-wrap mb-5">
  <?php foreach ($plan_days as $pd):
    $dpn = (int)preg_replace('/\D/', '', $pd['day_label']);
    $isActive = $pd['day_label'] === $active_day;
  ?>
  <a href="workout.php?day=<?= urlencode($pd['day_label']) ?>"
     class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-ghost' ?>">
    <?= htmlspecialchars($pd['day_label']) ?> &middot; <?= htmlspecialchars($pd['day_title']) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($total_target > 0): ?>
<!-- Progress bar -->
<div class="mb-5">
  <div class="flex items-center gap-3">
    <div class="flex-1 h-2 bg-bg3 rounded-full overflow-hidden">
      <div class="h-full bg-accent rounded-full transition-all duration-300" style="width:<?= $total_target ? round($total_logged/$total_target*100) : 0 ?>%"></div>
    </div>
    <span class="text-xs text-muted font-semibold"><?= $total_target ? round($total_logged/$total_target*100) : 0 ?>%</span>
  </div>
</div>
<?php endif; ?>

<!-- Exercise Cards -->
<?php if (!$exercises): ?>
<div class="card"><div class="empty"><p>No exercises assigned to <?= htmlspecialchars($active_day) ?>.</p><a href="plan_builder.php?plan_id=<?= $ap['id'] ?>&day=<?= urlencode($active_day) ?>" class="btn btn-primary btn-sm">Add Exercises</a></div></div>
<?php endif; ?>

<?php foreach ($by_section as $section => $sec_exs): ?>
<div class="section-hdr"><?= htmlspecialchars($section) ?></div>

<?php foreach ($sec_exs as $e):
    $ex_id = $e['exercise_id'];
    $ex_sets = $logged_sets[$ex_id] ?? [];
    $next_set = count($ex_sets) + 1;
    $col = $colors[$active_day] ?? '#888';
    $last_data = $last_session_data[$ex_id] ?? [];
    $last_set = $last_data[0] ?? null;

    // Smart weight suggestion
    $suggest_weight = '';
    if ($last_set && $last_set['weight_kg']) {
        $diff = $last_set['difficulty'] ?? null;
        if ($diff === 'easy') $suggest_weight = round($last_set['weight_kg'] + 2.5, 1);
        elseif ($diff === 'hard') $suggest_weight = $last_set['weight_kg'];
        else $suggest_weight = $last_set['weight_kg'];
    }

    // Smart side default
    $default_side = 'both';
    if ($e['is_left_priority']) {
        $last_logged_side = end($ex_sets);
        if (!$last_logged_side) $default_side = 'left';
        elseif ($last_logged_side['side'] === 'left') $default_side = 'right';
        else $default_side = 'left';
    }
?>
<div id="ex-<?= $ex_id ?>" class="card mb-2.5" style="border-left:3px solid <?= $col ?>"
     x-data="{ difficulty: '' }">
  <!-- Exercise header -->
  <div class="flex justify-between items-start gap-2 mb-2">
    <div>
      <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
        <span class="font-bold text-[15px] text-[var(--text)]"><?= htmlspecialchars($e['name']) ?></span>
        <span class="text-xs text-muted"><?= htmlspecialchars($e['muscle_group']) ?></span>
        <?php if ($e['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
        <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
        <?php if ($e['ex_cardio']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
        <?php if ($e['ex_cardio']==='steady_state'): ?><span class="badge badge-ss">Steady</span><?php endif; ?>
      </div>
      <div class="text-[13px] text-muted">
        <?= $e['sets_target'] ?> × <?= htmlspecialchars($e['reps_target']) ?>
      </div>
      <?php if ($last_data): ?>
      <div class="text-[11px] text-muted2 mt-1">Last:
        <?php foreach (array_slice($last_data, 0, 3) as $ls): ?>
        <?= $ls['weight_kg'] ? $ls['weight_kg'].'kg' : '' ?><?= $ls['reps'] ? '×'.$ls['reps'] : '' ?><?= $ls['difficulty'] ? ' ('.$ls['difficulty'].')' : '' ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($e['youtube_url']): ?>
    <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt flex-shrink-0">▶ Watch</a>
    <?php endif; ?>
  </div>

  <?php if ($e['coach_tip']): ?>
  <div class="coach-tip mb-2.5"><?= htmlspecialchars($e['coach_tip']) ?></div>
  <?php endif; ?>

  <!-- Logged sets -->
  <?php if ($ex_sets): ?>
  <div class="mb-2.5">
    <?php foreach ($ex_sets as $i => $s): ?>
    <div x-data="{ editing: false }" class="py-1.5 border-b border-border-app <?= ($i === count($ex_sets) - 1 && isset($_GET['day'])) ? 'animate-highlight' : '' ?>">
      <!-- View mode -->
      <div x-show="!editing" class="flex items-center gap-0 text-sm cursor-pointer bg-bg3 rounded-app overflow-hidden" x-on:click="editing = true" title="Click to edit">
        <span class="bg-surface2 text-muted text-xs font-bold px-3 py-2 text-center" style="min-width:36px"><?= $s['set_number'] ?></span>
        <span class="px-2.5 py-2 text-[11px] <?= $s['side']==='left' ? 'bg-left-dim text-left-text' : ($s['side']==='right' ? 'bg-accent-dim text-accent-text' : 'text-muted') ?>"><?= ucfirst($s['side']) ?></span>
        <span class="px-2.5 py-2 font-semibold"><?= $s['reps'] ?: '—' ?><span class="text-muted font-normal text-xs"> reps</span></span>
        <span class="px-2.5 py-2 font-semibold"><?= $s['weight_kg'] ? $s['weight_kg'] : '—' ?><span class="text-muted font-normal text-xs"> kg</span></span>
        <?php if ($s['duration_sec']): ?><span class="px-2.5 py-2 text-muted text-xs"><?= $s['duration_sec'] ?>s rest</span><?php endif; ?>
        <?php if (!empty($s['difficulty'])): ?><span class="px-2 py-2 text-[13px]"><?= $s['difficulty']==='easy' ? '😊' : ($s['difficulty']==='hard' ? '😤' : '😐') ?></span><?php endif; ?>
        <span class="text-muted2 text-[10px] ml-auto pr-3">✏️</span>
      </div>
      <!-- Edit mode -->
      <form x-show="editing" x-transition x-cloak method="post" class="flex items-end gap-2 flex-wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_set">
        <input type="hidden" name="set_id" value="<?= $s['id'] ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <input type="hidden" name="exercise_id" value="<?= $ex_id ?>">
        <div style="width:55px"><label class="text-[10px]">Reps</label><input type="number" name="reps" value="<?= $s['reps'] ?>" min="1" class="min-h-[36px] text-sm"></div>
        <div style="width:60px"><label class="text-[10px]">kg</label><input type="number" name="weight_kg" value="<?= $s['weight_kg'] ?>" step="0.5" min="0" class="min-h-[36px] text-sm"></div>
        <div style="width:55px"><label class="text-[10px]">Rest</label><input type="number" name="duration_sec" value="<?= $s['duration_sec'] ?>" min="0" class="min-h-[36px] text-sm"></div>
        <div style="width:65px"><label class="text-[10px]">Side</label><select name="side" class="min-h-[36px] text-sm"><option value="both" <?= $s['side']==='both'?'selected':'' ?>>Both</option><option value="left" <?= $s['side']==='left'?'selected':'' ?>>Left</option><option value="right" <?= $s['side']==='right'?'selected':'' ?>>Right</option></select></div>
        <div style="width:70px"><label class="text-[10px]">Feel</label><select name="difficulty" class="min-h-[36px] text-sm"><option value="">—</option><option value="easy" <?= ($s['difficulty']??'')==='easy'?'selected':'' ?>>😊</option><option value="medium" <?= ($s['difficulty']??'')==='medium'?'selected':'' ?>>😐</option><option value="hard" <?= ($s['difficulty']??'')==='hard'?'selected':'' ?>>😤</option></select></div>
        <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px;min-height:36px">Save</button>
        <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 10px;min-height:36px" x-on:click="editing = false">✕</button>
        <form method="post" class="inline" x-data x-on:submit="if (!confirm('Delete?')) $event.preventDefault()">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_set">
          <input type="hidden" name="set_id" value="<?= $s['id'] ?>">
          <input type="hidden" name="day" value="<?= htmlspecialchars($active_day) ?>">
          <button class="btn btn-danger btn-sm" style="padding:4px 8px;min-height:36px">🗑</button>
        </form>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Add set form -->
  <form method="post" x-on:submit="$el.querySelector('[type=submit]').disabled=true">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="log_set">
    <input type="hidden" name="submit_token" value="<?= bin2hex(random_bytes(16)) ?>">
    <input type="hidden" name="session_id" value="<?= $session_id ?>">
    <input type="hidden" name="exercise_id" value="<?= $ex_id ?>">
    <input type="hidden" name="day" value="<?= htmlspecialchars($active_day) ?>">
    <input type="hidden" name="notes" value="">
    <input type="hidden" name="difficulty" x-model="difficulty">

    <div class="grid grid-cols-4 gap-2 mb-2">
      <div>
        <label class="text-[10px]">#</label>
        <input type="number" name="set_number" value="<?= $next_set ?>" min="1" required class="min-h-[44px] text-base">
      </div>
      <div>
        <label class="text-[10px]">Reps</label>
        <input type="number" name="reps" min="1" placeholder="12" class="min-h-[44px] text-base" <?= $last_set && $last_set['reps'] ? 'value="'.$last_set['reps'].'"' : '' ?>>
      </div>
      <div>
        <label class="text-[10px]">kg</label>
        <input type="number" name="weight_kg" step="0.5" min="0" placeholder="<?= $suggest_weight ?: '20' ?>" class="min-h-[44px] text-base" <?= $suggest_weight ? 'value="'.$suggest_weight.'"' : '' ?>>
      </div>
      <div>
        <label class="text-[10px]">Rest (sec)</label>
        <input type="number" name="duration_sec" min="0" value="30" placeholder="30" class="min-h-[44px] text-base">
      </div>
    </div>
    <div class="flex items-center gap-3 mb-2">
      <span class="text-[10px] text-muted font-semibold uppercase">Side:</span>
      <label class="text-xs flex items-center gap-1 cursor-pointer"><input type="radio" name="side" value="both" <?= $default_side==='both'?'checked':'' ?> style="width:auto;-webkit-appearance:radio;appearance:radio"> Both</label>
      <label class="text-xs flex items-center gap-1 cursor-pointer"><input type="radio" name="side" value="left" <?= $default_side==='left'?'checked':'' ?> style="width:auto;-webkit-appearance:radio;appearance:radio"> Left</label>
      <label class="text-xs flex items-center gap-1 cursor-pointer"><input type="radio" name="side" value="right" <?= $default_side==='right'?'checked':'' ?> style="width:auto;-webkit-appearance:radio;appearance:radio"> Right</label>
    </div>

    <!-- Difficulty pills -->
    <div class="flex items-center gap-2 mb-3">
      <span class="text-[10px] text-muted font-semibold uppercase">Feel:</span>
      <button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:12px" :class="difficulty === 'easy' ? 'bg-green-dim text-green-text border border-[rgba(99,153,34,0.4)]' : 'btn-ghost'" x-on:click="difficulty = difficulty === 'easy' ? '' : 'easy'">😊 Easy</button>
      <button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:12px" :class="difficulty === 'medium' ? 'bg-warn-dim text-warn-text border border-[rgba(186,117,23,0.4)]' : 'btn-ghost'" x-on:click="difficulty = difficulty === 'medium' ? '' : 'medium'">😐 Medium</button>
      <button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:12px" :class="difficulty === 'hard' ? 'bg-red-dim text-red-text border border-[rgba(224,92,92,0.4)]' : 'btn-ghost'" x-on:click="difficulty = difficulty === 'hard' ? '' : 'hard'">😤 Hard</button>
    </div>

    <div class="flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm min-h-[44px]">+ Log Set</button>
      <?php if ($ex_sets): ?>
      <?php $last_ex_set = end($ex_sets); ?>
      <button type="button" class="btn btn-ghost btn-sm min-h-[44px]" onclick="
        this.closest('form').querySelector('[name=reps]').value='<?= $last_ex_set['reps'] ?>';
        this.closest('form').querySelector('[name=weight_kg]').value='<?= $last_ex_set['weight_kg'] ?>';
        this.closest('form').querySelector('[name=side]').value='<?= $default_side ?>';
        this.closest('form').submit();
      ">↻ Repeat</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php endforeach; ?>
<?php endforeach; ?>

<?php if ($session): ?>
<div class="mt-5 text-center">
  <a href="log.php?session_id=<?= $session_id ?>" class="btn btn-ghost btn-sm">View full session in History &rarr;</a>
</div>
<?php endif; ?>

<?php render_foot(); ?>
