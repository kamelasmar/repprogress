<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/auth.php';
require_auth();
$db  = db();
$uid = current_user_id();

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

// 4-week calendar grid starting from last Tuesday
$anchor = new DateTime();
$iso    = (int)$anchor->format('N'); // 1=Mon … 7=Sun
$toTue  = ($iso >= 2) ? $iso - 2 : 6;
$anchor->modify("-{$toTue} days")->modify('-3 weeks');

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

render_head('Schedule', 'schedule');

// ── Weekly plan definition ───────────────────────────────────────────────────
// Mon = full rest,  Sun = Day 5
$week_plan = [
    'Tue' => [
        'type'        => 'train',
        'day'         => 'Day 1',
        'title'       => 'Lower Body',
        'pill'        => 1,
        'icon'        => '🦵',
        'duration'    => '75 min',
        'cardio'      => 'Rowing Machine — Steady State (10 min)',
        'cardio_type' => 'steady_state',
        'core'        => 'Block A + Block B',
        'notes'       => ['Leg press, curl, extension, abductor, adductor, glute bridge, calf raise',
                          'Functional: KB deadlift, sled push, goblet squat (DB at chest)',
                          'Hip mobility warm-up · pigeon + couch stretch cool-down',
                          'No bar on shoulders — machine focus throughout'],
    ],
    'Wed' => [
        'type'        => 'train',
        'day'         => 'Day 2',
        'title'       => 'Push',
        'pill'        => 2,
        'icon'        => '💪',
        'duration'    => '70 min',
        'cardio'      => 'Ski Erg HIIT — 6 × 20s on / 40s off',
        'cardio_type' => 'hiit',
        'core'        => 'Block A + Block B',
        'notes'       => ['Serratus wall slide, pec deck, chest press machine',
                          'Landmine press + cable fly + floor press — bilateral then left emphasis',
                          'Tricep finisher: cable extension both arms, left extra sets',
                          'Ski Erg before session activates serratus and lats'],
    ],
    'Thu' => [
        'type'        => 'recovery',
        'day'         => null,
        'title'       => 'Active Recovery',
        'pill'        => 0,
        'icon'        => '🚶',
        'duration'    => '35–40 min',
        'cardio'      => 'Zone 2 walk / incline treadmill / swim',
        'cardio_type' => 'steady_state',
        'core'        => 'Block A (morning only)',
        'notes'       => ['Conversational pace — if you can\'t hold a sentence, slow down',
                          'Incline treadmill (5–8°) or outdoor walk preferred',
                          'Swimming is best option for cervical spine decompression',
                          'No strength work — this is a true recovery day'],
    ],
    'Fri' => [
        'type'        => 'train',
        'day'         => 'Day 3',
        'title'       => 'Pull',
        'pill'        => 3,
        'icon'        => '🔙',
        'duration'    => '75 min',
        'cardio'      => 'Rowing Machine — Steady State (10 min)',
        'cardio_type' => 'steady_state',
        'core'        => 'Block A + Block B',
        'notes'       => ['Cable pulldowns, seated rows, ring rows — both arms, left extra volume',
                          'KB swings (two-hand then single-arm left) — hip hinge power',
                          'Rowing warm-up doubles as pull-day lat primer',
                          'Dead hang cool-down decompresses cervical spine after pulling'],
    ],
    'Sat' => [
        'type'        => 'train',
        'day'         => 'Day 4',
        'title'       => 'Arms & Functional',
        'pill'        => 4,
        'icon'        => '⚡',
        'duration'    => '80 min',
        'cardio'      => 'Ski Erg HIIT — 8 × 20s on / 40s off',
        'cardio_type' => 'hiit',
        'core'        => 'Block A + Block B',
        'notes'       => ['Tricep: cable pushdown, overhead extension, dip machine — both arms, left emphasis',
                          'Functional block: KB swings, battle ropes, sled push, farmer carries',
                          'Battle ropes HIIT 30s on/30s off — serratus under high load',
                          'Ski Erg before session — tricep + lat activation primer'],
    ],
    'Sun' => [
        'type'        => 'train',
        'day'         => 'Day 5',
        'title'       => 'Full Body + Mobility',
        'pill'        => 5,
        'icon'        => '🧘',
        'duration'    => '75 min',
        'cardio'      => 'Bike Steady State (15 min) or Rowing 250m sprints × 5',
        'cardio_type' => 'steady_state',
        'core'        => 'Block A + Block B',
        'notes'       => ['OPTION A: Full Body Day 5 — bike, deep hip mobility reset, integrated strength',
                          'OPTION B: Reformer Pilates class — replaces the session entirely',
                          'Reformer: spring resistance, no axial cervical load, deep stabiliser activation',
                          '10 min 90/90 flow · thoracic rotation · cossack squat · dead hang'],
    ],
    'Mon' => [
        'type'        => 'rest',
        'day'         => null,
        'title'       => 'Full Rest',
        'pill'        => 0,
        'icon'        => '😴',
        'duration'    => null,
        'cardio'      => null,
        'cardio_type' => null,
        'core'        => 'Morning mobility only (10 min)',
        'notes'       => ['No training — full nervous system recovery',
                          '90/90 hip switch + bird dog in the morning (10 min max)',
                          'Sleep 7.5–8.5 hrs — neuro-muscular reconnection happens here',
                          'Magnesium glycinate 400 mg before bed'],
    ],
];

$day_colors = [
    'Day 1'=>'#639922','Day 2'=>'#5b9fd6',
    'Day 3'=>'#D4537E','Day 4'=>'#d4924a','Day 5'=>'#4dd8a7',
];
$dow_labels = ['Tue','Wed','Thu','Fri','Sat','Sun','Mon'];
// Map date's day-of-week to our plan
$dow_plan_map = [
    'Tue'=>'Tue','Wed'=>'Wed','Thu'=>'Thu',
    'Fri'=>'Fri','Sat'=>'Sat','Sun'=>'Sun','Mon'=>'Mon',
];
?>

<div class="page-header">
  <div class="page-title">Training Schedule</div>
  <div class="page-sub">5 training days · Starts Tuesday · Monday = full rest</div>
</div>

<!-- ── WEEKLY SPLIT GRID ────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Weekly Split</div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px" class="week-grid">
    <?php foreach ($week_plan as $dow => $p):
      $isTrain    = $p['type'] === 'train';
      $isRecovery = $p['type'] === 'recovery';
      $isRest     = $p['type'] === 'rest';
      $hiit       = $p['cardio_type'] === 'hiit';
    ?>
    <div style="
      background:var(--surface2);
      border:1px solid <?= $isRest ? 'var(--border)' : ($isTrain ? 'var(--border2)' : 'rgba(212,146,74,0.3)') ?>;
      border-radius:10px;padding:12px 8px;text-align:center;
      opacity:<?= $isRest ? '0.6' : '1' ?>
    ">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px"><?= $dow ?></div>
      <div style="font-size:22px;margin-bottom:6px"><?= $p['icon'] ?></div>
      <?php if ($isTrain): ?>
        <div class="day-pill day-pill-<?= $p['pill'] ?>" style="font-size:10px;padding:2px 6px;margin-bottom:5px;display:inline-block"><?= $p['day'] ?></div>
        <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:5px;line-height:1.3"><?= $p['title'] ?></div>
        <div style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;display:inline-block;
          background:<?= $hiit?'var(--red-dim)':'var(--left-dim)' ?>;
          color:<?= $hiit?'var(--red-text)':'var(--left-text)' ?>">
          <?= $hiit ? 'HIIT' : 'Zone 2' ?>
        </div>
        <?php if ($p['duration']): ?><div style="font-size:11px;color:var(--muted);margin-top:4px"><?= $p['duration'] ?></div><?php endif; ?>
      <?php elseif ($isRecovery): ?>
        <div style="font-size:11px;font-weight:700;color:var(--warn-text);background:var(--warn-dim);padding:2px 6px;border-radius:4px;margin-bottom:4px;display:inline-block">Active</div>
        <div style="font-size:12px;font-weight:600;color:var(--text)"><?= $p['title'] ?></div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px"><?= $p['duration'] ?></div>
      <?php else: ?>
        <div style="font-size:12px;font-weight:700;color:var(--muted2)">Full Rest</div>
        <div style="font-size:11px;color:var(--muted2);margin-top:3px">Off</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── DAY-BY-DAY DETAIL ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Day-by-Day Detail</div>
  <?php foreach ($week_plan as $dow => $p): ?>
  <div style="display:grid;grid-template-columns:72px 1fr;gap:16px;padding:16px 0;border-bottom:1px solid var(--border);align-items:start">
    <div style="text-align:center">
      <div style="font-size:24px;margin-bottom:4px"><?= $p['icon'] ?></div>
      <div style="font-size:14px;font-weight:700;color:var(--text)"><?= $dow ?></div>
      <?php if ($p['duration']): ?><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= $p['duration'] ?></div><?php endif; ?>
    </div>
    <div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
        <?php if ($p['day']): ?><span class="day-pill day-pill-<?= $p['pill'] ?>" style="font-size:11px"><?= $p['day'] ?></span><?php endif; ?>
        <span style="font-size:15px;font-weight:700;color:var(--text)"><?= $p['title'] ?></span>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
        <?php if ($p['cardio']): ?>
        <span style="font-size:12px;padding:3px 10px;border-radius:5px;font-weight:600;
          background:<?= $p['cardio_type']==='hiit'?'var(--red-dim)':'var(--left-dim)' ?>;
          color:<?= $p['cardio_type']==='hiit'?'var(--red-text)':'var(--left-text)' ?>">
          <?= $p['cardio_type']==='hiit'?'HIIT':'Steady State' ?> — <?= htmlspecialchars($p['cardio']) ?>
        </span>
        <?php endif; ?>
        <?php if ($p['core']): ?>
        <span style="font-size:12px;padding:3px 10px;border-radius:5px;font-weight:600;background:var(--accent-dim);color:var(--accent-text)">
          🧠 Core: <?= $p['core'] ?>
        </span>
        <?php endif; ?>
      </div>
      <?php foreach ($p['notes'] as $note): ?>
      <div style="display:flex;gap:8px;font-size:13px;color:var(--muted);margin-bottom:3px;line-height:1.5">
        <span style="color:var(--accent-text);flex-shrink:0">·</span><?= htmlspecialchars($note) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── CARDIO GUIDE ────────────────────────────────────────────────────── -->
<div class="grid-2">
  <div class="card">
    <div class="card-title" style="color:var(--left-text)">Steady State (Zone 2) — Tue, Thu, Fri, Sun</div>
    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:1rem">
      Heart rate 60–70% max. Conversational pace. Builds aerobic base and mitochondrial density without taxing the CNS — important given your neurological context.
    </div>
    <?php foreach ([
        ['Rowing machine',       '10 min · 22–24 spm', 'Tue + Fri warm-up'],
        ['Stationary bike',      '15 min · low resistance', 'Sun option'],
        ['Incline treadmill walk','35–40 min · 5–8°', 'Thursday'],
        ['Swimming / pool walk', '30 min', 'Thursday — best for cervical decompression'],
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
    <div class="card-title" style="color:var(--red-text)">HIIT — Wed + Sat only</div>
    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:1rem">
      High output twice a week maximum. Ski erg and rowing — no running (cervical jarring), no box jumps. Short work periods keep cortisol spike controlled.
    </div>
    <?php foreach ([
        ['Ski Erg',        '20s hard / 40s easy × 6–8', 'Wed push + Sat arms'],
        ['Rowing 250m',    '250m hard / 90s rest × 5',   'Sun option B'],
        ['Battle Ropes',   '30s on / 30s off × 6',       'Sat functional block'],
    ] as [$name,$spec,$when]): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-top:1px solid var(--border);gap:10px">
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $name ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= $when ?></div>
      </div>
      <span style="font-size:11px;font-weight:600;color:var(--red-text);background:var(--red-dim);padding:2px 8px;border-radius:4px;white-space:nowrap"><?= $spec ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:1rem;padding:10px 12px;background:var(--bg);border-radius:8px;font-size:12px;color:var(--muted);line-height:1.5">
      ⚠️ Avoid fasted high-intensity cardio on your current protocol — hypoglycaemic risk with IGF-1 LR3 and AOD 9604.
    </div>
  </div>
</div>

<!-- ── CORE 2× DAILY ─────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Core Protocol — 2× Daily</div>
  <div class="grid-2" style="margin-bottom:0">
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--accent-text);margin-bottom:10px">Block A — Morning (every day incl. rest days)</div>
      <?php foreach ([
        ['Dead Bug',      '3 × 10 each side','Spine flat. Cervical neutral. Alternate arm + leg.'],
        ['McGill Curl-Up','3 × 8',           'Safe cervical flexion. Head lifts — neck doesn\'t crane.'],
        ['Bird Dog',      '3 × 10, 2 sec hold','Opposite arm-leg. Zero lumbar rotation.'],
      ] as [$n,$s,$t]): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $n ?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px;line-height:1.4"><?= $t ?></div>
        </div>
        <span style="font-size:11px;font-weight:600;color:var(--accent-text);background:var(--accent-dim);padding:2px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0"><?= $s ?></span>
      </div>
      <?php endforeach; ?>
      <div style="font-size:11px;color:var(--muted);margin-top:6px;font-style:italic">~10 min total including on Monday and Thursday</div>
    </div>
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--left-text);margin-bottom:10px">Block B — Post Session (training days only)</div>
      <?php foreach ([
        ['Pallof Press (kneeling)','3 × 12 each (left +1)','Anti-rotation. Left serratus + core. 2 sec hold.'],
        ['Forearm Plank',         '3 × 45 sec',            'Rigid body. Cervical neutral. No hip sag.'],
        ['Copenhagen Plank',      '3 × 20 sec each',       'Both sides. Track left vs right hold time.'],
        ['Ab Wheel Rollout',      '3 × 8',                 'From knees. Go to point of no lumbar arch.'],
      ] as [$n,$s,$t]): ?>
      <div style="padding:9px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text)"><?= $n ?></div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px;line-height:1.4"><?= $t ?></div>
        </div>
        <span style="font-size:11px;font-weight:600;color:var(--left-text);background:var(--left-dim);padding:2px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0"><?= $s ?></span>
      </div>
      <?php endforeach; ?>
      <div style="font-size:11px;color:var(--muted);margin-top:6px;font-style:italic">~12 min total. Rotate exercises across days.</div>
    </div>
  </div>
</div>

<!-- ── REFORMER OPTION ────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem;border:1px solid var(--accent)">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:1rem">
    <span style="font-size:28px">🧘</span>
    <div>
      <div style="font-size:15px;font-weight:700;color:var(--text)">Reformer Pilates — Sunday Option</div>
      <div style="font-size:13px;color:var(--muted)">Replaces Day 5 entirely when you attend a class</div>
    </div>
  </div>
  <div class="grid-2" style="margin-bottom:0">
    <?php foreach ([
      ['Why it works','Spring resistance loads muscles without axial load on the cervical spine. Deep stabilisers — serratus anterior, multifidus, hip rotators — activate in ways machines cannot replicate.'],
      ['Hip mobility','Footbar work and long-box hip circles directly address your capsular hip tightness. More effective than static stretching for this type of restriction.'],
      ['Left-side activation','Unilateral footbar and arm spring work isolates left pec, lat, serratus, and tricep through eccentric spring tension — complementary to cable work.'],
      ['Cervical safety','Most exercises are supine, side-lying, or quadruped. Zero axial cervical loading. Ideal given your stenosis.'],
    ] as [$label,$desc]): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="font-size:13px;font-weight:600;color:var(--accent-text);margin-bottom:2px"><?= $label ?></div>
      <div style="font-size:13px;color:var(--muted);line-height:1.5"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── 4-WEEK CALENDAR ─────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-title">Last 4 Weeks</div>
  <!-- Header row -->
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
      $col       = $loggedDay ? ($day_colors[$loggedDay] ?? '#888') : 'transparent';
      $dow2      = date('D', strtotime($date));
      $planType  = ['Tue'=>'train','Wed'=>'train','Thu'=>'recovery',
                    'Fri'=>'train','Sat'=>'train','Sun'=>'train','Mon'=>'rest'][$dow2] ?? 'rest';
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
        <div style="font-size:9px;color:var(--border)">—</div>
      <?php elseif ($planType==='rest'): ?>
        <div style="font-size:12px">😴</div>
      <?php elseif ($planType==='recovery'): ?>
        <div style="font-size:12px">🚶</div>
      <?php else: ?>
        <div style="font-size:9px;color:var(--red-text);font-weight:600">missed</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <!-- Legend -->
  <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
    <?php foreach ($day_colors as $dl => $c): ?>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted)">
      <div style="width:7px;height:7px;border-radius:50%;background:<?= $c ?>"></div><?= $dl ?>
    </div>
    <?php endforeach; ?>
    <div style="font-size:11px;color:var(--muted)">🚶 Recovery &nbsp; 😴 Rest</div>
  </div>
</div>

<!-- ── 12-WEEK ROADMAP ────────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-title">12-Week Progression Roadmap</div>
  <div class="grid-3">
    <?php foreach ([
      ['Weeks 1–4','Reconnection','var(--accent-text)','var(--accent-dim)',
       'Left-right gap identification and neural signal work',
       ['All left-side loads 30–40% lighter than right',
        'Bilateral first, then unilateral on every exercise',
        'Thursday: walk only, 30 min flat terrain',
        'Ski Erg HIIT: 4 rounds only to start',
        'Log both sides every set — establish baseline gap',
        'Core Block A every morning including Monday']],
      ['Weeks 5–8','Loading','var(--left-text)','var(--left-dim)',
       'Gradual unilateral load increase on left side',
       ['Left-side loads within 15–20% of right',
        'Add 1 extra left set per exercise per week',
        'Thursday: 35 min incline walk or easy bike',
        'Ski Erg HIIT: 6–8 full rounds',
        'KB swings: increase weight by 2 kg every 2 weeks',
        'Monitor: can you feel left pec / lat / serratus fire?']],
      ['Weeks 9–12','Equalisation','#f07faa','rgba(212,83,126,0.15)',
       'Closing the gap — bilateral strength parity',
       ['Push left side toward equal loading with right',
        'Sunday reformer: increase to 2× per month',
        'Thursday: 40 min + optional swim added',
        'Assess bilateral barbell work with physio only if symptoms allow',
        'Target < 10% left-right strength gap by week 12',
        'Benchmark Copenhagen plank hold time both sides']],
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

<style>
@media(max-width:640px){
  .week-grid{grid-template-columns:repeat(4,1fr)!important;}
}
@media(max-width:420px){
  .week-grid{grid-template-columns:repeat(2,1fr)!important;}
}
</style>

<?php render_foot(); ?>
