# Design System: Hit The Grounds 2026 ("Pitch Spine")
**Project ID:** hit-the-grounds-main-website (Laravel 12 · Livewire/Volt · Tailwind 4 · daisyUI 5 · maryUI)

> **Scope of this document.** This is a *reskin*. Routes, controllers, Livewire components, form fields, JWT/auth flow, DOM ids used by scripts (`#leftLight`, `.stats`, `[data-count]`, `#mainarticle`) and all copy stay as they are. Only colour tokens, type, shape, decoration, imagery and motion change.
> **Sources.** Three reference images: the "Coming Soon" poster (batsman on a throne of bats), the "Complete Event Kit" board (palette, apparel, posters, certificates, signage), and a blank white image. The kit is a mood reference, not a component list.

> **REVISION 2 (supersedes conflicting details below).** The brand is now **two colours: Signal Orange (`#FF6A13`) and Cream (`#F4F1EA`)**. Navy is removed. Text and dark bands use a warm near-black ink (`#1F1B16`) as a *neutral*, not a brand colour. Inspiration: skroll.lk: a floating white pill navbar with an orange pill CTA, a bold geometric grotesk headline with orange emphasis words, drifting orange square "pixels", pill buttons with nested arrow circles, and soft double-bezel cards.
> **Type:** Plus Jakarta Sans only (400–800). Headings 800, tracking -0.03em, sentence case (no forced uppercase). Replaces Big Shoulders Display + Archivo.
> **Hero (built):** editorial split. Left: eyebrow pill, "Hit the *Grounds.*" headline (second line orange), paragraph, two island pill buttons. Right: double-bezel card with an SVG cricket-field diagram (orange ball trajectory) and the 20+/300+/100% counters. Orange pixels drift behind. Entrance is a blur/fade-up reveal; reduced-motion shows content immediately.
> **Navbar:** floating blurred cream pill, detached from the top, orange pill CTA.

---

## 1. Visual Theme & Atmosphere

**Name: Pitch Spine.** The kit's own tagline is "Pitch Spine / Cream + Ink": the look of a groundsman's chalk lines on a dry, sun-bleached pitch, redrawn as a technical diagram.

**Physical scene.** A student or sponsor opens the site on a phone or laptop in daylight, in a corridor, a lecture hall or outdoors near the ground. They want dates, rules and a way to register fast. The page should feel like a printed match-day programme, not a glowing night-time dashboard. This is why the theme is **light**.

**Mood.** Confident, athletic, engineered. Three ideas carry it:
1. **Bold poster typography.** Heavy, condensed, all-caps headlines set very large, in ink and signal orange.
2. **Chalk-and-circuit line work.** Thin ink lines, dotted arcs, concentric circles and small solid orange discs. They read as both cricket-field geometry (creases, boundary ropes, the ball) and circuit traces, which fits a Computer Science & Engineering department.
3. **Print grit.** A subtle speckle/halftone texture on large orange and ink fills, like ink on rough stock. Used sparingly, on big shapes only.

**Density.** Open and poster-like on the home page, with large type and generous white space. Tighter and calmer on data pages (rules, teams, admin), where orange is nearly absent.

**Colour strategy: Committed with a restrained core.** Cream is the page, ink is the ink, and orange is the single loud accent, used for one or two large moments per screen and for primary actions. Cyan and stone grey are quiet supports.

**What it is not.** No dark-mode glow, no neon teal, no glassmorphism, no gradient text, no rounded "pill-everything" SaaS look. The old teal/green identity and the dark layered-photo hero are retired.

> *Note on the cream background:* a warm off-white is normally a generic default, but here it is the event's own brand colour from the kit, so it stays. To keep it from reading as "default paper", it is paired with saturated ink and orange, line graphics, and a heavy display face.

---

## 2. Color Palette & Roles

Hex values are the source of truth (sampled from the kit). daisyUI themes take OKLCH, so approximate OKLCH values are given for `app.css`.

| Role | Descriptive name | Hex | OKLCH (approx.) | Used for |
| :-- | :-- | :-- | :-- | :-- |
| **base-100** | Sun-bleached Cream | `#F4F1EA` | `oklch(96% 0.014 90)` | Page background, navbar, cards that sit on a tinted section |
| **base-200** | Deeper Cream | `#EBE6DB` | `oklch(92% 0.025 90)` | Alternating section bands, table stripes, form wells |
| **base-300** | Pitch Dust | `#DDD6C7` | `oklch(86% 0.04 90)` | Borders, dividers, footer band |
| **base-content** | Warm Ink | `#1F1B16` | `oklch(26% 0.09 265)` | All body text and headings on cream (≈14:1) |
| **primary** | Signal Orange | `#FF6A13` | `oklch(70% 0.19 45)` | Primary buttons, big display words, key discs and rules. **Never** small text on cream (≈2.7:1) |
| **primary-ink** (Tailwind token `text-primary-ink`) | Burnt Orange Ink | `#B8480A` | `oklch(53% 0.15 45)` | Orange *text* on cream at any size (≈4.9:1). Bright Signal Orange stays for fills and hero-scale display type |
| **primary-content** | Ink on Orange | `#1F1B16` | `oklch(26% 0.09 265)` | Text on orange buttons (≈5.5:1; white on orange fails) |
| **secondary** | Warm Ink (near-black) | `#1F1B16` | `oklch(26% 0.09 265)` | Secondary buttons, dark bands, stage backdrops, footer variant |
| **secondary-content** | Cream on Ink | `#F4F1EA` | `oklch(96% 0.014 90)` | Text on ink surfaces (≈14:1) |
| **accent** | Peach Tint | `#F9D5BE` | `oklch(86% 0.06 205)` | Small highlights, focus rings, selected states, chart secondary |
| **accent-content** | Ink on Peach | `#1F1B16` | `oklch(26% 0.09 265)` | Text on cyan |
| **neutral** | Warm Stone | `#B8B2A6` | `oklch(79% 0.012 100)` | Disabled states, muted icons, dotted decorative marks |
| **neutral-content** | Ink | `#1F1B16` | `oklch(26% 0.09 265)` | Text on stone grey |
| **info** | Clear Cyan-Blue | `#2F7FB8` | `oklch(58% 0.1 245)` | Informational alerts (white text) |
| **success** | Field Green | `#2E7D4F` | `oklch(52% 0.11 155)` | Approved/confirmed states (white text) |
| **warning** | Amber | `#E8A317` | `oklch(76% 0.15 80)` | Pending states (ink text) |
| **error** | Brick Red | `#C8321E` | `oklch(54% 0.18 32)` | Errors, destructive actions (white text) |

**Secondary text.** Muted copy uses ink at ~70% opacity (`#1F1B16` / 0.7), not grey, which keeps ≥4.5:1 on cream. Placeholders use the same.

**Colour rules**
- Orange fills carry **ink** text. Ink fills carry **cream** text. Never white on orange.
- Orange as *text* is only for display-size words (≥ 48px) and numerals. Anything small in orange fails contrast.
- No gradients for decoration. The only blends allowed are ink/orange fills with the speckle texture overlay.
- Hard-coded `text-white`, `bg-white`, `text-gray-*`, `bg-black/*` and `dark:` variants in the current Blade files are all replaced by the tokens above.

---

## 3. Typography Rules

All free Google Fonts, replacing Francois One / Staatliches / Nata Sans.

| Role | Font | Notes |
| :-- | :-- | :-- |
| **Display** (`.font-heading`, `.font-title`, h1/h2) | **Big Shoulders Display**, weight 800–900, ALL CAPS | Heavy, condensed, squared. The closest free match to the poster's "COMING SOON". Tight leading (0.92–1.0). |
| **Body & UI** | **Archivo**, weights 400 / 500 / 600 / 700 | Sturdy neo-grotesque with good small-size legibility. One family for body, forms, tables and nav. |
| **Label / mono accent** | Archivo 600, uppercase, letter-spacing 0.14em, 11–13px | The kit's wide-tracked small caps ("COMPUTER SCIENCE & ENGINEERING"). Used for the logo lockup, card meta and table headers. **Not** repeated above every section heading. |

**Scale (fluid, `clamp`)**
- Hero H1: `clamp(3rem, 9vw, 6rem)` (max 96px), letter-spacing −0.01em, leading 0.95.
- Section H2: `clamp(2.25rem, 5vw, 3.75rem)`.
- H3 / card title: 1.5–1.875rem, display font.
- Body: 1rem, line-height 1.6, max line length 65–72ch. Long-form rules text uses `#mainarticle` styling with `text-wrap: pretty`.
- Headings use `text-wrap: balance`.

**Character.** Display text is loud and compact: ink by default, with one word per heading allowed in orange (as in "COMING SOON" in the poster). Body text is quiet ink on cream. The jump between the two is the main source of hierarchy, so avoid mid-weight display sizes.

---

## 4. Component Stylings

daisyUI/maryUI component classes stay the same; their *look* comes from theme variables (`--radius-*`, `--border`, `--depth`, `--noise`) and a few targeted overrides.

- **Theme variables.** `--radius-box: 0.5rem`, `--radius-field: 0.375rem`, `--radius-selector: 0.375rem`, `--border: 2px`, `--depth: 0`, `--noise: 0`. Texture comes from the dedicated speckle overlay, not daisyUI's noise.
- **Buttons.**
  - *Primary:* Signal Orange fill, ink bold uppercase label (Archivo 700, tracking 0.04em), subtly rounded corners (6–8px), no shadow. Hover: darkens ~6% and lifts 1px. Focus: 3px Peach Tint ring offset 2px.
  - *Secondary / neutral:* Warm Ink (near-black) fill, cream label. Hover to slightly lighter ink.
  - *Ghost / outline:* 2px ink outline, transparent fill, ink label. Hover fills with Deeper Cream.
  - Minimum 44px tap height on touch.
- **Cards & containers.** Sharp-to-subtle corners (8px max). Cream or Deeper Cream background with a **2px Pitch Dust or ink border** and **no shadow**. Never a border plus a soft wide shadow. Feature cards may be solid ink with cream text. A single orange disc or a quarter-arc line sits in one corner as decoration. Never nest cards inside cards.
- **Navbar.** Cream, 2px ink bottom rule (a chalk line). Links are Archivo 600, active link marked by an orange 3px underline (not colour alone). The logo swaps to the single ink lockup (no dark variant).
- **Inputs & forms.** White-cream (`#FBF9F3`) fill, 2px ink-at-30% stroke that goes solid ink on focus plus the cyan focus ring. Labels sit above in Archivo 600. Errors use Brick Red text with an icon, never colour alone.
- **Tables.** Ink header row with cream label-style text; rows stripe between cream and Deeper Cream; 1px Pitch Dust row dividers. Status badges: pill-shaped, tinted fills with ink text (success/warning/error tokens).
- **Badges & tags.** Pill-shaped, 2px border, label type style.
- **Stats strip** (hero counters): large Big Shoulders numerals in orange on cream or ink, label type underneath. This keeps the existing counters and the `data-count` hook, but does not use the generic "big number + gradient" look.
- **Footer.** Pitch Dust band with ink logos (HTG, CSE, CSESS). The `dark:invert` logo logic is removed.
- **Admin & company layouts.** Same tokens. Sidebar is Warm Ink (near-black) with cream text and an orange active marker. The content area is Deeper Cream. The hard-coded `class="dark"` on `<html>` is removed.
- **Decorative motifs (CSS/SVG, no raster).** Concentric arcs, dotted arcs, vertical hairlines with dot terminals, solid orange discs (sizes vary, some with speckle). Placed at section edges, behind headings, never over text. Hidden or reduced below 640px.

---

## 5. Layout Principles

- **Grid.** 12-column container, max width ~1200px (rules/long-form 72ch inside). Section vertical padding `clamp(4rem, 8vw, 7rem)`, with varied rhythm: hero and feature bands are generous, data sections are tighter.
- **Section rhythm.** Alternate Cream → Deeper Cream → a full-bleed Ink band at the key moments (about / contenders / CTA). Dividers are thin chalk lines, not heavy rules.
- **Hero (poster-style).** Cream background. Giant two-line headline "HIT THE GROUNDS" (ink) with "CSE" or one key word in orange, supporting paragraph (max ~60ch), the two existing buttons, the stats strip, and line-and-circle graphics framing the composition. The eight layered photo AVIFs and the glitch-flicker script are removed from the hero (the `#leftLight`/`#leftBeam` elements go with them; the counter script stays). Asymmetric layout on desktop (text left or right, graphics opposite); stacked on mobile.
- **Hierarchy.** One dominant element per viewport (the display headline), then body, then actions. Avoid identical card grids: vary card sizes, mix one wide card with two narrow, or use list/editorial rows.
- **Spacing scale.** 4px base: 4, 8, 12, 16, 24, 32, 48, 64, 96, 128.
- **Responsive.** Mobile-first. Display type clamps down to ≥ 2.5rem so long words ("CHAMPIONSHIP", "CONNECTIONS") never overflow at 360px. Decorative graphics drop or shrink on mobile. Tables scroll inside their own container.
- **Imagery.** Existing photos (people, awards, partners, gallery) get a thin ink frame and optional duotone ink/cream treatment on decorative uses. Team and people photos remain true colour. `about-bg.avif` (dark photo behind text) is replaced with a ink band plus line graphics so light text stays readable.

---

## 6. Motion & Interaction

- Keep AOS, but retune: shorter durations (400–600ms), `ease-out` curves, no flip/zoom/bounce. Fades and small upward slides only.
- Hero: headline words rise in sequence; discs and arcs draw/scale in once. Counters keep their current behaviour.
- Hover/press states are quick (120–180ms) transform or colour changes only. No animated layout properties.
- `prefers-reduced-motion: reduce`: all entrance animation becomes an instant or simple cross-fade, and `animate-pulse` on the about-section borders is removed.
- Content must be visible without JavaScript or an animation firing.

---

## 7. Accessibility & Quality Bars

- Body text ≥ 4.5:1, large text ≥ 3:1, verified for every token pair above. Orange-on-cream is large/decorative only.
- Visible focus on every control (cyan ring + ink outline).
- State is never colour-only (icons/labels for success, warning, error, active nav).
- Touch targets ≥ 44px.
- Images keep their `alt` text; decorative SVG is `aria-hidden`.

---

## 8. Implementation Map (what changes, what doesn't)

| Area | Change | Files |
| :-- | :-- | :-- |
| Theme tokens | Replace `pastel` and `hitthegrounds-dark` with one light theme `pitch-spine`, make it the only theme | `resources/css/app.css` |
| Fonts | Swap Google Fonts link and `.font-heading` / `.font-title` / body family | `partials/head.blade.php`, `app.css` |
| Dark mode | Remove `@fluxAppearance` dark logic, `class="dark"` on admin layout, `dark:` variants, `theme-color` meta | `head.blade.php`, `layouts/admin.blade.php`, ~10 views |
| Hero | Rebuild markup and styling; keep buttons, routes, auth-aware CTA logic and counter script | `components/HeroSection.blade.php` |
| Home sections | Restyle with tokens; replace the dark photo background | `AboutSection`, `MoreThanGameSection`, `ContendersSection` |
| Chrome | Navbar, footer, logos | `Navbar`, `FooterSection`, `layouts/public` |
| Inner pages | Token pass: rules, timeline, awards, partners, gallery, committee, registrations | respective views |
| Auth & dashboards | Token pass on login/register, company and admin layouts and Livewire views | `layouts/company`, `layouts/admin`, `livewire/*` |
| **Untouched** | Controllers, routes, models, migrations, Livewire PHP logic, form fields and names, `wire:*` directives, JWT service, middleware | — |

**Assets to supply or generate:** a single ink HTG logo lockup (the kit's logo, light-background version), a favicon/theme-color update (`#F4F1EA`), and optional SVG motif pack (arcs, discs, hairlines). Existing `nav_logo_dark.avif` can serve as the light-theme logo in the meantime.
