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
- 419s were the cached bootstrap/cache/config.php: after `php artisan config:clear`, full `php artisan test` = 1 failed / 2769 passed (failing test not identified; rerun interrupted by user).
- RDD (cec5e9c8 + doc): granted, reviewed (reliability), approved and acknowledged. Advisory: no JS test for year loader; all-years endpoint untested; bg load doesn't repaint (by design: renderTables waits on missing years).
- Next: push/PR is the user's decision. Deploy needs `php artisan route:clear` (new route).

## T4 Pedido by creation date (added after PR)
- [x] T4 Pedido series (TwHistoricosPedidos) groups/filters by YearCreado/MonthCreado instead of ANIO/MES; Plan and Real keep ANIO/MES (user decision). New columns exist only in Pedidos (checked INFORMATION_SCHEMA). Payload/cache v8. — inline
  - Evidence: VentasComparaPorAnioTest 8 pass (new test: pedido placed by creation date); pint, phpstan ok.
  - Read-only prod check: combinado('2026') 11803 combos, O_QTY 8,599,257.01 = SUM(QTY) WHERE YearCreado=2026; anios() 0.36s.
- [x] T5 Agent filter in Compara: NOMBREAGENTE (present in all three tables) added as dimension + multi-select filter "Agente"; payload/cache v9. — inline
  - Evidence: 8 Compara tests pass; pint, typecheck, build, ratchet ok. Prod read-only: 2026 = 11804 rows (was 11803), 267 KB gz, 3.6s cold.
- [x] T6 Review follow-ups: compara endpoint only accepts years from comparaAnios() (bounded cache; no-year/all-years variant removed → 422). Blank-year concern checked: 0 rows without ANIO in Pronostico (23,821) and Ventas (141,101); YearCreado has 0 nulls. — inline
  - Evidence: 9 Compara tests pass (new: unknown year and missing year → 422, not cached); pint, phpstan ok.
