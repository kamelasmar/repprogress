<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

$all_colors = day_colors();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_session') {
        $session_date = trim($_POST['session_date'] ?? '');
        $day_label    = trim($_POST['day_label'] ?? '');
        if (!$session_date || !$day_label) {
            flash('Date and day are required.', 'error');
            header("Location: log.php?new=1"); exit;
        }
        $ap = active_plan();
        $dt_st = $db->prepare("SELECT day_title FROM plan_days WHERE plan_id=? AND day_label=?");
        $dt_st->execute([$ap['id'] ?? 0, $day_label]);
        $dt = $dt_st->fetchColumn() ?: $day_label;
        $db->prepare("INSERT INTO sessions (session_date,day_label,title,plan_id,duration_min,notes,user_id) VALUES (?,?,?,?,?,?,?)")
           ->execute([$session_date,$day_label,$dt,$ap['id']??null,$_POST['duration_min']?:null,$_POST['notes']?:null,$uid]);
        $sid = $db->lastInsertId();
        flash('Session created!');
        header("Location: log.php?session_id=$sid"); exit;
    }
    if ($action === 'log_set') {
        // Prevent double-submit via one-time token
        $submit_token = $_POST['submit_token'] ?? '';
        if ($submit_token && ($submit_token === ($_SESSION['last_submit_token'] ?? ''))) {
            // Duplicate submission — skip insert, just redirect
            header("Location: log.php?session_id=".$_POST['session_id']); exit;
        }
        $_SESSION['last_submit_token'] = $submit_token;

        // Validate required fields
        $sess_id = (int)($_POST['session_id'] ?? 0);
        $ex_id   = (int)($_POST['exercise_id'] ?? 0);
        $set_num = (int)($_POST['set_number'] ?? 0);
        if (!$sess_id || !$ex_id || !$set_num) {
            flash('Session, exercise, and set number are required.', 'error');
            header("Location: log.php?session_id=".$sess_id); exit;
        }

        // Verify session ownership
        $own = $db->prepare("SELECT id FROM sessions WHERE id=? AND user_id=?");
        $own->execute([$sess_id, $uid]);
        if ($own->fetch()) {
            $db->prepare("INSERT INTO sets_log (session_id,exercise_id,set_number,reps,weight_kg,duration_sec,side,notes,user_id) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$sess_id,$ex_id,$set_num,$_POST['reps']?:null,$_POST['weight_kg']?:null,$_POST['duration_sec']?:null,$_POST['side']?:'both',$_POST['notes']?:null,$uid]);
            flash('Set logged!');
        }
        header("Location: log.php?session_id=".$sess_id); exit;
    }
    if ($action === 'update_set') {
        $set_id = (int)($_POST['set_id'] ?? 0);
        $db->prepare("UPDATE sets_log SET reps=?, weight_kg=?, side=?, difficulty=?, duration_sec=? WHERE id=? AND user_id=?")
           ->execute([$_POST['reps'] ?: null, $_POST['weight_kg'] ?: null, $_POST['side'] ?: 'both', $_POST['difficulty'] ?: null, $_POST['duration_sec'] ?: null, $set_id, $uid]);
        flash('Set updated.');
        header("Location: log.php?session_id=".$_POST['session_id']); exit;
    }
    if ($action === 'delete_set') {
        $set_id = (int)($_POST['set_id'] ?? 0);
        $db->prepare("DELETE FROM sets_log WHERE id=? AND user_id=?")->execute([$set_id, $uid]);
        flash('Set removed.');
        header("Location: log.php?session_id=".$_POST['session_id']); exit;
    }
    if ($action === 'delete_session') {
        $sess_id = (int)($_POST['session_id'] ?? 0);
        $db->prepare("DELETE FROM sessions WHERE id=? AND user_id=?")->execute([$sess_id, $uid]);
        flash('Session deleted.');
        header("Location: log.php"); exit;
    }
}

$session_id = (int)($_GET['session_id'] ?? 0);
$show_new   = isset($_GET['new']);
$ap = active_plan();

// Pull exercises from the ACTIVE PLAN for the selected day
$plan_days_list = [];
$day_titles_map = [];
if ($ap) {
    $pdq = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_order");
    $pdq->execute([$ap['id']]);
    $plan_days_list = $pdq->fetchAll();
    foreach ($plan_days_list as $pd) {
        $day_titles_map[$pd['day_label']] = $pd['day_title'];
    }
}

// Group plan exercises by day for the set-log selector
$plan_ex_by_day = [];
if ($ap) {
    $peq = $db->prepare("
      SELECT pe.*, e.name, e.muscle_group, e.youtube_url, e.coach_tip, e.is_mobility, e.is_core, e.is_functional, e.cardio_type AS ex_cardio,
        pe.is_left_priority, pe.both_sides, pe.sets_target, pe.reps_target, pe.sets_left, pe.reps_left_bonus, pe.section
      FROM plan_exercises pe JOIN exercises e ON pe.exercise_id=e.id
      WHERE pe.plan_id=?
      ORDER BY pe.day_label, pe.section_order, pe.sort_order
    ");
    $peq->execute([$ap['id']]);
    foreach ($peq->fetchAll() as $e) $plan_ex_by_day[$e['day_label']][] = $e;
}

$session = null; $sets = [];
if ($session_id) {
    $session = $db->prepare("SELECT s.*, p.name AS plan_name FROM sessions s LEFT JOIN plans p ON s.plan_id=p.id WHERE s.id=? AND s.user_id=?");
    $session->execute([$session_id, $uid]); $session = $session->fetch();
    if ($session) {
        $sets = $db->prepare("
            SELECT sl.*, e.name AS ex_name, e.muscle_group, e.youtube_url, pe.is_left_priority
            FROM sets_log sl
            JOIN exercises e ON sl.exercise_id=e.id
            LEFT JOIN plan_exercises pe ON pe.exercise_id=e.id AND pe.plan_id=?
            WHERE sl.session_id=? AND sl.user_id=? ORDER BY sl.id
        ");
        $sets->execute([$ap['id']??0, $session_id, $uid]); $sets = $sets->fetchAll();
    }
}

$st_sessions = $db->prepare("
  SELECT s.id, s.session_date, s.day_label, s.title, COUNT(sl.id) AS set_count, p.name AS plan_name
  FROM sessions s LEFT JOIN sets_log sl ON sl.session_id=s.id LEFT JOIN plans p ON s.plan_id=p.id
  WHERE s.user_id=?
  GROUP BY s.id ORDER BY s.session_date DESC LIMIT 40
");
$st_sessions->execute([$uid]);
$all_sessions = $st_sessions->fetchAll();

$day_colors = $all_colors;

render_head('Workout History','log');
?>

<div class="page-header">
  <div class="page-title">Workout History</div>
  <div class="page-sub">
    <?php if ($ap): ?>
    <?= count($all_sessions) ?> session<?= count($all_sessions) !== 1 ? 's' : '' ?> logged
    <?php else: ?>
    <span class="text-red-text">No active plan — <a href="plan_manager.php">activate one first</a></span>
    <?php endif; ?>
  </div>
</div>

<?php if (!$all_sessions): ?>
<div class="card">
  <div class="empty">
    <div class="empty-icon">📋</div>
    <p>No workouts logged yet. Start a workout from the <a href="workout.php">Workout</a> page.</p>
  </div>
</div>
<?php else: ?>

<?php foreach ($all_sessions as $s):
  $pn  = (int)preg_replace('/\D/', '', $s['day_label']);
  $col = $day_colors[$s['day_label']] ?? '#888';
  $is_selected = $s['id'] == $session_id;

  // Load sets for this session if it's selected
  $sess_sets = [];
  $sess_sets_by_ex = [];
  if ($is_selected && $session) {
    $sess_sets = $sets;
    foreach ($sets as $st) {
      $sess_sets_by_ex[$st['ex_name']][] = $st;
    }
  }
?>
<div class="card mb-3" style="border-left:3px solid <?= $col ?>" x-data="{ open: <?= $is_selected ? 'true' : 'false' ?> }">
  <div class="flex justify-between items-start gap-3 cursor-pointer" x-on:click="open = !open">
    <div>
      <div class="flex items-center gap-2 flex-wrap mb-0.5">
        <span class="day-pill day-pill-<?= $pn ?>" style="font-size:10px;padding:2px 8px"><?= htmlspecialchars($s['day_label']) ?></span>
        <span class="font-bold text-sm text-[var(--text)]"><?= htmlspecialchars($s['title']) ?></span>
      </div>
      <div class="text-xs text-muted">
        <?= date('l, M j, Y', strtotime($s['session_date'])) ?>
        · <?= $s['set_count'] ?> set<?= $s['set_count'] != 1 ? 's' : '' ?>
        <?php if ($s['plan_name']): ?>· <?= htmlspecialchars($s['plan_name']) ?><?php endif; ?>
      </div>
    </div>
    <span class="text-muted text-xs" x-text="open ? '▲' : '▼'">▼</span>
  </div>

  <div x-show="open" x-transition x-cloak class="mt-3">
    <?php if ($is_selected && $sess_sets_by_ex): ?>
    <?php foreach ($sess_sets_by_ex as $ex_name => $ex_sets): ?>
    <div class="mb-3">
      <div class="text-[13px] font-semibold text-[var(--text)] mb-1"><?= htmlspecialchars($ex_name) ?>
        <span class="text-xs text-muted font-normal"><?= htmlspecialchars($ex_sets[0]['muscle_group'] ?? '') ?></span>
      </div>
      <?php foreach ($ex_sets as $st): ?>
      <div x-data="{ editing: false }" class="py-1 border-b border-border-app">
        <div x-show="!editing" class="flex items-center gap-0 text-sm cursor-pointer bg-bg3 rounded-app overflow-hidden" x-on:click="editing = true" title="Click to edit">
          <span class="bg-surface2 text-muted text-xs font-bold px-3 py-2 text-center" style="min-width:36px"><?= $st['set_number'] ?></span>
          <span class="px-2.5 py-2 text-[11px] <?= $st['side']==='left' ? 'bg-left-dim text-left-text' : ($st['side']==='right' ? 'bg-accent-dim text-accent-text' : 'text-muted') ?>"><?= ucfirst($st['side']) ?></span>
          <span class="px-2.5 py-2 font-semibold"><?= $st['reps'] ?: '—' ?><span class="text-muted font-normal text-xs"> reps</span></span>
          <span class="px-2.5 py-2 font-semibold"><?= $st['weight_kg'] ? $st['weight_kg'] : '—' ?><span class="text-muted font-normal text-xs"> kg</span></span>
          <?php if (!empty($st['difficulty'])): ?><span class="px-2 py-2 text-[13px]"><?= $st['difficulty']==='easy' ? '😊' : ($st['difficulty']==='hard' ? '😤' : '😐') ?></span><?php endif; ?>
          <span class="text-muted2 text-[10px] ml-auto pr-3">✏️</span>
        </div>
        <form x-show="editing" x-transition x-cloak method="post" class="flex items-end gap-2 flex-wrap py-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_set">
          <input type="hidden" name="set_id" value="<?= $st['id'] ?>">
          <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
          <div style="width:55px"><label class="text-[10px]">Reps</label><input type="number" name="reps" value="<?= $st['reps'] ?>" min="1" class="min-h-[36px] text-sm"></div>
          <div style="width:60px"><label class="text-[10px]">kg</label><input type="number" name="weight_kg" value="<?= $st['weight_kg'] ?>" step="0.5" min="0" class="min-h-[36px] text-sm"></div>
          <div style="width:55px"><label class="text-[10px]">Rest</label><input type="number" name="duration_sec" value="<?= $st['duration_sec'] ?>" min="0" class="min-h-[36px] text-sm"></div>
          <div style="width:65px"><label class="text-[10px]">Side</label><select name="side" class="min-h-[36px] text-sm"><option value="both" <?= $st['side']==='both'?'selected':'' ?>>Both</option><option value="left" <?= $st['side']==='left'?'selected':'' ?>>Left</option><option value="right" <?= $st['side']==='right'?'selected':'' ?>>Right</option></select></div>
          <div style="width:70px"><label class="text-[10px]">Feel</label><select name="difficulty" class="min-h-[36px] text-sm"><option value="">—</option><option value="easy" <?= ($st['difficulty']??'')==='easy'?'selected':'' ?>>😊</option><option value="medium" <?= ($st['difficulty']??'')==='medium'?'selected':'' ?>>😐</option><option value="hard" <?= ($st['difficulty']??'')==='hard'?'selected':'' ?>>😤</option></select></div>
          <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px;min-height:36px">Save</button>
          <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 10px;min-height:36px" x-on:click="editing = false">✕</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="flex gap-2 mt-2 pt-2 border-t border-border-app">
      <form method="post" class="inline" x-data x-on:submit="if (!confirm('Delete this entire session?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_session">
        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
        <button class="btn btn-danger btn-sm">Delete Session</button>
      </form>
    </div>

    <?php else: ?>
    <a href="log.php?session_id=<?= $s['id'] ?>" class="text-xs text-accent-text">Load session details →</a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php render_foot(); ?>
