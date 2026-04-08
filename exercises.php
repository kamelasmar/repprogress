<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();
$adm = is_admin();

// ── POST: suggest / add exercise ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_exercise') {
    verify_csrf();
    $name    = trim($_POST['name'] ?? '');
    $muscle  = trim($_POST['muscle_group'] ?? '');
    $tip     = trim($_POST['coach_tip'] ?? '');
    $yt      = trim($_POST['youtube_url'] ?? '');
    $left    = isset($_POST['is_left_priority']) ? 1 : 0;
    $mob     = isset($_POST['is_mobility'])      ? 1 : 0;
    $core    = isset($_POST['is_core'])          ? 1 : 0;
    $func    = isset($_POST['is_functional'])    ? 1 : 0;
    $both    = isset($_POST['both_sides'])       ? 1 : 0;
    $cardio  = $_POST['cardio_type'] ?? 'none';

    if ($name && $muscle) {
        // Admin exercises are approved immediately; user suggestions are pending
        $status = $adm ? 'approved' : 'pending';
        $is_suggested = $adm ? 0 : 1;

        $st = $db->prepare("INSERT INTO exercises
            (name, muscle_group, is_left_priority, is_mobility, is_core, is_functional,
             cardio_type, both_sides, youtube_url, coach_tip, created_by, status, is_suggested)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([$name, $muscle, $left, $mob, $core, $func,
                      $cardio, $both, $yt, $tip, $uid, $status, $is_suggested]);

        if ($adm) {
            flash("Exercise \"$name\" added to library.");
        } else {
            flash("Exercise \"$name\" suggested! It's available in your plans now and awaiting admin approval for public visibility.");
        }
    } else {
        flash('Name and muscle group are required.', 'error');
    }
    header("Location: exercises.php");
    exit;
}

// ── POST: delete exercise (admin only) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_exercise') {
    verify_csrf();
    if (!$adm) { flash('Only admins can delete exercises.', 'error'); header("Location: exercises.php"); exit; }
    $db->prepare("DELETE FROM exercises WHERE id=?")->execute([$_POST['id']]);
    flash('Exercise removed.');
    header("Location: exercises.php");
    exit;
}

// ── POST: edit exercise (admin only) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_exercise') {
    verify_csrf();
    if (!$adm) { flash('Only admins can edit exercises.', 'error'); header("Location: exercises.php"); exit; }
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $muscle  = trim($_POST['muscle_group'] ?? '');
    $tip     = trim($_POST['coach_tip'] ?? '');
    $yt      = trim($_POST['youtube_url'] ?? '');
    $left    = isset($_POST['is_left_priority']) ? 1 : 0;
    $mob     = isset($_POST['is_mobility'])      ? 1 : 0;
    $core    = isset($_POST['is_core'])          ? 1 : 0;
    $func    = isset($_POST['is_functional'])    ? 1 : 0;
    $both    = isset($_POST['both_sides'])       ? 1 : 0;
    $cardio  = $_POST['cardio_type'] ?? 'none';
    if ($id && $name && $muscle) {
        $db->prepare("UPDATE exercises SET
            name=?, muscle_group=?, is_left_priority=?, is_mobility=?, is_core=?, is_functional=?,
            cardio_type=?, both_sides=?, youtube_url=?, coach_tip=?
            WHERE id=?")
           ->execute([$name, $muscle, $left, $mob, $core, $func, $cardio, $both, $yt, $tip, $id]);
        flash("\"$name\" updated.");
    }
    header("Location: exercises.php#ex-".$id);
    exit;
}

// ── POST: approve exercise (admin only) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_exercise') {
    verify_csrf();
    if (!$adm) { flash('Only admins can approve exercises.', 'error'); header("Location: exercises.php"); exit; }
    $db->prepare("UPDATE exercises SET status='approved' WHERE id=?")->execute([$_POST['id']]);
    flash('Exercise approved and now visible to all users.');
    header("Location: exercises.php?tab=pending");
    exit;
}

// ── POST: reject exercise (admin only) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_exercise') {
    verify_csrf();
    if (!$adm) { flash('Only admins can reject exercises.', 'error'); header("Location: exercises.php"); exit; }
    $db->prepare("DELETE FROM exercises WHERE id=? AND status='pending'")->execute([$_POST['id']]);
    flash('Suggestion rejected and removed.');
    header("Location: exercises.php?tab=pending");
    exit;
}

// ── Filters ─────────────────────────────────────────────────────────────────
$filter_mg   = $_GET['mg']   ?? '';
$filter_type = $_GET['type'] ?? '';
$tab         = $_GET['tab']  ?? '';

$where  = "WHERE (e.status='approved' OR e.created_by = ?)";
$params = [$uid];
if ($filter_mg)                          { $where .= ' AND e.muscle_group=?'; $params[] = $filter_mg; }
if ($filter_type === 'core')             { $where .= ' AND e.is_core=1'; }
elseif ($filter_type === 'mobility')     { $where .= ' AND e.is_mobility=1'; }
elseif ($filter_type === 'functional')   { $where .= ' AND e.is_functional=1'; }
elseif ($filter_type === 'left')         { $where .= ' AND e.is_left_priority=1'; }
elseif ($filter_type === 'cardio')       { $where .= " AND e.cardio_type!='none'"; }

$stmt = $db->prepare("
  SELECT e.*,
    COUNT(DISTINCT CASE WHEN sl.user_id = ? THEN sl.id END) AS total_sets,
    MAX(CASE WHEN sl.user_id = ? THEN sl.weight_kg END) AS max_weight,
    MAX(CASE WHEN ss.user_id = ? THEN ss.session_date END) AS last_done
  FROM exercises e
  LEFT JOIN sets_log sl ON sl.exercise_id=e.id
  LEFT JOIN sessions ss ON sl.session_id=ss.id
  $where
  GROUP BY e.id
  ORDER BY e.muscle_group, e.name
");
$stmt->execute([$uid, $uid, $uid, ...$params]);
$exercises = $stmt->fetchAll();

$muscle_groups = ['Chest','Back','Shoulders','Biceps','Triceps','Core','Quads','Hamstrings','Glutes','Calves','Hips','Full Body','Cardio','Mobility'];
$cardio_label = ['steady_state'=>'Steady State','hiit'=>'HIIT','none'=>''];

// Pending suggestions (admin view)
$pending = [];
if ($adm) {
    $pending = $db->query("
      SELECT e.*, u.email AS suggested_by_email
      FROM exercises e
      LEFT JOIN users u ON e.created_by = u.id
      WHERE e.status='pending'
      ORDER BY e.created_at DESC
    ")->fetchAll();
}

$show_add = isset($_GET['add']);

// Group by muscle group
$by_group = [];
foreach ($exercises as $e) {
    $by_group[$e['muscle_group']][] = $e;
}

render_head('Exercise Library — Browse & Add Exercises','exercises', false, 'Browse exercises by muscle group. View coach tips, watch videos, and track your history.');
?>

<div class="page-header">
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <div class="page-title">Exercise Library</div>
      <div class="page-sub"><strong class="text-accent-text"><?= count($exercises) ?></strong> exercises available</div>
    </div>
    <a href="exercises.php?add=1" class="btn btn-primary">+ <?= $adm ? 'Add Exercise' : 'Suggest Exercise' ?></a>
  </div>
</div>

<?php if ($adm && count($pending) > 0): ?>
<a href="exercises.php?tab=pending" class="card block mb-5 border-warn no-underline">
  <div class="flex items-center gap-2">
    <span class="badge badge-pending">Pending</span>
    <span class="text-sm font-semibold"><?= count($pending) ?> exercise suggestion<?= count($pending)>1?'s':'' ?> awaiting review</span>
  </div>
</a>
<?php endif; ?>

<?php if ($tab === 'pending' && $adm): ?>
<!-- ── PENDING SUGGESTIONS (Admin) ──────────────────────────────────────────── -->
<div style="margin-bottom:1rem">
  <a href="exercises.php" class="btn btn-ghost btn-sm">&larr; Back to Library</a>
</div>

<?php if (!$pending): ?>
<div class="card"><div class="empty"><p>No pending suggestions.</p></div></div>
<?php else: ?>
<?php foreach ($pending as $p): ?>
<div class="card" style="margin-bottom:1rem;border-color:var(--warn)">
  <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap">
    <div>
      <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px">
        <span style="font-weight:600;font-size:15px"><?= htmlspecialchars($p['name']) ?></span>
        <span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($p['muscle_group']) ?></span>
        <span class="badge badge-pending">Pending</span>
        <?php if ($p['is_left_priority']): ?><span class="badge badge-left">Left</span><?php endif; ?>
        <?php if ($p['is_mobility']): ?><span class="badge badge-mob">Mobility</span><?php endif; ?>
        <?php if ($p['is_core']): ?><span class="badge badge-act">Core</span><?php endif; ?>
        <?php if ($p['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
      </div>
      <?php if ($p['coach_tip']): ?>
      <div style="font-size:12px;color:var(--muted);font-style:italic;margin-bottom:4px"><?= htmlspecialchars($p['coach_tip']) ?></div>
      <?php endif; ?>
      <div style="font-size:12px;color:var(--muted)">
        Suggested by <strong><?= htmlspecialchars($p['suggested_by_email'] ?? 'Unknown') ?></strong>
        <?php if ($p['created_at']): ?> on <?= date('M j, Y', strtotime($p['created_at'])) ?><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0">
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve_exercise">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
      </form>
      <form method="post" onsubmit="return confirm('Reject and delete this suggestion?')" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_exercise">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>

<!-- ── ADD / SUGGEST EXERCISE PANEL ──────────────────────────────────────────── -->
<?php if ($show_add): ?>
<div class="card" style="margin-bottom:1.25rem;border:2px solid var(--accent)">
  <div class="card-title" style="color:var(--accent)"><?= $adm ? 'Add New Exercise' : 'Suggest New Exercise' ?></div>
  <?php if (!$adm): ?>
  <div class="info-box" style="margin-bottom:1rem">
    Your suggestion will be visible to you immediately and sent to an admin for approval.
    Once approved, it will appear in the public library for all users.
  </div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_exercise">

    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Exercise Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="name" placeholder="e.g. Cable Hip Abduction" required autofocus>
      </div>
      <div class="form-group">
        <label>Muscle Group <span style="color:var(--red)">*</span></label>
        <select name="muscle_group" required>
          <option value="">— select —</option>
          <?php foreach ($muscle_groups as $mg): ?>
          <option value="<?= $mg ?>"><?= $mg ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Cardio Type</label>
        <select name="cardio_type">
          <option value="none">Not cardio</option>
          <option value="steady_state">Steady State</option>
          <option value="hiit">HIIT</option>
        </select>
      </div>
      <div class="form-group">
        <label>YouTube / Video Link</label>
        <input type="url" name="youtube_url" placeholder="https://www.youtube.com/results?search_query=...">
      </div>
    </div>

    <div class="form-group">
      <label>Coach Tip</label>
      <textarea name="coach_tip" rows="2" style="resize:vertical"
        placeholder="Key form cue or note..."></textarea>
    </div>

    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:1rem">
      <?php foreach ([
        ['is_mobility','Mobility'],
        ['is_core','Core'],
        ['is_functional','Functional'],
      ] as [$field,$label]): ?>
      <label class="flex items-center gap-2 text-sm font-normal text-[var(--text)] cursor-pointer" style="text-transform:none;letter-spacing:0;margin:0">
        <input type="checkbox" name="<?= $field ?>" value="1" style="width:auto;-webkit-appearance:checkbox;appearance:checkbox"> <?= $label ?>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="flex gap-2.5">
      <button type="submit" class="btn btn-primary"><?= $adm ? 'Save Exercise' : 'Submit Suggestion' ?></button>
      <a href="exercises.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ── FILTERS: muscle group pills + search ──────────────────────────────────── -->
<div x-data="{ search: '<?= htmlspecialchars($filter_mg) ? '' : '' ?>' }">
<div class="flex gap-1.5 flex-wrap mb-3">
  <a href="exercises.php" class="btn btn-sm <?= !$filter_mg && !$filter_type ? 'btn-primary' : 'btn-ghost' ?>">All</a>
  <?php foreach ($muscle_groups as $mg): ?>
  <a href="exercises.php?mg=<?= urlencode($mg) ?>" class="btn btn-sm <?= $filter_mg === $mg ? 'btn-primary' : 'btn-ghost' ?>"><?= $mg ?></a>
  <?php endforeach; ?>
</div>

<!-- Type sub-filters -->
<div class="flex gap-1.5 flex-wrap mb-3">
  <span class="text-[11px] text-muted font-semibold uppercase leading-relaxed py-1">Type:</span>
  <?php foreach ([''=>'All','core'=>'Core','mobility'=>'Mobility','functional'=>'Functional','cardio'=>'Cardio'] as $val=>$lbl): ?>
  <a href="exercises.php?type=<?= $val ?><?= $filter_mg ? '&mg='.urlencode($filter_mg) : '' ?>" class="btn btn-sm <?= $filter_type === $val && ($val || !$filter_type) ? 'btn-primary' : 'btn-ghost' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>
</div>

<!-- ── EXERCISE LIST ─────────────────────────────────────────────────────────── -->
<?php if (!$exercises): ?>
<div class="card"><div class="empty"><div class="empty-icon">📋</div><p>No exercises match these filters.</p></div></div>
<?php endif; ?>

<?php foreach ($by_group as $group => $exs): ?>
<div class="mb-2">
  <div class="section-hdr flex items-center justify-between">
    <span><?= htmlspecialchars($group) ?> <span class="font-normal text-muted">(<?= count($exs) ?>)</span></span>
  </div>

  <?php foreach ($exs as $e):
    $eid = 'ex-'.$e['id'];
    $fid = 'edit-'.$e['id'];
    $is_own_pending = ($e['status'] === 'pending' && $e['created_by'] == $uid);
    $is_suggested_approved = ($e['is_suggested'] && $e['status'] === 'approved');
  ?>
  <div id="<?= $eid ?>" class="py-2.5 border-b border-border-app">

    <!-- Summary row -->
    <div class="grid grid-cols-[1fr_auto] gap-3 items-start">
      <div>
        <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
          <a href="exercise_detail.php?id=<?= $e['id'] ?>" class="font-semibold text-sm text-[var(--text)] hover:text-accent-text"><?= htmlspecialchars($e['name']) ?></a>
          <?php if ($is_own_pending): ?><span class="badge badge-pending">Pending</span><?php endif; ?>
          <?php if ($adm && $is_suggested_approved): ?><span class="badge badge-suggested">Suggested</span><?php endif; ?>
          <?php if ($e['is_mobility']): ?><span class="badge badge-mob">Mobility</span><?php endif; ?>
          <?php if ($e['is_core']): ?><span class="badge badge-act">Core</span><?php endif; ?>
          <?php if ($e['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
          <?php if ($e['cardio_type']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
          <?php if ($e['cardio_type']==='steady_state'): ?><span class="badge badge-ss">Steady State</span><?php endif; ?>
        </div>
        <?php if ($e['coach_tip']): ?>
        <div class="coach-tip mb-1"><?= htmlspecialchars($e['coach_tip']) ?></div>
        <?php endif; ?>
        <div class="flex gap-3 text-xs text-muted flex-wrap">
          <?php if ($e['max_weight']): ?><span>Best: <strong class="text-[var(--text)]"><?= number_format($e['max_weight'],1) ?> kg</strong></span><?php endif; ?>
          <?php if ($e['total_sets']): ?><span><?= $e['total_sets'] ?> sets</span><?php endif; ?>
          <?php if ($e['last_done']): ?><span>Last: <?= date('M j',strtotime($e['last_done'])) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="flex gap-1 items-center flex-shrink-0">
        <?php if ($e['youtube_url']): ?>
        <a href="<?= htmlspecialchars($e['youtube_url']) ?>" target="_blank" class="btn-yt" style="font-size:11px;padding:2px 7px">▶</a>
        <?php endif; ?>
        <?php if ($adm): ?>
        <button onclick="toggleEdit('<?= $fid ?>')" class="btn btn-ghost btn-sm" type="button">Edit</button>
        <form method="post" class="inline" x-data x-on:submit="if (!confirm('Remove this exercise?')) $event.preventDefault()">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_exercise">
          <input type="hidden" name="id" value="<?= $e['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">×</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($adm): ?>
    <!-- Inline edit form (admin only, hidden by default) -->
    <div id="<?= $fid ?>" style="display:none;margin-top:12px;background:var(--bg3);border:1px solid var(--border2);border-radius:10px;padding:1rem">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:1rem">Edit Exercise</div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_exercise">
        <input type="hidden" name="id"     value="<?= $e['id'] ?>">

        <div class="form-group">
          <label style="color:var(--accent-text)">Video URL</label>
          <input type="url" name="youtube_url"
                 value="<?= htmlspecialchars($e['youtube_url']??'') ?>"
                 placeholder="https://www.youtube.com/watch?v=...">
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Exercise Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($e['name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Muscle Group</label>
            <select name="muscle_group" required>
              <?php foreach ($muscle_groups as $mg): ?>
              <option value="<?= $mg ?>" <?= $e['muscle_group'] === $mg ? 'selected' : '' ?>><?= $mg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Cardio Type</label>
            <select name="cardio_type">
              <option value="none"         <?= ($e['cardio_type']??'none')==='none'     ?'selected':'' ?>>Not cardio</option>
              <option value="steady_state" <?= ($e['cardio_type']??'')==='steady_state' ?'selected':'' ?>>Steady State</option>
              <option value="hiit"         <?= ($e['cardio_type']??'')==='hiit'         ?'selected':'' ?>>HIIT</option>
            </select>
          </div>
          <div class="form-group">
            <label>Coach Tip</label>
            <textarea name="coach_tip" rows="2" style="resize:vertical"><?= htmlspecialchars($e['coach_tip']??'') ?></textarea>
          </div>
        </div>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:1rem">
          <?php foreach ([
            ['is_mobility','Mobility'],
            ['is_core','Core'],
            ['is_functional','Functional'],
          ] as [$field,$label]): ?>
          <label class="flex items-center gap-2 text-[13px] font-normal text-[var(--text)] cursor-pointer" style="text-transform:none;letter-spacing:0;margin:0">
            <input type="checkbox" name="<?= $field ?>" value="1" style="width:auto;-webkit-appearance:checkbox;appearance:checkbox"
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
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

</div>
<?php endforeach; ?>

<?php endif; /* end tab check */ ?>

<!-- Shared datalists -->
<datalist id="mg-list">
  <?php foreach ($muscle_groups as $mg): ?><option value="<?= htmlspecialchars($mg) ?>"><?php endforeach; ?>
</datalist>

<script>
function toggleEdit(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const open = el.style.display === 'block';
  document.querySelectorAll('[id^="edit-"]').forEach(p => { p.style.display = 'none'; });
  if (!open) {
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    const ytField = el.querySelector('input[name="youtube_url"]');
    if (ytField) setTimeout(() => ytField.focus(), 150);
  }
}
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
