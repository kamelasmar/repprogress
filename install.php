<?php
$errors=[]; $success=false; $log=[]; $cfg="";
$step = 'db'; // 'db' or 'admin'

// Check if config already exists and DB is set up
if (file_exists(__DIR__.'/includes/config.php')) {
    try {
        require_once __DIR__.'/includes/config.php';
        if (defined('DB_HOST') && DB_HOST) {
            $pdo = db();
            // Check if users table exists
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('users', $tables)) {
                // Check if any admin exists
                $admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
                if ($admin_count > 0) {
                    // Fully installed — redirect away
                    header('Location: login.php');
                    exit;
                }
                $step = 'admin';
            }
        }
    } catch (Throwable $e) {
        // Config exists but DB not accessible — allow re-install
    }
}

// Handle admin account creation
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['step'] ?? '') === 'admin') {
    try {
        require_once __DIR__.'/includes/config.php';
        require_once __DIR__.'/includes/auth.php';
        $pdo = db();

        $name  = trim($_POST['admin_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $phone = trim($_POST['admin_phone'] ?? '');
        $pass  = $_POST['admin_pass'] ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';

        if (!$name || !$email || !$phone || !$pass) {
            $errors[] = 'All fields are required.';
        } elseif ($pass !== $pass2) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($pass) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            $result = register_user($pdo, $email, $phone, $pass, $name);
            if ($result['ok']) {
                $admin_id = $result['user_id'];
                // Mark as admin and pre-verified, save DOB and country
                $admin_dob = $_POST['admin_dob'] ?? '';
                $admin_country = $_POST['admin_country'] ?? '';
                $pdo->prepare("UPDATE users SET is_admin=1, email_verified=1, verification_token=NULL, verification_expires=NULL, date_of_birth=?, country=? WHERE id=?")
                    ->execute([$admin_dob ?: null, $admin_country ?: null, $admin_id]);
                // Assign any seeded data (plans, sessions, weight, sets) to the admin
                foreach (['plans', 'sessions', 'sets_log', 'weight_log'] as $tbl) {
                    $pdo->prepare("UPDATE $tbl SET user_id=? WHERE user_id IS NULL")->execute([$admin_id]);
                }
                $success = true;
                $step = 'done';
            } else {
                $errors[] = $result['error'];
            }
        }

        if (!$success) $step = 'admin';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $step = 'admin';
    }
}

// Handle database setup
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['step'] ?? '') !== 'admin') {
    $host=trim($_POST['db_host']??'');
    $name=trim($_POST['db_name']??'');
    $user=trim($_POST['db_user']??'');
    $pass=$_POST['db_pass']??'';
    $mail_from=trim($_POST['mail_from']??'noreply@yourdomain.com');
    $app_url=rtrim(trim($_POST['app_url']??''),'/');
    if(!$host||!$name||!$user){ $errors[]='Host, database name, and username are all required.'; }
    else {
        try{
            $pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
            ]);
            $log[]="Connected to <strong>$name</strong> on <strong>$host</strong>";
            $sql_file=__DIR__.'/setup.sql';
            if(!file_exists($sql_file)) throw new Exception('setup.sql not found — make sure it is uploaded to the same folder as install.php.');
            $sql=file_get_contents($sql_file);
            $sql=preg_replace('/CREATE DATABASE.*?;\s*/si','',$sql);
            $sql=preg_replace('/USE\s+`?\w+`?;\s*/si','',$sql);
            $statements=array_filter(array_map('trim',explode(';',$sql)),fn($s)=>strlen($s)>5);
            $count=0;
            foreach($statements as $stmt){ $pdo->exec($stmt); $count++; }
            $log[]="Ran $count SQL statements";
            $tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $log[]='Tables created: '.implode(', ',$tables);
            $ex=$pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();
            $log[]="$ex exercises seeded";

            // Generate config.php with auth support
            $esc_host = addslashes($host);
            $esc_name = addslashes($name);
            $esc_user = addslashes($user);
            $esc_pass = addslashes($pass);
            $esc_mail  = addslashes($mail_from);
            $esc_url   = addslashes($app_url);
            $esc_sgkey = addslashes(trim($_POST['sendgrid_key'] ?? ''));

            $cfg = <<<'CONFIGTPL'
<?php
// ── Database Configuration ────────────────────────────────────────────────────
define('DB_HOST',    '%HOST%');
define('DB_NAME',    '%NAME%');
define('DB_USER',    '%USER%');
define('DB_PASS',    '%PASS%');
define('DB_CHARSET', 'utf8mb4');

// ── Email / App Configuration ─────────────────────────────────────────────────
define('MAIL_FROM',      '%MAIL%');
define('MAIL_FROM_NAME', 'Repprogress');
define('APP_URL',        '%URL%');
define('SENDGRID_API_KEY', '%SGKEY%');

// ── Load all functions (committed to git — always up to date) ────────────────
require_once __DIR__ . '/functions.php';
CONFIGTPL;

            $cfg = str_replace(
                ['%HOST%', '%NAME%', '%USER%', '%PASS%', '%MAIL%', '%URL%', '%SGKEY%'],
                [$esc_host, $esc_name, $esc_user, $esc_pass, $esc_mail, $esc_url, $esc_sgkey],
                $cfg
            );

            file_put_contents(__DIR__.'/includes/config.php', $cfg);
            $log[]='includes/config.php saved';

            // Now run migrations to create users table
            require_once __DIR__.'/includes/config.php';
            $step = 'admin';
            $success = false;
        }catch(Throwable $e){ $errors[]=$e->getMessage(); }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Repprogress</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8f7f4;color:#1a1916;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem;}
.box{background:#fff;border:1px solid #e8e6e0;border-radius:14px;padding:2rem;width:100%;max-width:540px;margin:auto;}
h1{font-size:20px;font-weight:700;margin-bottom:4px;}
.tagline{font-size:14px;color:#7a7872;margin-bottom:1.75rem;}
/* Steps */
.steps{background:#f8f7f4;border:1px solid #e8e6e0;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;}
.steps-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#7a7872;margin-bottom:10px;}
.step{display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px solid #e8e6e0;font-size:13px;line-height:1.5;color:#1a1916;}
.step:last-child{border-bottom:none;}
.step-num{width:20px;height:20px;border-radius:50%;background:#1D9E75;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
/* Form */
.field{margin-bottom:1rem;}
label{display:block;font-size:13px;font-weight:600;color:#7a7872;margin-bottom:4px;}
.label-hint{font-size:11px;font-weight:400;color:#aaa;margin-left:6px;}
input[type=text],input[type=password],input[type=email],input[type=tel],input[type=url]{width:100%;padding:10px 12px;border:1px solid #e8e6e0;border-radius:8px;font-size:14px;color:#1a1916;font-family:inherit;}
input:focus{outline:none;border-color:#1D9E75;box-shadow:0 0 0 3px rgba(29,158,117,0.12);}
.btn{display:block;width:100%;margin-top:1.5rem;padding:12px;background:#1D9E75;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;}
.btn:hover{background:#178a65;}
/* Messages */
.err{background:#fcebeb;border:1px solid #F7C1C1;color:#791F1F;padding:12px 14px;border-radius:8px;font-size:14px;margin-bottom:1.25rem;line-height:1.6;}
.ok{background:#e1f5ee;border:1px solid #9FE1CB;border-radius:10px;padding:1.5rem;}
.ok h2{font-size:18px;margin-bottom:12px;color:#085041;}
.logline{font-size:13px;color:#085041;padding:3px 0;line-height:1.5;}
.warn-box{background:#faeeda;border:1px solid #EF9F27;border-radius:8px;padding:10px 14px;font-size:13px;color:#633806;margin-top:1rem;line-height:1.5;}
.go{display:inline-block;margin-top:1.25rem;padding:11px 24px;background:#1D9E75;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:15px;}
code{background:#f0ede6;padding:1px 6px;border-radius:3px;font-size:12px;font-family:monospace;}
.success-steps{margin-top:1rem;}
.success-steps .step{border-color:#9FE1CB;}
</style>
</head>
<body>
<div class="box">

<?php if($step === 'done'): ?>
  <div class="ok">
    <h2>Installation complete!</h2>
    <div class="logline">Database configured and admin account created.</div>
    <div class="warn-box" style="margin-top:1rem">
      <strong>Security:</strong> Delete <code>install.php</code> once you're done.
    </div>
    <a href="login.php" class="go">Sign in to Repprogress</a>
  </div>

<?php elseif($step === 'admin'): ?>

  <h1>Create Admin Account</h1>
  <p class="tagline">Set up the administrator account for Repprogress</p>

  <?php if($log): ?>
  <div class="ok" style="margin-bottom:1.25rem">
    <?php foreach($log as $l): ?>
    <div class="logline"><?= $l ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if($errors): ?>
  <div class="err"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="step" value="admin">
    <div class="field">
      <label>Full Name</label>
      <input type="text" name="admin_name"
        value="<?= htmlspecialchars($_POST['admin_name']??'') ?>"
        placeholder="Your name" required>
    </div>
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="admin_email"
        value="<?= htmlspecialchars($_POST['admin_email']??'') ?>"
        placeholder="admin@example.com" required>
    </div>
    <div class="field">
      <label>Phone Number</label>
      <input type="tel" name="admin_phone"
        value="<?= htmlspecialchars($_POST['admin_phone']??'') ?>"
        placeholder="+1 (555) 123-4567" required>
    </div>
    <div style="display:flex;gap:12px">
      <div class="field" style="flex:1">
        <label>Date of Birth</label>
        <input type="date" name="admin_dob"
          value="<?= htmlspecialchars($_POST['admin_dob']??'') ?>">
      </div>
      <div class="field" style="flex:1">
        <label>Country</label>
        <select name="admin_country" style="width:100%;padding:10px 12px;border:1px solid #e8e6e0;border-radius:8px;font-size:14px;color:#1a1916;font-family:inherit">
          <option value="">-- Select --</option>
          <?php
          require_once __DIR__.'/includes/layout.php';
          foreach (get_countries() as $code => $cname): ?>
          <option value="<?= $code ?>" <?= ($_POST['admin_country']??'')===$code?'selected':'' ?>><?= htmlspecialchars($cname) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label>Password <span class="label-hint">at least 8 characters</span></label>
      <input type="password" name="admin_pass" minlength="8" required>
    </div>
    <div class="field">
      <label>Confirm Password</label>
      <input type="password" name="admin_pass2" minlength="8" required>
    </div>
    <button type="submit" class="btn">Create Admin Account</button>
  </form>

<?php else: ?>

  <h1>Repprogress</h1>
  <p class="tagline">One-time installer — fill in the credentials your hosting gave you</p>

  <?php if($errors): ?>
  <div class="err"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?></div>
  <?php endif; ?>

  <!-- Before you start -->
  <div class="steps">
    <div class="steps-title">Before you fill this in</div>
    <div class="step">
      <div class="step-num">1</div>
      <div>Log into your hosting control panel (cPanel, Plesk, or similar) and <strong>create a MySQL database</strong>. Note the exact database name.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div>Create a <strong>database user</strong> and assign it full privileges on that database. Note the username and password.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div>Find your <strong>MySQL hostname</strong> — usually shown on the database page. Often <code>localhost</code> but may differ on shared hosts.</div>
    </div>
    <div class="step">
      <div class="step-num">4</div>
      <div>Make sure <code>setup.sql</code> is uploaded to the same folder as this file.</div>
    </div>
  </div>

  <form method="post">
    <div class="field">
      <label>MySQL Hostname <span class="label-hint">from your hosting panel</span></label>
      <input type="text" name="db_host"
        value="<?= htmlspecialchars($_POST['db_host']??'') ?>"
        placeholder="e.g. localhost or sql123.example.com" required>
    </div>
    <div class="field">
      <label>Database Name <span class="label-hint">exactly as shown in cPanel</span></label>
      <input type="text" name="db_name"
        value="<?= htmlspecialchars($_POST['db_name']??'') ?>"
        placeholder="e.g. repprogress" required>
    </div>
    <div class="field">
      <label>Database Username</label>
      <input type="text" name="db_user"
        value="<?= htmlspecialchars($_POST['db_user']??'') ?>"
        placeholder="e.g. repprogress_user" required>
    </div>
    <div class="field">
      <label>Database Password</label>
      <input type="password" name="db_pass" placeholder="your database user password">
    </div>

    <div style="border-top:1px solid #e8e6e0;padding-top:1.25rem;margin-top:1.25rem">
      <div class="steps-title" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#7a7872;margin-bottom:10px">App Settings</div>
      <div class="field">
        <label>Email From Address <span class="label-hint">for verification emails</span></label>
        <input type="email" name="mail_from"
          value="<?= htmlspecialchars($_POST['mail_from']??'noreply@repprogress.com') ?>"
          placeholder="noreply@repprogress.com">
      </div>
      <div class="field">
        <label>App URL <span class="label-hint">no trailing slash</span></label>
        <input type="url" name="app_url"
          value="<?= htmlspecialchars($_POST['app_url']??'') ?>"
          placeholder="https://repprogress.com">
      </div>
      <div class="field">
        <label>SendGrid API Key <span class="label-hint">for sending verification emails</span></label>
        <input type="password" name="sendgrid_key"
          value="<?= htmlspecialchars($_POST['sendgrid_key']??'') ?>"
          placeholder="SG.xxxxxxxxxx">
      </div>
    </div>

    <button type="submit" class="btn">Install</button>
  </form>

<?php endif; ?>
</div>
</body>
</html>
