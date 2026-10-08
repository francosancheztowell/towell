---
name: Towell · Panel /admin (Monitoreo)
description: Observability console for the Sistemas area. Dark zinc by default, light mirror on request, violet only for selection and action.
colors:
  console-black: "#09090b"
  rail-black: "#0c0c0f"
  panel-zinc: "#18181b"
  hover-zinc: "#222227"
  hairline: "#27272a"
  hairline-strong: "#3f3f46"
  ink: "#f4f4f5"
  ink-muted: "#a1a1aa"
  ink-faint: "#8a8a94"
  violet-text: "#b59cff"
  violet-solid: "#7c3aed"
  violet-solid-dark-flux: "#8b5cf6"
  rose-error: "#fb7185"
  amber-warn: "#fbbf24"
  emerald-ok: "#34d399"
  sky-info: "#38bdf8"
  bar-idle: "#2e2e34"
  light-bg: "#fafafa"
  light-rail: "#f4f4f5"
  light-panel: "#ffffff"
  light-hover: "#f4f4f5"
  light-hairline: "#e4e4e7"
  light-hairline-strong: "#d4d4d8"
  light-ink: "#18181b"
  light-ink-muted: "#52525b"
  light-ink-faint: "#71717a"
  light-violet-text: "#6d28d9"
  light-rose-error: "#e11d48"
  light-amber-warn: "#b45309"
  light-emerald-ok: "#047857"
  light-sky-info: "#0369a1"
  light-bar-idle: "#d4d4d8"
typography:
  headline:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.75rem
    letterSpacing: "-0.025em"
  figure:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 2rem
    letterSpacing: "-0.025em"
    fontFeature: "'tnum'"
  title:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.25rem
  body:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.25rem
  label:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1rem
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Cascadia Mono, Consolas, monospace"
    fontSize: "0.8125em"
    fontWeight: 400
rounded:
  sm: "6px"
  md: "8px"
  lg: "12px"
  full: "9999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "20px"
  xl: "32px"
components:
  panel:
    backgroundColor: "{colors.panel-zinc}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  panel-header:
    typography: "{typography.title}"
    padding: "12px 16px"
  figure-cell:
    typography: "{typography.figure}"
    padding: "14px 16px"
  list-row:
    textColor: "{colors.ink-muted}"
    padding: "12px 16px"
  list-row-hover:
    backgroundColor: "{colors.hover-zinc}"
  segmented-option-active:
    backgroundColor: "{colors.hover-zinc}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    height: "28px"
  text-link:
    textColor: "{colors.violet-text}"
    typography: "{typography.label}"
  button-primary:
    backgroundColor: "{colors.violet-solid}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
  sidebar:
    backgroundColor: "{colors.rail-black}"
    width: "240px"
  hour-bars:
    textColor: "{colors.rose-error}"
    height: "22px"
  bar-readout:
    backgroundColor: "{colors.panel-zinc}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "6px 10px"
---

# Design System: Towell · Panel /admin (Monitoreo)

## Scope

This document records **only the `/admin` monitoring panel** (Sistemas area): the shell in `resources/views/layouts/admin.blade.php` and the eight screens under it (Resumen, Errores, Detalle de error, Rendimiento, En línea, Sesiones, Navegación, Accesos). Every token lives under the `.admin-shell` class in `resources/js/modulos/admin/admin.css`, a file only `/admin` loads.

**Out of scope:** the rest of Towell, meaning the plant screens with the blue navbar, the slate/blue tokens in the `@theme` block of `resources/css/app.css`, and the `x-ui.*` components. That is a separate system that came before this one and is not documented here. Do not apply this file's palette, shell or rules to it, and do not infer its rules from this file. The only things the two worlds share are the `text-caption` token (12 px, from the app `@theme`), the shared `x-tabla` component (restyled inside `/admin` by scoped overrides only), Flux components, and Heroicons through `flux:icon`.

## Overview

**Creative North Star: "The Night Console"**

`/admin` is an observability console in the Sentry/Nightwatch style. It gives equal weight to two questions: is something breaking, and who is connected. The world is near-black zinc, with panels one step lighter and separated by 1 px hairlines rather than shadows. Color carries meaning, not decoration. Rose means error, amber means warning or idle, emerald means healthy or live, and sky means info. Violet is the single interactive accent and marks only selection, links and the primary action. Dark is the default. A light mirror in zinc-50 and white is stored in a cookie and rendered on the server, so the page never flashes the wrong theme.

The panel is compact. Body text is 14 px, labels are 12 px, and the only large numbers are the 24 px figures in the summary strip. Numbers are always tabular. Monospace appears only for machine strings such as routes, ids, file:line, HTTP method and status, and milliseconds. The signature element is the hourly bar strip. Every error row carries one, and so do the 24 h chart and each device row. The bar for the current hour is fully opaque and every earlier bar is a step dimmer, so "now" reads without a label.

The panel rejects the app's blue navbar, a tab strip for navigation, the generic blue-header zebra table, and KPI cards. Figures sit in one divided strip, not in separate boxes.

**Key Characteristics:**
- Near-black zinc canvas, flat panels, 1 px hairlines, no resting shadows.
- Violet only for selection, links, focus and the primary action. Semantic colors only for state.
- A sidebar shell with grouped navigation (Salud / Actividad) and a sticky, blurred page header that holds breadcrumbs, the single h1 and the right-aligned actions.
- Hourly bars as the reused signature, with a single floating readout on hover.
- A live pulsing dot for "en línea", which respects `prefers-reduced-motion`.

## Colors

The palette is a zinc ramp plus four semantic hues and one violet accent, defined twice (dark default, light mirror) under the same `--adm-*` names.

### Primary
- **Console Violet** (violet-text in dark, light-violet-text in light): link text, active navigation, focus outline, input focus border, and the selected-row tint (`--adm-accent-soft`, violet at 16% / 9%).
- **Solid Violet** (violet-solid): the filled primary button and the current page in pagination. In dark mode Flux's accent becomes violet-solid-dark-flux for Flux buttons, the switch and the navlist.

### Semantic
- **Rose Error** (rose-error / light-rose-error): error counts above zero, error bars, the "nuevo" state dot, slow routes over the threshold, and auth-failure entries. Its soft tint is `--adm-err-soft`.
- **Amber Warn** (amber-warn / light-amber-warn): the "visto" state and idle devices.
- **Emerald OK** (emerald-ok / light-emerald-ok): the live dot, "resuelto", and empty states that mean health.
- **Sky Info** (sky-info / light-sky-info): informational accents.

### Neutral
- **Console Black** (console-black): page canvas. Also the fill of inputs and of `<pre>` traces, which sit one step below the panel.
- **Rail Black** (rail-black): the sidebar, just off the canvas.
- **Panel Zinc** (panel-zinc): every panel, the table body and the readout.
- **Hover Zinc** (hover-zinc): row hover, the active segmented option and the track of the p95 meter.
- **Hairline / Hairline Strong** (hairline, hairline-strong): panel borders, dividers and table rules. The strong variant is for the readout border, the max-scale dashed line and scrollbars.
- **Ink / Ink Muted / Ink Faint** (ink, ink-muted, ink-faint): primary text and first table column, secondary text and data, then labels, timestamps and table headers.
- **Bar Idle** (bar-idle): hourly buckets with zero events.

### Named Rules
**The State-Only Color Rule.** Rose, amber, emerald and sky appear only when they encode a state or a threshold breach. A count of zero stays in ink. Rose turns on only when the value is above zero.

**The Violet Means Act Rule.** Violet marks where you can act or what is selected. It is never used as a fill for decoration or as a section color.

**The Mirrored Token Rule.** Every color is written as a `--adm-*` variable defined in both themes. Views never hard-code a zinc or a hex value.

## Typography

**Display Font:** none (no display face).
**Body Font:** system sans (Tailwind `ui-sans-serif, system-ui`), with `cv11` and `ss01` features on the shell.
**Label/Mono Font:** `ui-monospace, SFMono-Regular, Cascadia Mono, Consolas`, set at 0.8125em of its context.

**Character:** a quiet system sans that does not compete with the data. Monospace marks text that a machine wrote.

### Hierarchy
- **Headline** (600, 18 px, tight tracking): the single page h1 in the sticky header.
- **Figure** (600, 24 px, tight tracking, tabular): the four numbers in the summary strip. Detail figures step down to 16–18 px.
- **Title** (600, 14 px): panel headings (h2/h3). A qualifier follows in 400 weight and ink-faint after a middle dot ("Dispositivos · activos en 24 h").
- **Body** (400, 14 px): error messages, row text and form copy.
- **Label** (400–500, 12 px, sentence case): dt labels, timestamps, breadcrumbs, nav group names and axis marks. Table headers are 12 px / 600 / +0.01em in ink-faint.
- **Mono** (0.8125em): routes, ids, fingerprints, file:line, method/status and `<pre>` traces.

### Named Rules
**The Machine Text Rule.** Monospace is only for strings a machine produced. Names, messages and labels stay in sans.

**The Tabular Rule.** Every number (`td`, `[data-cifra]`, bar readout, axis) uses tabular figures so columns don't jitter on poll.

## Layout

The shell is a fixed 240 px sidebar (the contract called for 248 px; the build uses 240 px, so 240 is the system value). Next to it is a scrolling main column whose sticky header (min 64 px tall, bottom hairline, canvas at 90% with a small backdrop blur) holds an optional breadcrumb, the h1, and a right-aligned slot (`#tabla-navbar-acciones`). Page actions teleport into that slot. On mobile, the sidebar collapses behind a toggle inside the same header, and the actions slot wraps to a full-width last row.

Content padding is 20 px, or 32 px from `lg` up, with 24 px vertically. Panels stack with a 20 px gap. Inside a panel, headers and rows use 16 px horizontal and 12 px vertical padding. The detail page uses 20 px horizontal padding. The summary is a 4-up figure strip (2×2 below `lg`), then a full-width chart, then a 2/3 + 1/3 split at `xl`, then a full-width slow-routes list. The error detail page uses a main column plus a 20 rem aside at `xl`. Rows are CSS grids with fixed trailing columns (6 rem bars, 3.5 rem count) so bars and numbers line up down a list.

**The Strip Not Cards Rule.** Summary figures share one panel and are separated by hairline dividers. Each figure is a link to its section.

## Elevation & Depth

The system is flat. Depth comes from tone (canvas, then rail, then panel, then hover), from 1 px hairlines, and from the inset `<pre>` and inputs, which drop back to the canvas color. Panels carry no shadow at rest, and the shared `x-tabla` has its shadow removed inside `/admin`.

### Shadow Vocabulary
- **Float** (`--adm-shadow`; dark `0 1px 2px rgb(0 0 0 / .4), 0 8px 24px -8px rgb(0 0 0 / .5)`, light `0 1px 2px rgb(24 24 27 / .04), 0 4px 12px -4px rgb(24 24 27 / .06)`): only for things that float above the page, which are the bar readout and the rename dialog.
- **Focus halo** (`0 0 0 3px var(--adm-accent-soft)`): inputs and selects on focus, together with a violet border.

### Named Rules
**The Only Floaters Cast Rule.** A shadow means the element is above the page plane (a readout or a dialog). Panels, tables and rows never cast one.

## Shapes

Shapes use gentle, nested radii. Panels and the restyled table are 12 px. Inputs, buttons, the readout, `<pre>` blocks and the segmented control are 8 px. The segmented options and the mono page chips inside them are 6 px. State dots, the live dot and the p95 meter are fully round. Hour bars are thin columns (2.4 units on a 4-unit pitch) with a barely rounded top (rx 0.4), stretched to the container width. Borders are always 1 px. The only dashed line is the max-scale guide on charts.

## Components

### Buttons
- **Shape:** Flux buttons, 8 px radius, mostly `size="sm"`.
- **Primary:** solid violet fill with white text. Used once per form (Guardar).
- **Secondary / Ghost:** Flux default and ghost for toolbar actions (Renombrar) and dialog cancel. **Danger** (Flux `danger`) is only for destructive actions (Cerrar sesión), always behind `notify.confirm`.
- **Segmented toggle:** a hairline-bordered panel group with 2 px padding. The active option fills with hover-zinc and ink, and inactive options sit in ink-faint. The header's 24 h / 7 días toggle and the error-state radio (Flux `segmented`) use it.

### Chips
- **State dot:** an 8 px round dot (rose nuevo, amber visto, emerald resuelto, ink-faint ignorado) with sr-only text, used in lists.
- **State badge:** a Flux `badge` (`sm`, red/amber/green/zinc), used only on the error detail header.
- **Page chip:** mono text, 6 px radius, hairline border, ink-muted.

### Cards / Containers
- **Corner Style:** 12 px.
- **Background:** panel-zinc on console-black.
- **Shadow Strategy:** none (see Elevation).
- **Border:** 1 px hairline. An error-cause panel tints its border to rose at 40%.
- **Internal Padding:** a header bar (16 px / 12 px, bottom hairline, title left, violet text link right), then rows divided by hairlines.

### Inputs / Fields
- **Style:** Flux inputs, plus `[data-adm-control]` and the inputs inside `x-tabla`, filled with the canvas color, with a hairline border, ink text and ink-faint placeholder.
- **Focus:** violet border plus a 3 px violet-soft halo. Elsewhere the global focus-visible is a 2 px violet outline offset by 2 px.

### Navigation
- **Sidebar:** a Flux sidebar on rail-black with a right hairline. The logo (inverted in dark) and the "Monitoreo" wordmark sit at the top. Two groups, Salud and Actividad, each have a 12 px sentence-case group label in ink-faint. The current item takes Flux's accent. At the bottom are Pulse, Volver a Towell, the theme switch (whose label names the action for the current theme, swapped by CSS), and the user block above a hairline.
- **Header:** breadcrumbs (12 px, ink-faint), the h1, and the actions slot.

### Tables (x-tabla inside /admin)
The shared table is restyled only through `.admin-shell` selectors. The panel background, the 12 px radius and the hairline replace the blue header. There is no zebra striping. Rows hover in hover-zinc and selected rows are tinted violet-soft. Headers are 12 px / 600 in ink-faint. The first column is ink and the rest ink-muted. The current page in pagination is solid violet.

### Hourly Bars (signature)
`x-admin.barras` draws one SVG bar per hour bucket. Filled buckets take the component's text color (rose for errors, ink-muted for views and devices) at 78% opacity, and the current hour is at 100%. Empty buckets are a 1-unit bar-idle stub at 60%. Optional extras are a dashed max-scale line with its value at the top right, and a -24 h · -12 h · ahora axis (days for windows over 48 buckets). Each bar carries `role="img"` with a total, and has a `data-lectura` string that the single floating **readout** shows on hover. The readout is a panel-colored 8 px tooltip with a strong hairline and the Float shadow. It fades over 120 ms with `cubic-bezier(0.16, 1, 0.3, 1)`.

### Live Dot (signature)
An 8 px emerald dot whose `::after` pulses outward to 2.8× scale over 2 s with the same ease-out. The pulse runs only under `prefers-reduced-motion: no-preference`. It is used for "En vivo", "En línea ahora" and online devices. Idle devices show a static amber dot, and offline devices show a hollow ink-faint ring.

### Meter
A 6 px round track in hover-zinc with a fill in ink-faint, or in rose once the p95 exceeds the server threshold. The value sits beside it in ms, tabular.

## Do's and Don'ts

### Do:
- **Do** write every color as a `--adm-*` token (`bg-(--adm-panel)`, `text-(--adm-ink-2)`) so both themes follow.
- **Do** build sections as a 12 px panel with a 16 / 12 px header bar and hairline-divided rows.
- **Do** put a `x-admin.barras` strip on any row that represents a stream of events over time.
- **Do** use monospace at 0.8125em for routes, ids, file:line and ms, and tabular figures for every number.
- **Do** teleport page actions into `#tabla-navbar-acciones` instead of adding a toolbar row.
- **Do** gate any looping motion behind `prefers-reduced-motion: no-preference`.

### Don't:
- **Don't** use the app's blue navbar, blue table header or zebra rows inside `/admin`.
- **Don't** split summary numbers into separate cards. Use one divided strip.
- **Don't** color a value rose, amber or emerald unless it encodes a state or a breached threshold.
- **Don't** add shadows to panels, rows or tables. Only floating layers (the readout and dialogs) cast one.
- **Don't** edit `x-tabla` markup for `/admin` looks. Restyle it from `.admin-shell` selectors or `dark:` variants only.
- **Don't** apply this system outside `/admin`.
