# Plan Builder UX Enhancements — Design Spec

## Overview

Redesign the Plan Builder page (`plan_builder.php`) for better UX. All PHP logic (POST handlers, queries, redirects) stays identical. HTML output changes use Tailwind utilities and Alpine.js. One new interactive component: a category-based exercise picker.

## Changes

### 1. Tailwind Migration

Replace all inline `style=""` attributes with Tailwind utility classes. Keep legacy CSS classes (`.card`, `.btn`, `.form-group`, `.form-row`, `.section-hdr`, `.badge`, etc.) for base styling. Same pattern as Plan Manager migration.

### 2. Collapsible Day Settings

**Current:** Day Settings card is always expanded at the top, taking prime real estate.

**After:**

- Wrapped in Alpine.js: `x-data="{ open: false }"`
- Default collapsed — shows a single summary line: "Day Title · Recommended Day · Cardio Type" with an "Edit" button
- Clicking "Edit" expands the full form with smooth `x-transition`
- After saving, returns to collapsed state (page reloads via PRG)

### 3. Interactive Exercise Picker

**Current:** A single `<select>` dropdown with 50+ exercises grouped by optgroup. Hard to scan, no exercise details visible.

**After:** A two-step picker built with Alpine.js:

**Step 1 — Category buttons:**
- Horizontal row of pill buttons, one per muscle group (from `$ex_by_mg` keys)
- Clicking a category shows the exercises for that group
- An "All" button shows everything (default state on first load)

**Step 2 — Exercise browse list:**
- Each exercise displays as a compact row within the Add Exercise card:
  - Exercise name (bold)
  - Badges: Core, Functional, HIIT, Steady State (same badge classes as existing)
  - Coach tip (first ~60 chars, truncated with ellipsis)
  - "▶ Video" link (opens YouTube in new tab)
  - "Select" button that populates the hidden `exercise_id` input and shows the exercise name in a confirmation line
- A search input at the top filters exercises by name within the selected category
- The list scrolls within a max-height container (e.g., `max-h-80 overflow-y-auto`)

**Step 3 — After selection:**
- The picker collapses to show "Selected: [Exercise Name] ✕" (click ✕ to deselect and reopen picker)
- The section/sets/reps form fields below become visible
- The hidden `<input name="exercise_id">` is populated with the selected exercise's ID

**Data:** All exercise data is already loaded in PHP (`$ex_by_mg`). The picker is pure Alpine.js client-side filtering — no AJAX calls. The exercise data is embedded as a JSON object in a `<script>` tag for Alpine to reference.

### 4. Hidden Weak Side Emphasis

**Current:** Left Side Emphasis section is always visible with Extra L Sets, Extra L Reps, Left priority, Both sides checkboxes.

**After:**

- Hidden by default behind an Alpine.js toggle: "Add weak side emphasis +"
- Clicking reveals the fields with `x-transition`
- When hidden, the form still submits default values (0 sets, 0 reps, unchecked)
- Label changes from "Left Side Emphasis" to "Weak Side Emphasis" (display text only — form field names stay the same for backwards compatibility)

### 5. Better Empty State

**Current:** Plus icon + "No exercises yet for Day 1. Add from the panel on the right."

**After:**
- Friendlier message: "This day is empty. Start building your workout by picking exercises from the panel."
- If `openai_api_key_configured()`: add a secondary link "Or generate a plan with AI →" linking to `ai_builder.php`

### Data Requirements

No new database tables or columns. No new queries. All data is already loaded.

The exercise picker needs the exercise data available to Alpine.js. This is done by embedding `$all_ex` as JSON in a `<script>` tag:

```php
<script>
const exercises = <?= json_encode(array_values($all_ex)) ?>;
</script>
```

### What Does NOT Change

- All POST handlers (add_exercise, update_exercise, remove_exercise, update_day, move)
- Form field names (exercise_id, section, sets_target, reps_target, sets_left, reps_left_bonus, is_left_priority, both_sides, notes)
- Exercise list display (section headers, exercise rows, edit forms, move buttons)
- Day tabs behavior
- Two-column layout structure (exercises left, add panel right)
