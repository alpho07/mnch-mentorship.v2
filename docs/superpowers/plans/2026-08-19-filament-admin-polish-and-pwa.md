# Filament Admin Polish + PWA Install Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Filament admin panel (`/admin`) feel as polished as `public/m-assessment-app` — consistent rounded/animated buttons, inputs, checkboxes, toggles, cards, tables, and charts — and make it installable as an app via the browser's native PWA install flow, without changing its navy/teal color identity or building offline data support.

**Architecture:** Two independent, additive layers. (1) CSS-only visual polish extending the two files that already style the panel (`public/css/filament-admin-theme.css`, `resources/css/theme.css`), plus a small PHP helper (`app/Support/ChartTheme.php`) merged into each Filament `ChartWidget::getOptions()`. (2) A PWA install layer: new static files (`public/manifest.webmanifest`, `public/sw.js`, generated icons) and a few additions to `AdminPanelProvider`'s existing render-hook pattern. No new build tooling; no changes to the public frontend, `m-assessment-app`, or offline data.

**Tech Stack:** Laravel 12, Filament v3, Tailwind CSS v4 (`@apply` in `resources/css/theme.css`), plain CSS (`public/css/filament-admin-theme.css`, loaded via `filemtime()`-cache-busted `<link>`, not through Vite), Chart.js (via Filament `ChartWidget`), Alpine.js (already loaded by Filament), PHP GD (for one-off icon cropping).

**Spec:** `docs/superpowers/specs/2026-08-19-filament-admin-polish-and-pwa-design.md`

## Global Constraints

- Keep the existing navy/teal "Clinical Theme" identity (sidebar gradient `#1C3A8A`→`#112460`, Inter font, accent palette teal `#0097A7` / amber `#F59E0B` / emerald `#10B981` / violet `#8B5CF6`) — do not adopt m-assessment-app's purple/DM Sans palette.
- No offline data caching. Service worker precaches static assets only (CSS/JS/manifest/icons); admin pages and API calls always hit the network.
- Everything is scoped to the `admin` panel (`/admin`) — do not touch the public resource frontend, `m-assessment-app`, or any other panel.
- No Capacitor / native wrapper work.
- Follow existing code patterns: CSS edits go in the two files already used for panel theming; render-hook additions follow the existing `PanelsRenderHook::HEAD_END` / `USER_MENU_*` pattern already used in `AdminPanelProvider.php`.

---

## Task 1: Design tokens + button/input polish

**Files:**
- Modify: `public/css/filament-admin-theme.css` (append new section at end of file)

**Interfaces:**
- Produces: CSS custom properties `--mnch-radius-sm`, `--mnch-radius-md`, `--mnch-radius-lg`, `--mnch-shadow-sm`, `--mnch-shadow-md`, `--mnch-transition` on `:root`, consumed by Tasks 2 and 3.

- [ ] **Step 1: Append the design-tokens block and button/input polish to `public/css/filament-admin-theme.css`**

Add this to the end of the file:

```css

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   DESIGN TOKENS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
:root {
    --mnch-radius-sm: 0.5rem;
    --mnch-radius-md: 0.75rem;
    --mnch-radius-lg: 1rem;
    --mnch-shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.08);
    --mnch-shadow-md: 0 8px 24px rgba(15, 23, 42, 0.12);
    --mnch-transition: all 0.2s ease;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   BUTTONS — all variants
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-btn {
    border-radius: var(--mnch-radius-sm) !important;
    transition: var(--mnch-transition) !important;
}

.fi-btn:not(.fi-btn-color-gray):hover {
    transform: translateY(-1px);
    box-shadow: var(--mnch-shadow-sm);
}

.fi-btn:active {
    transform: translateY(0);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   INPUTS / SELECTS / TEXTAREAS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-input,
.fi-select-input,
.fi-textarea {
    border-radius: var(--mnch-radius-sm) !important;
    transition: var(--mnch-transition) !important;
}

.fi-input:focus,
.fi-select-input:focus,
.fi-textarea:focus {
    box-shadow: var(--mnch-shadow-sm) !important;
}
```

- [ ] **Step 2: Verify in the browser**

Run `composer run dev` (or `php artisan serve` if the dev servers are already running some other way), sign in to `/admin`, and open any resource create/edit form.

Expected: buttons show a rounded corner + a subtle lift-and-shadow on hover; text inputs/selects show a rounded corner and a soft shadow when focused. No layout shift, no console errors.

- [ ] **Step 3: Commit**

```bash
git add public/css/filament-admin-theme.css
git commit -m "style: add design tokens and polish buttons/inputs in admin theme"
```

---

## Task 2: Checkbox + toggle polish

**Files:**
- Modify: `public/css/filament-admin-theme.css` (append new section at end of file)

**Interfaces:**
- Consumes: `--mnch-radius-sm`, `--mnch-transition` from Task 1.

- [ ] **Step 1: Append checkbox and toggle styling to `public/css/filament-admin-theme.css`**

The checkbox input class is `.fi-checkbox-input` (from `vendor/filament/support/resources/views/components/input/checkbox.blade.php`). The toggle button class is `.fi-fo-toggle`, and its thumb is the first `<span>` child (from `vendor/filament/forms/resources/views/components/toggle.blade.php`).

```css

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   CHECKBOXES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-checkbox-input {
    border-radius: 0.3rem !important;
    transition: var(--mnch-transition) !important;
    cursor: pointer;
}

.fi-checkbox-input:checked {
    transform: scale(1.05);
}

.fi-checkbox-input:hover:not(:disabled) {
    box-shadow: 0 0 0 3px rgba(0, 151, 167, 0.15);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   TOGGLES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-fo-toggle {
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
}

.fi-fo-toggle > span:first-child {
    box-shadow: var(--mnch-shadow-sm);
}

.fi-fo-toggle:hover {
    filter: brightness(0.97);
}
```

- [ ] **Step 2: Verify in the browser**

On any form with a checkbox field (e.g. an assessment question form) and any form with a toggle field (e.g. a settings page), check both states.

Expected: checkboxes have a slightly rounded corner and a soft focus/hover ring; toggling shows the existing slide animation (unchanged, from Filament core) plus a subtle shadow on the thumb. No visual glitches in dark mode.

- [ ] **Step 3: Commit**

```bash
git add public/css/filament-admin-theme.css
git commit -m "style: polish checkbox and toggle inputs in admin theme"
```

---

## Task 3: Cards, sections, stat widgets, and table polish

**Files:**
- Modify: `public/css/filament-admin-theme.css` (append new section at end of file)

**Interfaces:**
- Consumes: `--mnch-radius-lg`, `--mnch-shadow-sm`, `--mnch-shadow-md`, `--mnch-transition` from Task 1.

- [ ] **Step 1: Append card/section/stat/table styling to `public/css/filament-admin-theme.css`**

`.fi-section` is the Filament section wrapper (`vendor/filament/support/resources/views/components/section/index.blade.php`); `.fi-wi-stats-overview-stat` is the dashboard stat-card widget (`vendor/filament/widgets/resources/views/stats-overview-widget/stat.blade.php`).

```css

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   SECTIONS / CARDS / STAT WIDGETS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-section,
.fi-wi-stats-overview-stat {
    border-radius: var(--mnch-radius-lg) !important;
    box-shadow: var(--mnch-shadow-sm) !important;
    transition: var(--mnch-transition) !important;
}

.fi-section:hover,
.fi-wi-stats-overview-stat:hover {
    box-shadow: var(--mnch-shadow-md) !important;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   TABLES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-ta-header-cell {
    font-weight: 700 !important;
    letter-spacing: 0.02em;
}

.fi-pagination-item {
    border-radius: var(--mnch-radius-sm) !important;
    transition: var(--mnch-transition) !important;
}

.fi-pagination-item:hover {
    transform: translateY(-1px);
}
```

- [ ] **Step 2: Verify in the browser**

Open any resource list page (table + pagination) and any dashboard page with stat-overview widgets.

Expected: table header text is slightly bolder with letter spacing; pagination buttons lift slightly on hover; stat cards and form sections show a soft shadow that deepens on hover. Existing row-hover behavior (already in `resources/css/theme.css`) is unaffected.

- [ ] **Step 3: Commit**

```bash
git add public/css/filament-admin-theme.css
git commit -m "style: polish sections, stat widgets, and tables in admin theme"
```

---

## Task 4: ChartTheme helper + wire into all chart widgets

**Files:**
- Create: `app/Support/ChartTheme.php`
- Test: `tests/Unit/ChartThemeTest.php`
- Modify: `app/Filament/Widgets/ParticipantsByCadreChartWidget.php:53-76`
- Modify: `app/Filament/Widgets/ParticipantsByDepartmentChartWidget.php:57` (`getOptions()` method body)
- Modify: `app/Filament/Widgets/TrainingInsightsWidget.php:142` (`TrainingPerformanceChart::getOptions()`)
- Modify: `app/Filament/Widgets/TrainingInsightsWidget.php:329` (`TrainingAnalyticsWidget::getOptions()`)
- Modify: `app/Filament/Widgets/TrainingsByCountyChartWidget.php:52` (`getOptions()` method body)
- Modify: `app/Filament/Widgets/TrainingsByMonthChartWidget.php:52` (`getOptions()` method body)
- Modify: `public/css/filament-admin-theme.css` (append new section)

**Interfaces:**
- Produces: `App\Support\ChartTheme::base(): array` (a standalone Chart.js options array) and `App\Support\ChartTheme::merge(array $overrides): array` (deep-merges `$overrides` on top of `base()`, with `$overrides` values winning at the leaf level). Both are `public static`.
- Consumes: nothing.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ChartThemeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\ChartTheme;
use Tests\TestCase;

class ChartThemeTest extends TestCase
{
    public function test_base_returns_shared_chart_options(): void
    {
        $options = ChartTheme::base();

        $this->assertTrue($options['responsive']);
        $this->assertFalse($options['maintainAspectRatio']);
        $this->assertSame(6, $options['elements']['bar']['borderRadius']);
    }

    public function test_merge_overlays_caller_options_onto_the_base(): void
    {
        $merged = ChartTheme::merge([
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ]);

        // Caller's leaf value wins.
        $this->assertFalse($merged['plugins']['legend']['display']);

        // Base's unrelated keys under the same top-level array survive the merge.
        $this->assertTrue($merged['responsive']);
        $this->assertSame(6, $merged['elements']['bar']['borderRadius']);
    }

    public function test_merge_deep_merges_nested_scales_without_losing_base_grid_styling(): void
    {
        $merged = ChartTheme::merge([
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);

        $this->assertTrue($merged['scales']['y']['beginAtZero']);
        $this->assertSame('rgba(15, 23, 42, 0.06)', $merged['scales']['x']['grid']['color']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ChartThemeTest`
Expected: FAIL — `Class "App\Support\ChartTheme" not found`.

- [ ] **Step 3: Implement `app/Support/ChartTheme.php`**

```php
<?php

namespace App\Support;

class ChartTheme
{
    public const PALETTE = ['#0097A7', '#F59E0B', '#10B981', '#8B5CF6', '#1C3A8A', '#DC2626'];

    public static function base(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 600,
                'easing' => 'easeOutQuart',
            ],
            'elements' => [
                'bar' => [
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
                'line' => [
                    'tension' => 0.35,
                ],
                'point' => [
                    'radius' => 3,
                    'hoverRadius' => 5,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'color' => 'rgba(15, 23, 42, 0.06)',
                        'drawBorder' => false,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'color' => 'rgba(15, 23, 42, 0.06)',
                        'drawBorder' => false,
                    ],
                ],
            ],
        ];
    }

    public static function merge(array $overrides): array
    {
        return self::deepMerge(self::base(), $overrides);
    }

    private static function deepMerge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && array_is_list($value) === false) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=ChartThemeTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Wire `ChartTheme::merge()` into each chart widget's `getOptions()`**

In each of the six files below, change `return [` to `return \App\Support\ChartTheme::merge([` and the closing `];` of that same `getOptions()` method to `]);`. Do not change anything else in the method — the existing option keys become the `$overrides` array passed to `merge()`.

`app/Filament/Widgets/ParticipantsByCadreChartWidget.php` (currently lines 53-76):
```php
    protected function getOptions(): array
    {
        return \App\Support\ChartTheme::merge([
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
                ],
            ],
        ]);
    }
```

Apply the same transformation (wrap the returned array literal in `\App\Support\ChartTheme::merge([ ... ]);`, leaving every existing key/value untouched) to the `getOptions()` method in:
- `app/Filament/Widgets/ParticipantsByDepartmentChartWidget.php`
- `app/Filament/Widgets/TrainingInsightsWidget.php` — the `getOptions()` inside `class TrainingPerformanceChart` (around line 142)
- `app/Filament/Widgets/TrainingInsightsWidget.php` — the `getOptions()` inside `class TrainingAnalyticsWidget` (around line 329)
- `app/Filament/Widgets/TrainingsByCountyChartWidget.php`
- `app/Filament/Widgets/TrainingsByMonthChartWidget.php`

- [ ] **Step 6: Run the full widget-related test suite to confirm nothing broke**

Run: `php artisan test --filter=ChartTheme`

Then also run any existing tests that touch these widgets, if present:

Run: `php artisan test --filter=Widget`
Expected: PASS, or "No tests found" if none exist — either is fine, but there must be no failures.

- [ ] **Step 7: Add a chart-card CSS wrapper**

Append to `public/css/filament-admin-theme.css`:

```css

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   CHART WIDGET CARDS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.fi-wi-chart {
    border-radius: var(--mnch-radius-lg) !important;
    box-shadow: var(--mnch-shadow-sm) !important;
    transition: var(--mnch-transition) !important;
}

.fi-wi-chart:hover {
    box-shadow: var(--mnch-shadow-md) !important;
}
```

- [ ] **Step 8: Verify in the browser**

Visit a dashboard page containing at least one of the six chart widgets (e.g. the main admin dashboard or the training analytics dashboard).

Expected: chart renders with rounded bar corners, soft gridlines, and a smooth fade-in on load; the chart's card has the same radius/shadow treatment as other cards; no console errors from Chart.js about invalid option shapes.

- [ ] **Step 9: Commit**

```bash
git add app/Support/ChartTheme.php tests/Unit/ChartThemeTest.php app/Filament/Widgets/ParticipantsByCadreChartWidget.php app/Filament/Widgets/ParticipantsByDepartmentChartWidget.php app/Filament/Widgets/TrainingInsightsWidget.php app/Filament/Widgets/TrainingsByCountyChartWidget.php app/Filament/Widgets/TrainingsByMonthChartWidget.php public/css/filament-admin-theme.css
git commit -m "feat: add shared ChartTheme and wire it into all admin chart widgets"
```

---

## Task 5: Generate app icons from the existing logo

**Files:**
- Create: `public/icons/admin-icon-192.png`
- Create: `public/icons/admin-icon-512.png`
- Create: `public/icons/admin-icon-maskable-512.png`

**Interfaces:**
- Produces: three PNG files at the paths above, consumed by Task 6 (`manifest.webmanifest`) and Task 8 (`apple-touch-icon` link).

`public/moh_logo.png` is 656×123px — a horizontal lockup (Kenya coat-of-arms crest + "MINISTRY OF HEALTH" wordmark). The crest itself is a clean square in the top-left 123×123px region. This task crops that crest and composites it onto a navy square background at three sizes.

- [ ] **Step 1: Write and run the one-off icon-generation script**

This is a one-time asset-generation script, not part of the application — run it from the project root with `php`, then discard it (don't commit the script itself).

Create a temporary file `generate-icons.php` in the project root:

```php
<?php
// One-off script: generates public/icons/*.png from public/moh_logo.png. Delete after running.

function makeIcon(string $srcPath, string $destPath, int $size, bool $maskableSafeArea): void
{
    $source = imagecreatefrompng($srcPath);

    // Crop the square crest out of the top-left of the wide logo.
    $crest = imagecrop($source, ['x' => 0, 'y' => 0, 'width' => 123, 'height' => 123]);

    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);
    $navy = imagecolorallocate($canvas, 0x1C, 0x3A, 0x8A);
    imagefill($canvas, 0, 0, $navy);

    // Maskable icons need padding so the crest survives OS icon-shape masking (safe area ~ 80% of canvas).
    $crestTargetSize = $maskableSafeArea ? (int) round($size * 0.7) : (int) round($size * 0.85);
    $offset = (int) round(($size - $crestTargetSize) / 2);

    imagecopyresampled(
        $canvas, $crest,
        $offset, $offset, 0, 0,
        $crestTargetSize, $crestTargetSize,
        123, 123
    );

    imagepng($canvas, $destPath);
    imagedestroy($canvas);
    imagedestroy($crest);
    imagedestroy($source);
}

$logo = __DIR__ . '/public/moh_logo.png';
@mkdir(__DIR__ . '/public/icons', 0755, true);

makeIcon($logo, __DIR__ . '/public/icons/admin-icon-192.png', 192, false);
makeIcon($logo, __DIR__ . '/public/icons/admin-icon-512.png', 512, false);
makeIcon($logo, __DIR__ . '/public/icons/admin-icon-maskable-512.png', 512, true);

echo "Icons generated in public/icons/\n";
```

Run: `php generate-icons.php`
Expected output: `Icons generated in public/icons/`

- [ ] **Step 2: Verify the icons visually**

Open `public/icons/admin-icon-512.png` and `public/icons/admin-icon-maskable-512.png` and confirm: navy square background, centered Kenya crest, no visible cropping artifacts, no transparency showing through where the navy fill should be.

- [ ] **Step 3: Delete the generation script**

```bash
rm generate-icons.php
```

- [ ] **Step 4: Commit the generated icons**

```bash
git add public/icons/admin-icon-192.png public/icons/admin-icon-512.png public/icons/admin-icon-maskable-512.png
git commit -m "feat: add admin PWA install icons derived from ministry logo crest"
```

---

## Task 6: Web app manifest

**Files:**
- Create: `public/manifest.webmanifest`

**Interfaces:**
- Consumes: `public/icons/admin-icon-192.png`, `public/icons/admin-icon-512.png`, `public/icons/admin-icon-maskable-512.png` from Task 5.
- Produces: `public/manifest.webmanifest`, linked from Task 8.

- [ ] **Step 1: Create `public/manifest.webmanifest`**

```json
{
    "name": "MNCH Mentorship Admin",
    "short_name": "MNCH Admin",
    "description": "MNCH Mentorship Platform admin panel for training, mentorship, and facility assessment management.",
    "start_url": "/admin",
    "scope": "/admin/",
    "display": "standalone",
    "background_color": "#F4F6FB",
    "theme_color": "#1C3A8A",
    "icons": [
        {
            "src": "/icons/admin-icon-192.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any"
        },
        {
            "src": "/icons/admin-icon-512.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "any"
        },
        {
            "src": "/icons/admin-icon-maskable-512.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "maskable"
        }
    ]
}
```

- [ ] **Step 2: Validate the manifest is well-formed JSON**

Run: `php -r "json_decode(file_get_contents('public/manifest.webmanifest'), flags: JSON_THROW_ON_ERROR); echo 'valid';"`
Expected: `valid`

- [ ] **Step 3: Commit**

```bash
git add public/manifest.webmanifest
git commit -m "feat: add PWA manifest for admin panel"
```

---

## Task 7: Service worker (static-asset precache only)

**Files:**
- Create: `public/sw.js`

**Interfaces:**
- Produces: `public/sw.js`, registered by Task 8 with `scope: '/admin/'`.

- [ ] **Step 1: Create `public/sw.js`**

```js
// Admin panel service worker — installability only. No HTML or API caching.
// Scoped to /admin/ at registration time (see AdminPanelProvider render hook).

const CACHE_NAME = 'mnch-admin-static-v1';

const STATIC_ASSETS = [
    '/css/filament-admin-theme.css',
    '/manifest.webmanifest',
    '/icons/admin-icon-192.png',
    '/icons/admin-icon-512.png',
    '/icons/admin-icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Cache-first for the known static assets only; everything else (pages, API calls)
// goes straight to the network — this worker never serves stale admin data.
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || !STATIC_ASSETS.includes(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
});
```

- [ ] **Step 2: Verify syntax**

Run: `node --check public/sw.js` (if Node is available; otherwise skip to Step 3 and rely on the browser check in Task 8, which will report SW registration errors in DevTools).

- [ ] **Step 3: Commit**

```bash
git add public/sw.js
git commit -m "feat: add static-asset-only service worker for admin panel"
```

---

## Task 8: Wire manifest, meta tags, and service-worker registration into the panel

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php:53-58`

**Interfaces:**
- Consumes: `public/manifest.webmanifest` (Task 6), `public/sw.js` (Task 7), `public/icons/admin-icon-192.png` (Task 5).

- [ ] **Step 1: Extend the existing `HEAD_END` render hook**

In `app/Providers/Filament/AdminPanelProvider.php`, find the existing hook (lines 53-58):

```php
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="'.asset('css/filament-admin-theme.css').'?v='.filemtime(public_path('css/filament-admin-theme.css')).'">'
                ),
            )
```

Replace it with:

```php
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="'.asset('css/filament-admin-theme.css').'?v='.filemtime(public_path('css/filament-admin-theme.css')).'">'
                    .'<link rel="manifest" href="'.asset('manifest.webmanifest').'">'
                    .'<meta name="theme-color" content="#1C3A8A">'
                    .'<link rel="apple-touch-icon" href="'.asset('icons/admin-icon-192.png').'">'
                    .'<meta name="apple-mobile-web-app-capable" content="yes">'
                    .'<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
                    .'<meta name="apple-mobile-web-app-title" content="MNCH Admin">'
                ),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(
                    '<script>'
                    ."if ('serviceWorker' in navigator) {"
                    ."  window.addEventListener('load', function () {"
                    ."    navigator.serviceWorker.register('".asset('sw.js')."', { scope: '/admin/' }).catch(function () {});"
                    .'  });'
                    .'}'
                    .'</script>'
                ),
            )
```

- [ ] **Step 2: Verify in the browser**

Visit `/admin` (logged in), open DevTools → Application tab:
- "Manifest" section shows the app name, icons, and theme color with no errors.
- "Service Workers" section shows `sw.js` registered and activated, scope `/admin/`.
- Run a Lighthouse PWA audit (DevTools → Lighthouse → Progressive Web App) on `/admin` — the "installable" checks should pass.

Also verify the public resource frontend (any non-`/admin` page) has no manifest/SW link — confirming the scope stayed contained to the admin panel.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: register PWA manifest and service worker on admin panel"
```

---

## Task 9: "Install App" user-menu affordance

**Files:**
- Create: `resources/views/filament/components/install-app-menu-item.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (add one `renderHook` call near the existing `USER_MENU_PROFILE_BEFORE` hook)

**Interfaces:**
- Consumes: none beyond browser `beforeinstallprompt` / `matchMedia` APIs.

- [ ] **Step 1: Create the Alpine-driven menu item view**

`resources/views/filament/components/install-app-menu-item.blade.php`:

```blade
<div
    x-data="{
        deferredPrompt: null,
        show: false,
        isIos: false,
        isStandalone: false,
        init() {
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            if (this.isStandalone) {
                return;
            }

            this.isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferredPrompt = event;
                this.show = true;
            });

            if (this.isIos) {
                this.show = true;
            }
        },
        async install() {
            if (this.isIos || !this.deferredPrompt) {
                return;
            }

            this.deferredPrompt.prompt();
            await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.show = false;
        },
    }"
    x-show="show"
    x-cloak
    style="padding: 4px 8px;"
>
    <button
        type="button"
        x-on:click="install()"
        x-bind:title="isIos ? 'Tap the Share icon, then \'Add to Home Screen\'' : 'Install this app'"
        style="
            display:flex;align-items:center;gap:10px;width:100%;
            padding:8px 10px;border-radius:0.5rem;border:none;background:transparent;
            font-size:13px;font-weight:600;color:inherit;cursor:pointer;text-align:left;
        "
        onmouseover="this.style.background='rgba(28,58,138,0.08)'"
        onmouseout="this.style.background='transparent'"
    >
        <span aria-hidden="true">📲</span>
        <span x-text="isIos ? 'Add to Home Screen' : 'Install App'"></span>
    </button>
</div>
```

- [ ] **Step 2: Register the render hook in `AdminPanelProvider.php`**

Add this immediately after the existing `USER_MENU_PROFILE_BEFORE` hook (around line 62, right after the `renderHook` block that renders `filament.components.user-menu-header`):

```php
            ->renderHook(
                PanelsRenderHook::USER_MENU_AFTER,
                fn () => view('filament.components.install-app-menu-item'),
            )
```

- [ ] **Step 3: Verify in the browser**

On desktop Chrome/Edge visiting `/admin`, open the user menu — an "Install App" item should appear (it may take a moment for `beforeinstallprompt` to fire) and clicking it should trigger the native install prompt. On iOS Safari, the item should read "Add to Home Screen" and do nothing on click except show its title tooltip on long-press (iOS has no programmatic install API). After installing and re-opening the app in standalone mode, the menu item should not appear at all.

- [ ] **Step 4: Commit**

```bash
git add resources/views/filament/components/install-app-menu-item.blade.php app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: add Install App affordance to admin user menu"
```

---

## Self-Review Notes

- **Spec coverage:** Tasks 1-3 cover buttons/inputs/checkboxes/toggles/cards/sections/stat-widgets/tables from spec §1. Task 4 covers charts from spec §1. Tasks 5-8 cover the manifest/service-worker/icons/meta-tags from spec §2. Task 9 covers the install affordance from spec §2 (including the iOS fallback). Non-goals (no offline caching, no palette change, no Capacitor, no other panels touched) are respected throughout — Task 7's service worker only precaches the fixed `STATIC_ASSETS` list, never HTML or API routes.
- **Type consistency:** `ChartTheme::base()` and `ChartTheme::merge()` signatures in Task 4 match across the test and implementation steps. All six `getOptions()` call sites use the identical `\App\Support\ChartTheme::merge([...])` wrapping pattern.
- **No placeholders:** every CSS/PHP/Blade/JSON block above is complete, runnable code — nothing deferred to "later."
