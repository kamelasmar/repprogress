<?php
function render_head(string $title, string $active = '', bool $auth_page = false, string $description = ''): void {
    if (!$description) $description = 'Repprogress — Track every rep, build every plan, see real progress. Workout tracker with training plans, exercise library, and body composition tracking.';
    $pages = [
        'index'    => ['Dashboard',   'index.php',    '&#128202;'],
        'workout'  => ['Workout',     'workout.php',  '&#128170;'],
        'log'      => ['History',     'log.php',      '&#128203;'],
        'weight'   => ['Body',        'weight.php',   '&#9878;&#65039;'],
        'exercises'=> ['Exercises',   'exercises.php', '&#127947;&#65039;'],
        'plans'    => ['Plans',       'plan_manager.php', '&#128221;'],
        'schedule' => ['Schedule',    'schedule.php',  '&#128197;'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f0f0f" id="meta-theme-color">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<meta name="robots" content="<?= $auth_page ? 'noindex, nofollow' : 'index, follow' ?>">
<meta property="og:title" content="<?= htmlspecialchars($title) ?> | Repprogress">
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Repprogress">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= htmlspecialchars($title) ?> | Repprogress">
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
<link rel="canonical" href="<?= htmlspecialchars((defined('APP_URL') ? rtrim(APP_URL, '/') : '') . $_SERVER['REQUEST_URI']) ?>">
<title><?= htmlspecialchars($title) ?> | Repprogress</title>
<?= vite_assets() ?>
<?php if (!$auth_page): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<?php endif; ?>
<!-- Legacy CSS now in src/css/app.css via Tailwind layers -->
<script>
(function(){var t=localStorage.getItem('rp_theme');if(t){document.documentElement.setAttribute('data-theme',t);var m=document.getElementById('meta-theme-color');if(m)m.content=t==='light'?'#f5f5f5':'#0f0f0f';}})();
</script>
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
  <div class="sidebar-brand">Rep<em>progress</em></div>

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
    <?php
    // Profile switcher
    $shared_profiles = [];
    try {
        $sp = db()->prepare("SELECT u.id, u.name, u.email FROM shared_access sa JOIN users u ON sa.owner_id=u.id WHERE sa.granted_to=? ORDER BY u.name");
        $sp->execute([current_user_id()]);
        $shared_profiles = $sp->fetchAll();
    } catch (Exception $e) {}
    if ($shared_profiles):
      $vu = viewed_user();
    ?>
    <div style="margin-bottom:8px">
      <label style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Viewing</label>
      <select onchange="window.location='switch_profile.php?to='+this.value"
        style="width:100%;padding:6px 8px;font-size:12px;background:var(--bg3);color:var(--text);border:1px solid var(--border2);border-radius:6px">
        <option value="self" <?= !$vu?'selected':'' ?>>Your Account</option>
        <?php foreach ($shared_profiles as $sp_item): ?>
        <option value="<?= $sp_item['id'] ?>" <?= ($vu && $vu['id']==$sp_item['id'])?'selected':'' ?>>
          <?= htmlspecialchars($sp_item['name'] ?: $sp_item['email']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if ($cu['name']): ?>
    <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:2px"><?= htmlspecialchars($cu['name']) ?></div>
    <?php endif; ?>
    <div style="font-size:12px;color:var(--muted);margin-bottom:6px;word-break:break-all">
      <?= htmlspecialchars($cu['email']) ?>
      <?php if ($cu['is_admin']): ?>
        <span class="badge badge-admin" style="margin-left:4px">Admin</span>
      <?php endif; ?>
    </div>
    <button type="button" class="nav-link" style="border:none;background:none;cursor:pointer;font-family:inherit;width:100%;text-align:left" onclick="var h=document.documentElement;var t=h.getAttribute('data-theme')==='light'?'':'light';h.setAttribute('data-theme',t);localStorage.setItem('rp_theme',t);">
      <span class="nav-icon">&#9728;&#65039;</span><span id="theme-label">Theme</span>
    </button>
    <a href="account.php" class="nav-link">
      <span class="nav-icon">&#9881;</span>Account
    </a>
    <a href="logout.php" class="nav-link" style="color:var(--red-text)">
      <span class="nav-icon">&#128682;</span>Logout
    </a>
  </div>
  <?php endif; ?>
</aside>

<main class="main">
<?php
  // "Viewing as" banner
  if (viewing_other_profile()):
    $vu_banner = viewed_user();
    if ($vu_banner): ?>
<div style="background:var(--left-dim);color:var(--left-text);padding:8px 16px;border-radius:8px;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:500">
  <span>Viewing as <strong><?= htmlspecialchars($vu_banner['name'] ?: $vu_banner['email']) ?></strong></span>
  <a href="switch_profile.php?to=self" style="color:var(--left-text);font-weight:600;text-decoration:underline">Back to your account</a>
</div>
<?php endif; endif; ?>
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
      'workout'  => ['Workout',   'workout.php',  '&#128170;'],
      'log'      => ['History',   'log.php',      '&#128203;'],
      'weight'   => ['Body',      'weight.php',   '&#9878;&#65039;'],
      'exercises'=> ['Exercises', 'exercises.php', '&#127947;&#65039;'],
      'plans'    => ['Plans',     'plan_manager.php', '&#128221;'],
      'schedule' => ['Schedule',  'schedule.php',  '&#128197;'],
      'account'  => ['Account',   'account.php',   '&#9881;'],
  ];
  // Detect active page from current script
  $current = basename($_SERVER['PHP_SELF'], '.php');
  $map = ['index'=>'index','workout'=>'workout','log'=>'log','weight'=>'weight','exercises'=>'exercises','plan_manager'=>'plans','plan_builder'=>'plans','schedule'=>'schedule','account'=>'account'];
  $active = $map[$current] ?? '';
  ?>
</main>
</div>

<?php if (!$auth_page):
  $main_nav = [
      'index'    => ['Home',     'index.php',        '&#128202;'],
      'workout'  => ['Workout',  'workout.php',      '&#128170;'],
      'plans'    => ['Plans',    'plan_manager.php',  '&#128221;'],
      'exercises'=> ['Exercises','exercises.php',     '&#127947;&#65039;'],
  ];
  $more_nav = [
      'log'      => ['History',  'log.php',           '&#128203;'],
      'weight'   => ['Body',     'weight.php',        '&#9878;&#65039;'],
      'schedule' => ['Schedule', 'schedule.php',      '&#128197;'],
      'account'  => ['Account',  'account.php',       '&#9881;'],
  ];
  $more_active = in_array($active, array_keys($more_nav));
?>
<!-- Mobile bottom nav -->
<nav class="bottom-nav" x-data="{ moreOpen: false }">
  <!-- More slide-up menu -->
  <div x-show="moreOpen" x-transition.opacity x-on:click="moreOpen = false" class="fixed inset-0 bg-[rgba(0,0,0,0.5)] z-[99]" x-cloak></div>
  <div x-show="moreOpen" x-transition x-cloak class="fixed bottom-[calc(var(--nav-h)+env(safe-area-inset-bottom))] left-0 right-0 bg-surface border-t border-border-app rounded-t-xl z-[101] px-4 py-4">
    <div class="grid grid-cols-4 gap-2 text-center mb-3">
      <?php foreach ($more_nav as $key => [$label, $href, $icon]): ?>
      <a href="<?= $href ?>" class="flex flex-col items-center gap-1 py-2 rounded-app no-underline <?= $active===$key ? 'bg-accent-dim text-accent-text' : 'text-muted hover:text-[var(--text)]' ?>">
        <span class="text-xl"><?= $icon ?></span>
        <span class="text-[10px] font-semibold"><?= $label ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="flex justify-between items-center border-t border-border-app pt-2 mt-1">
      <button type="button" class="text-xs text-muted py-1 cursor-pointer" style="background:none;border:none" onclick="var h=document.documentElement;var t=h.getAttribute('data-theme')==='light'?'':'light';h.setAttribute('data-theme',t);localStorage.setItem('rp_theme',t);">&#9728;&#65039; Switch Theme</button>
      <a href="logout.php" class="text-xs text-red-text py-1 no-underline">Log Out</a>
    </div>
  </div>
  <div class="bottom-nav-inner">
    <?php foreach ($main_nav as $key => [$label, $href, $icon]): ?>
    <a href="<?= $href ?>" class="bnav-item <?= $active===$key?'active':'' ?>">
      <span class="bnav-icon"><?= $icon ?></span>
      <span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
    <button type="button" class="bnav-item <?= $more_active ? 'active' : '' ?>" x-on:click="moreOpen = !moreOpen" style="background:none;border:none;font-family:inherit;cursor:pointer">
      <span class="bnav-icon">&#8943;</span>
      <span>More</span>
    </button>
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

function day_colors(): array {
    $palette = ['#639922','#378ADD','#D4537E','#BA7517','#1D9E75','#8B5CF6','#E05C5C'];
    $colors = [];
    for ($i = 1; $i <= 7; $i++) {
        $colors["Day $i"] = $palette[$i - 1];
    }
    return $colors;
}

function get_countries(): array {
    return [
        'AF'=>'Afghanistan','AL'=>'Albania','DZ'=>'Algeria','AD'=>'Andorra','AO'=>'Angola',
        'AG'=>'Antigua & Deps','AR'=>'Argentina','AM'=>'Armenia','AU'=>'Australia','AT'=>'Austria',
        'AZ'=>'Azerbaijan','BS'=>'Bahamas','BH'=>'Bahrain','BD'=>'Bangladesh','BB'=>'Barbados',
        'BY'=>'Belarus','BE'=>'Belgium','BZ'=>'Belize','BJ'=>'Benin','BT'=>'Bhutan',
        'BO'=>'Bolivia','BA'=>'Bosnia Herzegovina','BW'=>'Botswana','BR'=>'Brazil','BN'=>'Brunei',
        'BG'=>'Bulgaria','BF'=>'Burkina','BI'=>'Burundi','KH'=>'Cambodia','CM'=>'Cameroon',
        'CA'=>'Canada','CV'=>'Cape Verde','CF'=>'Central African Rep','TD'=>'Chad','CL'=>'Chile',
        'CN'=>'China','CO'=>'Colombia','KM'=>'Comoros','CG'=>'Congo','CD'=>'Congo (Democratic Rep)',
        'CR'=>'Costa Rica','HR'=>'Croatia','CU'=>'Cuba','CY'=>'Cyprus','CZ'=>'Czech Republic',
        'DK'=>'Denmark','DJ'=>'Djibouti','DM'=>'Dominica','DO'=>'Dominican Republic','TL'=>'East Timor',
        'EC'=>'Ecuador','EG'=>'Egypt','SV'=>'El Salvador','GQ'=>'Equatorial Guinea','ER'=>'Eritrea',
        'EE'=>'Estonia','ET'=>'Ethiopia','FJ'=>'Fiji','FI'=>'Finland','FR'=>'France',
        'GA'=>'Gabon','GM'=>'Gambia','GE'=>'Georgia','DE'=>'Germany','GH'=>'Ghana',
        'GR'=>'Greece','GD'=>'Grenada','GT'=>'Guatemala','GN'=>'Guinea','GW'=>'Guinea-Bissau',
        'GY'=>'Guyana','HT'=>'Haiti','HN'=>'Honduras','HU'=>'Hungary','IS'=>'Iceland',
        'IN'=>'India','ID'=>'Indonesia','IR'=>'Iran','IQ'=>'Iraq','IE'=>'Ireland (Republic)',
        'IL'=>'Israel','IT'=>'Italy','CI'=>'Ivory Coast','JM'=>'Jamaica','JP'=>'Japan',
        'JO'=>'Jordan','KZ'=>'Kazakhstan','KE'=>'Kenya','KI'=>'Kiribati','KP'=>'Korea North',
        'KR'=>'Korea South','XK'=>'Kosovo','KW'=>'Kuwait','KG'=>'Kyrgyzstan','LA'=>'Laos',
        'LV'=>'Latvia','LB'=>'Lebanon','LS'=>'Lesotho','LR'=>'Liberia','LY'=>'Libya',
        'LI'=>'Liechtenstein','LT'=>'Lithuania','LU'=>'Luxembourg','MK'=>'Macedonia','MG'=>'Madagascar',
        'MW'=>'Malawi','MY'=>'Malaysia','MV'=>'Maldives','ML'=>'Mali','MT'=>'Malta',
        'MH'=>'Marshall Islands','MR'=>'Mauritania','MU'=>'Mauritius','MX'=>'Mexico','FM'=>'Micronesia',
        'MD'=>'Moldova','MC'=>'Monaco','MN'=>'Mongolia','ME'=>'Montenegro','MA'=>'Morocco',
        'MZ'=>'Mozambique','MM'=>'Myanmar (Burma)','NA'=>'Namibia','NR'=>'Nauru','NP'=>'Nepal',
        'NL'=>'Netherlands','NZ'=>'New Zealand','NI'=>'Nicaragua','NE'=>'Niger','NG'=>'Nigeria',
        'NO'=>'Norway','OM'=>'Oman','PK'=>'Pakistan','PW'=>'Palau','PA'=>'Panama',
        'PG'=>'Papua New Guinea','PY'=>'Paraguay','PE'=>'Peru','PH'=>'Philippines','PL'=>'Poland',
        'PT'=>'Portugal','QA'=>'Qatar','RO'=>'Romania','RU'=>'Russian Federation','RW'=>'Rwanda',
        'KN'=>'St Kitts & Nevis','LC'=>'St Lucia','VC'=>'Saint Vincent & the Grenadines',
        'WS'=>'Samoa','SM'=>'San Marino','ST'=>'Sao Tome & Principe','SA'=>'Saudi Arabia',
        'SN'=>'Senegal','RS'=>'Serbia','SC'=>'Seychelles','SL'=>'Sierra Leone','SG'=>'Singapore',
        'SK'=>'Slovakia','SI'=>'Slovenia','SB'=>'Solomon Islands','SO'=>'Somalia','ZA'=>'South Africa',
        'SS'=>'South Sudan','ES'=>'Spain','LK'=>'Sri Lanka','SD'=>'Sudan','SR'=>'Suriname',
        'SZ'=>'Swaziland','SE'=>'Sweden','CH'=>'Switzerland','SY'=>'Syria','TW'=>'Taiwan',
        'TJ'=>'Tajikistan','TZ'=>'Tanzania','TH'=>'Thailand','TG'=>'Togo','TO'=>'Tonga',
        'TT'=>'Trinidad & Tobago','TN'=>'Tunisia','TR'=>'Turkey','TM'=>'Turkmenistan','TV'=>'Tuvalu',
        'UG'=>'Uganda','UA'=>'Ukraine','AE'=>'United Arab Emirates','GB'=>'United Kingdom',
        'US'=>'United States','UY'=>'Uruguay','UZ'=>'Uzbekistan','VU'=>'Vanuatu','VA'=>'Vatican City',
        'VE'=>'Venezuela','VN'=>'Vietnam','YE'=>'Yemen','ZM'=>'Zambia','ZW'=>'Zimbabwe',
    ];
}

function active_plan(): ?array {
    $uid = active_user_id();
    if (!$uid) return null;
    try {
        $st = db()->prepare("SELECT * FROM plans WHERE is_active=1 AND user_id=? LIMIT 1");
        $st->execute([$uid]);
        return $st->fetch() ?: null;
    } catch (Exception $e) { return null; }
}
