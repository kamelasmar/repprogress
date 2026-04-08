# Workout Page UX Enhancements — Design Spec

## Overview

Redesign the Workout page (`workout.php`) for better UX. All PHP logic stays identical. HTML output changes use Tailwind utilities and Alpine.js. New features: perceived difficulty tracking, rest timer, smart weight suggestions, and improved set logging UX.

## Changes

### 1. Tailwind Migration

Replace all inline `style=""` attributes with Tailwind utility classes. Keep legacy CSS classes for base styling. Same pattern as Plan Manager and Plan Builder migrations.

### 2. Larger Touch-Friendly Set Logging Inputs

**Current:** Tiny inputs in a cramped flex row (50px-75px wide). Hard to tap on mobile.

**After:**
- Inputs use a responsive grid: `grid grid-cols-3 gap-2` on mobile, `flex gap-2` on desktop
- Minimum tap target size: `min-h-[44px]` on all inputs
- Slightly larger font: `text-sm` → `text-base` for input values
- Labels above each input (already present, just styled larger)

### 3. Smart Side Default

**Current:** Side selector always defaults to "Both".

**After:** For exercises with `is_left_priority = 1`:
- If no sets logged yet for this exercise today → default to the priority side (left)
- After logging a left set → default switches to "right" for the next set
- After logging both sides → default back to "both" or priority side
- For non-priority exercises → stays "both"

This is PHP logic only — check the last logged set for this exercise in the current session and set the `selected` attribute accordingly.

### 4. Last Session Reference + Weight Pre-fill

**Current:** No reference to previous performance. User must remember or check history.

**After:** For each exercise card, query the last session's sets for this exercise:

```sql
SELECT sl.weight_kg, sl.reps, sl.side, sl.difficulty
FROM sets_log sl
JOIN sessions s ON sl.session_id = s.id
WHERE sl.exercise_id = ? AND s.user_id = ? AND s.session_date < ?
ORDER BY s.session_date DESC, sl.set_number ASC
LIMIT 10
```

Display a compact "Last session" line below the exercise target info:
- "Last: 20kg × 12 (easy), 22.5kg × 10 (medium)"
- Muted text, small font

Pre-fill the weight input with the last session's weight for set 1. If the last difficulty was "easy", suggest +2.5kg. If "hard", suggest same weight.

### 5. Perceived Difficulty Field

**Current:** No difficulty tracking.

**After:**

**Database change:** Add column to `sets_log`:
```sql
ALTER TABLE sets_log ADD COLUMN difficulty ENUM('easy','medium','hard') DEFAULT NULL;
```

This is added via the existing migration system in `includes/migrate.php`.

**UI:** After the "Side" select and before the "+ Log" button, add three pill buttons for difficulty:
- 😊 Easy (green-dim background when selected)
- 😐 Medium (warn-dim background when selected)  
- 😤 Hard (red-dim background when selected)

Implemented with Alpine.js — clicking a pill sets a hidden input value. Default: no selection (null). The pills are styled as small toggle buttons.

The hidden input `<input type="hidden" name="difficulty">` is included in the form.

### 6. Rest Timer

**Current:** "Sec" field logs exercise duration.

**After:**

- Remove the "Sec" (duration_sec) input from the set logging form — it's rarely used and adds clutter
- After logging a set, show a rest countdown timer at the top of the exercise card
- Timer defaults to 30 seconds, with quick-select buttons: 30s, 60s, 90s, 120s
- Countdown displayed as a large number (e.g., "0:27") with a circular or linear progress indicator
- Timer uses Alpine.js `x-data` with `setInterval` — no backend needed
- When timer hits 0: the card flashes briefly (CSS animation) as a visual cue
- Timer is dismissible — "Skip" button to close it early
- Timer state is per-exercise, not global (multiple exercises can have independent timers)

**Note:** The `duration_sec` column stays in the database and sets_log INSERT — just pass NULL from the form. No schema change needed for this field.

### 7. Repeat Last Set Button

**Current:** User manually types weight/reps for every set, even when doing the same weight.

**After:**
- If at least one set is logged for this exercise today, show a "Repeat" ghost button next to "+ Log"
- Clicking "Repeat" pre-fills weight, reps, and side from the last logged set, increments set number, and submits the form immediately
- Implemented with Alpine.js reading from the logged sets data

### 8. Highlight Newly Logged Sets

**Current:** Page reloads after logging, set appears in the table with no visual distinction.

**After:**
- The URL includes `#ex-{exercise_id}` after logging (already implemented)
- Add a CSS animation: if the URL hash matches the exercise ID, the last row in the logged sets table gets a brief green glow animation (1s fade)
- Implemented with a small Alpine.js `x-init` that checks `window.location.hash`

### Data Requirements

**New database column:**
```sql
ALTER TABLE sets_log ADD COLUMN difficulty ENUM('easy','medium','hard') DEFAULT NULL;
```

Added via `includes/migrate.php`.

**New query per exercise** (last session data):
```sql
SELECT sl.weight_kg, sl.reps, sl.side, sl.difficulty
FROM sets_log sl
JOIN sessions s ON sl.session_id = s.id
WHERE sl.exercise_id = ? AND s.user_id = ? AND s.session_date < CURDATE()
ORDER BY s.session_date DESC, sl.set_number ASC
LIMIT 10
```

This query runs once per exercise on the page. For a typical day with 8-12 exercises, that's 8-12 additional queries — acceptable for this page size.

**Modified INSERT** for sets_log: add `difficulty` to the INSERT statement.

### What Does NOT Change

- All POST handlers (log_set, delete_set) — only modified to include the new `difficulty` field
- Day tabs behavior
- Section headers
- Exercise card structure (name, badges, target info, coach tip, video link)
- Session creation logic
- Progress bar and set counter
