# Reporte OT Diarias — Plan de implementación

> Ejecutar en esta sesión. Spec: `docs/superpowers/specs/2026-09-14-reporte-ot-diarias-design.md`.

**Goal:** Matriz semanal de OT por mecánico en `/mecanicos/reportes/ot-diarias` con Excel, PDF, imagen y Telegram.

**Architecture:** `ReporteOtDiariasService` arma rango, conteo por renglón y fórmulas. `MecReportesController` autoriza y orquesta. Export y PDF copian el patrón de Estado de Máquina.

**Tech Stack:** Laravel 12, Blade, Dompdf, Maatwebsite Excel, html2canvas, Telegram `ReporteMecanico`.

## Archivos

- Create: `app/Services/Mecanicos/ReporteOtDiariasService.php`
- Create: `app/Exports/ReporteOtDiariasExport.php`
- Create: `resources/views/modulos/mecanicos/reportes/ot-diarias.blade.php`
- Create: `resources/views/pdf/mecanicos/ot-diarias.blade.php`
- Create: `tests/Unit/Mecanicos/ReporteOtDiariasServiceTest.php`
- Modify: `app/Http/Controllers/mecanicos/MecReportesController.php`
- Modify: `routes/modules/mecanicos.php`

## Tareas

1. Service puro: `rangoDesde`, `armarReporte` (conteo por renglón + fórmulas). Tests primero.
2. `build()` lee `SYSUsuario` área Mantenimiento y renglones de `MecOrdenTrabajoLine` en el rango.
3. Vista consulta GET `fecha` + inputs; Excel/PDF/imagen POST + Telegram.
4. Permiso: `moduleNameForRoute('mecanicos/reportes/ot-diarias')` o `OT Diarias`.
