# Design system — Mickey's Travel and Tour

Tokens live in one place: the `<style type="text/tailwindcss">` block in `includes/header.php` (Tailwind v4 `@theme`). This file explains them. If you change a colour there, change it in `bin/contrast.php` too and re-run `php bin/contrast.php`.

## Point of view

A Lagos travel desk, not a template. The look is deep ink navy, warm paper backgrounds, and one saffron accent. The search box is the hero, and there are no stock beach photos. Prices are set in tabular figures so columns of fares line up. The copy is plain and warm, the way a good agent talks on the phone.

**Never:** purple or indigo gradients, glassmorphism, blobs, emoji as icons, three identical feature cards, `rounded-3xl`, grey-on-grey text, invented counters ("10,000+ happy travellers"), fake reviews.

## Colour tokens

| Token | Hex | Use |
|---|---|---|
| `ink-950` | `#0a1420` | Footer background, text on saffron |
| `ink-900` | `#0f1f33` | Header/hero background, body text, dark buttons |
| `ink-800` | `#183049` | Hover on dark, links |
| `ink-700` | `#24425f` | Secondary text |
| `ink-600` | `#36597a` | Labels, captions |
| `ink-500` | `#557595` | Placeholders, input borders |
| `ink-300` | `#afc0d1` | Muted text **on dark only**; dividers |
| `ink-200` | `#d3dde7` | Secondary text on dark |
| `ink-100` | `#e9eef3` | Neutral badge, skeletons, hover fills |
| `saffron-300` | `#f6c873` | Accent on dark, primary hover, demo banner |
| `saffron-400` | `#f0b048` | **Primary buttons** (with `ink-950` text) |
| `saffron-500` | `#e39a26` | Focus ring, link underline |
| `saffron-700` | `#94600f` | Eyebrow text on light backgrounds |
| `paper-50` | `#fcfaf6` | Page background |
| `paper-100` | `#f6f1e7` | Alternate section background |
| `paper-200` | `#ece3d2` | Borders on paper |
| `ok-700` / `ok-100` | `#1f6b47` / `#dcefe4` | Confirmed, paid |
| `warn-700` / `warn-100` | `#8c5b0c` / `#fbecd0` | Pending, awaiting action |
| `bad-700` / `bad-100` | `#a53a26` / `#f8e0da` | Failed, refused, errors |

Rules: saffron never carries text on a light background except `saffron-700`. Secondary text on light backgrounds uses `ink-600` or darker; `ink-500` is for placeholders only.

### Contrast (WCAG AA), from `php bin/contrast.php`, 2026-11-07

All 26 pairs pass. Lowest results: `ink-500` placeholder on white 4.81:1, `warn-700` on `warn-100` 4.98:1, `saffron-700` on paper 5.11:1. Body text is 15.94:1 and the primary button is 9.71:1. Input borders (`ink-500`) are 4.81:1, which meets the 3:1 needed for form controls.

## Typography

- **Display:** Bricolage Grotesque (variable 200–800), `font-display`. Used for headings and big prices.
- **Text:** IBM Plex Sans 400/500/600/700, `font-sans` (the default).
- Both are self-hosted from `public/assets/fonts/` under the SIL OFL 1.1 licence, which is included in that folder. Latin-ext files load only when a page contains ₦.
- **Prices, times, references:** add `num` (tabular figures).

| Role | Classes |
|---|---|
| Hero heading | `text-3xl sm:text-5xl font-bold leading-tight` |
| Page heading (h1) | `text-2xl sm:text-3xl font-bold` |
| Section heading (h2) | `text-xl sm:text-2xl font-bold` |
| Card title (h3) | `text-lg font-semibold` |
| Body | `text-base` (16px; never smaller for paragraphs) |
| Small / meta | `text-sm` |
| Label / eyebrow | `text-xs font-semibold uppercase tracking-wide` |
| Big price | `font-display text-2xl font-semibold num` |

## Spacing and layout

- Tailwind's 4px scale. Page gutter: `px-4` (16px) on phones, `sm:px-6`. Content width: `max-w-6xl` (`.container-page`).
- Sections: `py-10 sm:py-14`. Card padding: `p-4 sm:p-5`. Gap between cards: `gap-4`.
- Radius: `rounded-md` for controls, `rounded-lg` for cards. Nothing larger.
- Mobile first. Check every page at 360px. Touch targets are at least 44px tall: buttons are `py-2.5` plus line height, and fields are `py-2.5`.

## Components

Each one is a set of Tailwind classes, defined in `includes/header.php` (`@layer components`) or written inline. Combine base and variant classes, e.g. `class="btn btn-primary"`. Tailwind v4 cannot `@apply` one custom class inside another.

| Component | Classes / file | Notes |
|---|---|---|
| Button | `btn` + `btn-primary` / `btn-dark` / `btn-ghost` | One primary action per view |
| Text input | `field-label` on the label, `field` on the input | Errors: `field-error` text under the field, and `aria-invalid="true"` |
| Search tabs | `includes/search-box.php` + `assets/js/search.js` | Real `role="tablist"`, arrow-key navigation, each tab is its own GET form |
| Airport autocomplete | `data-airport` inputs, `api/airport-suggest.php` | Shows city, airport name, IATA code; keyboard navigable; hidden input holds the code |
| Passenger/cabin picker | `<details>` popover in the search box | Adults 1–9, children 0–8, infants ≤ adults |
| Flight card | Phase 2 (`flight-results.php`) | Airline, times, duration, stops, bags, ₦ total |
| Hotel card | Phase 5 | Photo, name, rating, nightly + total ₦ |
| Status badge | `badge` + `bg-ok-100 text-ok-700` etc. | Text always states the status; colour is never the only signal |
| Stepper | Phase 3 booking steps | `ol` with `aria-current="step"` |
| Table | Admin, Phase 4 | `text-sm`, zebra `odd:bg-paper-50`, stacks into cards under `sm` |
| Card | `card` | White, `rounded-lg`, hairline ring |
| WhatsApp button | `includes/footer.php` | Fixed bottom-right, `wa.me` link, labelled |

## Icons

Inline SVG from `public/assets/icons/*.svg` via `icon('name')`, 24×24, `stroke="currentColor"`. No icon fonts and no emoji.

## Images

No stock photos in the hero. Tour cards use the package's own photo when the agency uploads one. Until then they use a typographic panel (destination name on an ink background). Real photos must be WebP, at most 1200px wide, and `loading="lazy"` below the fold.

## Visual language (v2, 2026-11-07)

The brand borrows from real travel objects instead of generic web patterns:

| Motif | Where | How it's built |
|---|---|---|
| **Boarding pass** | The search box on every page | `includes/search-box.php`: a white ticket with notches cut where it "tears" (CSS mask on a background layer, so dropdowns aren't clipped), a dashed perforation, a stub holding the main button and a decorative barcode. Fields are "cells" with tiny mono labels. |
| **Split-flap departures board** | Homepage, popular routes | `#departures` in `index.php` + `assets/js/site.js`. Letters flip into place when the board scrolls into view. Distances are real great-circle distances from the airport coordinates. Screen readers get the word via `aria-label`; the tiles are `aria-hidden`. |
| **Passport stamp** | Homepage visa section | Inline SVG with text on a circular path ("Mickey's Visa Desk · Lagos"). Decorative, `aria-hidden`. |
| **Editorial numbering** | Destination cards, visa steps, hero list | `01`, `02`… in IBM Plex Mono. |

**Type:** Bricolage Grotesque ExtraBold at large sizes with tight tracking (`tracking-[-0.035em]`, `leading-[0.95]`) for headlines; IBM Plex Sans for text; **IBM Plex Mono** (500/600, self-hosted, OFL) for eyebrows, codes, labels and the board (`.eyebrow`, `font-mono`).

**Photos:** full-bleed heroes with a dark gradient (stronger on phones for contrast), a slow 24-second zoom (`.hero-photo`), and image cards that scale up slightly on hover.

**Motion:** sections fade and rise as they scroll in (`.reveal`, optional `--delay` for stagger). Everything is switched off under `prefers-reduced-motion`. A 4-second safety net shows all sections even if the scroll observer never fires.

**Layout:** wider container (`max-w-7xl`), asymmetric 12-column grids (7/5, then 4/4/4), generous section spacing (`py-16 sm:py-24`).
