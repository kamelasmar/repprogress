<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();
$cu  = current_user();

$ap = active_plan();

$st = $db->prepare("SELECT logged_date,weight_kg FROM weight_log WHERE user_id=? ORDER BY logged_date DESC LIMIT 8");
$st->execute([$uid]);
$weights = array_reverse($st->fetchAll());
$latest_weight = $weights ? (float)end($weights)['weight_kg'] : null;
$first_weight  = $weights ? (float)$weights[0]['weight_kg'] : null;
$weight_delta  = ($latest_weight && $first_weight) ? round($latest_weight - $first_weight, 1) : null;

$st = $db->prepare("SELECT COUNT(*) FROM sessions WHERE MONTH(session_date)=MONTH(CURDATE()) AND YEAR(session_date)=YEAR(CURDATE()) AND user_id=?");
$st->execute([$uid]);
$sessions_month = $st->fetchColumn();

$st = $db->prepare("SELECT COUNT(*) FROM sessions WHERE session_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND user_id=?");
$st->execute([$uid]);
$sessions_week = $st->fetchColumn();

$st = $db->prepare("SELECT COUNT(*) FROM sets_log WHERE user_id=?");
$st->execute([$uid]);
$total_sets = $st->fetchColumn();

$streak=0; $check=new DateTime();
while(true){
    $d=$check->format('Y-m-d');
    $h=$db->prepare("SELECT COUNT(*) FROM sessions WHERE session_date=? AND user_id=?"); $h->execute([$d, $uid]);
    if(!$h->fetchColumn()) break; $streak++; $check->modify('-1 day');
}

$st = $db->prepare("SELECT e.muscle_group, ROUND(SUM(COALESCE(s.weight_kg,0)*COALESCE(s.reps,1)),0) AS volume
  FROM sets_log s JOIN exercises e ON s.exercise_id=e.id JOIN sessions ss ON s.session_id=ss.id
  WHERE ss.session_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) AND ss.user_id=?
  GROUP BY e.muscle_group ORDER BY volume DESC LIMIT 7");
$st->execute([$uid]);
$muscle_vol = $st->fetchAll();

$dow_map=['Monday'=>0,'Tuesday'=>0,'Wednesday'=>0,'Thursday'=>0,'Friday'=>0,'Saturday'=>0,'Sunday'=>0];
$st = $db->prepare("SELECT DAYNAME(session_date) AS dow,COUNT(*) AS cnt FROM sessions WHERE user_id=? GROUP BY DAYOFWEEK(session_date),DAYNAME(session_date)");
$st->execute([$uid]);
foreach($st->fetchAll() as $r) $dow_map[$r['dow']]=(int)$r['cnt'];

$st = $db->prepare("SELECT s.id,s.session_date,s.day_label,s.title,COUNT(sl.id) AS set_count,p.name AS plan_name
  FROM sessions s LEFT JOIN sets_log sl ON sl.session_id=s.id LEFT JOIN plans p ON s.plan_id=p.id
  WHERE s.user_id=?
  GROUP BY s.id ORDER BY s.session_date DESC LIMIT 6");
$st->execute([$uid]);
$recent = $st->fetchAll();

$wLabels=array_column($weights,'logged_date'); $wData=array_column($weights,'weight_kg');
$vLabels=array_column($muscle_vol,'muscle_group'); $vData=array_column($muscle_vol,'volume');
$day_colors=['Day 1'=>'#639922','Day 2'=>'#378ADD','Day 3'=>'#D4537E','Day 4'=>'#BA7517','Day 5'=>'#1D9E75'];

// Plan progress
$plan_week=1; $plan_pct=0;
if($ap && $ap['start_date']){
    $plan_week=max(1,(int)ceil((time()-strtotime($ap['start_date']))/604800));
    $plan_pct=min(100,round($plan_week/$ap['weeks_duration']*100));
}

// Greeting name from email
$greeting_name = explode('@', $cu['email'] ?? '')[0];

render_head('Dashboard','index');
?>

<div class="page-header">
  <div class="page-title">Good <?= (date('G')<12?'morning':(date('G')<18?'afternoon':'evening')) ?>, <?= htmlspecialchars(ucfirst($greeting_name)) ?></div>
  <div class="page-sub"><?= date('l, F j, Y') ?></div>
</div>

<!-- Active plan banner -->
<?php if ($ap): ?>
<div class="card" style="border:1px solid var(--accent);margin-bottom:1.25rem">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent-text);margin-bottom:2px">Active Plan</div>
      <div style="font-size:16px;font-weight:700;color:var(--text)"><?= htmlspecialchars($ap['name']) ?></div>
      <div style="font-size:13px;color:var(--muted)">Week <?= $plan_week ?> of <?= $ap['weeks_duration'] ?> &middot; <?= max(0,$ap['weeks_duration']-$plan_week) ?> weeks remaining</div>
    </div>
    <div style="flex:1;max-width:240px">
      <div style="height:8px;background:var(--bg3);border-radius:4px;overflow:hidden">
        <div style="height:100%;width:<?= $plan_pct ?>%;background:var(--accent);border-radius:4px"></div>
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $plan_pct ?>% complete</div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="plan_manager.php" class="btn btn-ghost btn-sm">Change Plan</a>
      <a href="log.php?new=1" class="btn btn-primary btn-sm">+ Log Session</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card" style="border:1px solid var(--warn);margin-bottom:1.25rem">
  <strong style="color:var(--warn-text)">No active plan.</strong> <a href="plan_manager.php">Activate a plan</a> to start logging sessions.
</div>
<?php endif; ?>

<div class="grid-4">
  <div class="metric"><div class="metric-label">Current Weight</div>
    <div class="metric-value"><?= $latest_weight?number_format($latest_weight,1).' kg':'—' ?></div>
    <?php if($weight_delta!==null): ?><div class="metric-sub <?= $weight_delta<=0?'metric-up':'metric-down' ?>"><?= $weight_delta>0?'+':'' ?><?= $weight_delta ?> kg since start</div><?php endif; ?>
  </div>
  <div class="metric"><div class="metric-label">Sessions This Month</div>
    <div class="metric-value"><?= $sessions_month ?></div>
    <div class="metric-sub"><?= $sessions_week ?> this week</div>
  </div>
  <div class="metric"><div class="metric-label">Total Sets Logged</div>
    <div class="metric-value"><?= number_format($total_sets) ?></div>
    <div class="metric-sub">all-time</div>
  </div>
  <div class="metric"><div class="metric-label">Streak</div>
    <div class="metric-value"><?= $streak ?></div>
    <div class="metric-sub">consecutive days</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-title">Weight Trend (kg)</div>
    <?php if(count($weights)>=2): ?><canvas id="wChart" height="160"></canvas>
    <?php else: ?><div class="empty"><p>Log weight to see trend.</p><a href="weight.php" class="btn btn-primary btn-sm">+ Log Weight</a></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-title">Volume by Muscle Group — last 30 days</div>
    <?php if($muscle_vol): ?><canvas id="vChart" height="160"></canvas>
    <?php else: ?><div class="empty"><p>Log workouts to see volume.</p><a href="log.php?new=1" class="btn btn-primary btn-sm">+ Log Workout</a></div><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-title">Weekly Training Frequency</div>
    <canvas id="fChart" height="150"></canvas>
  </div>
  <div class="card">
    <div class="card-title">Recent Sessions</div>
    <?php if($recent): ?>
    <table>
      <thead><tr><th>Date</th><th>Session</th><th>Plan</th><th>Sets</th></tr></thead>
      <tbody>
      <?php foreach($recent as $r): $col=$day_colors[$r['day_label']]??'#888'; ?>
      <tr>
        <td style="font-size:13px"><a href="log.php?session_id=<?= $r['id'] ?>"><?= date('M j',strtotime($r['session_date'])) ?></a></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="width:7px;height:7px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></span>
            <span style="font-size:13px"><?= htmlspecialchars($r['title']) ?></span>
          </div>
        </td>
        <td style="font-size:11px;color:var(--muted)"><?= $r['plan_name']?htmlspecialchars($r['plan_name']):'—' ?></td>
        <td><?= $r['set_count'] ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><p>No sessions logged yet.</p><a href="log.php?new=1" class="btn btn-primary btn-sm">+ Log First Workout</a></div>
    <?php endif; ?>
  </div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap">
  <a href="log.php?new=1" class="btn btn-primary">+ Log Workout</a>
  <a href="weight.php" class="btn btn-ghost">+ Log Weight</a>
  <a href="plan_manager.php" class="btn btn-ghost">Plans</a>
  <a href="schedule.php" class="btn btn-ghost">Schedule</a>
</div>

<script>
Chart.defaults.font={family:"-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif",size:12};
Chart.defaults.color='#888';
Chart.defaults.borderColor='rgba(255,255,255,0.07)';
<?php if(count($weights)>=2): ?>
new Chart(document.getElementById('wChart'),{type:'line',data:{labels:<?= json_encode($wLabels) ?>,datasets:[{data:<?= json_encode(array_map('floatval',$wData)) ?>,borderColor:'#5b9fd6',backgroundColor:'rgba(91,159,214,0.12)',fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#5b9fd6'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{grid:{color:'rgba(255,255,255,0.06)'}},x:{grid:{display:false}}}}});
<?php endif; if($muscle_vol): ?>
new Chart(document.getElementById('vChart'),{type:'bar',data:{labels:<?= json_encode($vLabels) ?>,datasets:[{data:<?= json_encode(array_map('intval',$vData)) ?>,backgroundColor:'#1D9E75',borderRadius:5}]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.06)'}},y:{grid:{display:false}}}}});
<?php endif; ?>
new Chart(document.getElementById('fChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($dow_map)) ?>,datasets:[{data:<?= json_encode(array_values($dow_map)) ?>,backgroundColor:'rgba(29,158,117,0.2)',borderColor:'#1D9E75',borderWidth:1.5,borderRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(255,255,255,0.06)'}},x:{grid:{display:false}}}}});
</script>

<?php render_foot(); ?>
