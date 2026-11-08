<?php
// Fonts, Tailwind theme and shared styles. Included in <head> by header.php and admin-header.php.
// $base is '' for pages in the main folder and '../' for pages in admin/.
if (!isset($base)) $base = '';
?>
<link rel="preload" href="<?= $base ?>assets/fonts/ibm-plex-sans-latin-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $base ?>assets/fonts/bricolage-grotesque-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $base ?>assets/css/fonts.css">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.5/dist/index.global.js"></script>
<style type="text/tailwindcss">
  /* Colours and fonts. Explained in docs/DESIGN.md */
  @theme {
    --font-display: "Bricolage Grotesque", ui-sans-serif, system-ui, sans-serif;
    --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    --font-mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace;

    --color-ink-950: #0a1420; --color-ink-900: #0f1f33; --color-ink-800: #183049; --color-ink-700: #24425f;
    --color-ink-600: #36597a; --color-ink-500: #557595; --color-ink-400: #7e98b2; --color-ink-300: #afc0d1;
    --color-ink-200: #d3dde7; --color-ink-100: #e9eef3;

    --color-saffron-300: #f6c873; --color-saffron-400: #f0b048; --color-saffron-500: #e39a26;
    --color-saffron-600: #c27d12; --color-saffron-700: #94600f; --color-saffron-800: #6e480e;

    --color-paper-50: #fcfaf6; --color-paper-100: #f6f1e7; --color-paper-200: #ece3d2; --color-paper-300: #ddd0b8;

    --color-ok-700: #1f6b47; --color-ok-100: #dcefe4;
    --color-warn-700: #8c5b0c; --color-warn-100: #fbecd0;
    --color-bad-700: #a53a26; --color-bad-100: #f8e0da;

    --ease-out-soft: cubic-bezier(0.22, 1, 0.36, 1);
  }
  @layer base {
    body { @apply bg-paper-50 text-ink-900 font-sans antialiased; }
    h1, h2, h3 { font-family: var(--font-display); letter-spacing: -0.02em; text-wrap: balance; }
    :focus-visible { outline: 3px solid var(--color-saffron-500); outline-offset: 2px; }
    a, button, summary, label[for], select { cursor: pointer; }
    .num { font-variant-numeric: tabular-nums; }
  }
  @layer components {
    .btn { @apply inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition duration-200 active:scale-[0.98]; }
    .btn-primary { @apply bg-saffron-400 text-ink-950 hover:bg-saffron-300; }
    .btn-dark { @apply bg-ink-900 text-paper-50 hover:bg-ink-800; }
    .btn-ghost { @apply text-ink-800 ring-1 ring-inset ring-ink-300 hover:bg-ink-100; }
    .field-label { @apply block text-xs font-semibold uppercase tracking-wide text-ink-600; }
    .field { @apply mt-1 block w-full rounded-md border border-ink-500 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-ink-700 focus:outline-none focus:ring-2 focus:ring-saffron-400; }
    .field-error { @apply mt-1 text-sm text-bad-700; }
    .card { @apply rounded-lg bg-white shadow-sm ring-1 ring-ink-900/5; }
    .badge { @apply inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold; }
    .link { @apply font-medium text-ink-800 underline decoration-saffron-500 decoration-2 underline-offset-4; }
    .container-page { @apply mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8; }
    .eyebrow { @apply font-mono text-xs font-semibold uppercase tracking-[0.2em]; }
  }
</style>
<style>
  /* ---- Things Tailwind classes can't express neatly ---- */

  /* Boarding-pass search box: round notches cut out where the ticket "tears" */
  .ticket { --tear: 0px; border-radius: 1rem; }
  @media (min-width: 1024px) {
    .ticket {
      --tear: calc(100% - 17rem);
      -webkit-mask: radial-gradient(circle 14px at var(--tear) 0, #0000 97%, #000) top / 100% 51% no-repeat,
                    radial-gradient(circle 14px at var(--tear) 100%, #0000 97%, #000) bottom / 100% 51% no-repeat;
              mask: radial-gradient(circle 14px at var(--tear) 0, #0000 97%, #000) top / 100% 51% no-repeat,
                    radial-gradient(circle 14px at var(--tear) 100%, #0000 97%, #000) bottom / 100% 51% no-repeat;
    }
  }
  .perforation { background-image: linear-gradient(to bottom, var(--color-ink-300) 50%, transparent 50%); background-size: 1px 10px; }
  .perforation-x { background-image: linear-gradient(to right, var(--color-ink-300) 50%, transparent 50%); background-size: 10px 1px; }
  .barcode { background: repeating-linear-gradient(90deg, currentColor 0 2px, transparent 2px 4px, currentColor 4px 5px, transparent 5px 8px, currentColor 8px 11px, transparent 11px 13px); }

  /* Split-flap departure board letters */
  .flap { display: inline-block; min-width: 1.05ch; text-align: center; background: linear-gradient(#16263b 50%, #111f31 50%); border-radius: 3px; margin-right: 1px; box-shadow: inset 0 -1px 0 #0006; }
  .flap.flip { animation: flap 120ms linear; }
  @keyframes flap { 0% { transform: rotateX(0) } 50% { transform: rotateX(80deg) } 100% { transform: rotateX(0) } }

  /* Gentle reveal as sections scroll into view (only when JS has run) */
  .js .reveal { opacity: 0; transform: translateY(24px); transition: opacity 700ms cubic-bezier(0.22, 1, 0.36, 1), transform 700ms cubic-bezier(0.22, 1, 0.36, 1); transition-delay: var(--delay, 0ms); }
  .js .reveal.is-visible { opacity: 1; transform: none; }

  /* Slow zoom on the big hero photo */
  .hero-photo { animation: drift 24s ease-out both; }
  @keyframes drift { from { transform: scale(1.08) } to { transform: scale(1) } }

  /* Anyone who asked their device for less motion gets none */
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
    .js .reveal { opacity: 1; transform: none; }
  }
</style>
<script>document.documentElement.classList.add('js');</script>
