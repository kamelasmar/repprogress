<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = active_user_id();
$ap  = active_plan();
$colors = day_colors();

// Load active plan's days
$plan_days = [];
$days_by_weekday = [];
if ($ap) {
    $st = $db->prepare("SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_order");
    $st->execute([$ap['id']]);
    $plan_days = $st->fetchAll();
    foreach ($plan_days as $pd) {
        if ($pd['week_day']) {
            $days_by_weekday[$pd['week_day']] = $pd;
        }
    }
}

// Last 4 weeks of sessions for the calendar
$st = $db->prepare("
    SELECT session_date, day_label FROM sessions
    WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY) AND user_id = ?
    ORDER BY session_date ASC
");
$st->execute([$uid]);
$recent = $st->fetchAll();
$logged = [];
foreach ($recent as $r) $logged[$r['session_date']] = $r['day_label'];

// 4-week calendar grid starting from last Monday
$anchor = new DateTime();
$iso    = (int)$anchor->format('N');
$toMon  = $iso - 1;
$anchor->modify("-{$toMon} days")->modify('-3 weeks');

$weeks = [];
for ($w = 0; $w < 4; $w++) {
    $row = [];
    for ($d = 0; $d < 7; $d++) {
        $dt = clone $anchor;
        $dt->modify('+'.($w * 7 + $d).' days');
        $row[] = $dt->format('Y-m-d');
    }
    $weeks[] = $row;
}

$dow_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

render_head('Schedule', 'schedule');
?>

<div class="page-header">
  <div class="page-title">Training Schedule</div>
  <div class="page-sub">
    <?php if ($ap): ?>
    <?= count($plan_days) ?> training day<?= count($plan_days) !== 1 ? 's' : '' ?> per week
    · <strong><?= htmlspecialchars($ap['name']) ?></strong>
    <?php else: ?>
    <span style="color:var(--warn-text)">No active plan — <a href="plan_manager.php">activate one</a> to see your schedule</span>
    <?php endif; ?>
  </div>
</div>

<!-- ── WEEKLY SPLIT GRID ────────────────────────────────────────────────── -->
<?php if ($ap && $plan_days): ?>
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Weekly Split</div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px" class="week-grid">
    <?php foreach ($dow_labels as $dow):
      $pd = $days_by_weekday[$dow] ?? null;
      $isTrain = $pd !== null;
      $pn = $isTrain ? (int)preg_replace('/\D/', '', $pd['day_label']) : 0;
      $hiit = $isTrain && ($pd['cardio_type'] ?? 'none') === 'hiit';
      $ss   = $isTrain && ($pd['cardio_type'] ?? 'none') === 'steady_state';
    ?>
    <div style="
      background:var(--surface2);
      border:1px solid <?= $isTrain ? 'var(--border2)' : 'var(--border)' ?>;
      border-radius:10px;padding:12px 8px;text-align:center;
      opacity:<?= $isTrain ? '1' : '0.6' ?>
    ">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px"><?= $dow ?></div>
      <?php if ($isTrain): ?>
        <div class="day-pill day-pill-<?= $pn ?>" style="font-size:10px;padding:2px 6px;margin-bottom:5px;display:inline-block"><?= htmlspecialchars($pd['day_label']) ?></div>
        <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:5px;line-height:1.3"><?= htmlspecialchars($pd['day_title']) ?></div>
        <?php if ($hiit || $ss): ?>
        <div style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;display:inline-block;
          background:<?= $hiit?'var(--red-dim)':'var(--left-dim)' ?>;
          color:<?= $hiit?'var(--red-text)':'var(--left-text)' ?>">
          <?= $hiit ? 'HIIT' : 'Zone 2' ?>
        </div>
        <?php endif; ?>
        <?php if ($pd['cardio_description']): ?>
        <div style="font-size:10px;color:var(--muted);margin-top:4px"><?= htmlspecialchars($pd['cardio_description']) ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div style="font-size:20px;margin-bottom:4px">&#128564;</div>
        <div style="font-size:12px;font-weight:700;color:var(--muted2)">Rest</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── DAY-BY-DAY DETAIL ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Training Days</div>
  <?php foreach ($plan_days as $pd):
    $pn = (int)preg_replace('/\D/', '', $pd['day_label']);
    $hiit = ($pd['cardio_type'] ?? 'none') === 'hiit';
    $ss   = ($pd['cardio_type'] ?? 'none') === 'steady_state';
    // Count exercises for this day
    $ex_count = $db->prepare("SELECT COUNT(*) FROM plan_exercises WHERE plan_id=? AND day_label=?");
    $ex_count->execute([$ap['id'], $pd['day_label']]);
    $num_ex = $ex_count->fetchColumn();
  ?>
  <div style="display:grid;grid-template-columns:72px 1fr;gap:16px;padding:16px 0;border-bottom:1px solid var(--border);align-items:start">
    <div style="text-align:center">
      <div class="day-pill day-pill-<?= $pn ?>" style="font-size:11px;margin-bottom:4px"><?= htmlspecialchars($pd['day_label']) ?></div>
      <div style="font-size:12px;color:var(--muted)"><?= $pd['week_day'] ?: 'TBD' ?></div>
    </div>
    <div>
      <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px"><?= htmlspecialchars($pd['day_title']) ?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
        <?php if ($hiit || $ss): ?>
        <span style="font-size:12px;padding:3px 10px;border-radius:5px;font-weight:600;
          background:<?= $hiit?'var(--red-dim)':'var(--left-dim)' ?>;
          color:<?= $hiit?'var(--red-text)':'var(--left-text)' ?>">
          <?= $hiit ? 'HIIT' : 'Steady State' ?><?= $pd['cardio_description'] ? ' — '.htmlspecialchars($pd['cardio_description']) : '' ?>
        </span>
        <?php endif; ?>
        <span style="font-size:12px;padding:3px 10px;border-radius:5px;background:var(--bg3);color:var(--muted)"><?= $num_ex ?> exercises</span>
      </div>
      <a href="plan_builder.php?plan_id=<?= $ap['id'] ?>&day=<?= urlencode($pd['day_label']) ?>" style="font-size:12px">View in plan builder &rarr;</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif (!$ap): ?>
<div class="card" style="margin-bottom:1.25rem">
  <div class="empty">
    <p>Activate a plan to see your weekly schedule here.</p>
    <a href="plan_manager.php" class="btn btn-primary btn-sm">Go to Plans</a>
  </div>
</div>
<?php endif; ?>

<!-- ── 4-WEEK CALENDAR ─────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Last 4 Weeks</div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin-bottom:5px;text-align:center">
    <?php foreach ($dow_labels as $d): ?>
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:3px 0"><?= $d ?></div>
    <?php endforeach; ?>
  </div>
  <?php foreach ($weeks as $week): ?>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin-bottom:5px">
    <?php foreach ($week as $date):
      $isToday  = $date === date('Y-m-d');
      $isFuture = $date > date('Y-m-d');
      $loggedDay = $logged[$date] ?? null;
      $col       = $loggedDay ? ($colors[$loggedDay] ?? '#888') : 'transparent';
      $dow3      = date('D', strtotime($date));
      $isTrainDay = isset($days_by_weekday[$dow3]);
    ?>
    <div style="
      text-align:center;padding:7px 3px;border-radius:7px;
      border:<?= $isToday?'2px solid var(--accent)':'1px solid var(--border)' ?>;
      background:<?= $loggedDay?'var(--surface2)':'transparent' ?>;
      opacity:<?= $isFuture?'.35':'1' ?>
    ">
      <div style="font-size:9px;color:var(--muted2);margin-bottom:2px"><?= date('M j',strtotime($date)) ?></div>
      <?php if ($loggedDay): ?>
        <div style="width:7px;height:7px;border-radius:50%;background:<?= $col ?>;margin:0 auto 2px"></div>
        <div style="font-size:9px;font-weight:700;color:<?= $col ?>"><?= $loggedDay ?></div>
      <?php elseif ($isFuture): ?>
        <div style="font-size:9px;color:var(--border)">&mdash;</div>
      <?php elseif (!$isTrainDay): ?>
        <div style="font-size:12px">&#128564;</div>
      <?php else: ?>
        <div style="font-size:9px;color:var(--red-text);font-weight:600">missed</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($plan_days): ?>
  <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
    <?php foreach ($plan_days as $pd):
      $pn = (int)preg_replace('/\D/', '', $pd['day_label']);
      $c = $colors[$pd['day_label']] ?? '#888';
    ?>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
      <div style="width:7px;height:7px;border-radius:50%;background:<?= $c ?>"></div><?= htmlspecialchars($pd['day_label']) ?>
    </div>
    <?php endforeach; ?>
    <div style="font-size:11px;color:var(--muted)">&#128564; Rest</div>
  </div>
  <?php endif; ?>
</div>

<!-- ── SAMPLE PLAN REFERENCE ───────────────────────────────────────────────── -->
<details style="margin-bottom:1.25rem">
<summary class="card" style="cursor:pointer;display:flex;align-items:center;gap:10px;list-style:none;margin-bottom:0">
  <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted)">Sample Plan — Reference Guide</span>
  <span style="font-size:12px;color:var(--muted2)">(cardio, core protocol, roadmap)</span>
</summary>

<div style="margin-top:1rem">
<!-- Cardio Guide -->
<div class="grid-2" style="margin-bottom:1.25rem">
  <div class="card">
    <div class="card-title" style="color:var(--left-text)">Steady State (Zone 2)</div>
    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:1rem">
      Heart rate 60-70% max. Conversational pace. Builds aerobic base and mitochondrial density.
    </div>
    <?php foreach ([
        ['Rowing machine',       '10 min', 'Warm-up'],
        ['Stationary bike',      '15 min', 'Low resistance'],
        ['Incline treadmill walk','35-40 min', 'Recovery day'],
        ['Swimming / pool walk', '30 min', 'Recovery day'],
    ] as [$name,$spec,$when]): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-top:1px solid var(--border);gap:10px">
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $name ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $when ?></div>
      </div>
      <span style="font-size:11px;font-weight:600;color:var(--left-text);background:var(--left-dim);padding:2px 8px;border-radius:4px;white-space:nowrap"><?= $spec ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title" style="color:var(--red-text)">HIIT</div>
    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:1rem">
      High output 1-2x per week maximum. Short work periods keep cortisol spike controlled.
    </div>
    <?php foreach ([
        ['Ski Erg',        '20s hard / 40s easy x 6-8', 'Push or arms day'],
        ['Rowing 250m',    '250m hard / 90s rest x 5',   'Full body day'],
        ['Battle Ropes',   '30s on / 30s off x 6',       'Functional block'],
    ] as [$name,$spec,$when]): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-top:1px solid var(--border);gap:10px">
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $name ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $when ?></div>
      </div>
      <span style="font-size:11px;font-weight:600;color:var(--red-text);background:var(--red-dim);padding:2px 8px;border-radius:4px;white-space:nowrap"><?= $spec ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Core Protocol -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Core Protocol — 2x Daily</div>
  <div class="grid-2" style="margin-bottom:0">
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--accent-text);margin-bottom:10px">Block A — Morning (daily)</div>
      <?php foreach ([
        ['Dead Bug',      '3 x 10 each side','Spine flat. Cervical neutral.'],
        ['McGill Curl-Up','3 x 8',           'Safe cervical flexion.'],
        ['Bird Dog',      '3 x 10, 2 sec hold','Opposite arm-leg. Zero lumbar rotation.'],
      ] as [$n,$s,$t]): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $n ?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px;line-height:1.4"><?= $t ?></div>
        </div>
        <span style="font-size:11px;font-weight:600;color:var(--accent-text);background:var(--accent-dim);padding:2px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0"><?= $s ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--left-text);margin-bottom:10px">Block B — Post Session (training days)</div>
      <?php foreach ([
        ['Pallof Press (kneeling)','3 x 12 each','Anti-rotation. 2 sec hold.'],
        ['Forearm Plank',         '3 x 45 sec', 'Rigid body. Cervical neutral.'],
        ['Copenhagen Plank',      '3 x 20 sec each','Both sides.'],
        ['Ab Wheel Rollout',      '3 x 8',      'From knees.'],
      ] as [$n,$s,$t]): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $n ?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px;line-height:1.4"><?= $t ?></div>
        </div>
        <span style="font-size:11px;font-weight:600;color:var(--left-text);background:var(--left-dim);padding:2px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0"><?= $s ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- 12-Week Roadmap -->
<div class="card">
  <div class="card-title">12-Week Progression Roadmap</div>
  <div class="grid-3">
    <?php foreach ([
      ['Weeks 1-4','Foundation','var(--accent-text)','var(--accent-dim)',
       'Build baseline strength and movement patterns',
       ['Start with lighter loads to establish form',
        'Focus on mind-muscle connection',
        'Build cardio base with steady state work',
        'Track all sets to establish baseline']],
      ['Weeks 5-8','Loading','var(--left-text)','var(--left-dim)',
       'Progressive overload and volume increase',
       ['Increase weight by 2-5% per week',
        'Add an extra set per exercise',
        'Increase HIIT rounds gradually',
        'Monitor recovery between sessions']],
      ['Weeks 9-12','Peak','#f07faa','rgba(212,83,126,0.15)',
       'Performance phase and assessment',
       ['Push toward personal records',
        'Maintain form under heavier loads',
        'Assess progress against week 1 baseline',
        'Plan next phase based on results']],
    ] as [$wk,$title,$col,$bg,$focus,$items]): ?>
    <div style="border:1px solid <?= $col ?>;border-radius:10px;padding:1rem;background:<?= $bg ?>">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:<?= $col ?>;margin-bottom:4px"><?= $wk ?></div>
      <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:2px"><?= $title ?></div>
      <div style="font-size:12px;color:var(--muted);font-style:italic;margin-bottom:10px"><?= $focus ?></div>
      <ul style="font-size:12px;color:var(--text);line-height:1.8;padding-left:1rem">
        <?php foreach ($items as $i): ?><li><?= htmlspecialchars($i) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>
</details>

<style>
@media(max-width:640px){
  .week-grid{grid-template-columns:repeat(4,1fr)!important;}
}
@media(max-width:420px){
  .week-grid{grid-template-columns:repeat(2,1fr)!important;}
}
</style>

<?php render_foot(); ?>
