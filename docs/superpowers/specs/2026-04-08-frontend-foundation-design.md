# Frontend Foundation — Design Spec

## Overview

Introduce a modern frontend build pipeline (Vite + Tailwind CSS + Alpine.js) to the Repprogress PHP app. This is phase 1 of a multi-phase UI overhaul. The existing inline CSS and vanilla JS continue working for non-migrated pages. Plan Manager is migrated as the proof page. This stack matches Laravel's default frontend tooling, enabling a smooth future migration.

## Goals

- Modern CSS tooling with utility-first classes (Tailwind)
- Declarative interactivity without a full SPA framework (Alpine.js)
- Hot module replacement during development (Vite)
- Production builds committed to git — no Node.js required on the server
- Incremental migration — old and new CSS coexist

## File Structure

```
package.json              — Dependencies: tailwindcss, vite, alpinejs, postcss, autoprefixer
vite.config.js            — Entry points, output to dist/, PHP dev server proxy
tailwind.config.js        — Custom theme tokens, content paths for .php files
postcss.config.js         — Tailwind + autoprefixer
src/
  css/app.css             — Tailwind directives + custom theme variables
  js/app.js               — Alpine.js init + shared utilities
dist/                     — Production build output (committed to git)
  .vite/manifest.json     — Asset manifest for PHP helper
  assets/
    app-[hash].css
    app-[hash].js
```

## Build Pipeline

### Vite Configuration

```js
// vite.config.js
import { defineConfig } from 'vite';

export default defineConfig({
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

### Development Workflow

1. `npm run dev` — starts Vite dev server on port 5173 with HMR
2. PHP server runs as normal (Apache/Lightsail)
3. CSS/JS changes reflect instantly without page reload

### Production Build

1. `npm run build` — outputs hashed files to `dist/`
2. Commit `dist/` folder to git
3. Deploy — PHP reads `dist/.vite/manifest.json` to load correct files
4. No Node.js needed on the server

## PHP Integration

### Asset Helper

A `vite_assets()` function added to `includes/functions.php`:

- Checks if Vite dev server is running (via a constant or env check)
- **Development:** outputs `<script type="module" src="http://localhost:5173/@vite/client">` and `<script type="module" src="http://localhost:5173/src/js/app.js">`
- **Production:** reads `dist/.vite/manifest.json`, outputs `<link>` and `<script>` tags for the hashed files

A constant `VITE_DEV` is added to `includes/config.php`. Set to `true` during local development, `false` in production.

### Layout Integration

`render_head()` in `layout.php` calls `vite_assets()` to load the Tailwind CSS and Alpine.js bundle. This is added alongside (not replacing) the existing inline `<style>` block.

## Theme & CSS Strategy

### Tailwind Theme Tokens

The existing CSS variables are mapped to Tailwind custom colors in `tailwind.config.js`:

```js
// tailwind.config.js
module.exports = {
  content: ['./**/*.php'],
  important: true,
  theme: {
    extend: {
      colors: {
        bg:        'var(--bg)',
        bg2:       'var(--bg2)',
        bg3:       'var(--bg3)',
        surface:   'var(--surface)',
        surface2:  'var(--surface2)',
        accent:    'var(--accent)',
        'accent-dark': 'var(--accent-dark)',
        'accent-text': 'var(--accent-text)',
        muted:     'var(--muted)',
        muted2:    'var(--muted2)',
        left:      'var(--left)',
        'left-text': 'var(--left-text)',
        warn:      'var(--warn)',
        'warn-text': 'var(--warn-text)',
        red:       'var(--red)',
        'red-text': 'var(--red-text)',
        'green-text': 'var(--green-text)',
      },
      borderColor: {
        app:  'var(--border)',
        app2: 'var(--border2)',
      },
      borderRadius: {
        app:   'var(--radius)',
        'app-lg': 'var(--radius-lg)',
      },
    },
  },
};
```

### CSS Entry Point

```css
/* src/css/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Theme variables — same values as current layout.php inline styles */
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
```

### Coexistence Rules

- `important: true` in Tailwind config so Tailwind classes override legacy CSS when both apply
- The inline `<style>` block in `layout.php` remains for non-migrated pages
- Migrated pages use Tailwind classes; their old inline styles are removed
- Once all pages are migrated in future phases, the inline `<style>` block is deleted

## Alpine.js Setup

### Initialization

```js
// src/js/app.js
import Alpine from 'alpinejs';
import '../css/app.css';

window.Alpine = Alpine;
Alpine.start();
```

### Usage Pattern

Interactive components are declared directly in HTML with `x-data`:

```html
<div x-data="{ showForm: false }">
  <button x-on:click="showForm = !showForm">Toggle</button>
  <div x-show="showForm" x-transition>
    <!-- form content -->
  </div>
</div>
```

No separate JS files per page. Each component is self-contained in the HTML.

### What Alpine.js Does NOT Replace

- Chart.js — stays as CDN load for chart rendering
- Simple one-liner event handlers that don't benefit from Alpine's reactivity

## Plan Manager Migration

### What Changes

Only the HTML output of `plan_manager.php` is modified. All PHP logic (POST handlers, queries, redirects, flash messages) stays identical.

#### Page Header

- Before: `<div class="page-header">` with legacy CSS classes
- After: Tailwind utilities — `text-xl font-bold tracking-tight`, `text-sm text-muted mt-0.5`

#### Plan Cards

- Before: `<div class="card" style="margin-bottom:1rem;border:2px solid var(--accent)">` with inline styles
- After: Tailwind classes — `bg-surface border border-app rounded-app p-5 mb-3` with Alpine.js conditional for active border

#### Progress Bar

- Before: inline `style="width:<?= $progress_pct ?>%"`
- After: same inline width (dynamic from PHP), but container uses Tailwind — `h-1.5 bg-bg3 rounded-full overflow-hidden`

#### Create Plan Chooser (Blank vs AI)

- Before: `onclick="document.getElementById('blank-form').style.display='block'"` 
- After: `x-data="{ mode: 'choose' }"` wrapping the card. "Blank Plan" button sets `mode = 'form'`, form shows via `x-show="mode === 'form'" x-transition`

#### Action Buttons

- Before: `style="display:flex;flex-direction:column;gap:6px;align-items:flex-end"`
- After: `class="flex flex-col gap-1.5 items-end"`

#### Delete Confirmation

- Before: `onsubmit="return confirm('Delete this plan?')"`
- After: `x-on:submit="if (!confirm('Delete this plan?')) $event.preventDefault()"`

### What Does NOT Change

- All PHP logic (POST handlers, queries, redirects)
- Server-rendered PRG pattern
- Sidebar and bottom nav in layout.php (migrated in later phases)
- Flash message rendering
- Other pages (they continue using the legacy inline CSS)

## .gitignore Updates

Add `node_modules/` to `.gitignore`. Do NOT gitignore `dist/` — it must be committed for production deployments without Node.

## Future Phases (Out of Scope)

These are not part of this spec but inform the design decisions above:

- **Phase 2:** Migrate sidebar/bottom nav layout to Tailwind + Alpine.js
- **Phase 3:** Migrate remaining pages one at a time
- **Phase 4:** Remove legacy inline CSS from layout.php
- **Phase 5:** Add API endpoints for Flutter readiness
