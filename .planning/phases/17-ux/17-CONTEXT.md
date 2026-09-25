# Fase 17 — Experiencia de usuario

**Track:** UX · **Ola:** 17-01 en Ola 2 (documento), 17-02 en Ola 3 · **IDs:** UX-01..18 · **Ramas:** `claude/17-01-auditoria-ux`, `claude/17-02-ux-global`
**Antes de ejecutar:** escribir el PLAN de la subfase.

## Objetivo
Análisis exhaustivo de UX basado en uso real y corrección de los problemas globales que afectan a todas las pantallas (tablets de piso incluidas).

## 17-01 — Auditoría (documento)
`docs/cerebro-towell/UX/auditoria-ux-2026.md` sobre las **25 pantallas más usadas según `SYSMonVista`** (consulta en `/admin/rendimiento` o SQL). Por pantalla: capturas 1280×800 y 768×1024 (skill `run` + Playwright), tiempos reales (p50/p95), errores asociados, crítica con `.impeccable/critique`, hallazgos con severidad (P0–P3) y dueño (UX-global, DS o sesión 19-xx del módulo). Incluir flujo de login → home → módulo en tablet.

## Hallazgos ya verificados (2026-09-24) → 17-02
- UX-01 Los flash de error no se muestran (ni `produccionProceso.blade.php` ni el layout): "No tienes acceso a este módulo" se pierde.
- UX-02 Solo 9 vistas ponen `<title>`. UX-03 `h1` anidados (navbar + `x-layout.page-title`) en 9 páginas.
- UX-04 `layout-head` desactiva pinch-zoom (`user-scalable=no, maximum-scale=1`). UX-05 `user-select:none` global impide copiar folios.
- UX-06 Acciones solo por clic derecho en 12 archivos (Alineación, Atadores, Programa Tejido*, Codificación, Liberar*, Captura fórmula…) — inalcanzables en iPad (`-webkit-touch-callout:none`). *PT las hace en su track.
- UX-07 33 vistas con texto de 9–11 px.
- UX-08 Sin `lang/`, locale `en` → validaciones en inglés; faltan acentos ("contrasenia", "sesion", "conexion").
- UX-09 `errors/{403,404,500}` usan Tailwind sin cargar CSS; destinos de "volver" inconsistentes.
- ~~UX-10 Contraseñas contradictorias~~ → **fuera de alcance**: el owner decidió no tocar contraseñas (2026-09-25).
- UX-11 419: solo 5 fetch lo manejan; Livewire aparte. UX-12 Sin banner sin conexión (usar `towell:conexion`).
- UX-13 Toasts de 1.3 s a 5 s según módulo. UX-14 botones de ícono sin `aria-label`; "×" sin label en Atadores/Departamentos. UX-15 foco visible.
- UX-16 Mojibake en `ModulosController` ("Ãºnico"). (El de `programa-tejido/index.js` lo corrige PT.)
- Otros para 19-xx: Usuarios muestra Editar/Eliminar sin permiso; alta de usuario descarga toda la página para leer áreas; home sin estado vacío ni búsqueda; loader global no anuncia a lectores de pantalla.

## Criterios de éxito
- Auditoría publicada con dueño por hallazgo; 17-02 cierra UX-01..16 con capturas antes/después; UX-18 checklist por pantalla en la receta de fase 19.

## Skills
`.impeccable/` critique, plugin `frontend-design`, `run`, `code-review`.
