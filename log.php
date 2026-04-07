<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();

$all_colors = day_colors();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_session') {
        $ap = active_plan();
        // Look up day title from plan_days
        $dt_st = $db->prepare("SELECT day_title FROM plan_days WHERE plan_id=? AND day_label=?");
        $dt_st->execute([$ap['id'] ?? 0, $_POST['day_label']]);
        $dt = $dt_st->fetchColumn() ?: $_POST['day_label'];
        $db->prepare("INSERT INTO sessions (session_date,day_label,title,plan_id,duration_min,notes,user_id) VALUES (?,?,?,?,?,?,?)")
           ->execute([$_POST['session_date'],$_POST['day_label'],$dt,$ap['id']??null,$_POST['duration_min']?:null,$_POST['notes']?:null,$uid]);
        $sid = $db->lastInsertId();
        flash('Session created!');
        header("Location: log.php?session_id=$sid"); exit;
    }
    if ($action === 'log_set') {
        // Verify session ownership
        $own = $db->prepare("SELECT id FROM sessions WHERE id=? AND user_id=?");
        $own->execute([$_POST['session_id'], $uid]);
        if ($own->fetch()) {
            $db->prepare("INSERT INTO sets_log (session_id,exercise_id,set_number,reps,weight_kg,duration_sec,side,notes,user_id) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$_POST['session_id'],$_POST['exercise_id'],$_POST['set_number'],$_POST['reps']?:null,$_POST['weight_kg']?:null,$_POST['duration_sec']?:null,$_POST['side'],$_POST['notes']?:null,$uid]);
            flash('Set logged!');
        }
        header("Location: log.php?session_id=".$_POST['session_id']); exit;
    }
    if ($action === 'delete_set') {
        $db->prepare("DELETE FROM sets_log WHERE id=? AND user_id=?")->execute([$_POST['set_id'], $uid]);
        flash('Set removed.');
        header("Location: log.php?session_id=".$_POST['session_id']); exit;
    }
    if ($action === 'delete_session') {
        $db->prepare("DELETE FROM sessions WHERE id=? AND user_id=?")->execute([$_POST['session_id'], $uid]);
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

render_head('Log Workout','log');
?>

<div class="page-header">
  <div class="page-title">Log Workout</div>
  <div class="page-sub">
    <?php if ($ap): ?>
    Active plan: <strong><?= htmlspecialchars($ap['name']) ?></strong>
    — exercises shown come from this plan
    <?php else: ?>
    <span style="color:var(--red)">No active plan — <a href="plan_manager.php">activate one first</a></span>
    <?php endif; ?>
  </div>
</div>

<div class="log-grid" style="display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start">

<!-- Sidebar -->
<div>
  <div style="margin-bottom:1rem">
    <button onclick="var f=document.getElementById('nsf');f.style.display=f.style.display==='none'?'block':'none'"
      class="btn btn-primary" style="width:100%">+ New Session</button>
  </div>

  <!-- New session form -->
  <div id="nsf" style="display:<?= $show_new?'block':'none' ?>;margin-bottom:1rem">
    <div class="card">
      <div class="card-title">New Session</div>
      <?php if (!$ap): ?>
      <p style="color:var(--red);font-size:13px">No active plan. <a href="plan_manager.php">Activate a plan first.</a></p>
      <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_session">
        <input type="hidden" name="title" id="session-title" value="<?= htmlspecialchars($day_titles_map['Day 1'] ?? '') ?>">
        <div class="form-group"><label>Date</label><input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group">
          <label>Which day?</label>
          <select name="day_label" id="day-sel" required onchange="selDay(this.value)">
            <?php foreach ($plan_days_list as $pd): ?>
            <option value="<?= $pd['day_label'] ?>"><?= $pd['day_label'] ?> — <?= htmlspecialchars($pd['day_title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Session (auto-set)</label>
          <div id="day-preview" style="padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg);color:var(--muted);font-weight:500">
            <?= htmlspecialchars($day_titles_map['Day 1'] ?? '') ?>
          </div>
        </div>
        <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_min" min="1" placeholder="70"></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Energy, activation, pain levels..."></textarea></div>
        <button type="submit" class="btn btn-primary btn-sm" style="width:100%">Create Session</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Session list -->
  <div class="card">
    <div class="card-title">Sessions</div>
    <?php if ($all_sessions): ?>
    <div>
    <?php foreach ($all_sessions as $s):
      $pn  = (int)preg_replace('/\D/', '', $s['day_label']);
      $col = $day_colors[$s['day_label']] ?? '#888';
      $isA = $s['id'] == $session_id;
    ?>
    <a href="log.php?session_id=<?= $s['id'] ?>"
       style="display:block;padding:9px 10px;border-radius:8px;text-decoration:none;margin-bottom:2px;background:<?= $isA?'var(--accent-light)':'transparent' ?>">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">
        <span style="width:6px;height:6px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></span>
        <span style="font-size:12px;font-weight:600;color:<?= $isA?'var(--accent)':'var(--text)' ?>"><?= htmlspecialchars($s['title']) ?></span>
      </div>
      <div style="font-size:11px;color:var(--muted);padding-left:12px">
        <?= date('M j, Y', strtotime($s['session_date'])) ?> &middot; <?= $s['set_count'] ?> sets
        <?php if ($s['plan_name']): ?><span style="opacity:0.7"> &middot; <?= htmlspecialchars($s['plan_name']) ?></span><?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--muted);font-size:13px">No sessions yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Main panel -->
<div>
<?php if ($session):
  $pn  = (int)preg_replace('/\D/', '', $session['day_label']);
  $dt  = $day_titles_map[$session['day_label']] ?? $session['title'];
  // Get the plan exercises for this session's day (from the session's plan)
  $sess_plan_id = $session['plan_id'];
  $sess_plan_exs = [];
  if ($sess_plan_id) {
    $speq = $db->prepare("
      SELECT pe.*, e.name, e.muscle_group, e.youtube_url, e.coach_tip, pe.is_left_priority, pe.both_sides, pe.sets_target, pe.reps_target, pe.section
      FROM plan_exercises pe JOIN exercises e ON pe.exercise_id=e.id
      WHERE pe.plan_id=? AND pe.day_label=?
      ORDER BY pe.section_order, pe.sort_order
    ");
    $speq->execute([$sess_plan_id, $session['day_label']]);
    foreach ($speq->fetchAll() as $e) $sess_plan_exs[$e['section']][] = $e;
  }
?>

  <!-- Session header -->
  <div class="card" style="margin-bottom:1.25rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
          <span class="day-pill day-pill-<?= $pn ?>"><?= $session['day_label'] ?> &middot; <?= $dt ?></span>
          <?php if ($session['plan_name']): ?>
          <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($session['plan_name']) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size:17px;font-weight:700"><?= htmlspecialchars($session['title']) ?></div>
        <div style="color:var(--muted);font-size:13px"><?= date('l, F j, Y', strtotime($session['session_date'])) ?><?= $session['duration_min']?' &middot; '.$session['duration_min'].' min':'' ?></div>
        <?php if ($session['notes']): ?><div style="font-size:13px;color:var(--muted);margin-top:6px"><?= nl2br(htmlspecialchars($session['notes'])) ?></div><?php endif; ?>
      </div>
      <form method="post" onsubmit="return confirm('Delete this session?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_session">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>
    </div>
  </div>

  <!-- Plan exercises for this day (reference) -->
  <?php if ($sess_plan_exs): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-title">Today's Programme</div>
    <?php foreach ($sess_plan_exs as $section => $exs): ?>
    <div class="section-hdr"><?= htmlspecialchars($section) ?></div>
    <?php foreach ($exs as $e): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);gap:8px">
      <div>
        <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($e['name']) ?></span>
        <span style="font-size:12px;color:var(--muted);margin-left:6px"><?= $e['sets_target'] ?>&times;<?= htmlspecialchars($e['reps_target']) ?></span>
        <?php if ($e['is_left_priority']): ?><span class="badge badge-left" style="margin-left:4px">Left+</span><?php endif; ?>
      </div>
      <?php if ($e['youtube_url']): ?><a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt" style="font-size:10px;padding:2px 7px">&#9654;</a><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Sets logged -->
  <?php if ($sets): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-title">Sets Logged (<?= count($sets) ?>)</div>
    <table>
      <thead><tr><th>#</th><th>Exercise</th><th>Side</th><th>Reps</th><th>Weight</th><th>Duration</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sets as $i => $s): ?>
      <tr>
        <td style="color:var(--muted)"><?= $i+1 ?></td>
        <td>
          <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($s['ex_name']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= $s['muscle_group'] ?>
            <?php if ($s['is_left_priority']): ?><span style="color:var(--left)"> &middot; Left+</span><?php endif; ?>
            <?php if ($s['youtube_url']): ?> &middot; <a href="<?= htmlspecialchars($s['youtube_url']) ?>" target="_blank" style="color:#FF0000;font-size:11px">&#9654; Watch</a><?php endif; ?>
          </div>
        </td>
        <td><?= $s['side'] ?></td>
        <td><?= $s['reps'] ?: '—' ?></td>
        <td><?= $s['weight_kg'] ? $s['weight_kg'].' kg' : '—' ?></td>
        <td><?= $s['duration_sec'] ? $s['duration_sec'].'s' : '—' ?></td>
        <td>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_set">
            <input type="hidden" name="set_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="session_id" value="<?= $session_id ?>">
            <button class="btn btn-danger btn-sm">&times;</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Log a set -->
  <div class="card">
    <div class="card-title">Log a Set</div>
    <form method="post" id="set-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log_set">
      <input type="hidden" name="session_id" value="<?= $session_id ?>">
      <div class="form-group">
        <label>Exercise <span style="font-size:11px;font-weight:400;color:var(--muted)">(from <?= $session['plan_name'] ? htmlspecialchars($session['plan_name']) : 'active plan' ?>)</span></label>
        <select name="exercise_id" id="ex-sel" required onchange="onExChange(this)">
          <option value="">— select exercise —</option>
          <?php foreach ($sess_plan_exs as $section => $exs): ?>
          <optgroup label="<?= htmlspecialchars($section) ?>">
            <?php foreach ($exs as $e): ?>
            <option value="<?= $e['exercise_id'] ?>"
              data-left="<?= $e['is_left_priority'] ?>"
              data-yt="<?= htmlspecialchars($e['youtube_url']??'') ?>"
              data-tip="<?= htmlspecialchars($e['coach_tip']??'') ?>"
              data-sets="<?= $e['sets_target'] ?>"
              data-reps="<?= htmlspecialchars($e['reps_target']) ?>"
              data-lsets="<?= $e['sets_left']??0 ?>"
              data-lreps="<?= $e['reps_left_bonus']??0 ?>">
              <?= htmlspecialchars($e['name']) ?><?= $e['is_left_priority']?' *':'' ?>
            </option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
          <?php if (!$sess_plan_exs): ?>
          <?php
          $all_lib_st = $db->prepare("SELECT id, name, muscle_group FROM exercises WHERE status='approved' OR created_by=? ORDER BY muscle_group, name");
          $all_lib_st->execute([$uid]);
          foreach ($all_lib_st->fetchAll() as $e): ?>
          <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div id="ex-info" style="display:none;margin-bottom:1rem">
        <div id="target-box" style="display:none;padding:8px 12px;background:var(--accent-dim);border-radius:7px;font-size:13px;color:var(--accent-text);margin-bottom:8px">
          Target: <strong id="target-text"></strong>
        </div>
        <div id="left-banner" style="display:none" class="left-banner">
          Left priority — log left and right sides separately to track the gap
        </div>
        <div id="tip-box" style="display:none;padding:10px 14px;background:var(--bg);border-radius:8px;font-size:13px;color:var(--muted);line-height:1.5;border:1px solid var(--border)">
          <strong style="color:var(--text);font-size:11px;text-transform:uppercase;letter-spacing:0.04em">Coach tip:</strong>
          <div id="tip-text" style="margin-top:2px"></div>
        </div>
        <div id="yt-box" style="margin-top:8px;display:none">
          <a id="yt-link" href="#" target="_blank" class="btn-yt">&#9654; Watch on YouTube</a>
        </div>
      </div>

      <div class="form-row form-row-4">
        <div><label>Set #</label><input type="number" name="set_number" min="1" value="1" required></div>
        <div><label>Reps</label><input type="number" name="reps" min="1" placeholder="12"></div>
        <div><label>Weight (kg)</label><input type="number" name="weight_kg" step="0.5" min="0" placeholder="20"></div>
        <div><label>Duration (sec)</label><input type="number" name="duration_sec" min="1" placeholder="60"></div>
      </div>
      <div class="form-row form-row-2">
        <div><label>Side</label>
          <select name="side">
            <option value="both">Both / N/A</option>
            <option value="left">Left</option>
            <option value="right">Right</option>
          </select>
        </div>
        <div><label>Notes</label><input type="text" name="notes" placeholder="e.g. felt strong"></div>
      </div>
      <button type="submit" class="btn btn-primary">Log Set</button>
    </form>
  </div>

<?php else: ?>
  <div class="card"><div class="empty">
    <div class="empty-icon">&#128203;</div>
    <p>Select a session on the left, or create a new one.</p>
    <button onclick="document.getElementById('nsf').style.display='block'" class="btn btn-primary">+ New Session</button>
  </div></div>
<?php endif; ?>
</div>
</div>

<script>
const dayTitles = <?= json_encode($day_titles_map) ?>;
function selDay(dl) {
  document.getElementById('session-title').value = dayTitles[dl] || dl;
  document.getElementById('day-preview').textContent = dayTitles[dl] || dl;
}
function onExChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  const info = document.getElementById('ex-info');
  if (!opt.value) { info.style.display='none'; return; }
  info.style.display='block';
  const isLeft = opt.dataset.left==='1';
  const sets = opt.dataset.sets; const reps = opt.dataset.reps;
  const lsets = opt.dataset.lsets; const lreps = opt.dataset.lreps;
  const tip = opt.dataset.tip; const yt = opt.dataset.yt;
  document.getElementById('left-banner').style.display = isLeft?'block':'none';
  const tb = document.getElementById('target-box');
  if (sets && reps) {
    tb.style.display='block';
    let t = sets+'\u00d7'+reps;
    if (isLeft && (lsets>0||lreps>0)) t += ' \u00b7 Left: +'+(lsets||0)+' sets, +'+(lreps||0)+' reps';
    document.getElementById('target-text').textContent = t;
  } else tb.style.display='none';
  const tipBox = document.getElementById('tip-box');
  if (tip) { tipBox.style.display='block'; document.getElementById('tip-text').textContent=tip; } else tipBox.style.display='none';
  const ytBox = document.getElementById('yt-box');
  if (yt) { ytBox.style.display='block'; document.getElementById('yt-link').href=yt; } else ytBox.style.display='none';
}
</script>

<?php render_foot(); ?>
