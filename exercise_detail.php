<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: exercises.php"); exit; }
$ex = $db->prepare("SELECT * FROM exercises WHERE id=?");
$ex->execute([$id]); $ex = $ex->fetch();
if (!$ex) { header("Location: exercises.php"); exit; }

// Filter by plan
$filter_plan = $_GET['plan_id'] ?? '';
$plan_filter_sql = $filter_plan ? "AND ss.plan_id=?" : "";
$params = [$id, $uid];
if ($filter_plan) $params[] = $filter_plan;

$sets = $db->prepare("
  SELECT sl.*, ss.session_date, ss.title AS session_title, ss.id AS session_id, ss.plan_id, p.name AS plan_name
  FROM sets_log sl JOIN sessions ss ON sl.session_id=ss.id LEFT JOIN plans p ON ss.plan_id=p.id
  WHERE sl.exercise_id=? AND ss.user_id=? $plan_filter_sql ORDER BY ss.session_date ASC, sl.id ASC
");
$sets->execute($params); $sets = $sets->fetchAll();

$st_plans = $db->prepare("SELECT DISTINCT p.id, p.name FROM plans p JOIN sessions s ON s.plan_id=p.id JOIN sets_log sl ON sl.session_id=s.id WHERE sl.exercise_id=? AND s.user_id=? ORDER BY p.created_at");
$st_plans->execute([$id, $uid]);
$all_plans = $st_plans->fetchAll();

// Progression data
$prog = $left_prog = $right_prog = [];
foreach ($sets as $s) {
    if (!$s['weight_kg']) continue;
    $d = $s['session_date'];
    $prog[$d] = max($prog[$d] ?? 0, (float)$s['weight_kg']);
    if ($s['side']==='left') $left_prog[$d] = max($left_prog[$d]??0,(float)$s['weight_kg']);
    elseif ($s['side']==='right') $right_prog[$d] = max($right_prog[$d]??0,(float)$s['weight_kg']);
}

render_head(htmlspecialchars($ex['name']).' — Exercise Detail','exercises', false, 'View your progress, set history, and performance charts for '.htmlspecialchars($ex['name']).'.');
?>

<div class="mb-4"><a href="exercises.php" class="text-muted text-sm">← Back to library</a></div>

<div class="page-header">
  <div class="page-title"><?= htmlspecialchars($ex['name']) ?></div>
  <div class="flex items-center gap-2.5 flex-wrap mt-1.5">
    <span class="text-sm text-muted"><?= $ex['muscle_group'] ?></span>
    <?php if ($ex['is_core']): ?><span class="badge badge-core">Core</span><?php endif; ?>
    <?php if ($ex['is_mobility']): ?><span class="badge badge-mob">Mobility</span><?php endif; ?>
    <?php if ($ex['is_functional']): ?><span class="badge badge-func">Functional</span><?php endif; ?>
    <?php if ($ex['cardio_type']==='hiit'): ?><span class="badge badge-hiit">HIIT</span><?php endif; ?>
    <?php if ($ex['youtube_url']): ?><a href="<?= htmlspecialchars($ex['youtube_url']) ?>" target="_blank" class="btn-yt">▶ Watch on YouTube</a><?php endif; ?>
  </div>
</div>

<?php if ($ex['coach_tip']): ?>
<div class="py-3.5 px-4.5 bg-accent-dim rounded-[10px] text-sm text-accent-text leading-relaxed mb-5 border border-accent">
  <strong>Coach note:</strong> <?= htmlspecialchars($ex['coach_tip']) ?>
</div>
<?php endif; ?>

<!-- Plan filter -->
<?php if (count($all_plans) > 1): ?>
<div class="flex gap-2 flex-wrap mb-5 items-center">
  <span class="text-[13px] text-muted">View by plan:</span>
  <a href="exercise_detail.php?id=<?= $id ?>" class="btn btn-sm <?= !$filter_plan?'btn-primary':'btn-ghost' ?>">All Plans</a>
  <?php foreach ($all_plans as $p): ?>
  <a href="exercise_detail.php?id=<?= $id ?>&plan_id=<?= $p['id'] ?>" class="btn btn-sm <?= $filter_plan==$p['id']?'btn-primary':'btn-ghost' ?>">
    <?= htmlspecialchars($p['name']) ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-3 mb-5">
  <div class="metric"><div class="metric-label">Total Sets</div><div class="metric-value"><?= count($sets) ?></div>
    <div class="metric-sub"><?= $filter_plan ? 'this plan' : 'all plans' ?></div>
  </div>
  <div class="metric"><div class="metric-label">Max Weight</div>
    <div class="metric-value"><?php $mw=$prog?max($prog):null; echo $mw?number_format($mw,1).' kg':'—'; ?></div>
    <?php if ($left_prog && $right_prog): ?>
    <div class="metric-sub">L: <?= number_format(max($left_prog),1) ?> · R: <?= number_format(max($right_prog),1) ?></div>
    <?php endif; ?>
  </div>
  <div class="metric"><div class="metric-label">Last Done</div>
    <div class="metric-value text-lg"><?= $sets?date('M j',strtotime(end($sets)['session_date'])):'—' ?></div>
  </div>
</div>

<?php if (count($prog) >= 2): ?>
<div class="card mb-5">
  <div class="card-title">Weight Progression<?= (count($left_prog)>=1||count($right_prog)>=1)?' — Left vs Right comparison':'' ?></div>
  <canvas id="progChart" height="160"></canvas>
</div>
<?php endif; ?>

<?php if ($sets): ?>
<div class="card">
  <div class="card-title">All Sets Logged</div>
  <table>
    <thead><tr><th>Date</th><th>Session</th><th>Plan</th><th>Set</th><th>Side</th><th>Reps</th><th>Weight</th><th>Duration</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($sets) as $s): ?>
    <tr>
      <td class="text-[13px] whitespace-nowrap"><?= date('M j, Y',strtotime($s['session_date'])) ?></td>
      <td class="text-[13px]"><a href="log.php?session_id=<?= $s['session_id'] ?>"><?= htmlspecialchars($s['session_title']) ?></a></td>
      <td class="text-[11px] text-muted"><?= $s['plan_name']?htmlspecialchars($s['plan_name']):'—' ?></td>
      <td><?= $s['set_number'] ?></td>
      <td><span class="text-[11px] px-1.5 py-0.5 rounded bg-bg text-muted"><?= $s['side'] ?></span></td>
      <td><?= $s['reps']?:'—' ?></td>
      <td><strong><?= $s['weight_kg']?number_format($s['weight_kg'],1).' kg':'—' ?></strong></td>
      <td><?= $s['duration_sec']?$s['duration_sec'].'s':'—' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="card"><div class="empty"><div class="empty-icon">📈</div><p>No sets logged yet for this exercise.</p><a href="log.php?new=1" class="btn btn-primary btn-sm">+ Log a Workout</a></div></div>
<?php endif; ?>

<?php if (count($prog) >= 2): ?>
<script>
const allDates=<?= json_encode(array_keys($prog)) ?>;
const allVals=<?= json_encode(array_values($prog)) ?>;
const leftProg=<?= json_encode($left_prog) ?>;
const rightProg=<?= json_encode($right_prog) ?>;
let datasets=[];
if(Object.keys(leftProg).length>0||Object.keys(rightProg).length>0){
  if(Object.keys(leftProg).length) datasets.push({label:'Left',data:allDates.map(d=>leftProg[d]||null),borderColor:'#5b9fd6',backgroundColor:'rgba(91,159,214,0.12)',fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#5b9fd6',spanGaps:true});
  if(Object.keys(rightProg).length) datasets.push({label:'Right',data:allDates.map(d=>rightProg[d]||null),borderColor:'#4dd8a7',backgroundColor:'rgba(29,158,117,0.08)',fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#4dd8a7',spanGaps:true});
} else {
  datasets.push({label:'Weight (kg)',data:allVals,borderColor:'#4dd8a7',backgroundColor:'rgba(29,158,117,0.15)',fill:true,tension:0.4,pointRadius:5,pointBackgroundColor:'#4dd8a7'});
}
new Chart(document.getElementById('progChart'),{type:'line',data:{labels:allDates,datasets},
options:{responsive:true,plugins:{legend:{display:datasets.length>1,position:'top',labels:{boxWidth:12,font:{size:12}}}},
scales:{y:{grid:{color:'rgba(255,255,255,0.06)'}},x:{grid:{display:false}}}}});
</script>
<?php endif; ?>

<?php render_foot(); ?>
