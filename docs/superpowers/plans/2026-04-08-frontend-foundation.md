# Frontend Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Set up Vite + Tailwind CSS + Alpine.js build pipeline and migrate Plan Manager as the proof page.

**Architecture:** Vite bundles `src/js/app.js` (Alpine.js) and `src/css/app.css` (Tailwind) into `dist/`. A PHP helper function `vite_assets()` loads dev server URLs or production manifest assets. Tailwind is configured with the app's existing dark theme CSS variables. Plan Manager's HTML is rewritten with Tailwind classes and Alpine.js directives while all PHP logic stays untouched.

**Tech Stack:** Vite 6, Tailwind CSS 4, Alpine.js 3, PostCSS, PHP 8+

---

### Task 1: Initialize Node Project and Install Dependencies

**Files:**
- Create: `package.json`
- Modify: `.gitignore`

- [ ] **Step 1: Verify Node.js is available**

Run: `node --version && npm --version`

If Node.js is not installed, install it first:
```bash
brew install node
```

Then verify again. Expected: Node 18+ and npm 9+.

- [ ] **Step 2: Initialize package.json and install dependencies**

```bash
cd /Users/kamelasmar/apps/repprogress
npm init -y
npm install -D vite tailwindcss @tailwindcss/vite
npm install alpinejs
```

- [ ] **Step 3: Update package.json scripts**

Replace the `"scripts"` block in `package.json` with:

```json
"scripts": {
  "dev": "vite",
  "build": "vite build"
}
```

Also remove the `"main": "index.js"` line if present (not applicable to this project).

- [ ] **Step 4: Add node_modules to .gitignore**

Append to `.gitignore`:

```
# ── Node / frontend build ─────────────────────────────────────────────────────
node_modules/
```

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json .gitignore
git commit -m "chore: initialize Node project with Vite, Tailwind, Alpine.js"
```

---

### Task 2: Create Vite and Tailwind Configuration Files

**Files:**
- Create: `vite.config.js`
- Create: `postcss.config.js`
- Create: `tailwind.config.js`

- [ ] **Step 1: Create vite.config.js**

Create `vite.config.js` in the project root:

```js
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: {
      input: {
        app: 'src/js/app.js',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
```

- [ ] **Step 2: Create src/css/app.css**

Create `src/css/app.css`:

```css
@import "tailwindcss";

/* ── Theme variables — matches existing layout.php dark theme ── */
:root {
  --bg:           #0f0f0f;
  --bg2:          #1a1a1a;
  --bg3:          #222;
  --surface:      #1a1a1a;
  --surface2:     #222;
  --border:       rgba(255,255,255,0.08);
  --border2:      rgba(255,255,255,0.14);
  --text:         #f0f0f0;
  --muted:        #888;
  --muted2:       #666;
  --accent:       #1D9E75;
  --accent-dim:   rgba(29,158,117,0.15);
  --accent-dark:  #178a65;
  --accent-text:  #4dd8a7;
  --left:         #5b9fd6;
  --left-dim:     rgba(55,138,221,0.15);
  --left-text:    #7ec1f7;
  --warn:         #d4924a;
  --warn-dim:     rgba(186,117,23,0.15);
  --warn-text:    #f5b76a;
  --red:          #e05c5c;
  --red-dim:      rgba(226,75,74,0.15);
  --red-text:     #f08080;
  --green-dim:    rgba(99,153,34,0.15);
  --green-text:   #8dcc4a;
  --radius:       10px;
  --radius-lg:    14px;
  --sidebar-w:    220px;
  --nav-h:        60px;
}

/* ── Tailwind theme tokens mapped to CSS variables ── */
@theme {
  --color-bg:          var(--bg);
  --color-bg2:         var(--bg2);
  --color-bg3:         var(--bg3);
  --color-surface:     var(--surface);
  --color-surface2:    var(--surface2);
  --color-accent:      var(--accent);
  --color-accent-dim:  var(--accent-dim);
  --color-accent-dark: var(--accent-dark);
  --color-accent-text: var(--accent-text);
  --color-muted:       var(--muted);
  --color-muted2:      var(--muted2);
  --color-left:        var(--left);
  --color-left-dim:    var(--left-dim);
  --color-left-text:   var(--left-text);
  --color-warn:        var(--warn);
  --color-warn-dim:    var(--warn-dim);
  --color-warn-text:   var(--warn-text);
  --color-red:         var(--red);
  --color-red-dim:     var(--red-dim);
  --color-red-text:    var(--red-text);
  --color-green-dim:   var(--green-dim);
  --color-green-text:  var(--green-text);
  --color-border-app:  var(--border);
  --color-border-app2: var(--border2);
}
```

Note: Tailwind CSS v4 uses `@import "tailwindcss"` and `@theme` instead of v3's `@tailwind` directives and `tailwind.config.js`. The `@theme` block defines custom design tokens directly in CSS.

- [ ] **Step 3: Create src/js/app.js**

Create `src/js/app.js`:

```js
import Alpine from 'alpinejs';
import '../css/app.css';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 4: Test the build**

```bash
cd /Users/kamelasmar/apps/repprogress
npm run build
```

Expected: `dist/` folder created with `dist/.vite/manifest.json`, `dist/assets/app-[hash].js`, and `dist/assets/app-[hash].css`.

- [ ] **Step 5: Verify manifest.json structure**

```bash
cat dist/.vite/manifest.json
```

Expected: JSON with an `"src/js/app.js"` entry containing `"file"` and `"css"` keys pointing to hashed filenames.

- [ ] **Step 6: Commit**

```bash
git add vite.config.js src/ dist/
git commit -m "feat: add Vite + Tailwind CSS + Alpine.js build pipeline"
```

---

### Task 3: Add PHP Vite Asset Helper and Layout Integration

**Files:**
- Modify: `includes/config.php` (add `VITE_DEV` constant)
- Modify: `includes/functions.php` (add `vite_assets()` function)
- Modify: `includes/layout.php` (call `vite_assets()` in `render_head()`)

- [ ] **Step 1: Add VITE_DEV constant to config.php**

Add after the `OPENAI_MODEL` line in `includes/config.php`:

```php
define('VITE_DEV', false);  // Set to true during local development with `npm run dev`
```

- [ ] **Step 2: Add vite_assets() function to functions.php**

Add before the `// ── AI Workout Builder` section in `includes/functions.php` (around line 141):

```php
// ── Vite Asset Loading ───────────────────────────────────────────────────────
function vite_assets(): string {
    $dev = defined('VITE_DEV') && VITE_DEV;

    if ($dev) {
        return '<script type="module" src="http://localhost:5173/@vite/client"></script>'
             . '<script type="module" src="http://localhost:5173/src/js/app.js"></script>';
    }

    $manifest_path = __DIR__ . '/../dist/.vite/manifest.json';
    if (!file_exists($manifest_path)) return '';

    $manifest = json_decode(file_get_contents($manifest_path), true);
    $entry = $manifest['src/js/app.js'] ?? null;
    if (!$entry) return '';

    $html = '';
    foreach (($entry['css'] ?? []) as $css) {
        $html .= '<link rel="stylesheet" href="/dist/' . $css . '">';
    }
    $html .= '<script type="module" src="/dist/' . $entry['file'] . '"></script>';

    return $html;
}
```

- [ ] **Step 3: Call vite_assets() in render_head()**

In `includes/layout.php`, find the line (around line 21):

```php
<title><?= htmlspecialchars($title) ?> — Repprogress</title>
```

Add immediately after it:

```php
<?= vite_assets() ?>
```

This loads the Tailwind CSS and Alpine.js on every page, alongside the existing inline `<style>` block.

- [ ] **Step 4: Verify the page still loads**

Open any page in the browser (e.g., `plan_manager.php`). Confirm:
- The page renders normally (legacy CSS still works)
- View source shows `<link rel="stylesheet" href="/dist/assets/app-[hash].css">` and `<script type="module" src="/dist/assets/app-[hash].js">` in the `<head>`
- No console errors

- [ ] **Step 5: Commit**

```bash
git add includes/functions.php includes/layout.php
git commit -m "feat: add vite_assets() helper and integrate in layout"
```

Note: `includes/config.php` is gitignored, so the `VITE_DEV` constant is only in the local file.

---

### Task 4: Migrate Plan Manager — Page Header and Plan Cards

**Files:**
- Modify: `plan_manager.php` (lines 102-169 — page header and plan cards loop)

All PHP logic stays identical. Only HTML attributes and inline styles change.

- [ ] **Step 1: Replace the page header**

In `plan_manager.php`, replace lines 102-105:

```php
<div class="page-header">
  <div class="page-title">Training Plans</div>
  <div class="page-sub">Switch plans every 8 weeks — all historical data is always preserved</div>
</div>
```

With:

```php
<div class="mb-6">
  <h1 class="text-xl font-bold tracking-tight text-[var(--text)]">Training Plans</h1>
  <p class="text-sm text-muted mt-0.5">Switch plans every 8 weeks — all historical data is always preserved</p>
</div>
```

- [ ] **Step 2: Replace the plan cards loop**

Replace the entire plan cards section (from `<?php foreach ($plans as $p):` through `<?php endforeach; ?>`, lines 108-169) with:

```php
<?php foreach ($plans as $p):
  $week_num = $p['start_date'] ? max(1,(int)ceil((time()-strtotime($p['start_date']))/604800)) : '—';
  $weeks_left = $p['end_date'] ? max(0,(int)ceil((strtotime($p['end_date'])-time())/604800)) : '—';
  $progress_pct = ($p['weeks_duration'] && $p['start_date'])
    ? min(100, round(((time()-strtotime($p['start_date']))/604800) / $p['weeks_duration'] * 100))
    : 0;
?>
<div class="bg-surface border rounded-app p-5 mb-3 <?= $p['is_active'] ? 'border-2 border-accent' : 'border-border-app' ?>">
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2.5 flex-wrap mb-1">
        <span class="text-[17px] font-bold text-[var(--text)]"><?= htmlspecialchars($p['name']) ?></span>
        <?php if ($p['is_active']): ?>
        <span class="bg-accent text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full">● ACTIVE</span>
        <?php else: ?>
        <span class="bg-bg text-muted text-[11px] font-semibold px-2.5 py-0.5 rounded-full border border-border-app">Inactive</span>
        <?php endif; ?>
        <span class="text-xs text-muted">Phase <?= $p['phase_number'] ?> · <?= $p['weeks_duration'] ?> weeks</span>
      </div>
      <?php if ($p['description']): ?>
      <div class="text-[13px] text-muted mb-2 leading-relaxed"><?= htmlspecialchars($p['description']) ?></div>
      <?php endif; ?>
      <div class="flex gap-4 text-xs text-muted flex-wrap">
        <?php if ($p['start_date']): ?>
        <span>📅 <?= date('M j, Y', strtotime($p['start_date'])) ?> → <?= $p['end_date'] ? date('M j, Y',strtotime($p['end_date'])) : '?' ?></span>
        <span>📊 Week <?= $week_num ?> of <?= $p['weeks_duration'] ?><?= is_numeric($weeks_left) ? " · $weeks_left weeks left" : '' ?></span>
        <?php endif; ?>
        <span>🏋️ <?= $p['session_count'] ?> sessions logged</span>
      </div>

      <?php if ($p['is_active'] && $p['weeks_duration']): ?>
      <div class="mt-2.5">
        <div class="h-1.5 bg-bg3 rounded-full overflow-hidden">
          <div class="h-full bg-accent rounded-full transition-all duration-300" style="width:<?= $progress_pct ?>%"></div>
        </div>
        <div class="text-[11px] text-muted mt-1"><?= $progress_pct ?>% through this plan</div>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex flex-col gap-1.5 items-end">
      <a href="plan_builder.php?plan_id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit Plan</a>
      <?php if (!$p['is_active']): ?>
      <form method="post" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="activate">
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
        <button class="btn btn-primary btn-sm">▶ Activate</button>
      </form>
      <?php endif; ?>
      <?php if (!$p['is_active'] && !$p['session_count']): ?>
      <form method="post" class="inline" x-data x-on:submit="if (!confirm('Delete this plan?')) $event.preventDefault()">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
```

- [ ] **Step 3: Replace the empty state**

Replace lines 171-173:

```php
<?php if (!$plans): ?>
<div class="card"><div class="empty"><div class="empty-icon">🗂️</div><p>No plans yet. Create your first one below.</p></div></div>
<?php endif; ?>
```

With:

```php
<?php if (!$plans): ?>
<div class="bg-surface border border-border-app rounded-app p-10 text-center text-muted">
  <div class="text-3xl mb-3">🗂️</div>
  <p class="text-sm mb-4">No plans yet. Create your first one below.</p>
</div>
<?php endif; ?>
```

- [ ] **Step 4: Verify the plan cards render correctly**

Open `plan_manager.php` in the browser. Confirm:
- Plan cards show with correct dark theme styling
- Active plan has green accent border
- Progress bar renders with correct width
- Action buttons (Edit, Activate, Delete) work
- Delete confirmation dialog appears
- Responsive layout works on mobile

- [ ] **Step 5: Commit**

```bash
git add plan_manager.php
git commit -m "feat: migrate plan manager header and cards to Tailwind + Alpine.js"
```

---

### Task 5: Migrate Plan Manager — Create/Clone Section and Info Box

**Files:**
- Modify: `plan_manager.php` (lines 175-283 — create/clone grid and info box)

- [ ] **Step 1: Replace the create/clone grid and create card**

Replace from `<!-- ── Create / Clone -->` through the end of the create card's `</div>` (lines 175-235) with:

```php
<!-- ── Create / Clone ─────────────────────────────────────────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">

  <!-- New plan — choose type -->
  <div class="bg-surface border border-border-app rounded-app p-5" x-data="{ mode: '<?= openai_api_key_configured() ? 'choose' : 'form' ?>' }">
    <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-4">Create New Plan</div>

    <?php if (openai_api_key_configured()): ?>
    <div x-show="mode === 'choose'">
      <p class="text-[13px] text-muted mb-4 leading-relaxed">Choose how to start your new plan:</p>
      <div class="grid grid-cols-2 gap-2.5 mb-5">
        <button type="button" class="btn btn-ghost flex flex-col items-center justify-center p-3.5 h-auto whitespace-normal text-center" x-on:click="mode = 'form'">
          <span class="text-xl block mb-1">&#128221;</span>
          <span class="font-bold block">Blank Plan</span>
          <span class="text-xs text-muted block mt-0.5">Start from scratch</span>
        </button>
        <a href="ai_builder.php" class="btn btn-ghost flex flex-col items-center justify-center p-3.5 h-auto whitespace-normal text-center no-underline">
          <span class="text-xl block mb-1">&#129302;</span>
          <span class="font-bold block">AI Generated</span>
          <span class="text-xs text-muted block mt-0.5">Answer questions, get a plan</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div x-show="mode === 'form'" x-transition>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Plan Name</label>
        <input type="text" name="name" placeholder="Phase 2 — Loading" required>
      </div>
      <div class="form-row form-row-3">
        <div>
          <label>Training Days</label>
          <select name="num_days">
            <?php for ($d=1; $d<=7; $d++): ?>
            <option value="<?= $d ?>" <?= $d===3?'selected':'' ?>><?= $d ?> day<?= $d>1?'s':'' ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label>Phase #</label>
          <input type="number" name="phase_number" value="<?= count($plans)+1 ?>" min="1">
        </div>
        <div>
          <label>Duration (weeks)</label>
          <input type="number" name="weeks_duration" value="8" min="1" max="52">
        </div>
      </div>
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea name="description" rows="2" placeholder="Focus, goals, key differences from last phase..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Create &amp; Open Builder &rarr;</button>
    </form>
    </div>
  </div>

```

- [ ] **Step 2: Replace the clone card**

Replace the clone card (from `<!-- Clone existing plan -->` through its closing `</div>`, lines 237-274) with:

```php
  <!-- Clone existing plan -->
  <div class="bg-surface border border-border-app rounded-app p-5">
    <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-4">Clone from Existing Plan</div>
    <?php if ($plans): ?>
    <p class="text-[13px] text-muted mb-4 leading-relaxed">Copies all days and exercises from the source plan. Then customise in the builder — add, remove or swap exercises without touching your historical data.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clone">
      <div class="form-group">
        <label>Copy from</label>
        <select name="source_plan_id" required>
          <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>New Plan Name</label>
        <input type="text" name="name" placeholder="Phase 2 — Loading" required>
      </div>
      <div class="form-row form-row-2">
        <div><label>Phase #</label><input type="number" name="phase_number" value="<?= count($plans)+1 ?>" min="1"></div>
        <div><label>Duration (weeks)</label><input type="number" name="weeks_duration" value="8" min="1" max="52"></div>
      </div>
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea name="description" rows="2" placeholder="What's different this phase?"></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Clone &amp; Open Builder &rarr;</button>
    </form>
    <?php else: ?>
    <div class="text-center py-10 text-muted text-sm">Create your first plan to enable cloning.</div>
    <?php endif; ?>
  </div>
</div>
```

- [ ] **Step 3: Replace the info box**

Replace lines 277-281:

```php
<div class="info-box" style="margin-top:1rem">
  <strong style="color:var(--text)">How it works:</strong>
  When you activate a new plan, all future sessions are logged under it. Old sessions remain permanently linked to the plan they were logged under — nothing is ever deleted.
  You can view history filtered by plan on the dashboard and exercise detail pages.
</div>
```

With:

```php
<div class="mt-4 px-3.5 py-3 bg-bg3 border-l-[3px] border-l-accent rounded-r-lg text-[13px] text-muted leading-relaxed">
  <strong class="text-[var(--text)]">How it works:</strong>
  When you activate a new plan, all future sessions are logged under it. Old sessions remain permanently linked to the plan they were logged under — nothing is ever deleted.
  You can view history filtered by plan on the dashboard and exercise detail pages.
</div>
```

- [ ] **Step 4: Verify the full page**

Open `plan_manager.php` in the browser. Confirm:
- Create plan chooser shows Blank Plan / AI Generated buttons (if API key configured)
- Clicking "Blank Plan" reveals the form with a smooth transition
- Clone form renders correctly
- Info box has the green left border
- Two-column grid on desktop, stacked on mobile
- All forms submit correctly (create, clone work as before)

- [ ] **Step 5: Commit**

```bash
git add plan_manager.php
git commit -m "feat: migrate plan manager create/clone section to Tailwind + Alpine.js"
```

---

### Task 6: Production Build and Final Verification

**Files:**
- Regenerate: `dist/` (fresh production build)

- [ ] **Step 1: Run production build**

```bash
cd /Users/kamelasmar/apps/repprogress
npm run build
```

Expected: `dist/` folder updated with fresh hashed assets.

- [ ] **Step 2: Verify production mode works**

Ensure `VITE_DEV` is `false` in `includes/config.php` (it should be by default). Open `plan_manager.php` in the browser. Confirm:
- Page loads with Tailwind styles from the built CSS file
- Alpine.js interactivity works (create plan chooser toggle, delete confirmation)
- No 404s for CSS/JS assets in browser dev tools Network tab

- [ ] **Step 3: Verify other pages still work**

Open `index.php`, `workout.php`, and `exercises.php`. Confirm:
- They render normally with the legacy inline CSS
- No visual regressions (Tailwind's base styles should not conflict with legacy styles)
- Chart.js charts still render on the dashboard
- If any Tailwind reset causes issues (like form element appearance changes), note them for fixing

- [ ] **Step 4: Verify dev mode works**

Set `VITE_DEV` to `true` in `includes/config.php`, then:

```bash
npm run dev
```

Open `plan_manager.php`. Confirm:
- Vite dev server script tag appears in page source
- Hot module replacement works (edit `src/css/app.css`, changes appear without reload)

Set `VITE_DEV` back to `false` when done.

- [ ] **Step 5: Commit the production build**

```bash
git add dist/
git commit -m "chore: add production build of Tailwind + Alpine.js assets"
```
