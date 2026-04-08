# Plan Manager UX Enhancements — Design Spec

## Overview

Redesign the Plan Manager page (`plan_manager.php`) for better UX. All PHP logic (POST handlers, queries, redirects) stays identical. Only the HTML output changes, using Tailwind utilities and Alpine.js (already set up in the project).

## Changes

### 1. Active Plan Card — Enhanced

**Current:** Dense metadata line, tiny 6px progress bar, only "Edit Plan" button.

**After:**

- **Larger progress bar** — 8px height with percentage label displayed inline next to the bar (e.g., "42%").
- **"Start Workout" button** — Green primary button alongside "Edit Plan". Links to `workout.php?day=X` where X is today's scheduled day from `plan_days.week_day` (uses `date('D')` to match). Falls back to `workout.php` (no param) if no day matches today (rest day).
- **"Last session" indicator** — Small muted text below the metadata line showing "Last session: X days ago" or "No sessions yet". Queried from `sessions` table: `SELECT MAX(session_date) FROM sessions WHERE plan_id=? AND user_id=?`.
- **Visual emphasis** — Active card gets `shadow-[0_0_0_1px_var(--accent),0_0_12px_var(--accent-dim)]` for a subtle accent glow. Slightly more padding than inactive cards.

**Inactive plan cards** remain as-is (no changes).

### 2. Create Plan — Single Full-Width Card with Tabs

**Current:** Two side-by-side cards (Create New Plan + Clone from Existing) in a `.grid-2`. Heights are unbalanced.

**After:** One single full-width card below the plan list, with three tabs.

**Tabs:** "Blank Plan" | "AI Generated" | "Clone from Existing"

- Tabs implemented with Alpine.js: `x-data="{ tab: 'blank' }"`
- Tab bar styled as horizontal pill buttons (similar to day pills)
- Active tab gets accent background, inactive tabs get ghost styling

**Tab content:**

- **Blank Plan** (default active tab): The existing create form — plan name, training days, phase #, duration, start date, description, submit button. Shown immediately, no extra click needed.
- **AI Generated**: Brief description text + "Open AI Builder →" primary button linking to `ai_builder.php`. Only shown if `openai_api_key_configured()` returns true. If not configured, this tab is hidden entirely.
- **Clone from Existing**: The existing clone form — source plan select, new name, phase #, duration, start date, description, submit button. Only shown if `$plans` is non-empty. If no plans exist, this tab is hidden entirely.

### 3. Info Box — Dismissible Tip

**Current:** Always-visible info box at the bottom explaining how plan switching works.

**After:**

- Wrapped in Alpine.js: `x-data="{ show: !localStorage.getItem('rp_plans_tip_dismissed') }"`
- Conditionally shown via `x-show="show"` with `x-transition`
- Small "×" dismiss button on the right side
- On click: sets `localStorage.setItem('rp_plans_tip_dismissed', '1')` and hides

### Data Requirements

One new query needed for the "last session" indicator on the active plan card:

```sql
SELECT MAX(session_date) FROM sessions WHERE plan_id = ? AND user_id = ?
```

One new query needed for the "Start Workout" button day detection:

```sql
SELECT day_label FROM plan_days WHERE plan_id = ? AND week_day = ?
```

Both queries are added to the PHP section at the top of plan_manager.php (before `render_head()`), only executed when there is an active plan.

### What Does NOT Change

- All POST handlers (create, clone, activate, delete)
- Flash message rendering
- The sidebar/bottom nav
- Other pages
- Database schema
