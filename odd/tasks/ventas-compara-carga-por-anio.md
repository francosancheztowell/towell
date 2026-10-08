# Ventas Compara: load by year

## Objective
Compara (Ventas › PV vs OC) opens slowly: it downloads every year at once (~48k combos, ~5 MB JSON
decompressed) before painting anything. Paint the latest year first and load the rest in the background.

## Scope
- Branch: `fix/ventas-compara-agrupar-cliente` (same fix as the CUSTACCOUNT grouping, commit 986fcc32).
- Backend: `PvVsOcReportRepository`, `PvVsOcPayloadBuilder`, `VentasDatosController`, `routes/modules/ventas.php`.
- Frontend: `resources/js/ventas/dashboard.js`, `resources/views/livewire/ventas/dashboard-pv-vs-oc.blade.php`.
- Out of scope: Ventas históricas tab; migrating dashboard.js to TS.

## Constraints
- SQL Server 2008 R2 (no OFFSET/FETCH, STRING_AGG, etc.). Heaps without indexes.
- Raw SQL only parameterized, with a comment explaining why.
- Read-only against the DB; never write.
- TDD: off (no project setting); ordinary checks.

## Tasks
- [x] T1 Group Compara by client name without CUSTACCOUNT — inline — 986fcc32 (RDD approved, acknowledged)
- [x] T2 Per-year Compara payload + years endpoint, cached per year — delegated writer — cec5e9c8
- [x] T3 dashboard.js: paint latest year first, fetch other years in background, wait for a year if filtered before it arrives — same writer — cec5e9c8

## Acceptance criteria
- First paint only needs the latest year's payload.
- Selecting older years / "Análisis Histórico" shows full data once loaded (or waits for it).
- Totals for a year match the previous all-years payload filtered to that year.

## Checks
`npm run test:js`, `npm run build`, `npm run typecheck`, `php artisan test`, `vendor/bin/pint --test <changed php>`,
`vendor/bin/phpstan analyse --memory-limit=2G`, `npm run ratchet`.

Known failing on base: `tests/Js/tejido-cortes-eficiencia.test.mjs` (RPM 750 vs 800, commit 13960b14).

## Progress
- T2/T3 done in cec5e9c8 (payload v7, cache key `ventas:compara:v7:{anio}`, `GET /ventas/datos/compara/anios`).
- Timings (tinker, read-only, prod DB): anios() 0.23s; build('2026') 2.6s / 258 KB gz; build() all years 9.3s / 929 KB gz.
- Checks: test:js only known RPM failure; typecheck, build, ratchet, phpstan ok; pint ok on changed php;
  new tests/Feature/VentasComparaPorAnioTest.php 7 pass. Full `php artisan test`: 675 failed / 2095 passed, but
  failures are environmental (419 CSRF, reproduces on base with UsuarioDuplicarTest; stale bootstrap/cache/config.php).
- Ran `php artisan route:clear` (stale gitignored bootstrap/cache/routes-v7.php hid the new route).
