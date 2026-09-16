# MNCH Mobile App UI Redesign

**Date:** 2026-05-23  
**Status:** Approved  
**Direction:** Apple iOS Minimal · Indigo Sapphire

---

## Design Direction

iOS-inspired minimal layout. Crisp white cards on an iOS system-grey background. Deep navy-to-indigo gradient hero panels (same pattern used on login, dashboard, and every screen header). Indigo Sapphire (`#4F6AF5`) as the single accent colour driving buttons, active nav, progress fills, and links. SF Pro / `-apple-system` font stack with tight letter-spacing on headings.

---

## Design Tokens  (`src/constants.js`)

```js
// Backgrounds
bg:           '#F2F2F7'   // iOS system grey — whole-app background
card:         '#FFFFFF'   // pure white cards
cardHover:    '#F9F9FB'

// Accent — Indigo Sapphire
primary:      '#4F6AF5'
primaryDark:  '#3A54D4'
primaryLight: '#6C63FF'
primaryGhost: 'rgba(79,106,245,0.08)'
primaryGlow:  'rgba(79,106,245,0.20)'

// Hero gradient stops
heroFrom:     '#1A1A2E'
heroMid:      '#1E2A5E'
heroTo:       '#2D3B8E'

// Text hierarchy  (iOS system colours)
text:         '#1C1C1E'
textMid:      '#3C3C43'
textSub:      '#636366'
textMuted:    '#8E8E93'

// Borders
border:       'rgba(0,0,0,0.08)'
borderLight:  '#F2F2F7'
separator:    'rgba(60,60,67,0.12)'   // iOS hairline

// Radii
radius:       18    // cards, modals, hero panels
radiusSm:     14    // list cells, inner cards
radiusXs:     10    // icon containers
radiusPill:   50    // buttons, badges

// Shadows
shadowCard:   '0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(79,106,245,0.06)'
shadowMd:     '0 4px 20px rgba(79,106,245,0.12)'
shadowLg:     '0 8px 32px rgba(79,106,245,0.18)'
shadowHero:   '0 12px 48px rgba(26,26,46,0.25)'

// Gradients
gradientPrimary: 'linear-gradient(135deg, #4F6AF5 0%, #6C63FF 100%)'
gradientHero:    'linear-gradient(150deg, #1A1A2E 0%, #1E2A5E 55%, #2D3B8E 100%)'
gradientSuccess: 'linear-gradient(135deg, #10B981 0%, #34D399 100%)'
gradientWarn:    'linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)'
gradientDanger:  'linear-gradient(135deg, #EF4444 0%, #F87171 100%)'

// Keep grade colours unchanged
```

---

## Typography

- **Font stack:** `-apple-system, 'SF Pro Display', 'Segoe UI', sans-serif`  
- **Import:** Drop Google Fonts import; rely on system stack only (faster, looks native)
- **Headings:** `font-weight: 800`, `letter-spacing: -0.5px` (large) / `-0.3px` (medium)
- **Body:** `font-weight: 400–600`, `letter-spacing: 0`
- **Labels/caps:** `font-weight: 700`, `letter-spacing: 1.5px`, `text-transform: uppercase`
- **Font smoothing:** keep `-webkit-font-smoothing: antialiased`

---

## Layout Patterns

### Hero Panel (login, dashboard, every screen top)
- `background: gradientHero`
- `border-radius: 0 0 24px 24px` + `margin: 0 6px` (floating card effect)
- Decorative radial-gradient orbs (2–3 per panel, `position: absolute`, `pointer-events: none`)
- Glassmorphism stat pills: `background: rgba(255,255,255,0.08)`, `border: 1px solid rgba(255,255,255,0.06)`, `backdrop-filter: blur(8px)`

### Cards / List Cells
- `background: #FFFFFF`, `border-radius: 14px`, `box-shadow: shadowCard`
- No coloured borders — colour lives in icon containers and badges only
- Icon containers: `border-radius: 10px`, `width/height: 36–40px`, tinted background matching the section colour, emoji or SVG inside
- Score/status badge: pill shape, `border-radius: 50px`, colour-coded bg+text pairs

### Bottom Navigation
- Frosted glass: `background: rgba(255,255,255,0.92)`, `backdrop-filter: blur(20px) saturate(180%)`, `border-top: 0.5px solid rgba(0,0,0,0.10)`
- Active tab: filled indigo pill (`gradientPrimary`) behind icon + indigo label  
- Inactive: `#8E8E93` icon + label, no background

### Buttons
- Primary: `gradientPrimary`, `border-radius: 14px`, `box-shadow: shadowMd`, white text `font-weight: 700`
- Secondary/ghost: `border: 1px solid separator`, transparent bg, `color: primary`
- Destructive: `gradientDanger`

### Form Fields
- Container: `background: #FFFFFF`, `border-radius: 12px`, `box-shadow: shadowCard`
- Focus ring: `box-shadow: 0 0 0 3px rgba(79,106,245,0.2), shadowCard`
- Leading icon tints to `primary` on focus

---

## Animations

All defined in `src/index.css` global keyframes (already exist — extend, don't replace):

| Name | Use | Spec |
|---|---|---|
| `fadeInUp` | List cards staggered entry | `from {opacity:0; translateY(14px)}`, `0.4s ease`, delay `i * 0.06s` |
| `scaleIn` | Modals, error toasts | `from {opacity:0; scale(0.94)}`, `0.2s cubic-bezier(0.34,1.56,0.64,1)` |
| `slideInUp` | Bottom sheets | `from {translateY(100%)}`, `0.35s cubic-bezier(0.32,0.72,0,1)` |
| `shimmer` | Skeleton loaders | existing — keep |
| `spin` | Loading spinners | existing — keep |
| `gradientShift` | Hero panel (subtle) | existing — keep |
| `float` | Login logo mark | `0%/100% translateY(0)`, `50% translateY(-6px)`, `4s ease-in-out infinite` |

Interactive spring: `transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1)` on buttons, cards, nav items.

---

## Scope of Changes

### 1. `src/index.css`
- Remove Google Fonts import
- Update CSS custom properties (`--primary`, `--bg`, etc.) to new tokens
- Keep all existing keyframes, add `slideInUp`

### 2. `src/constants.js`
- Replace entire `T` object with new tokens above
- Keep grade colours, `SECTION_META`, helper functions unchanged

### 3. `src/components/shared-components.jsx`
- `PhoneShell`: update background to `T.bg`
- `BottomNav`: frosted glass bar, indigo pill on active tab
- `GradeBadge`, `StatusChip`, `ProgressBar`: update to use new tokens (shapes unchanged)

### 4. `src/screens/screen-login.jsx`
- Hero: `gradientHero` background, floating logo mark with `gradientPrimary`, `float` animation
- Form section: white background, clean field boxes with focus ring

### 5. `src/screens/screen-dashboard.jsx`
- Hero header: `gradientHero` panel with glassmorphism stat pills
- Cards: white with `shadowCard`, emoji icon containers, colour-coded score badges
- Filter tabs: pill-style, active gets `gradientPrimary`

### 6. All other screen files
- Replace every inline `T.primary`, `T.bg`, `T.gradientDark`, `T.gradientPrimary`, `T.shadowCard` etc. reference — the token rename in `constants.js` handles most of this automatically since screens import `T`
- Update any hardcoded hex colours that were outside the token system
- Update hero sections on: `screen-assessments-list`, `screen-mentorships-list`, `screen-mentorship-detail`, `screen-class-detail`, `screen-module-detail`, `screen-profile`, `screen-training-detail`

### 7. `src/App.jsx`
- Update loading screen background and spinner to new tokens

---

## What Does NOT Change

- All logic, state, API calls, offline queue — untouched
- Screen component structure — same JSX tree, only style values change
- `offline-store.js`, `sync-queue.js`, `api.service.js`, `network-status.js` — untouched
- Grade colours (green/yellow/red) — unchanged, already work well

---

## Success Criteria

- Login, Dashboard, and a detail screen visually match the approved mockup
- No regressions: all screens render without JS errors
- Build passes (`npm run build`) without warnings beyond the existing chunk-size note
- Fonts render as `-apple-system` (no Google Fonts request in network tab)
