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
}
