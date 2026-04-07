# Changelog

All notable changes to Repprogress are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — 2026-04-07

### Initial release

#### Core features
- Dashboard with weight trend chart, volume by muscle group, weekly frequency, and recent sessions
- Workout logger — create sessions by day (Day 1–5), log sets with weight, reps, side (left/right/both)
- Weight tracker — daily log, trend chart, total delta and 7-day delta metrics
- Exercise programme — full 5-day neuro-functional plan pre-seeded (89 exercises)
- Schedule page — weekly split, cardio guide (HIIT vs Steady State), core protocol, 4-week calendar, 12-week roadmap

#### Programme design
- Day 1 (Tuesday): Lower Body — machine focus, no bar on shoulders (cervical stenosis safe)
- Day 2 (Wednesday): Push — left pec, serratus anterior, triceps
- Day 3 (Friday): Pull — left lat, rows, functional pulls
- Day 4 (Saturday): Arms & Functional — KB swings, ski erg, battle ropes, sled push
- Day 5 (Sunday): Full Body + Mobility OR Reformer Pilates option
- Thursday: Active recovery — Zone 2 cardio only
- Monday: Full rest

#### Left-side neuro-reconnection protocol
- All exercises support bilateral + left-emphasis unilateral logging
- Left/right side tracked separately in sets_log
- Exercise detail page shows left vs right weight progression chart

#### Technical
- PHP 8.0+ / MySQL 5.7+
- Browser-based installer (install.php) — no terminal required
- Auto-migration system (includes/migrate.php) — adds missing columns to existing databases on first load
- Dark theme throughout
- Mobile-first layout with bottom tab navigation on small screens
- YouTube search links per exercise (never break unlike direct video links)
- Inline exercise editor — edit name, video URL, coach tip, tags without leaving the page
- Add custom exercises per day/section
