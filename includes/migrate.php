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
    } catch (Exception $e) {}
}
