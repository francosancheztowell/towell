# HANDOFF — fase 18-01 (PERF-infra) → otros dueños

## 1. `towell-ruta` vacío en rutas sin nombre → dueño de `components/layout-head.blade.php` (17-02 UX-global)

- **Archivo:** `resources/views/components/layout-head.blade.php` línea 11.
- **Qué pasa:** el meta imprime `request()->route()?->getName() ?? ''`. En una ruta sin nombre el cliente manda `ruta` vacía, así que `SYSMonVista.Ruta` queda `desconocida` y el error de cliente también (18-01 cae a la vista y luego a `desconocida`). El contrato 11 §3 dice "nombre de ruta; si no tiene nombre, URI con placeholders".
- **Cambio propuesto (1 línea):** `content="{{ request()->route()?->getName() ?? request()->route()?->uri() ?? '' }}"`. `uri()` es la plantilla (`tejido/{id}`), sin IDs.
- **Por qué:** el panel agrupa vistas y errores por ruta; hoy todas las pantallas sin nombre se juntan en `desconocida`.

## 2. (Opcional) `ModuloService::limpiarCacheUsuario()` → integrador (archivo sin dueño en la Ola 2)

- `moduleNameForRoute()` se invalida con los eventos Eloquent de `SYSRoles` (alta/edición/baja desde `ModulosController`). Un `UPDATE` por SQL directo (DBA, script) no dispara eventos: el resultado viejo dura hasta 1 h o hasta el siguiente `optimize:clear` (cada deploy lo corre).
- Si se quiere que "limpiar cache" también lo cubra: agregar `olvidarModulosPorRuta();` al final de `limpiarCacheUsuario()` (1 línea; la función vive en `app/Helpers/permission-helpers.php`).
