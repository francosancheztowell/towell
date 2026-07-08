# Trazabilidad Production Grouped Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render one compact Crudo card per order, with consolidated totals and an internal loom breakdown opened from “Ver telares” at the lower-left.

**Architecture:** Keep the current database lookup rules in `TrazabilidadProduccionService`, but convert its flat card output into one order-level item containing a `telares` array and totals. Render that contract through a dedicated Blade partial and use delegated JavaScript for expansion so AJAX-loaded production content continues to work.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Tailwind utilities, page-scoped CSS, jQuery delegated events, PHPUnit.

---

### Task 1: Define the grouped Crudo contract

**Files:**
- Create: `tests/Unit/Services/TrazabilidadProduccionGroupingTest.php`
- Modify: `app/Services/Trazabilidad/TrazabilidadProduccionService.php`

- [ ] **Step 1: Write the failing grouping test**

Test a new pure method through reflection with a canonical card plus three trace cards. Assert one returned order, four `telares`, summed trace pieces/weight, canonical status, and canonical program totals.

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
'/mnt/c/xampp/php/php.exe' artisan test tests/Unit/Services/TrazabilidadProduccionGroupingTest.php
```

Expected: failure because `agruparCardsCrudo` does not exist.

- [ ] **Step 3: Implement the grouping method**

Add a private `agruparCardsCrudo(array $cards): array` that groups by `grupoKey`, finds the canonical item, creates the loom rows, calculates trace totals, preserves the canonical production value, and exposes consolidated `pesoTotal`, `producidasTotal`, `cantidadTelares`, and `esMultiTelar`.

- [ ] **Step 4: Return grouped orders from `buildCrudo`**

Replace the flat `ordenes` output with grouped order items. Preserve summary counts using the canonical cards before grouping.

- [ ] **Step 5: Run the targeted test and verify GREEN**

Run the same PHPUnit command and expect all assertions to pass.

### Task 2: Render compact expandable cards

**Files:**
- Create: `resources/views/modulos/trazabilidad/_produccion_crudo_card.blade.php`
- Modify: `resources/views/modulos/trazabilidad/_produccion.blade.php`
- Modify: `resources/views/modulos/trazabilidad/index.blade.php`

- [ ] **Step 1: Add a failing Blade contract test**

Extend the unit test to render `_produccion_crudo_card` with grouped data and assert it contains one order heading, `Ver telares`, the lower-left action class, and one row for every loom.

- [ ] **Step 2: Run the test and verify RED**

Expected: failure because the grouped-card partial does not exist.

- [ ] **Step 3: Create the compact card partial**

Render the card heading, status/month metadata, three compact totals, the lower-left expansion button, loom summary, and hidden internal detail table. Use `aria-expanded="false"` and a unique panel id.

Render `Pzas/día`, `Kg/día`, and the percentage progress bar below the three primary totals.

- [ ] **Step 4: Replace flat grouped rendering**

Update `_produccion.blade.php` to iterate once per grouped order and include the new partial. Keep Rollos Teñido unchanged.

- [ ] **Step 5: Replace obsolete group CSS**

Remove `.prod-card-grupo` styling and add styles for compact order cards, four closed cards per wide desktop row, two-column expanded state, internal loom rows, lower-left action placement, and one-column mobile behavior.

- [ ] **Step 6: Add delegated expansion behavior**

On `.prod-crudo-toggle`, toggle the hidden panel, `aria-expanded`, button label, chevron, and `.is-expanded` card class. Keep the existing delegated filter behavior and make it count/filter grouped cards.

- [ ] **Step 7: Run the targeted test and verify GREEN**

Expect the rendered contract assertions to pass.

### Task 3: Validate Laravel and the rendered UI

**Files:**
- Verify: `app/Services/Trazabilidad/TrazabilidadProduccionService.php`
- Verify: `resources/views/modulos/trazabilidad/_produccion.blade.php`
- Verify: `resources/views/modulos/trazabilidad/_produccion_crudo_card.blade.php`
- Verify: `resources/views/modulos/trazabilidad/index.blade.php`

- [ ] **Step 1: Check PHP syntax**

```bash
'/mnt/c/xampp/php/php.exe' -l app/Services/Trazabilidad/TrazabilidadProduccionService.php
```

- [ ] **Step 2: Compile all Blade views**

```bash
'/mnt/c/xampp/php/php.exe' artisan view:clear
'/mnt/c/xampp/php/php.exe' artisan view:cache
```

- [ ] **Step 3: Run targeted tests**

```bash
'/mnt/c/xampp/php/php.exe' artisan test tests/Unit/Services/TrazabilidadProduccionGroupingTest.php
```

- [ ] **Step 4: Run formatting checks**

```bash
'/mnt/c/xampp/php/php.exe' vendor/bin/pint --test app/Services/Trazabilidad/TrazabilidadProduccionService.php tests/Unit/Services/TrazabilidadProduccionGroupingTest.php
```

- [ ] **Step 5: Browser QA**

Open `/trazabilidad`, load Producción, verify one card per order, open/close a multi-loom card, change Todos/Activo/Terminado, and inspect desktop and mobile widths. Confirm Rollos Teñido still opens its machine detail modal.
