<?php
function render_head(string $title, string $active = '', bool $auth_page = false): void {
    $pages = [
        'index'    => ['Dashboard',   'index.php',    '&#128202;'],
        'log'      => ['Log',         'log.php',      '&#127947;&#65039;'],
        'weight'   => ['Weight',      'weight.php',   '&#9878;&#65039;'],
        'exercises'=> ['Programme',   'exercises.php', '&#128203;'],
        'schedule' => ['Schedule',    'schedule.php',  '&#128197;'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f0f0f">
<meta name="apple-mobile-web-app-capable" content="yes">
<title><?= htmlspecialchars($title) ?> — FitTracker</title>
<?php if (!$auth_page): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<?php endif; ?>
<style>
/* ── Dark theme tokens ──────────────────────────────────────── */
:root {
  --bg:           #0f0f0f;
  --bg2:          #1a1a1a;
  --bg3:          #222;
  --surface:      #1a1a1a;
  --surface2:     #222;
  --border:       rgba(255,255,255,0.08);
  --border2:      rgba(255,255,255,0.14);
  --text:         #f0f0f0;
  --muted:        #888;
  --muted2:       #666;

  --accent:       #1D9E75;
  --accent-dim:   rgba(29,158,117,0.15);
  --accent-dark:  #178a65;
  --accent-text:  #4dd8a7;

  --left:         #5b9fd6;
  --left-dim:     rgba(55,138,221,0.15);
  --left-text:    #7ec1f7;

  --warn:         #d4924a;
  --warn-dim:     rgba(186,117,23,0.15);
  --warn-text:    #f5b76a;

  --red:          #e05c5c;
  --red-dim:      rgba(226,75,74,0.15);
  --red-text:     #f08080;

  --green-dim:    rgba(99,153,34,0.15);
  --green-text:   #8dcc4a;

  --radius:       10px;
  --radius-lg:    14px;

  /* Sidebar */
  --sidebar-w:    220px;
  /* Bottom nav height on mobile */
  --nav-h:        60px;
}

/* ── Reset ──────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-text-size-adjust: 100%; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 15px;
  line-height: 1.6;
  min-height: 100dvh;
}
a { color: var(--accent-text); text-decoration: none; }
a:hover { text-decoration: underline; }
img { max-width: 100%; }

/* ── Layout ─────────────────────────────────────────────────── */
.layout { display: flex; min-height: 100dvh; }

/* Sidebar — desktop only */
.sidebar {
  width: var(--sidebar-w);
  background: var(--surface);
  border-right: 1px solid var(--border);
  padding: 1.5rem 1rem 2rem;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.sidebar-brand {
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 2rem;
  letter-spacing: -0.3px;
}
.sidebar-brand em {
  font-style: normal;
  color: var(--accent-text);
}
.nav-section {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--muted2);
  padding: 0 12px;
  margin: 1.25rem 0 6px;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 8px;
  color: var(--muted);
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 2px;
  transition: all 0.12s;
}
.nav-link:hover { background: var(--bg3); color: var(--text); text-decoration: none; }
.nav-link.active { background: var(--accent-dim); color: var(--accent-text); }
.nav-icon { font-size: 16px; width: 20px; text-align: center; }

/* Main content */
.main {
  flex: 1;
  padding: 1.75rem 2rem;
  max-width: 980px;
  min-width: 0;
  padding-bottom: calc(1.75rem + env(safe-area-inset-bottom));
}

/* ── Auth page layout ──────────────────────────────────────── */
.auth-box {
  max-width: 420px;
  margin: 3rem auto;
}

/* ── Mobile bottom nav ──────────────────────────────────────── */
.bottom-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: calc(var(--nav-h) + env(safe-area-inset-bottom));
  padding-bottom: env(safe-area-inset-bottom);
  background: var(--surface);
  border-top: 1px solid var(--border);
  z-index: 100;
}
.bottom-nav-inner {
  display: flex;
  height: var(--nav-h);
}
.bnav-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  text-decoration: none;
  color: var(--muted);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.03em;
  transition: color 0.12s;
  -webkit-tap-highlight-color: transparent;
  padding: 6px 2px 4px;
}
.bnav-item.active { color: var(--accent-text); }
.bnav-item:hover { text-decoration: none; color: var(--text); }
.bnav-icon { font-size: 20px; line-height: 1; }

/* ── Page header ────────────────────────────────────────────── */
.page-header { margin-bottom: 1.5rem; }
.page-title  { font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
.page-sub    { color: var(--muted); font-size: 14px; margin-top: 2px; }

/* ── Cards ──────────────────────────────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.25rem;
}
.card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--muted);
  margin-bottom: 1rem;
}

/* ── Grids ──────────────────────────────────────────────────── */
.grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 1.25rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr;       gap: 1.25rem; margin-bottom: 1.25rem; }
.grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.25rem; margin-bottom: 1.25rem; }

/* ── Metric cards ───────────────────────────────────────────── */
.metric {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem 1.2rem;
}
.metric-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 4px; }
.metric-value { font-size: 26px; font-weight: 700; color: var(--text); line-height: 1.2; }
.metric-sub   { font-size: 12px; color: var(--muted); margin-top: 3px; }
.metric-up    { color: var(--accent-text); }
.metric-down  { color: var(--red-text); }

/* ── Badges ─────────────────────────────────────────────────── */
.badge      { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 5px; }
.badge-left { background: var(--left-dim);    color: var(--left-text); }
.badge-mob  { background: var(--accent-dim);  color: var(--accent-text); }
.badge-act  { background: var(--warn-dim);    color: var(--warn-text); }
.badge-ss   { background: var(--left-dim);    color: var(--left-text); }
.badge-hiit { background: var(--red-dim);     color: var(--red-text); }
.badge-core { background: var(--warn-dim);    color: var(--warn-text); }
.badge-func { background: var(--green-dim);   color: var(--green-text); }
.badge-pending { background: var(--warn-dim); color: var(--warn-text); }
.badge-suggested { background: var(--left-dim); color: var(--left-text); }
.badge-admin { background: var(--accent-dim); color: var(--accent-text); }

/* ── Day pills ──────────────────────────────────────────────── */
.day-pill   { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px 4px 8px; border-radius: 20px; font-size: 13px; font-weight: 600; }
.day-pill-1 { background: var(--green-dim);  color: var(--green-text); }
.day-pill-2 { background: var(--left-dim);   color: var(--left-text); }
.day-pill-3 { background: rgba(212,83,126,0.15); color: #f07faa; }
.day-pill-4 { background: var(--warn-dim);   color: var(--warn-text); }
.day-pill-5 { background: var(--accent-dim); color: var(--accent-text); }

/* ── Forms ──────────────────────────────────────────────────── */
label { display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 4px; }
input, select, textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border2);
  border-radius: 8px;
  font-size: 15px;
  background: var(--bg3);
  color: var(--text);
  font-family: inherit;
  -webkit-appearance: none;
  appearance: none;
}
input:focus, select:focus, textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(29,158,117,0.18);
}
input::placeholder, textarea::placeholder { color: var(--muted2); }
.form-group   { margin-bottom: 1rem; }
.form-row     { display: grid; gap: 12px; margin-bottom: 1rem; }
.form-row-2   { grid-template-columns: 1fr 1fr; }
.form-row-3   { grid-template-columns: 1fr 1fr 1fr; }
.form-row-4   { grid-template-columns: 1fr 1fr 1fr 1fr; }

/* ── Buttons ────────────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 18px; border-radius: 8px;
  font-size: 14px; font-weight: 600; cursor: pointer;
  border: none; transition: all 0.12s; font-family: inherit;
  white-space: nowrap;
}
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: var(--accent-dark); }
.btn-sm { padding: 6px 13px; font-size: 13px; }
.btn-ghost { background: transparent; border: 1px solid var(--border2); color: var(--muted); }
.btn-ghost:hover { background: var(--bg3); color: var(--text); }
.btn-danger { background: transparent; border: 1px solid rgba(224,92,92,0.3); color: var(--red-text); }
.btn-danger:hover { background: var(--red-dim); }
.btn-warn   { background: transparent; border: 1px solid rgba(212,146,74,0.3); color: var(--warn-text); }
.btn-warn:hover { background: var(--warn-dim); }
.btn-yt {
  background: #cc0000; color: #fff; font-size: 12px;
  padding: 4px 10px; border-radius: 5px;
  display: inline-flex; align-items: center; gap: 4px;
  text-decoration: none; font-weight: 600;
}
.btn-yt:hover { background: #aa0000; color: #fff; }

/* ── Tables ─────────────────────────────────────────────────── */
table  { width: 100%; border-collapse: collapse; font-size: 14px; }
th     { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); padding: 8px 12px; border-bottom: 1px solid var(--border); }
td     { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--bg3); }

/* ── Flash messages ─────────────────────────────────────────── */
.flash { padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-bottom: 1.25rem; }
.flash-success { background: var(--accent-dim); color: var(--accent-text); border: 1px solid rgba(29,158,117,0.3); }
.flash-error   { background: var(--red-dim);    color: var(--red-text);    border: 1px solid rgba(224,92,92,0.3); }

/* ── Misc ───────────────────────────────────────────────────── */
.empty { text-align: center; padding: 2.5rem; color: var(--muted); }
.empty-icon { font-size: 32px; margin-bottom: .75rem; }
.empty p { margin-bottom: 1rem; font-size: 14px; }
.section-hdr {
  font-size: 11px; font-weight: 700; letter-spacing: .06em;
  text-transform: uppercase; color: var(--muted);
  padding: 8px 0 6px; border-bottom: 1px solid var(--border);
  margin-bottom: 4px; margin-top: 1rem;
}
.section-hdr:first-child { margin-top: 0; }
.left-banner {
  padding: 10px 14px; background: var(--left-dim); color: var(--left-text);
  border-radius: 8px; font-size: 13px; font-weight: 500;
  margin-bottom: 1rem; border: 1px solid rgba(91,159,214,0.25);
}
.coach-tip { font-size: 12px; color: var(--muted); line-height: 1.55; font-style: italic; margin-top: 3px; }
.info-box {
  padding: 12px 14px; background: var(--bg3);
  border-left: 3px solid var(--accent);
  border-radius: 0 8px 8px 0;
  font-size: 13px; color: var(--muted); line-height: 1.6;
}


/* Log page specific */
.log-grid { display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; align-items: start; }
@media (max-width: 768px) {
  .day-overview-grid { grid-template-columns: repeat(3,1fr) !important; }
  .log-grid { grid-template-columns: 1fr; }
}

/* ── Chart.js dark defaults ─────────────────────────────────── */
canvas { display: block; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 768px) {
  .day-overview-grid { grid-template-columns: repeat(3,1fr) !important; }
  .sidebar { display: none; }
  .bottom-nav { display: block; }
  .main {
    padding: 1rem;
    padding-bottom: calc(var(--nav-h) + env(safe-area-inset-bottom) + 1rem);
    max-width: 100%;
  }
  .grid-4 { grid-template-columns: 1fr 1fr; }
  .grid-2, .grid-3 { grid-template-columns: 1fr; }
  .form-row-2, .form-row-3, .form-row-4 { grid-template-columns: 1fr 1fr; }
  .page-title { font-size: 20px; }
  table { font-size: 13px; }
  th, td { padding: 8px 8px; }
  /* Stack action columns on narrow tables */
  .table-stack-mobile td:last-child,
  .table-stack-mobile th:last-child { display: none; }
}
@media (max-width: 480px) {
  .day-overview-grid { grid-template-columns: 1fr 1fr !important; }
  .form-row-2, .form-row-3, .form-row-4 { grid-template-columns: 1fr; }
  .grid-4 { grid-template-columns: 1fr 1fr; }
  .metric-value { font-size: 22px; }
}
</style>
</head>
<body>
<?php if ($auth_page): ?>
<div class="layout">
<main class="main" style="max-width:100%">
<?php
  $flash = get_flash();
  if ($flash): ?>
<div style="max-width:420px;margin:0 auto">
<div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
</div>
<?php endif;
else: ?>
<div class="layout">

<!-- Desktop sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">Fit<em>Tracker</em></div>

  <div class="nav-section">Menu</div>
  <?php foreach ($pages as $key => [$label, $href, $icon]): ?>
  <a href="<?= $href ?>" class="nav-link <?= $active === $key ? 'active' : '' ?>">
    <span class="nav-icon"><?= $icon ?></span><?= $label ?>
  </a>
  <?php endforeach; ?>

  <?php
  $cu = current_user();
  if ($cu): ?>
  <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--border)">
    <div style="font-size:12px;color:var(--muted);margin-bottom:6px;word-break:break-all">
      <?= htmlspecialchars($cu['email']) ?>
      <?php if ($cu['is_admin']): ?>
        <span class="badge badge-admin" style="margin-left:4px">Admin</span>
      <?php endif; ?>
    </div>
    <a href="logout.php" class="nav-link" style="color:var(--red-text)">
      <span class="nav-icon">&#128682;</span>Logout
    </a>
  </div>
  <?php endif; ?>
</aside>

<main class="main">
<?php
  $flash = get_flash();
  if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif;
endif;
}

function render_foot(bool $auth_page = false): void {
  $pages = [
      'index'    => ['Dashboard', 'index.php',    '&#128202;'],
      'log'      => ['Log',       'log.php',      '&#127947;&#65039;'],
      'weight'   => ['Weight',    'weight.php',   '&#9878;&#65039;'],
      'exercises'=> ['Programme', 'exercises.php', '&#128203;'],
      'schedule' => ['Schedule',  'schedule.php',  '&#128197;'],
  ];
  // Detect active page from current script
  $current = basename($_SERVER['PHP_SELF'], '.php');
  $map = ['index'=>'index','log'=>'log','weight'=>'weight','exercises'=>'exercises','schedule'=>'schedule'];
  $active = $map[$current] ?? '';
  ?>
</main>
</div>

<?php if (!$auth_page): ?>
<!-- Mobile bottom nav -->
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <?php foreach ($pages as $key => [$label, $href, $icon]): ?>
    <a href="<?= $href ?>" class="bnav-item <?= $active===$key?'active':'' ?>">
      <span class="bnav-icon"><?= $icon ?></span>
      <span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<script>
// Dark theme for Chart.js
if (typeof Chart !== 'undefined') {
  Chart.defaults.color = '#888';
  Chart.defaults.borderColor = 'rgba(255,255,255,0.07)';
  Chart.defaults.font.family = "-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif";
  Chart.defaults.font.size = 12;
}
</script>
<?php endif; ?>
</body></html>
<?php }

function day_pill(string $day_label): string {
    $n = preg_replace('/\D/','',$day_label);
    return '<span class="day-pill day-pill-'.$n.'">'.$day_label.'</span>';
}

function active_plan(): ?array {
    $uid = current_user_id();
    if (!$uid) return null;
    try {
        $st = db()->prepare("SELECT * FROM plans WHERE is_active=1 AND user_id=? LIMIT 1");
        $st->execute([$uid]);
        return $st->fetch() ?: null;
    } catch (Exception $e) { return null; }
}
