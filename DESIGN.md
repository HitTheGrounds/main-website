# Design Guidelines: Hit the Grounds 2026

The public website of the CSE Hit the Grounds cricket tournament. This document records the design decisions we have made, so new pages and changes stay consistent. When code and this document disagree, the code shows what is live and this document shows what we intend. Fix whichever is wrong.

**Stack:** Laravel 12, Livewire (Volt and Flux), Tailwind CSS 4, daisyUI 5, maryUI, Vite.
**Theme name:** `pitch-spine` (one light theme, no dark mode).

---

## 1. Principles

1. **Two brand colours: orange and cream.** Warm near-black ink is a neutral for text and dark panels, not a third brand colour. Navy and teal are retired.
2. **Full width.** Public pages use the whole viewport width with side padding. We do not use a narrow centred column for page content.
3. **Centred typography.** Headings, intros, card text and calls to action are centre aligned across the site (see section 5).
4. **One consistent background.** Pages sit on cream. Dark fills are accent panels only, never a full-bleed page or hero background.
5. **Real content.** No lorem ipsum, no invented statistics, no filler descriptions. If a heading does not need a subtitle, it does not get one.
6. **Motion that earns its place.** Effects play once, respect reduced motion, and never get in the way of reading.
7. **Not generic.** Avoid the usual template look: no gradient text, no glassmorphism, no emoji as icons, no zoom-on-hover.

---

## 2. Colour

Defined as daisyUI theme tokens in `resources/css/app.css`. Use the tokens (`bg-base-100`, `text-primary`) in markup. Use hex values only for the accent panels listed below.

| Token | Hex | Use |
| :-- | :-- | :-- |
| `base-100` | `#F4F1EA` | Page background (cream) |
| `base-200` | `#EBE6DB` | Alternate sections, card fills |
| `base-300` | `#DDD6C7` | Borders, dividers |
| `base-content` / `secondary` | `#1F1B16` | Text, ink, dark panels |
| `primary` | `#FF6A13` | Signal orange: buttons, big emphasis words, accents |
| `primary-ink` | `#B8480A` | Orange for **small text** on cream (about 4.9:1 contrast) |
| `accent` | `#F9D5BE` | Focus ring, soft orange wash |
| `neutral` | `#B8B2A6` | Muted UI |
| `success` / `warning` / `error` / `info` | `#2E7D4F` / `#E8A317` / `#C8321E` / `#2F7FB8` | Status only |

Rules:
- Plain `primary` orange is for large text (headings, numerals), fills and icons. For small text on cream, use `primary-ink`.
- Text on orange is ink (`primary-content`), never white.
- Accent-panel colours used in the awards and landing sections:
  - dark panel `#17120E`
  - gold `#FFC440` and `#E8A317`
  - violet `#6B2FD0` (Purple Cap)
  - green `#2E7D4F` (green dot ball)
- Colours that must be told apart should differ in lightness, not only hue.

---

## 3. Typography

**Typeface:** Plus Jakarta Sans only, weights 400 to 800 (loaded in `partials/head.blade.php`).

| Role | Style |
| :-- | :-- |
| Page title (h1) | `font-heading`, weight 800, tracking `-0.03em`, line height about 1.0, size `clamp(2.75rem, 8vw, 5.5rem)` |
| Section heading (h2) | `font-heading`, `clamp(2.2rem, 5vw, 3.6rem)` |
| Card title (h3) | `font-heading`, `text-2xl` to `text-4xl` |
| Body | 16 to 18px, `leading-relaxed`, `text-base-content/70` to `/75` |
| Kicker (small label above a heading) | 12 to 14px, bold, uppercase, tracking `0.22em`, `text-primary-ink` |
| Buttons | bold, uppercase, tracking `0.04em` (`.btn`) or bold sentence case (`.btn-island`) |

Rules:
- Headings are **sentence case** with tight tracking. Uppercase is for kickers, labels and buttons only.
- A two-tone heading puts the key word in `text-primary` ("Hit the **Grounds.**", "Awards & **Recognition**").
- Headings use `text-wrap: balance` (set globally).
- Body text keeps a readable measure: cap paragraphs with `max-w-[40ch]` to `max-w-[56ch]` and centre the block with `mx-auto`.
- **Never use em dashes or en dashes.** Use a colon, parentheses, a comma or a hyphen. Examples: "Most Valuable Player (Male)", "Player of the Match: Final".
- Write plainly. Avoid stock phrases that read as machine written.

---

## 4. Layout and width

- **Page container:** `<x-PageContainer>` is the standard section wrapper. It is `w-full` with side padding `px-4 sm:px-6 lg:px-12` and vertical padding `py-12 sm:py-16 lg:py-20`. It also adds the floating orange squares behind the content. Do not add `max-w-*` to it.
- **Navbar and footer** use the same side padding so edges line up.
- **Narrow elements are fine inside the full-width container** when they exist for readability: forms (`max-w-md`), paragraph measures, the registration information column.
- **Section rhythm:** alternate `bg-base-100` and `bg-base-200`. Accent panels (dark, orange, purple) are used for emphasis, not for whole pages.
- **Landing hero** is one viewport tall, below the navbar, and must be fully visible without scrolling at common desktop sizes (1366x768 up to 2560x1440). It has three columns:
  1. title, paragraph and the Register and View Timeline buttons
  2. the stat card
  3. the interactive 3D throne scene
  The 3D stage height is capped to the viewport (`lg:h-[calc(100dvh-9.5rem)]`, minimum 420px, maximum 760px). On small screens the columns stack in the same order.
- **Stat card:** a two-column grid. Numbers are right aligned in the first column and labels left aligned in the second ("20+ Teams", "300+ Participants"), each stat on one line, never stacked.
- **Breakpoints:** design mobile first. Every page must work at 390px wide with no horizontal scroll.

---

## 5. Centred typography

Centre these:
- page titles and section headings, plus any kicker above them
- intro paragraphs
- card titles and card text (team, player, cap, skill and committee cards)
- stat blocks and calls to action
- the person cards in the committee section

Leave these left aligned, because centred text hurts reading or scanning:
- rule lists and multi-line bulleted content (Rules page, Registration guidelines)
- individual entries inside the timeline list (the list block itself is centred on the page)
- table cells and form fields
- a label placed over a photograph beside a control (the "Industry Teams" tile)

---

## 6. Components

**Buttons**
- `.btn-island` is the primary call to action: a pill with a round arrow dot at the right. `btn-island-solid` is orange with ink text, `btn-island-line` is outlined.
- `.btn` (daisyUI) is used in forms and admin screens: uppercase, bold, lifts 1px on hover.
- Orange buttons must always show their orange fill, including on dark panels.

**Cards**
- Rounded generously (`rounded-[1.75rem]` to `rounded-[2rem]`), soft border or soft shadow, no heavy drop shadows.
- Hover lift and light sweep are allowed on content cards (`.aw-card`). Do not lift the cap cards.

**Navbar:** a floating, blurred cream pill, sticky at the top, with an orange Company Login button.

**Floating squares:** small orange squares drifting slowly behind sections (`FloatingSquares`, `.pixel`). Decorative only, hidden from assistive technology.

**Committee cards:** a 112px round photo (`size-28`) with an orange ring and offset, name, role. No hover effect on these cards.

---

## 7. Imagery

- **Format:** AVIF for all photographs and illustrations, stored under `public/`. Convert before committing.
- **Do not name a public folder after a route.** A folder called `public/memories/` shadows the `/memories` page. Photos live in `public/images/memories/`.
- **Committee portraits:** 512px square, cropped to head and shoulders with the face centred, consistent framing across all people. Filenames are the first name (`akindu.avif`).
- **Memories photos:** longest side 1200px, quality about 55. Always shown in original colour. Sponsor and photographer strips baked into the photos are left in place. The list lives in `resources/views/memories.blade.php`: add a photo by dropping `memory-N.avif` into `public/images/memories/` and adding one line there (portrait photos also need an entry in `$sizes`).
- **Award trophies** are one green render (`public/award/award_1.avif`, champion, tinted gold with the `aw-gold` CSS class) plus pre-recoloured silver, bronze and violet versions of it. Keep any new award trophy in the same family: same shape, different accent colour.
- Every `<img>` has `alt` text describing the photo (or `alt=""` with `aria-hidden` when decorative), explicit `width` and `height`, and `loading="lazy"` below the fold.

---

## 8. Motion

- **Scroll reveals** use AOS (`data-aos="fade-up"`). They are configured to play **once**, on the first scroll past (`AOS.init({ once: true })`). Do not make effects replay while scrolling up and down.
- **Continuous effects** (floating trophies, spinning rings, the ticker, the green dot pulse) are slow and decorative.
- **Hover patterns we use:**
  - card lift of 8px with a light sweep across the card
  - memories collage: a thin orange frame and a soft light that follows the cursor on the hovered photo, while the others step back (slightly less saturated and darker)
- **Hover patterns we avoid:** zoom or scale on photos, bouncing or floating on hover, wobble, anything that moves the layout.
- **Reduced motion:** every animation and transition must be switched off under `prefers-reduced-motion: reduce`. Touch devices get no hover-only effects.
- Easing: `--ease-out-expo` (`cubic-bezier(0.32, 0.72, 0, 1)`).

---

## 9. Page notes

- **Home:** hero, About (photo background with a dark overlay), "Beyond the boundary" (dot-grid background), Contenders, Registration, Partners, Committee.
- **Awards:** the hero is a real-time 3D podium (`resources/js/awards-podium.js`, three.js): three stepped blocks (champion, runners-up, MVP) on a turntable under a spotlight, each carrying its trophy; it turns slowly, can be dragged, tilts with the pointer, and pauses off-screen and for reduced motion. The same three trophies show as flat images when WebGL is unavailable. The Super Striker, Most Four Hits and Green Dot Ball cards each show a navy pillar trophy with copper circuit lines (`super_striker.avif`, `most_fours.avif`, `green_dot.avif`): one 3D model built in three.js and rendered at 1200x1800 with a red, blue or green ball, so the three match exactly. The rest of the page: cream-themed sections with the champion trophy render beside two smaller recolours of the same trophy (silver for runners-up (`award_silver.avif`; only the bright accents are recoloured, the dark green body stays). The MVP trophy (framed with its own backdrop) also sits in the hero. The two MVP awards use the batter-and-star trophy (`mvp_male.avif` in copper, `mvp_female.avif` recoloured to violet) on dark cards that blend into the image's own backdrop, an orange ticker, team honours, player honours, the two caps (orange and purple, IPL style, compact and static), skill awards (6, 4, green dot, Player of the Match), closing call to action. Award names and descriptions are fixed content.
- **Past Seasons** (`/past-seasons`): title, an orange ticker of champions, then a real 3D stage (`resources/js/past-seasons-3d.js`, three.js): a turntable with all five seasons' podiums standing around a ring (runners-up and champion blocks with logo cards, a crown over the champion, and the year on a front step). The turntable physically rotates to bring each season to the front: auto every 7 seconds (paused on hover, focus and drag), prev/next buttons, year tabs, or drag to spin and it settles on the nearest season. It pauses off-screen and for reduced motion. Without WebGL the older flat carousel is shown instead. Below it: an "All time" leaderboard (companies ranked by how many times they reached the podium, as tally marks in groups of five with an orange slash, plus the number; the marks draw themselves and the numbers count up once when it scrolls into view) and a call to action. Logos live in `public/images/rankings/`. A white-only logo goes on a dark card (DirectFN).
- **Memories:** centred title and a masonry collage of 21 full-colour photos (2, 3 or 4 columns by width).
- **Timeline, Rules, Registrations, Teams:** centred headings, structured content left aligned inside.
- **Auth pages (login, register):** a narrow centred form inside the full-width container.
- **Admin and company areas** use their own layouts with the same colour tokens and typeface. They are not covered in detail here.

---

## 10. Accessibility

- Visible focus on every interactive element (`:focus-visible` outline in `accent`).
- Text contrast of at least 4.5:1 (3:1 for large text). Use `primary-ink` for small orange text.
- Real `<button>` and `<a>` elements, never clickable divs. Icon-only buttons have an `aria-label`.
- Decorative elements (squares, rings, glows, ghost numerals) carry `aria-hidden="true"`.
- The page must be usable with images off, with the keyboard only, and with reduced motion on.
- Touch targets are at least 44px.

---

## 11. Naming and code conventions

- Use Tailwind utilities in markup. Page-specific effects go in `resources/css/app.css` under a short prefix: `aw-` (awards), `mem-` (memories), `btn-island` (calls to action).
- Repeated markup becomes a Blade component under `resources/views/components/` (for example `award-cap`, `PageContainer`).
- Section anchors use readable ids (`#team`, `#committee`).

---

## 12. Known gaps

These are places where the live site does not yet follow the rules above:

- The Registration, Partners and Teams (industry) page headings are uppercase. They should be sentence case like the other pages.
- The "Industry Teams" tile on the home page still zooms its photo on hover, which section 8 says to avoid.
- The `registrations` page and the home Registration section use a `max-w-4xl` column, and the home Timeline list uses `max-w-5xl`. These are intentional reading widths but are narrower than the rest of the page.
- Five automated tests were already failing before the redesign (team size rules, a removed support page, the university teams page being behind login, and a captcha token in a registration test). They are unrelated to design but should be fixed or removed.

---

## 13. Link previews and page titles

- The share image is `public/og-cover-2.png` (1200x630, cream with the orange 2026 logo and the department name). When the logo or year changes, make a new file with a **new name** and update `resources/views/partials/head.blade.php`. Chat apps and search engines cache images by address, so overwriting the same name keeps showing the old one.
- Every public page passes its own `title` and `description` to `<x-layouts.public>`. Titles read "Page | Hit the Grounds 2026". The home page uses the site name.
- After changing a share image, ask the chat app to refresh its preview (WhatsApp and Telegram keep their own cache for days).
