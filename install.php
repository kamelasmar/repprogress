<?php
$errors=[]; $success=false; $log=[]; $cfg="";
if($_SERVER['REQUEST_METHOD']==='POST'){
    $host=trim($_POST['db_host']??'');
    $name=trim($_POST['db_name']??'');
    $user=trim($_POST['db_user']??'');
    $pass=$_POST['db_pass']??'';
    if(!$host||!$name||!$user){ $errors[]='Host, database name, and username are all required.'; }
    else {
        try{
            $pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
            ]);
            $log[]="✅ Connected to <strong>$name</strong> on <strong>$host</strong>";
            $sql_file=__DIR__.'/setup.sql';
            if(!file_exists($sql_file)) throw new Exception('setup.sql not found — make sure it is uploaded to the same folder as install.php.');
            $sql=file_get_contents($sql_file);
            $sql=preg_replace('/CREATE DATABASE.*?;\s*/si','',$sql);
            $sql=preg_replace('/USE\s+`?\w+`?;\s*/si','',$sql);
            $statements=array_filter(array_map('trim',explode(';',$sql)),fn($s)=>strlen($s)>5);
            $count=0;
            foreach($statements as $stmt){ $pdo->exec($stmt); $count++; }
            $log[]="✅ Ran $count SQL statements";
            $tables=$pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $log[]='✅ Tables created: '.implode(', ',$tables);
            $ex=$pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();
            $log[]="✅ $ex exercises seeded across Days 1–5";
            $cfg="<?php\ndefine('DB_HOST','$host');\ndefine('DB_NAME','$name');\ndefine('DB_USER','$user');\ndefine('DB_PASS','$pass');\ndefine('DB_CHARSET','utf8mb4');\nfunction db():PDO{static \$p=null;if(\$p===null){\$d='mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;\$p=new PDO(\$d,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}return \$p;}\nfunction flash(string \$m,string \$t='success'):void{\$_SESSION['flash']=['msg'=>\$m,'type'=>\$t];}\nfunction get_flash():?array{\$f=\$_SESSION['flash']??null;unset(\$_SESSION['flash']);return \$f;}\nsession_start();\n";
            file_put_contents(__DIR__.'/includes/config.php',$cfg);
            $log[]='✅ includes/config.php saved with your credentials';
            $success=true;
        }catch(Throwable $e){ $errors[]=$e->getMessage(); }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Kamel's Workout Tracker</title>
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
input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #e8e6e0;border-radius:8px;font-size:14px;color:#1a1916;font-family:inherit;}
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
</style>
</head>
<body>
<div class="box">

<?php if($success): ?>
  <div class="ok">
    <h2>✅ Installation complete!</h2>
    <?php foreach($log as $l): if($l==='MANUAL_CFG') continue; ?>
    <div class="logline"><?= $l ?></div>
    <?php endforeach; ?>
    <?php if (in_array('MANUAL_CFG', $log)): ?>
    <div class="warn-box" style="margin-top:1rem;background:#1a1a1a;border-color:#d4924a;color:#f5b76a">
      ⚠️ <strong>Could not write config automatically.</strong><br><br>
      Open <code>includes/config.php</code> on your server and replace the contents with:<br><br>
      <textarea style="width:100%;height:160px;font-family:monospace;font-size:12px;background:#111;color:#4dd8a7;border:1px solid #333;border-radius:6px;padding:8px;margin-top:6px" onclick="this.select()"><?php
        echo htmlspecialchars($cfg);
      ?></textarea>
      <small>Click the box and copy all text, then paste it into your config file.</small>
    </div>
    <?php else: ?>
    <div class="warn-box" style="margin-top:1rem">
      🔒 <strong>Security:</strong> Delete <code>install.php</code> once you're done.
    </div>
    <a href="index.php" class="go">Open Kamel's Workout Tracker →</a>
    <?php endif; ?>
  </div>

<?php else: ?>

  <h1>Kamel's Workout Tracker</h1>
  <p class="tagline">One-time installer — fill in the credentials your hosting gave you</p>

  <?php if($errors): ?>
  <div class="err">❌ <?= implode('<br>',array_map('htmlspecialchars',$errors)) ?></div>
  <?php endif; ?>

  <!-- Before you start -->
  <div class="steps">
    <div class="steps-title">Before you fill this in</div>
    <div class="step">
      <div class="step-num">1</div>
      <div>Log into your hosting control panel (cPanel, Plesk, or similar) and <strong>create a MySQL database</strong>. Note the exact database name — it's usually prefixed, like <code>if0_41598676_fittrack</code>.</div>
    </div>
    <div class="step">
      <div class="step-num">2</div>
      <div>Create a <strong>database user</strong> and assign it full privileges on that database. Note the username and password.</div>
    </div>
    <div class="step">
      <div class="step-num">3</div>
      <div>Find your <strong>MySQL hostname</strong> — usually shown on the database page in cPanel. It's often <code>localhost</code> but on shared hosts may be something like <code>sql123.infinityfree.com</code>.</div>
    </div>
    <div class="step">
      <div class="step-num">4</div>
      <div>Make sure <code>setup.sql</code> is uploaded to the same folder as this file.</div>
    </div>
    <div class="step">
      <div class="step-num">5</div>
      <div>Fill in the form below with those exact values and hit Install.</div>
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
        placeholder="e.g. if0_41598676_fittrack" required>
    </div>
    <div class="field">
      <label>Database Username <span class="label-hint">the user you assigned to this DB</span></label>
      <input type="text" name="db_user"
        value="<?= htmlspecialchars($_POST['db_user']??'') ?>"
        placeholder="e.g. if0_41598676" required>
    </div>
    <div class="field">
      <label>Database Password</label>
      <input type="password" name="db_pass" placeholder="your database user password">
    </div>
    <button type="submit" class="btn">Install →</button>
  </form>

<?php endif; ?>
</div>
</body>
</html>
