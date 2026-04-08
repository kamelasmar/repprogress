<?php
/**
 * migrate.php — runs automatically on every page load, costs ~1ms.
 * Adds missing columns to existing tables without dropping data.
 * Safe to run repeatedly.
 */
function run_migrations(PDO $db): void {

    // ── Get existing columns ──────────────────────────────────────────────
    $cols = $db->query("SHOW COLUMNS FROM exercises")->fetchAll(PDO::FETCH_COLUMN);

    $migrations = [
        'day_label'         => "ALTER TABLE exercises ADD COLUMN day_label VARCHAR(20) DEFAULT NULL",
        'day_title'         => "ALTER TABLE exercises ADD COLUMN day_title VARCHAR(60) DEFAULT NULL",
        'section'           => "ALTER TABLE exercises ADD COLUMN section VARCHAR(100) DEFAULT NULL",
        'section_order'     => "ALTER TABLE exercises ADD COLUMN section_order TINYINT DEFAULT 0",
        'is_left_priority'  => "ALTER TABLE exercises ADD COLUMN is_left_priority TINYINT(1) DEFAULT 0",
        'is_mobility'       => "ALTER TABLE exercises ADD COLUMN is_mobility TINYINT(1) DEFAULT 0",
        'is_core'           => "ALTER TABLE exercises ADD COLUMN is_core TINYINT(1) DEFAULT 0",
        'is_functional'     => "ALTER TABLE exercises ADD COLUMN is_functional TINYINT(1) DEFAULT 0",
        'cardio_type'       => "ALTER TABLE exercises ADD COLUMN cardio_type ENUM('none','steady_state','hiit') DEFAULT 'none'",
        'both_sides'        => "ALTER TABLE exercises ADD COLUMN both_sides TINYINT(1) DEFAULT 0",
        'youtube_url'       => "ALTER TABLE exercises ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL",
        'coach_tip'         => "ALTER TABLE exercises ADD COLUMN coach_tip TEXT DEFAULT NULL",
    ];

    foreach ($migrations as $col => $sql) {
        if (!in_array($col, $cols)) {
            try { $db->exec($sql); } catch (Exception $e) { /* already exists, skip */ }
        }
    }

    // ── sessions table ────────────────────────────────────────────────────
    try {
        $scols = $db->query("SHOW COLUMNS FROM sessions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('duration_min', $scols))
            $db->exec("ALTER TABLE sessions ADD COLUMN duration_min INT DEFAULT NULL");
        if (!in_array('notes', $scols))
            $db->exec("ALTER TABLE sessions ADD COLUMN notes TEXT DEFAULT NULL");
    } catch (Exception $e) {}

    // ── sets_log table ────────────────────────────────────────────────────
    try {
        $lcols = $db->query("SHOW COLUMNS FROM sets_log")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('duration_sec', $lcols))
            $db->exec("ALTER TABLE sets_log ADD COLUMN duration_sec INT DEFAULT NULL");
        if (!in_array('side', $lcols))
            $db->exec("ALTER TABLE sets_log ADD COLUMN side ENUM('left','right','both') DEFAULT 'both'");
        if (!in_array('notes', $lcols))
            $db->exec("ALTER TABLE sets_log ADD COLUMN notes TEXT DEFAULT NULL");
        if (!in_array('difficulty', $lcols))
            $db->exec("ALTER TABLE sets_log ADD COLUMN difficulty ENUM('easy','medium','hard') DEFAULT NULL");
    } catch (Exception $e) {}

    // ── users table ────────────────────────────────────────────────────
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(30) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email_verified TINYINT(1) DEFAULT 0,
            verification_token VARCHAR(64) DEFAULT NULL,
            verification_expires DATETIME DEFAULT NULL,
            reset_token VARCHAR(64) DEFAULT NULL,
            reset_expires DATETIME DEFAULT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME DEFAULT NULL,
            INDEX idx_verification_token (verification_token),
            INDEX idx_reset_token (reset_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {}

    // ── Add user_id to plans, sessions, sets_log, weight_log ────────
    $user_tables = ['plans', 'sessions', 'sets_log', 'weight_log'];
    foreach ($user_tables as $tbl) {
        try {
            $tcols = $db->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('user_id', $tcols)) {
                $db->exec("ALTER TABLE $tbl ADD COLUMN user_id INT DEFAULT NULL");
                $db->exec("ALTER TABLE $tbl ADD INDEX idx_{$tbl}_user (user_id)");
            }
        } catch (Exception $e) {}
    }

    // ── Update weight_log unique constraint for multi-user ──────────
    try {
        $keys = $db->query("SHOW INDEX FROM weight_log WHERE Key_name = 'unique_date'")->fetchAll();
        if ($keys) {
            $db->exec("ALTER TABLE weight_log DROP INDEX unique_date");
            $db->exec("ALTER TABLE weight_log ADD UNIQUE KEY unique_user_date (user_id, logged_date)");
        }
    } catch (Exception $e) {}

    // ── Add exercise approval columns ───────────────────────────────
    $ecols = $db->query("SHOW COLUMNS FROM exercises")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_by', $ecols)) {
        try { $db->exec("ALTER TABLE exercises ADD COLUMN created_by INT DEFAULT NULL"); } catch (Exception $e) {}
    }
    if (!in_array('status', $ecols)) {
        try {
            $db->exec("ALTER TABLE exercises ADD COLUMN status ENUM('approved','pending') DEFAULT 'pending'");
            // Mark all existing exercises as approved (original library)
            $db->exec("UPDATE exercises SET status = 'approved' WHERE status = 'pending' OR status IS NULL");
        } catch (Exception $e) {}
    }
    if (!in_array('is_suggested', $ecols)) {
        try { $db->exec("ALTER TABLE exercises ADD COLUMN is_suggested TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    }

    // ── Add body composition columns to weight_log ──────────────────
    try {
        $wcols = $db->query("SHOW COLUMNS FROM weight_log")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('body_fat_pct', $wcols)) {
            $db->exec("ALTER TABLE weight_log ADD COLUMN body_fat_pct DECIMAL(4,1) DEFAULT NULL");
        }
        if (!in_array('muscle_mass_pct', $wcols)) {
            $db->exec("ALTER TABLE weight_log ADD COLUMN muscle_mass_pct DECIMAL(4,1) DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // ── Add name and pending_email to users ─────────────────────────
    try {
        $ucols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('name', $ucols)) {
            $db->exec("ALTER TABLE users ADD COLUMN name VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('pending_email', $ucols)) {
            $db->exec("ALTER TABLE users ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('date_of_birth', $ucols)) {
            $db->exec("ALTER TABLE users ADD COLUMN date_of_birth DATE DEFAULT NULL");
        }
        if (!in_array('country', $ucols)) {
            $db->exec("ALTER TABLE users ADD COLUMN country VARCHAR(2) DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // ── Plan sharing token ────────────────────────────────────────────
    try {
        $pcols = $db->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('share_token', $pcols))
            $db->exec("ALTER TABLE plans ADD COLUMN share_token VARCHAR(32) DEFAULT NULL");
    } catch (Exception $e) {}

    // ── Rename "Hip Mobility" section to "Mobility" ─────────────────
    try {
        $db->exec("UPDATE plan_exercises SET section='Mobility' WHERE section='Hip Mobility'");
    } catch (Exception $e) {}

    // ── Shared access table ─────────────────────────────────────────
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS shared_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            granted_to INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_share (owner_id, granted_to),
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (granted_to) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {}

    // ── Assign orphaned data to first admin ─────────────────────────
    try {
        $orphans = $db->query("SELECT COUNT(*) FROM plans WHERE user_id IS NULL")->fetchColumn();
        if ($orphans > 0) {
            $admin = $db->query("SELECT id FROM users WHERE is_admin=1 ORDER BY id LIMIT 1")->fetch();
            if ($admin) {
                $aid = $admin['id'];
                foreach (['plans', 'sessions', 'sets_log', 'weight_log'] as $tbl) {
                    $db->exec("UPDATE $tbl SET user_id=$aid WHERE user_id IS NULL");
                }
            }
        }
    } catch (Exception $e) {}

    // ── Add is_class flag to exercises ──────────────────────────────
    try {
        $ecols2 = $db->query("SHOW COLUMNS FROM exercises")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_class', $ecols2))
            $db->exec("ALTER TABLE exercises ADD COLUMN is_class TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    // ── Recategorize legacy muscle groups ─────────────────────────────
    try {
        $db->exec("UPDATE exercises SET muscle_group='Back' WHERE muscle_group='Lats'");
        $db->exec("UPDATE exercises SET muscle_group='Quads' WHERE muscle_group='Legs'");
        $db->exec("UPDATE exercises SET muscle_group='Mobility' WHERE muscle_group='Recovery'");
        $db->exec("UPDATE exercises SET muscle_group='Chest' WHERE muscle_group='Serratus Anterior'");
    } catch (Exception $e) {}

    // ── Seed admin's Phase 1 plan ───────────────────────────────────
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS _migrations (name VARCHAR(100) PRIMARY KEY, ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $seeded = $db->query("SELECT 1 FROM _migrations WHERE name='seed_phase1'")->fetch();
        if (!$seeded) {
            $admin = $db->query("SELECT id FROM users WHERE is_admin=1 ORDER BY id LIMIT 1")->fetch();
            if ($admin) {
                $aid = (int)$admin['id'];
                $db->exec("INSERT INTO plans (name, description, phase_number, weeks_duration, start_date, end_date, is_active, user_id) VALUES (
                    'Phase 1 — Reconnection',
                    'Weeks 1–8. Focus: neural pathway re-establishment, machine-based lower body (cervical safe), hip mobility daily, core 2× daily.',
                    1, 8, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 56 DAY), 1, $aid
                )");
                $p1 = (int)$db->lastInsertId();

                // Plan Days
                $db->exec("INSERT INTO plan_days (plan_id, day_label, day_title, day_order, week_day, cardio_type, cardio_description) VALUES
                    ($p1,'Day 1','Lower Body',1,'Tue','steady_state','Rowing Machine 10 min — Zone 2 warm-up'),
                    ($p1,'Day 2','Push',2,'Wed','hiit','Ski Erg HIIT — 20s hard / 40s easy × 6 rounds'),
                    ($p1,'Day 3','Pull',3,'Fri','steady_state','Rowing Machine 10 min — Zone 2, lat primer'),
                    ($p1,'Day 4','Arms & Functional',4,'Sat','hiit','Ski Erg HIIT — 20s hard / 40s easy × 8 rounds'),
                    ($p1,'Day 5','Full Body + Mobility',5,'Sun','steady_state','Stationary Bike 15 min — Zone 2, easy recovery')
                ");

                // Helper to insert plan exercise by name
                $ins = function($day, $section, $so, $sort, $sets, $reps, $name, $notes = null) use ($db, $p1) {
                    $eid = $db->query("SELECT id FROM exercises WHERE name=" . $db->quote($name) . " LIMIT 1")->fetchColumn();
                    if (!$eid) return;
                    $n = $notes ? $db->quote($notes) : 'NULL';
                    $db->exec("INSERT INTO plan_exercises (plan_id,day_label,exercise_id,section,section_order,sort_order,sets_target,reps_target,notes) VALUES ($p1,'$day',$eid,'$section',$so,$sort,$sets,'$reps',$n)");
                };

                // DAY 1 — LOWER BODY
                $ins('Day 1','Cardio Warm-Up',1,1,1,'10 min','Rowing Machine — Steady State','Zone 2 — 22–24 spm');
                $ins('Day 1','Mobility',2,1,3,'60 sec','90/90 Hip Switch');
                $ins('Day 1','Mobility',2,2,3,'8 each','Hip CARs');
                $ins('Day 1','Mobility',2,3,3,'8 each','World Greatest Stretch');
                $ins('Day 1','Core Block A',3,1,3,'10 each','Dead Bug');
                $ins('Day 1','Core Block A',3,2,3,'8','McGill Curl-Up');
                $ins('Day 1','Core Block A',3,3,3,'10 each','Bird Dog');
                $ins('Day 1','Main Work',4,1,3,'12-15','Leg Press (Machine)');
                $ins('Day 1','Main Work',4,2,3,'12','Leg Curl — Lying (Machine)');
                $ins('Day 1','Main Work',4,3,3,'12','Leg Extension (Machine)');
                $ins('Day 1','Main Work',4,4,3,'15','Hip Abductor Machine');
                $ins('Day 1','Main Work',4,5,3,'15','Hip Adductor Machine');
                $ins('Day 1','Main Work',4,6,3,'12','Glute Bridge — Machine or Band');
                $ins('Day 1','Functional',5,1,4,'15','Kettlebell Deadlift');
                $ins('Day 1','Functional',5,2,3,'30m','Farmer Carry — Single Arm');
                $ins('Day 1','Core Block B',6,1,3,'12 each','Pallof Press — Tall Kneeling');
                $ins('Day 1','Core Block B',6,2,3,'45 sec','Plank — Forearm');
                $ins('Day 1','Cool-Down',7,1,2,'90 sec','Couch Stretch');
                $ins('Day 1','Cool-Down',7,2,2,'90 sec','Pigeon Pose');

                // DAY 2 — PUSH
                $ins('Day 2','Cardio Warm-Up',1,1,1,'6 rounds','Ski Erg — Intervals','20s hard / 40s easy');
                $ins('Day 2','Mobility',2,1,3,'60 sec','90/90 Hip Switch');
                $ins('Day 2','Core Block A',3,1,3,'10 each','Dead Bug');
                $ins('Day 2','Core Block A',3,2,3,'20 sec each','Copenhagen Plank');
                $ins('Day 2','Activation',4,1,3,'12','Serratus Wall Slide');
                $ins('Day 2','Activation',4,2,3,'15','Pec Deck / Machine Fly');
                $ins('Day 2','Main Work',5,1,3,'10-12','Chest Press Machine — Seated');
                $ins('Day 2','Main Work',5,2,4,'10','Single-Arm DB Floor Press');
                $ins('Day 2','Main Work',5,3,3,'10','Landmine Press');
                $ins('Day 2','Main Work',5,4,3,'8+5s hold','Push-Up with Scapular Protraction Hold');
                $ins('Day 2','Finisher',6,1,3,'15','Cable Crossover — Low to High');
                $ins('Day 2','Core Block B',7,1,3,'8','Ab Wheel Rollout');
                $ins('Day 2','Core Block B',7,2,3,'30 sec each','Side Plank');

                // DAY 3 — PULL
                $ins('Day 3','Cardio Warm-Up',1,1,1,'10 min','Rowing Machine — Steady State','22–24 spm');
                $ins('Day 3','Mobility',2,1,2,'90 sec each','Lat Prayer Stretch on Foam Roller');
                $ins('Day 3','Mobility',2,2,2,'10 each','Lateral Lunge + Adductor Stretch');
                $ins('Day 3','Core Block A',3,1,3,'10 each','Bird Dog');
                $ins('Day 3','Core Block A',3,2,3,'10 each','Dead Bug');
                $ins('Day 3','Activation',4,1,3,'15','Straight-Arm Cable Pulldown');
                $ins('Day 3','Main Work',5,1,4,'12','Cable Lat Pulldown — Single Arm');
                $ins('Day 3','Main Work',5,2,4,'10','Seated Cable Row — Single Arm');
                $ins('Day 3','Main Work',5,3,3,'8','Assisted Pull-Up / Ring Row');
                $ins('Day 3','Main Work',5,4,3,'12','TRX Row with Protraction');
                $ins('Day 3','Functional',6,1,4,'15','Kettlebell Swing — Two Hand');
                $ins('Day 3','Functional',6,2,3,'12 each','Kettlebell Swing — Single Arm');
                $ins('Day 3','Cool-Down',7,1,3,'30 sec','Passive Dead Hang');
                $ins('Day 3','Core Block B',8,1,3,'12 each','Cable Crunch');
                $ins('Day 3','Core Block B',8,2,3,'30m each','Suitcase Carry');

                // DAY 4 — ARMS & FUNCTIONAL
                $ins('Day 4','Cardio Warm-Up',1,1,1,'8 rounds','Ski Erg — Intervals','20s hard / 40s easy');
                $ins('Day 4','Mobility',2,1,2,'90 sec','Supine Psoas Release');
                $ins('Day 4','Mobility',2,2,2,'60 sec','Frog Stretch');
                $ins('Day 4','Core Block A',3,1,3,'8','McGill Curl-Up');
                $ins('Day 4','Core Block A',3,2,3,'20 sec each','Side Plank + Hip Dip');
                $ins('Day 4','Activation',4,1,3,'15','Overhead Cable Extension');
                $ins('Day 4','Activation',4,2,3,'15','Serratus Punch — Standing Cable');
                $ins('Day 4','Main Work',5,1,3,'10','Single-Arm Overhead DB Press');
                $ins('Day 4','Main Work',5,2,3,'12','Single-Arm Cable Tricep Pushdown');
                $ins('Day 4','Main Work',5,3,3,'10','Tricep Dip Machine');
                $ins('Day 4','Functional',6,1,4,'15','Kettlebell Swing — Two Hand');
                $ins('Day 4','Functional',6,2,1,'6 rounds','Battle Ropes — Alternating Waves');
                $ins('Day 4','Functional',6,3,3,'30m each','Farmer Carry — Single Arm');
                $ins('Day 4','Functional',6,4,3,'20m','Sled Push');
                $ins('Day 4','Core Block B',7,1,3,'45 sec','Plank — Forearm');
                $ins('Day 4','Core Block B',7,2,3,'12 each','Pallof Press — Tall Kneeling');

                // DAY 5 — FULL BODY + MOBILITY
                $ins('Day 5','Cardio Warm-Up',1,1,1,'15 min','Stationary Bike — Steady State','Zone 2 — easy pace');
                $ins('Day 5','Mobility',2,1,1,'10 min','90/90 Hip Switch');
                $ins('Day 5','Mobility',2,2,3,'10 each','Deep Squat + Thoracic Rotation');
                $ins('Day 5','Mobility',2,3,3,'10 each','Cossack Squat');
                $ins('Day 5','Core Block A',3,1,3,'10 each','Dead Bug');
                $ins('Day 5','Core Block A',3,2,3,'10 each','Bird Dog');
                $ins('Day 5','Main Work',4,1,3,'12 each','Pallof Press — Tall Kneeling');
                $ins('Day 5','Main Work',4,2,3,'15','Cable Crossover — Low to High');
                $ins('Day 5','Main Work',4,3,3,'12','Single-Arm Lat Pullover');
                $ins('Day 5','Main Work',4,4,4,'15','Kettlebell Deadlift');
                $ins('Day 5','Main Work',4,5,1,'5x250m','Rowing Machine — Power Intervals','250m hard / 90 sec rest');
                $ins('Day 5','Cool-Down',5,1,3,'30 sec','Passive Dead Hang');
                $ins('Day 5','Cool-Down',6,1,1,'5 min','Box Breathing 4-4-4-4');
                $ins('Day 5','Core Block B',7,1,3,'8','Ab Wheel Rollout');
                $ins('Day 5','Core Block B',7,2,3,'20 sec each','Copenhagen Plank');

                $db->exec("INSERT INTO _migrations (name) VALUES ('seed_phase1')");
            }
        }
    } catch (Exception $e) { error_log('seed_phase1 migration failed: ' . $e->getMessage()); }

    // ── One-time production cleanup (v2.0) ───────────────────────────
    // Wipe test data, keep exercises and users. Safe to run repeatedly
    // — only executes if the flag table doesn't have this migration.
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS _migrations (name VARCHAR(100) PRIMARY KEY, ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $done = $db->query("SELECT 1 FROM _migrations WHERE name='v2_cleanup'")->fetch();
        if (!$done) {
            $db->exec("SET FOREIGN_KEY_CHECKS=0");
            $db->exec("TRUNCATE TABLE sets_log");
            $db->exec("TRUNCATE TABLE sessions");
            $db->exec("TRUNCATE TABLE plan_exercises");
            $db->exec("TRUNCATE TABLE plan_days");
            $db->exec("TRUNCATE TABLE plans");
            $db->exec("TRUNCATE TABLE weight_log");
            $db->exec("TRUNCATE TABLE shared_access");
            $db->exec("SET FOREIGN_KEY_CHECKS=1");
            $db->exec("INSERT INTO _migrations (name) VALUES ('v2_cleanup')");
        }
    } catch (Exception $e) {}

    // ── v2_reseed: wipe everything + reseed plan for admin ───────────
    try {
        $done = $db->query("SELECT 1 FROM _migrations WHERE name='v2_reseed'")->fetch();
        if (!$done) {
            // Wipe all user data
            $db->exec("SET FOREIGN_KEY_CHECKS=0");
            $db->exec("TRUNCATE TABLE sets_log");
            $db->exec("TRUNCATE TABLE sessions");
            $db->exec("TRUNCATE TABLE plan_exercises");
            $db->exec("TRUNCATE TABLE plan_days");
            $db->exec("TRUNCATE TABLE plans");
            $db->exec("TRUNCATE TABLE weight_log");
            $db->exec("TRUNCATE TABLE shared_access");
            $db->exec("SET FOREIGN_KEY_CHECKS=1");

            // Delete seed_phase1 flag so it re-runs
            $db->exec("DELETE FROM _migrations WHERE name='seed_phase1'");

            $db->exec("INSERT INTO _migrations (name) VALUES ('v2_reseed')");
        }
    } catch (Exception $e) {}
}
