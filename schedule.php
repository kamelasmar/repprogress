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

// Load exercises per day for the toggle view
$exercises_by_day = [];
if ($ap) {
    $ex_st = $db->prepare("
        SELECT pe.day_label, pe.section, pe.sets_target, pe.reps_target, e.name, e.muscle_group
        FROM plan_exercises pe
        JOIN exercises e ON pe.exercise_id = e.id
        WHERE pe.plan_id = ?
        ORDER BY pe.section_order, pe.sort_order
    ");
    $ex_st->execute([$ap['id']]);
    foreach ($ex_st->fetchAll() as $row) {
        $exercises_by_day[$row['day_label']][] = $row;
    }
}

$dow_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

render_head('Training Schedule — Weekly Overview','schedule', false, 'View your weekly training schedule, assigned exercises per day, and 4-week training calendar.');
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
<div class="card mb-5">
  <div class="card-title">Training Days</div>
  <?php foreach ($plan_days as $pd):
    $pn = (int)preg_replace('/\D/', '', $pd['day_label']);
    $hiit = ($pd['cardio_type'] ?? 'none') === 'hiit';
    $ss   = ($pd['cardio_type'] ?? 'none') === 'steady_state';
    $day_exs = $exercises_by_day[$pd['day_label']] ?? [];
    $num_ex = count($day_exs);
  ?>
  <div class="py-4 border-b border-border-app" x-data="{ open: false }">
    <div class="grid grid-cols-[72px_1fr] gap-4 items-start">
      <div class="text-center">
        <div class="day-pill day-pill-<?= $pn ?>" style="font-size:11px;margin-bottom:4px"><?= htmlspecialchars($pd['day_label']) ?></div>
        <div class="text-xs text-muted"><?= $pd['week_day'] ?: 'TBD' ?></div>
      </div>
      <div>
        <div class="text-[15px] font-bold text-[var(--text)] mb-1.5"><?= htmlspecialchars($pd['day_title']) ?></div>
        <div class="flex gap-1.5 flex-wrap mb-2">
          <?php if ($hiit || $ss): ?>
          <span class="badge <?= $hiit ? 'badge-hiit' : 'badge-ss' ?>">
            <?= $hiit ? 'HIIT' : 'Steady State' ?><?= $pd['cardio_description'] ? ' — '.htmlspecialchars($pd['cardio_description']) : '' ?>
          </span>
          <?php endif; ?>
          <span class="text-xs px-2.5 py-0.5 rounded bg-bg3 text-muted"><?= $num_ex ?> exercise<?= $num_ex !== 1 ? 's' : '' ?></span>
        </div>
        <div class="flex gap-2 items-center flex-wrap">
          <a href="workout.php?day=<?= urlencode($pd['day_label']) ?>" class="btn btn-primary btn-sm">💪 Start Workout</a>
          <a href="plan_builder.php?plan_id=<?= $ap['id'] ?>&day=<?= urlencode($pd['day_label']) ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
          <?php if ($num_ex > 0): ?>
          <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = !open" x-text="open ? 'Hide exercises' : 'Show exercises'">Show exercises</button>
          <?php endif; ?>
        </div>

        <?php if ($num_ex > 0): ?>
        <div x-show="open" x-transition x-cloak class="mt-3">
          <?php
          $current_section = '';
          foreach ($day_exs as $ex):
            if ($ex['section'] !== $current_section):
              $current_section = $ex['section'];
          ?>
          <div class="text-[10px] font-bold uppercase tracking-wider text-muted mt-2 mb-1"><?= htmlspecialchars($current_section) ?></div>
          <?php endif; ?>
          <div class="flex justify-between items-center py-1 text-sm">
            <span class="text-[var(--text)]"><?= htmlspecialchars($ex['name']) ?> <span class="text-muted text-xs"><?= htmlspecialchars($ex['muscle_group']) ?></span></span>
            <span class="text-xs text-muted"><?= $ex['sets_target'] ?> × <?= htmlspecialchars($ex['reps_target']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
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

<style>
@media(max-width:640px){
  .week-grid{grid-template-columns:repeat(4,1fr)!important;}
}
@media(max-width:420px){
  .week-grid{grid-template-columns:repeat(2,1fr)!important;}
}
</style>

<?php render_foot(); ?>
