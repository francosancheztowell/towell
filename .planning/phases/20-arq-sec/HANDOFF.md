# HANDOFF — Fase 20-01

Pedidos de la sesión `claude/20-01-arq` a archivos que no son suyos.

## 1. `CLAUDE.md` (dueño: BASE / integrador)

- **Qué:** en "Model Organization" cambiar `urdengomado/` por `UrdEngomado/`. En "Key Services & Helpers" agregar que los servicios de Desarrolladores viven en `app/Services/Tejedores/Desarrolladores/` (antes estaban en `app/Http/Controllers/Tejedores/Desarrolladores/Funciones/`), y que `FolioHelper` también tiene `consumirFolioSugerido()` (Trama) y `asegurarSecuencia()`.
- **Por qué:** la carpeta en minúsculas ya no existe (BUG-022, borrada en `160613b`) y la ruta de Desarrolladores cambió en ARQ-01. Con la doc vieja, la próxima sesión buscaría en rutas que ya no existen.

## 2. `docs/cerebro-towell/Auditoria/inventario-bugs.md` (dueño: integrador)

- **Qué:** marcar BUG-022 como **resuelto**: el duplicado se borró en `160613b`, y en 20-01 se agregó la guardia `tests/Unit/Helpers/RutasSinColisionDeMayusculasTest.php`.

## 3. Programa Tejido

Sin pedidos: PT no consume `SSYSFoliosSecuencias` directamente. Las tres líneas `use` de PT se movieron en el commit de ARQ-01, como estaba acordado.
