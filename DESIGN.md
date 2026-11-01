---
name: BookEase — Labnasan Resort
description: A nautical chart of the bay — chart paper, hairlines, sounding numerals, teal water, one coral marker.
colors:
  chart-paper: "#faf7f1"
  chart-paper-deep: "#f1ebdf"
  ink: "#0b2324"
  lagoon-900: "#163a3b"
  lagoon-800: "#1c4747"
  lagoon-700: "#1e5756"
  lagoon-50: "#eef8f7"
  ocean-600: "#2b74b4"
  sand-100: "#f5ecdc"
  sand-300: "#dfc497"
  coral-marker: "#c2654b"
  coral-300: "#df9f8a"
  driftwood-600: "#635a4e"
  white: "#ffffff"
typography:
  display:
    fontFamily: "Libre Caslon Display, Libre Caslon Text, Georgia, serif"
    fontSize: "clamp(3.1rem, 8.6vw, 6rem)"
    fontWeight: 400
    lineHeight: 0.98
    letterSpacing: "-0.025em"
  heading:
    fontFamily: "Libre Caslon Display, Georgia, serif"
    fontSize: "3rem-3.75rem"
    fontWeight: 400
    lineHeight: 1.02
    letterSpacing: "-0.018em"
  title:
    fontFamily: "Libre Caslon Text, Georgia, serif"
    fontSize: "1.25rem"
    fontWeight: 400
    lineHeight: 1.35
  body:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  sounding:
    fontFamily: "Hanken Grotesk, sans-serif"
    fontSize: "0.68rem"
    fontWeight: 600
    letterSpacing: "0.18em"
rounded:
  sm: "2px"
  DEFAULT: "3px"
  md: "4px"
  lg: "5px"
  xl: "6px"
  2xl: "7px"
  3xl: "8px"
spacing:
  gutter-mobile: "24px"
  gutter-desktop: "48px"
  section: "96px-128px"
components:
  button-primary:
    backgroundColor: "{colors.lagoon-800}"
    textColor: "{colors.chart-paper}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-primary-hover:
    backgroundColor: "{colors.lagoon-700}"
  button-ink:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.chart-paper}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-ghost:
    backgroundColor: "rgba(255,255,255,0.6)"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
  button-light:
    backgroundColor: "{colors.chart-paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
  card:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.lg}"
  field:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "10px 14px"
  badge:
    rounded: "{rounded.DEFAULT}"
    padding: "2px 8px"
---

# Design System: BookEase — Labnasan Resort

## Overview

The UI is a nautical chart of the bay off Basey, Samar. Surfaces are chart paper with a faint grain. Structure comes from hairlines: neatlines with graduation ticks, graticule grids, and ruled tables. Numerals read like soundings, in small tracked caps with tabular figures. Water is deep teal, land is buff sand, and chart-magenta (a muted coral) marks things.

It replaces a first pass the user called "cartoony": flat sunset/palm/house drawings, pill buttons, bubbly 24px corners, chunky Fraunces, wave dividers and bright coral fills. Each of those is now a Don't.

Photography carries the atmosphere. All current photos and video are **stock placeholders** (Unsplash/Pexels). Their sources are in `public/images/stock/CREDITS.md`, `public/videos/CREDITS.md` and `database/seeders/photos/CREDITS.md`. Replace them with real Labnasan Resort media before launch.

## Colors

### Primary
- **Ink `#0b2324`** (`lagoon-950`): headlines, strongest buttons, night sections, the footer.
- **Lagoon `#1c4747` / `#1e5756`** (`lagoon-800/700`): primary actions, active states, links.

### Secondary
- **Ocean `#2b74b4`** (`ocean-600`): the second stay in the changeover strip, "checked in" status, calendar info.

### Tertiary
- **Coral marker `#c2654b`** (`coral-500`): chart pins, the logo's sun, today's date underline, small step labels, errors. Never use it as a large fill or a button.

### Neutral
- **Chart paper `#faf7f1`** is the page; `#f1ebdf` is for alternate bands.
- **Sand** (`sand-*`) is buff "land": placeholders and pending status.
- **Driftwood gray** (`gray-*`) is for body copy and secondary text.

### Named Rules
- **Marker rule.** Coral marks a point and never fills a region.
- **Hairline rule.** Separate content with 1px `ink/10` rules before reaching for a card.

## Typography

- **Libre Caslon Display** (one weight, 400) for headings 3xl and up. It is engraved and high-contrast; never fake its bold.
- **Libre Caslon Text** (`font-serif`) for titles up to 2xl, the wordmark, and the assistant's question list.
- **Hanken Grotesk** for everything else.
- Prices use the sans with `tabular-nums` because ₱ is not in the Caslon character set.

### Hierarchy
- **Hero:** `clamp(3.1rem, 8.6vw, 6rem)`, leading 0.98, set in lines that rise from a mask.
- **Section heading:** `text-5xl sm:text-6xl`, leading 1.02.
- **Page header:** `text-[2.1rem] sm:text-5xl`, over a bottom hairline.
- **Card title:** `font-serif text-xl`.
- **Sounding** (`.sounding`): 0.68rem caps, 0.18em tracking, for coordinates, field labels and data headers.

### Named Rules
- **No kickers.** Don't put an eyebrow line above a heading; `x-page-header` ignores its `eyebrow` prop. `.eyebrow` is allowed only as a section label on dense operate screens.

## Layout
- Content width is `max-w-7xl`. Gutters are 24px on mobile and up to 48px on the landing page (px-6 / sm:px-10 / lg:px-12).
- Landing sections use 96–128px vertical rhythm and alternate paper with ink so the page has pace.
- Data sits in ruled rows (a tide-table pattern) rather than grids of identical cards.

## Elevation & Depth

### Shadow Vocabulary
- `shadow-soft`: a 1px hairline lift for resting cards.
- `shadow-lift`: tilted or hovered cards and toasts.
- `shadow-plate`: the hero dock, cottage photo and chat panel, i.e. objects that float off the chart.
- `lagoon-glow` / `coral-glow` remain only as names for older markup. They are now neutral 1–2px shadows, with no colored halos.

### Named Rules
- **3D lives in transforms, not shadows.** Depth comes from perspective, rotateX/Y and translateZ (tilt, plate, pins). Shadows only ground those objects.

## Shapes
- Corners are drafted. Tailwind's radius scale is overridden: `rounded-2xl` is 7px and `rounded-3xl` is 8px.
- Use `rounded-full` only for status dots, avatars, toggles, the chat launcher and chart pin rings.

## Components

### Buttons
- `.btn-primary` is lagoon and `.btn-coral` is ink (the name is kept for older markup).
- `.btn-ghost` is a hairline outline, `.btn-light` is paper on dark sections, and `.btn-danger` is deep coral.
- All buttons have 4px corners and press with a 1px drop and 0.98 scale.
- Add `<span class="arrow">→</span>` for a forward action; the arrow slides on hover.

### Chips / badges
- `.badge` plus `.badge-{status}`: 3px corners, an inset ring and a dot. Use `.is-live` for the pulsing dot.

### Cards / Containers
- `.card` is white with a 1px `ink/10` border and `rounded-lg`.
- `x-tilt` + `.tilt` gives a 3D tilt (default 5°, 4° on cottage cards) with sun-glare.
- `.tilt-photo` makes the photo drift against the tilt for depth.

### Inputs / Fields
- `.field` is white with a 1px `ink/15` border and 4px corners. On focus it gets a lagoon border and a 3px lagoon/15 ring.
- An invalid field nudges.

### Navigation
- `x-nav-link`: the active link gets a 1px ink underline that draws from the left. No pills.
- Mobile menu rows mark the active row with a coral dot.

### Signature components
- **Hero plate** (`welcome.blade.php`):
  - A looping shore video settles flat from a 3D tilt (`.hero-plate`).
  - It has a ticked neatline frame and graticule hairlines that draw in, plus coordinates 11°17′N 125°04′E.
  - Headline lines rise from masks, and pointer parallax depth layers use `x-parallax` + `.depth` with `--d`.
  - The booking dock is a real GET search to `/cottages`.
- **The bay in layers** (`x-bay`):
  - An aerial plate tilts back in 3D and flattens as you scroll (`--p`). Contour layers float above it at `--z`.
  - Pins are the live cottages (name + rate), standing up off the plate and counter-rotated to face the viewer.
  - Positions are illustrative and labeled as such.
- **Changeover strip** (`.changeover`): the noon check-out bar draws in, then the 2 PM check-in bar draws back from the right, with hatching between them.
- **Cottage photo morph:** `<x-cottage-image morph>` sets `view-transition-name: cottage-photo-{id}`, so the photo flies from the card into the cottage page. Use `morph` once per cottage per page.
- **Reveals:**
  - `x-reveal`, `x-reveal.stagger`, and `x-reveal.stagger.rise` (cards flip up in 3D).
  - These use the individual `translate`/`rotate` properties, so they never fight the tilt transform.
  - The classes remove themselves after the entrance.

## Do's and Don'ts

### Do:
- Use real photography of the resort and replace the placeholders.
- Separate content with hairlines and ruled rows.
- Set numbers as soundings: small caps labels and tabular figures.
- Show live data from the database, never invented claims.
- Keep motion exponential (`ease-expo`, `cubic-bezier(.16,1,.3,1)`) and respect `prefers-reduced-motion`. Every effect has a static fallback.

### Don't:
- Don't use flat illustrated scenery (suns, palms, houses), wave dividers or blurred color blobs.
- Don't use pill buttons or corners above 10px on containers.
- Don't use coral fills, colored glows, gradient text or bounce easing.
- Don't put an eyebrow or kicker above a heading.
- Don't use Fraunces or any faux-bold of the Caslon faces.
