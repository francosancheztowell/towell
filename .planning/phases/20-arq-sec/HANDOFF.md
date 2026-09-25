# HANDOFF — Fase 20-01

Pedidos de la sesión `claude/20-01-arq` a archivos que no son suyos.

## 1. `CLAUDE.md` (dueño: BASE / integrador)

- **Qué:** en "Model Organization" cambiar `urdengomado/` por `UrdEngomado/`. En "Key Services & Helpers" agregar que los servicios de Desarrolladores viven en `app/Services/Tejedores/Desarrolladores/` (antes estaban en `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/`), y que `FolioHelper` también tiene `consumirFolioSugerido()` (Trama) y `asegurarSecuencia()`.
- **Por qué:** la carpeta en minúsculas ya no existe (BUG-022, borrada en `160613b`) y la ruta de Desarrolladores cambió en ARQ-01. Con la doc vieja, la próxima sesión buscaría en rutas que ya no existen.

## 2. `docs/cerebro-towell/Auditoria/inventario-bugs.md` (dueño: integrador)

- **Qué:** marcar BUG-022 como **resuelto**: el duplicado se borró en `160613b`, y en 20-01 se agregó la guardia `tests/Unit/Helpers/RutasSinColisionDeMayusculasTest.php`.

## 3. Programa Tejido

Sin pedidos: PT no consume `SSYSFoliosSecuencias` directamente. Las tres líneas `use` de PT se movieron en el commit de ARQ-01, como estaba acordado.

---

# HANDOFF — Fases 20-02 / 20-03

Pedidos de la sesión `claude/20-02-03-errores-authz`.

## 4. `tests/Feature/RutasDestructivasPermisoTest.php` (aviso al integrador; el cambio ya está hecho)

- **Qué:** una línea en `gatesDe()`: `explode(',', $args, 2)` pasó a `explode(',', $args)`, para que el tercer parámetro `auditar` de SEC-05 no se lea como parte del módulo.
- **Por qué está hecho y no pedido:** la sintaxis `module.permission:<acción>,<idrol>,auditar` hacía fallar ese test, y la rama no puede quedar en rojo. El owner aprobó el ajuste (2026-09-25). La regla "todo gate por idrol" no cambió.

## 5. `resources/views/errors/500.blade.php` (dueño: UX-global / MON-A)

- **Qué:** que el código de referencia salga del evento de la excepción que se está mostrando, no de `EstadoRequest::eventoId`. `bootstrap/app.php` ya guarda `excepción => Id de evento` en el `WeakMap` del contenedor `monitoreo.eventos_por_excepcion`. La vista tiene `$exception`: basta buscar `$exception` y sus `getPrevious()` en ese mapa.
- **Por qué:** `eventoId` es el último error registrado en la request. Si antes se registró otro error (p. ej. un catch con `report()`) y el que llega a la página no se registra (tope diario, clase ignorada), la página muestra el código del otro. Es el mismo hallazgo de code-review que se corrigió para JSON en 20-02.

## 6. Owner — idrol de 22 rutas

- **Qué:** correr en Laragon la consulta de `20-03-MAPA-AUTHZ.md` §Pendientes y pasar los idrol.
- **Por qué:** sin base de datos en la nube no se puede probar el idrol. Cuando lleguen, se les agrega `module.permission:<acción>,<idrol>,auditar` y se sacan de `SIN_PERMISO_DE_MODULO` en `tests/Feature/Seguridad/EscrituraFueraDePlaneacionTest.php`.
