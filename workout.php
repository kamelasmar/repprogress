<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();
$ap  = active_plan();
$colors = day_colors();
$today = date('Y-m-d');

if (!$ap) {
    render_head('Workout', 'workout');
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

        $db->prepare("INSERT INTO sets_log (session_id, exercise_id, set_number, reps, weight_kg, duration_sec, side, notes, user_id) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$session_id, $_POST['exercise_id'], $_POST['set_number'],
                      $_POST['reps'] ?: null, $_POST['weight_kg'] ?: null,
                      $_POST['duration_sec'] ?: null, $_POST['side'],
                      $_POST['notes'] ?: null, $uid]);
        flash('Set logged!');
        header("Location: workout.php?day=".urlencode($active_day)."#ex-".$_POST['exercise_id']); exit;
    }

    if ($action === 'delete_set') {
        $db->prepare("DELETE FROM sets_log WHERE id=? AND user_id=?")->execute([$_POST['set_id'], $uid]);
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

render_head('Workout', 'workout');
?>

<div class="page-header">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
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
    <div style="display:flex;align-items:center;gap:12px">
      <?php if ($session): ?>
      <span class="badge badge-admin">Session active</span>
      <?php endif; ?>
      <span style="font-size:14px;font-weight:600;color:var(--accent-text)"><?= $total_logged ?>/<?= $total_target ?> sets</span>
    </div>
  </div>
</div>

<?php if ($is_rest_day && !isset($_GET['day'])): ?>
<div class="card" style="margin-bottom:1.25rem;border-color:var(--accent)">
  <div style="text-align:center;padding:1rem 0">
    <div style="font-size:32px;margin-bottom:8px">&#128564;</div>
    <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:4px">No workout scheduled for today</div>
    <div style="font-size:13px;color:var(--muted)">Pick a day below to train anyway, or enjoy your rest.</div>
  </div>
</div>
<?php endif; ?>

<!-- Day tabs -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:1.25rem">
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
<div style="margin-bottom:1.25rem">
  <div style="height:8px;background:var(--bg3);border-radius:4px;overflow:hidden">
    <div style="height:100%;width:<?= $total_target ? round($total_logged/$total_target*100) : 0 ?>%;background:var(--accent);border-radius:4px;transition:width 0.3s"></div>
  </div>
  <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $total_target ? round($total_logged/$total_target*100) : 0 ?>% complete</div>
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
?>
<div id="ex-<?= $ex_id ?>" class="card" style="margin-bottom:10px;border-left:3px solid <?= $col ?>">
  <!-- Exercise header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px">
    <div>
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px">
        <span style="font-weight:700;font-size:15px;color:var(--text)"><?= htmlspecialchars($e['name']) ?></span>
        <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($e['muscle_group']) ?></span>
        <?php if ($e['is_left_priority']): ?><span class="badge badge-left">Left+</span><?php endif; ?>
        <?php if ($e['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
        <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
        <?php if ($e['ex_cardio']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
        <?php if ($e['ex_cardio']==='steady_state'): ?><span class="badge badge-ss">Steady</span><?php endif; ?>
      </div>
      <div style="font-size:13px;color:var(--muted)">
        <?= $e['sets_target'] ?> &times; <?= htmlspecialchars($e['reps_target']) ?>
        <?php if ($e['is_left_priority'] && ($e['sets_left'] || $e['reps_left_bonus'])): ?>
        <span style="color:var(--left-text)"> &middot; Left: +<?= $e['sets_left'] ?> sets, +<?= $e['reps_left_bonus'] ?> reps</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($e['youtube_url']): ?>
    <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt" style="flex-shrink:0">&#9654; Watch</a>
    <?php endif; ?>
  </div>

  <?php if ($e['coach_tip']): ?>
  <div class="coach-tip" style="margin-bottom:10px"><?= htmlspecialchars($e['coach_tip']) ?></div>
  <?php endif; ?>

  <!-- Logged sets -->
  <?php if ($ex_sets): ?>
  <table style="margin-bottom:10px">
    <thead><tr><th>#</th><th>Side</th><th>Reps</th><th>Weight</th><th>Dur</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($ex_sets as $s): ?>
    <tr>
      <td style="color:var(--muted)"><?= $s['set_number'] ?></td>
      <td><span style="font-size:11px;padding:2px 6px;border-radius:4px;background:<?= $s['side']==='left'?'var(--left-dim)':($s['side']==='right'?'var(--accent-dim)':'var(--bg3)') ?>;color:<?= $s['side']==='left'?'var(--left-text)':($s['side']==='right'?'var(--accent-text)':'var(--muted)') ?>"><?= $s['side'] ?></span></td>
      <td><?= $s['reps'] ?: '&mdash;' ?></td>
      <td><?= $s['weight_kg'] ? $s['weight_kg'].' kg' : '&mdash;' ?></td>
      <td><?= $s['duration_sec'] ? $s['duration_sec'].'s' : '&mdash;' ?></td>
      <td>
        <form method="post" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_set">
          <input type="hidden" name="set_id" value="<?= $s['id'] ?>">
          <input type="hidden" name="day" value="<?= htmlspecialchars($active_day) ?>">
          <button class="btn btn-danger btn-sm" style="padding:2px 6px">&times;</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Inline add set form -->
  <form method="post" onsubmit="this.querySelector('[type=submit]').disabled=true" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="log_set">
    <input type="hidden" name="submit_token" value="<?= bin2hex(random_bytes(16)) ?>">
    <input type="hidden" name="session_id" value="<?= $session_id ?>">
    <input type="hidden" name="exercise_id" value="<?= $ex_id ?>">
    <input type="hidden" name="day" value="<?= htmlspecialchars($active_day) ?>">
    <div style="width:50px">
      <label style="font-size:10px">#</label>
      <input type="number" name="set_number" value="<?= $next_set ?>" min="1" style="padding:7px 6px;font-size:13px">
    </div>
    <div style="width:65px">
      <label style="font-size:10px">Reps</label>
      <input type="number" name="reps" min="1" placeholder="12" style="padding:7px 6px;font-size:13px">
    </div>
    <div style="width:70px">
      <label style="font-size:10px">kg</label>
      <input type="number" name="weight_kg" step="0.5" min="0" placeholder="20" style="padding:7px 6px;font-size:13px">
    </div>
    <div style="width:55px">
      <label style="font-size:10px">Sec</label>
      <input type="number" name="duration_sec" min="1" placeholder="60" style="padding:7px 6px;font-size:13px">
    </div>
    <div style="width:75px">
      <label style="font-size:10px">Side</label>
      <select name="side" style="padding:7px 4px;font-size:13px">
        <option value="both">Both</option>
        <option value="left" <?= $e['is_left_priority']?'':'' ?>>Left</option>
        <option value="right">Right</option>
      </select>
    </div>
    <input type="hidden" name="notes" value="">
    <button type="submit" class="btn btn-primary btn-sm" style="padding:7px 12px;margin-bottom:0">+ Log</button>
  </form>
</div>
<?php endforeach; ?>
<?php endforeach; ?>

<?php if ($session): ?>
<div style="margin-top:1.25rem;text-align:center">
  <a href="log.php?session_id=<?= $session_id ?>" class="btn btn-ghost btn-sm">View full session in History &rarr;</a>
</div>
<?php endif; ?>

<?php render_foot(); ?>
