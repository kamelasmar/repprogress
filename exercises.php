<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
$db = db();

// ── POST: add new exercise ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_exercise') {
    $name    = trim($_POST['name'] ?? '');
    $muscle  = trim($_POST['muscle_group'] ?? '');
    $day     = $_POST['day_label'] ?? '';
    $section = trim($_POST['section'] ?? '');
    $tip     = trim($_POST['coach_tip'] ?? '');
    $yt      = trim($_POST['youtube_url'] ?? '');
    $left    = isset($_POST['is_left_priority']) ? 1 : 0;
    $mob     = isset($_POST['is_mobility'])      ? 1 : 0;
    $core    = isset($_POST['is_core'])          ? 1 : 0;
    $func    = isset($_POST['is_functional'])    ? 1 : 0;
    $both    = isset($_POST['both_sides'])       ? 1 : 0;
    $cardio  = $_POST['cardio_type'] ?? 'none';

    // Auto-set day_title from day_label
    $day_titles_map = [
        'Day 1'=>'Lower Body','Day 2'=>'Push','Day 3'=>'Pull',
        'Day 4'=>'Arms & Functional','Day 5'=>'Full Body + Mobility',
    ];
    $day_title = $day_titles_map[$day] ?? '';

    if ($name && $muscle) {
        $st = $db->prepare("INSERT INTO exercises
            (name,muscle_group,day_label,day_title,section,section_order,
             is_left_priority,is_mobility,is_core,is_functional,
             cardio_type,both_sides,youtube_url,coach_tip)
            VALUES (?,?,?,?,?,99,?,?,?,?,?,?,?,?)");
        $st->execute([$name,$muscle,$day,$day_title,$section,
                      $left,$mob,$core,$func,$cardio,$both,$yt,$tip]);
        flash("Exercise \"$name\" added.");
    } else {
        flash('Name and muscle group are required.', 'error');
    }
    header("Location: exercises.php?day=".urlencode($day));
    exit;
}

// ── POST: delete exercise ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_exercise') {
    $db->prepare("DELETE FROM exercises WHERE id=?")->execute([$_POST['id']]);
    flash('Exercise removed.');
    header("Location: exercises.php");
    exit;
}

// ── POST: edit exercise ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_exercise') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $muscle  = trim($_POST['muscle_group'] ?? '');
    $day     = $_POST['day_label'] ?? '';
    $section = trim($_POST['section'] ?? '');
    $tip     = trim($_POST['coach_tip'] ?? '');
    $yt      = trim($_POST['youtube_url'] ?? '');
    $left    = isset($_POST['is_left_priority']) ? 1 : 0;
    $mob     = isset($_POST['is_mobility'])      ? 1 : 0;
    $core    = isset($_POST['is_core'])          ? 1 : 0;
    $func    = isset($_POST['is_functional'])    ? 1 : 0;
    $both    = isset($_POST['both_sides'])       ? 1 : 0;
    $cardio  = $_POST['cardio_type'] ?? 'none';
    $day_titles_map2 = ['Day 1'=>'Lower Body','Day 2'=>'Push','Day 3'=>'Pull',
                        'Day 4'=>'Arms & Functional','Day 5'=>'Full Body + Mobility'];
    $day_title = $day_titles_map2[$day] ?? '';
    if ($id && $name && $muscle) {
        $db->prepare("UPDATE exercises SET
            name=?,muscle_group=?,day_label=?,day_title=?,section=?,
            is_left_priority=?,is_mobility=?,is_core=?,is_functional=?,
            cardio_type=?,both_sides=?,youtube_url=?,coach_tip=?
            WHERE id=?")
           ->execute([$name,$muscle,$day,$day_title,$section,
                      $left,$mob,$core,$func,$cardio,$both,$yt,$tip,$id]);
        flash(""$name" updated.");
    }
    header("Location: exercises.php?day=".urlencode($day)."#ex-".$id);
    exit;
}

// ── Filters ─────────────────────────────────────────────────────────────────
$filter_day  = $_GET['day']  ?? '';
$filter_mg   = $_GET['mg']   ?? '';
$filter_type = $_GET['type'] ?? '';

// Detect which columns actually exist (migration may have just added them)
$_ex_cols = $db->query("SHOW COLUMNS FROM exercises")->fetchAll(PDO::FETCH_COLUMN);
$_has = fn($c) => in_array($c, $_ex_cols);

$where  = 'WHERE 1=1';
$params = [];
if ($filter_day && $_has('day_label'))  { $where .= ' AND e.day_label=?'; $params[] = $filter_day; }
if ($filter_mg)                          { $where .= ' AND e.muscle_group=?'; $params[] = $filter_mg; }
if ($filter_type === 'core'       && $_has('is_core'))         { $where .= ' AND e.is_core=1'; }
elseif ($filter_type === 'mobility'   && $_has('is_mobility'))  { $where .= ' AND e.is_mobility=1'; }
elseif ($filter_type === 'functional' && $_has('is_functional')){ $where .= ' AND e.is_functional=1'; }
elseif ($filter_type === 'left'       && $_has('is_left_priority')){ $where .= ' AND e.is_left_priority=1'; }
elseif ($filter_type === 'cardio'     && $_has('cardio_type'))  { $where .= " AND e.cardio_type!='none'"; }

try {
    $stmt = $db->prepare("
      SELECT e.*,
        COALESCE(e.day_label,'')      AS day_label,
        COALESCE(e.day_title,'')      AS day_title,
        COALESCE(e.section,'')        AS section,
        COALESCE(e.section_order,0)   AS section_order,
        COALESCE(e.is_left_priority,0)AS is_left_priority,
        COALESCE(e.is_mobility,0)     AS is_mobility,
        COALESCE(e.is_core,0)         AS is_core,
        COALESCE(e.is_functional,0)   AS is_functional,
        COALESCE(e.cardio_type,'none')AS cardio_type,
        COALESCE(e.both_sides,0)      AS both_sides,
        COALESCE(e.youtube_url,'')    AS youtube_url,
        COALESCE(e.coach_tip,'')      AS coach_tip,
        COUNT(DISTINCT sl.id)         AS total_sets,
        MAX(sl.weight_kg)             AS max_weight,
        MAX(ss.session_date)          AS last_done
      FROM exercises e
      LEFT JOIN sets_log sl ON sl.exercise_id=e.id
      LEFT JOIN sessions  ss ON sl.session_id=ss.id
      $where
      GROUP BY e.id
      ORDER BY FIELD(e.day_label,'Day 1','Day 2','Day 3','Day 4','Day 5',''),
               e.section_order, e.section, e.name
    ");
    $stmt->execute($params);
    $exercises = $stmt->fetchAll();
} catch (PDOException $ex) {
    // Fallback for very old schemas: order by name only
    $stmt = $db->prepare("
      SELECT e.*, 0 AS total_sets, NULL AS max_weight, NULL AS last_done,
        COALESCE(e.day_label,'')   AS day_label,
        COALESCE(e.day_title,'')   AS day_title,
        COALESCE(e.section,'')     AS section,
        0 AS section_order, 0 AS is_left_priority, 0 AS is_mobility,
        0 AS is_core, 0 AS is_functional,
        'none' AS cardio_type, 0 AS both_sides,
        COALESCE(e.youtube_url,'') AS youtube_url,
        COALESCE(e.coach_tip,'')   AS coach_tip
      FROM exercises e
      ORDER BY e.name
    ");
    $stmt->execute();
    $exercises = $stmt->fetchAll();
}

$muscle_groups = $db->query("SELECT DISTINCT muscle_group FROM exercises ORDER BY muscle_group")->fetchAll(PDO::FETCH_COLUMN);

$day_titles_map = [
    'Day 1'=>'Lower Body','Day 2'=>'Push','Day 3'=>'Pull',
    'Day 4'=>'Arms & Functional','Day 5'=>'Full Body + Mobility',
];
$day_pill_n = ['Day 1'=>1,'Day 2'=>2,'Day 3'=>3,'Day 4'=>4,'Day 5'=>5];
$cardio_label = ['steady_state'=>'Steady State','hiit'=>'HIIT','none'=>''];

// Group by day then section
$by_day = [];
foreach ($exercises as $e) {
    $key = $e['day_label'] ?: 'Custom';
    $by_day[$key][$e['section'] ?: 'General'][] = $e;
}

$show_add = isset($_GET['add']);

render_head('My Programme', 'exercises');
?>

<div class="page-header">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div class="page-title">My Programme</div>
      <div class="page-sub">5-day plan · tap ▶ to watch a demo · <strong style="color:var(--accent)"><?= count($exercises) ?></strong> exercises</div>
    </div>
    <a href="exercises.php?add=1<?= $filter_day?'&day='.urlencode($filter_day):'' ?>"
       class="btn btn-primary">+ Add Exercise</a>
  </div>
</div>

<!-- ── ADD EXERCISE PANEL ──────────────────────────────────────────────────── -->
<?php if ($show_add): ?>
<div class="card" style="margin-bottom:1.25rem;border:2px solid var(--accent)">
  <div class="card-title" style="color:var(--accent)">Add New Exercise</div>
  <form method="post">
    <input type="hidden" name="action" value="add_exercise">

    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Exercise Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="name" placeholder="e.g. Cable Hip Abduction" required autofocus>
      </div>
      <div class="form-group">
        <label>Muscle Group <span style="color:var(--red)">*</span></label>
        <input type="text" name="muscle_group" placeholder="e.g. Hips" list="mg-list" required>
        <datalist id="mg-list">
          <?php foreach ($muscle_groups as $mg): ?><option value="<?= htmlspecialchars($mg) ?>"><?php endforeach; ?>
        </datalist>
      </div>
    </div>

    <div class="form-row form-row-3">
      <div class="form-group">
        <label>Training Day</label>
        <select name="day_label">
          <option value="">— Custom / No day —</option>
          <?php foreach ($day_titles_map as $dl => $dt): ?>
          <option value="<?= $dl ?>" <?= $filter_day===$dl?'selected':'' ?>><?= $dl ?> — <?= $dt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Section</label>
        <input type="text" name="section" placeholder="e.g. Main Work" list="section-list">
        <datalist id="section-list">
          <option value="Warm-Up"><option value="Hip Mobility"><option value="Core Block A — Morning">
          <option value="Activation"><option value="Main Work — Machines"><option value="Main Work">
          <option value="Functional"><option value="Finisher"><option value="Cool-Down">
          <option value="Core Block B — Post Session"><option value="Cardio Warm-Up">
        </datalist>
      </div>
      <div class="form-group">
        <label>Cardio Type</label>
        <select name="cardio_type">
          <option value="none">Not cardio</option>
          <option value="steady_state">Steady State</option>
          <option value="hiit">HIIT</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>YouTube / Video Link</label>
      <input type="url" name="youtube_url" placeholder="https://www.youtube.com/results?search_query=...">
      <div style="font-size:12px;color:var(--muted);margin-top:4px">
        Tip: go to YouTube, search the exercise, copy the search results URL — it will never break.
      </div>
    </div>

    <div class="form-group">
      <label>Coach Tip</label>
      <textarea name="coach_tip" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical"
        placeholder="Key form cue or note specific to your situation..."></textarea>
    </div>

    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:1rem">
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="is_left_priority" value="1" style="width:auto"> Left priority
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="both_sides" value="1" style="width:auto"> Both sides
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="is_mobility" value="1" style="width:auto"> Mobility
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="is_core" value="1" style="width:auto"> Core
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="is_functional" value="1" style="width:auto"> Functional
      </label>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">Save Exercise</button>
      <a href="exercises.php<?= $filter_day?'?day='.urlencode($filter_day):'' ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ── DAY TABS ────────────────────────────────────────────────────────────── -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
  <a href="exercises.php" class="btn btn-sm <?= !$filter_day&&!$filter_type?'btn-primary':'btn-ghost' ?>">All</a>
  <?php foreach ($day_titles_map as $dl => $dt): ?>
  <a href="exercises.php?day=<?= urlencode($dl) ?>"
     class="btn btn-sm <?= $filter_day===$dl?'btn-primary':'btn-ghost' ?>">
    <?= $dl ?> · <?= $dt ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Type filters -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:1rem;align-items:center">
  <span style="font-size:12px;color:var(--muted)">Filter:</span>
  <?php foreach ([''=>'All','left'=>'Left Priority','core'=>'Core','mobility'=>'Mobility','functional'=>'Functional','cardio'=>'Cardio'] as $val=>$lbl): ?>
  <a href="exercises.php?type=<?= $val ?><?= $filter_day?'&day='.urlencode($filter_day):'' ?>"
     style="font-size:12px;padding:3px 10px;border-radius:20px;border:1px solid var(--border);text-decoration:none;
            background:<?= $filter_type===$val&&($val||!$filter_day)?'var(--accent)':'transparent' ?>;
            color:<?= $filter_type===$val&&($val||!$filter_day)?'#fff':'var(--muted)' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
  <?php if ($filter_mg): ?>
  <span style="font-size:12px;padding:3px 10px;border-radius:20px;background:var(--accent);color:#fff">
    <?= htmlspecialchars($filter_mg) ?> <a href="exercises.php<?= $filter_day?'?day='.urlencode($filter_day):'' ?>" style="color:#fff;margin-left:4px">×</a>
  </span>
  <?php endif; ?>
</div>

<!-- ── EXERCISE LIST ───────────────────────────────────────────────────────── -->
<?php if (!$exercises): ?>
<div class="card"><div class="empty"><div class="empty-icon">📋</div><p>No exercises match these filters.</p></div></div>
<?php endif; ?>

<?php
$day_order = ['Day 1','Day 2','Day 3','Day 4','Day 5','Custom',''];
usort($exercises, function($a,$b) use ($day_order) {
    $ai = array_search($a['day_label'],$day_order); $bi = array_search($b['day_label'],$day_order);
    if ($ai !== $bi) return $ai - $bi;
    if ($a['section_order'] !== $b['section_order']) return $a['section_order'] - $b['section_order'];
    return strcmp($a['name'],$b['name']);
});
$by_day = [];
foreach ($exercises as $e) {
    $dk = $e['day_label'] ?: 'Custom';
    $by_day[$dk][$e['section'] ?: 'General'][] = $e;
}
foreach ($by_day as $day => $sections):
    $dt = $day_titles_map[$day] ?? 'Custom Exercises';
    $pn = $day_pill_n[$day] ?? 0;
    $total_in_day = array_sum(array_map('count',$sections));
?>
<div class="card" style="margin-bottom:1.25rem">

  <!-- Day header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:8px">
    <div style="display:flex;align-items:center;gap:10px">
      <?php if ($pn): ?><span class="day-pill day-pill-<?= $pn ?>"><?= $day ?> · <?= $dt ?></span>
      <?php else: ?><span style="font-size:14px;font-weight:600;color:var(--text)"><?= $dt ?></span><?php endif; ?>
      <span style="font-size:13px;color:var(--muted)"><?= $total_in_day ?> exercises</span>
    </div>
    <a href="exercises.php?add=1&day=<?= urlencode($day) ?>" class="btn btn-ghost btn-sm">+ Add to <?= $day ?></a>
  </div>

  <?php foreach ($sections as $section => $exs): ?>
  <div style="font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);
              padding:6px 0;border-bottom:1px solid var(--border);margin-bottom:2px"><?= htmlspecialchars($section) ?></div>

  <?php foreach ($exs as $e):
    $eid = 'ex-'.$e['id'];
    $fid = 'edit-'.$e['id'];
  ?>
  <div id="<?= $eid ?>" style="padding:12px 0;border-bottom:1px solid var(--border)">

    <!-- ── Summary row ───────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:start">
      <div>
        <!-- Name + badges -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px">
          <span style="font-weight:600;font-size:14px;color:var(--text)"><?= htmlspecialchars($e['name']) ?></span>
          <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($e['muscle_group']) ?></span>
          <?php if ($e['is_left_priority']): ?><span class="badge badge-left">Left</span><?php endif; ?>
          <?php if ($e['is_mobility']): ?><span class="badge badge-mob">Mobility</span><?php endif; ?>
          <?php if ($e['is_core']): ?><span class="badge badge-act">Core</span><?php endif; ?>
          <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
          <?php if ($e['cardio_type']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
          <?php if ($e['cardio_type']==='steady_state'): ?><span class="badge badge-ss">Steady State</span><?php endif; ?>
          <?php if ($e['both_sides']): ?><span class="badge" style="background:var(--bg3);color:var(--muted);border:1px solid var(--border)">Both sides</span><?php endif; ?>
        </div>
        <!-- Coach tip -->
        <?php if ($e['coach_tip']): ?>
        <div style="font-size:12px;color:var(--muted);font-style:italic;line-height:1.5;margin-bottom:5px"><?= htmlspecialchars($e['coach_tip']) ?></div>
        <?php endif; ?>
        <!-- Stats -->
        <div style="display:flex;gap:12px;font-size:12px;color:var(--muted);flex-wrap:wrap">
          <?php if ($e['max_weight']): ?><span>🏆 Best: <strong style="color:var(--text)"><?= number_format($e['max_weight'],1) ?> kg</strong></span><?php endif; ?>
          <?php if ($e['total_sets']): ?><span>📊 <?= $e['total_sets'] ?> sets</span><?php endif; ?>
          <?php if ($e['last_done']): ?><span>📅 <?= date('M j',strtotime($e['last_done'])) ?></span>
          <?php else: ?><span style="color:var(--warn-text)">Never logged</span><?php endif; ?>
          <?php if ($e['youtube_url']): ?>
          <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt" style="font-size:11px;padding:2px 8px">▶ Watch</a>
          <?php else: ?>
          <span style="font-size:11px;color:var(--red-text)">No video</span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Action buttons -->
      <div style="display:flex;gap:5px;align-items:flex-start;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end">
        <button onclick="toggleEdit('<?= $fid ?>')" class="btn btn-ghost btn-sm" type="button">✏️ Edit</button>
        <a href="exercise_detail.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">History</a>
        <form method="post" onsubmit="return confirm('Remove this exercise?')" style="display:inline">
          <input type="hidden" name="action" value="delete_exercise">
          <input type="hidden" name="id" value="<?= $e['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">✕</button>
        </form>
      </div>
    </div>

    <!-- ── Inline edit form (hidden by default) ──────────────────── -->
    <div id="<?= $fid ?>" style="display:none;margin-top:12px;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;padding:1rem">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:1rem">Edit Exercise</div>
      <form method="post">
        <input type="hidden" name="action" value="edit_exercise">
        <input type="hidden" name="id"     value="<?= $e['id'] ?>">

        <!-- Video URL — top and prominent -->
        <div class="form-group">
          <label style="color:var(--accent-text)">🎥 Video URL</label>
          <input type="url" name="youtube_url"
                 value="<?= htmlspecialchars($e['youtube_url']??'') ?>"
                 placeholder="https://www.youtube.com/watch?v=... or search URL">
          <div style="font-size:11px;color:var(--muted);margin-top:4px">
            Paste any URL — direct YouTube link, search results, or any video page
          </div>
        </div>

        <!-- Name + muscle group -->
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Exercise Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($e['name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Muscle Group</label>
            <input type="text" name="muscle_group" value="<?= htmlspecialchars($e['muscle_group']) ?>" list="mg-list" required>
          </div>
        </div>

        <!-- Day + section + cardio -->
        <div class="form-row form-row-3">
          <div class="form-group">
            <label>Training Day</label>
            <select name="day_label">
              <option value="">— Custom —</option>
              <?php foreach (['Day 1'=>'Lower Body','Day 2'=>'Push','Day 3'=>'Pull',
                              'Day 4'=>'Arms & Functional','Day 5'=>'Full Body + Mobility'] as $dl=>$dt): ?>
              <option value="<?= $dl ?>" <?= ($e['day_label']??'')===$dl?'selected':'' ?>><?= $dl ?> — <?= $dt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Section</label>
            <input type="text" name="section" value="<?= htmlspecialchars($e['section']??'') ?>" list="section-list">
          </div>
          <div class="form-group">
            <label>Cardio Type</label>
            <select name="cardio_type">
              <option value="none"         <?= ($e['cardio_type']??'none')==='none'         ?'selected':'' ?>>Not cardio</option>
              <option value="steady_state" <?= ($e['cardio_type']??'')==='steady_state'     ?'selected':'' ?>>Steady State</option>
              <option value="hiit"         <?= ($e['cardio_type']??'')==='hiit'             ?'selected':'' ?>>HIIT</option>
            </select>
          </div>
        </div>

        <!-- Coach tip -->
        <div class="form-group">
          <label>Coach Tip</label>
          <textarea name="coach_tip" rows="2"
            style="width:100%;padding:9px 12px;border:1px solid var(--border2);border-radius:8px;font-size:14px;font-family:inherit;background:var(--bg);color:var(--text);resize:vertical"
            ><?= htmlspecialchars($e['coach_tip']??'') ?></textarea>
        </div>

        <!-- Checkboxes -->
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:1rem">
          <?php foreach ([
            ['is_left_priority','Left priority'],
            ['both_sides','Both sides'],
            ['is_mobility','Mobility'],
            ['is_core','Core'],
            ['is_functional','Functional'],
          ] as [$field,$label]): ?>
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:400;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0;margin:0">
            <input type="checkbox" name="<?= $field ?>" value="1" style="width:auto"
              <?= ($e[$field]??0)?'checked':'' ?>> <?= $label ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
          <button type="button" onclick="toggleEdit('<?= $fid ?>')" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endforeach; ?>

</div>
<?php endforeach; ?>

<!-- Shared datalists -->
<datalist id="mg-list">
  <?php foreach ($muscle_groups as $mg): ?><option value="<?= htmlspecialchars($mg) ?>"><?php endforeach; ?>
</datalist>
<datalist id="section-list">
  <option value="Warm-Up"><option value="Hip Mobility"><option value="Core Block A — Morning">
  <option value="Activation"><option value="Main Work — Machines"><option value="Main Work">
  <option value="Functional"><option value="Finisher"><option value="Cool-Down">
  <option value="Core Block B — Post Session"><option value="Cardio Warm-Up">
</datalist>

<script>
function toggleEdit(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const open = el.style.display === 'block';
  // Close all other open panels first
  document.querySelectorAll('[id^="edit-"]').forEach(p => {
    p.style.display = 'none';
  });
  if (!open) {
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    // Focus the video URL field
    const ytField = el.querySelector('input[name="youtube_url"]');
    if (ytField) setTimeout(() => ytField.focus(), 150);
  }
}
// Auto-open edit panel if hash matches an exercise
window.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash && hash.startsWith('#ex-')) {
    const exId = hash.replace('#ex-', '');
    const panel = document.getElementById('edit-' + exId);
    if (panel) panel.style.display = 'block';
  }
});
</script>

<?php render_foot(); ?>
