# Paros: departamentos de URDCatalogoMaquinas + asignación usuario→máquina

Fecha: 2026-10-16 (reemplaza la versión del 2026-10-12) · Estado: **plan cerrado, sin código**

## Objetivo
1. Nuevo paro / Reporte / Finalizar paro listan departamentos de `URDCatalogoMaquinas`, no de `SysDepartamentos`.
2. Tabla `SYSMaquinasAsignacion`: plantilla usuario→máquina que reemplazará a `TelTelaresOperador` más adelante.
3. Formulario de usuario más simple, con la sección de máquinas dentro.

## Decisiones (grill 2026-10-16)
| # | Tema | Decisión |
|---|---|---|
| 1 | Naturaleza de la tabla | Plantilla (reemplazará a TelTelaresOperador) |
| 1b | Clave | `idusuario` (INT, FK). Los 32 empleados de TelTelaresOperador tienen usuario; `numero_empleado` sin duplicados |
| 2 | Turno | **No va** |
| 3 | Supervisor | `bit NOT NULL DEFAULT 0` por fila |
| 4 | Dónde se asigna | Sección dentro del formulario de usuario |
| 5 | Teléfono, Correo, Foto | Se quitan del formulario (los datos guardados se conservan: `update` no debe pisarlos con null) |
| 6 | Puesto | Select con constante de 14 valores + `UPDATE` de "desrrollador" → "desarrollador" |
| 7 | Área del usuario | Sigue de `SysDepartamentos` (esTejedor, Gate admin, BPM dependen de ella) |
| 8 | Atadores y Calidad | Casos especiales por área, igual que hoy (AtaMaquinas / todo) |
| 9 | Usuario sin asignación | Backfill único desde TelTelaresOperador; sin fallback. Sin filas = lista vacía + aviso |
| 10 | Supervisor en la UI | Una casilla para toda la sección (se guarda igual en todas sus filas) |
| 11 | Fallas de deptos nuevos | Solo las propias de `CatParosFallas` (+ Calidad en todos, como hoy). Sin catálogo "General" |
| 12 | Reporte / Finalizar | Filtro por `MaquinaId ∈ asignadas` (cubre el histórico guardado como "Tejedores"). Atadores/Calidad por área. `alcance=todos` se queda |
| 13 | Permisos en alta | Selector "Copiar permisos de…" (permisos + máquinas), alta arranca desmarcada; se conservan Seleccionar/Deseleccionar todos |
| — | Duplicar usuario | Copia también máquinas y Supervisor |

## Fase 1 — Tabla, modelo, backfill
```sql
CREATE TABLE dbo.SYSMaquinasAsignacion (
    Id         INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSMaquinasAsignacion PRIMARY KEY,
    idusuario  INT          NOT NULL,
    MaquinaId  NVARCHAR(20) NOT NULL,
    Supervisor BIT          NOT NULL CONSTRAINT DF_SYSMaquinasAsignacion_Supervisor DEFAULT 0,
    CONSTRAINT UX_SYSMaquinasAsignacion UNIQUE (idusuario, MaquinaId),
    CONSTRAINT FK_SYSMaquinasAsignacion_Usuario FOREIGN KEY (idusuario) REFERENCES dbo.SYSUsuario(idusuario) ON DELETE CASCADE,
    CONSTRAINT FK_SYSMaquinasAsignacion_Maquina FOREIGN KEY (MaquinaId) REFERENCES dbo.URDCatalogoMaquinas(MaquinaId) ON UPDATE CASCADE
);
```
- Departamento se deriva de `URDCatalogoMaquinas` (no se guarda).
- Backfill: `TelTelaresOperador` JOIN `SYSUsuario` (numero_empleado) JOIN `URDCatalogoMaquinas` (NoTelarId) → `DISTINCT idusuario, MaquinaId, MAX(Supervisor)`.
  El telar Jacquard que no está en el catálogo se omite (quitado a propósito por el owner).
- Modelo `App\Models\Sistema\SYSMaquinaAsignacion`; `Usuario::maquinasAsignadas()`.
- Revisar que el FK no choque con renombrar máquinas en `CatalogoMaquinas` (ON UPDATE CASCADE lo cubre si renombra la PK).

## Fase 2 — ParosCatalogoService
- `departamentos(Usuario)`: área Atadores → [Atadores]; área Calidad → [Calidad]; resto → `DISTINCT Departamento` de sus máquinas asignadas.
- `maquinas(depto, Usuario)`: Atadores/Calidad como hoy; resto → sus asignadas de ese depto.
- `catalogoDepartamentos`: Jacquard/Itema/Smith/Karl Mayer/Tejido → Tejido; demás → su propio nombre.
- Conectar en `ParosController::nuevoParo|validarAlta` (422 si no le corresponde), `ParosCatalogoController`, reporte y `ParosCierreController`.
- Reporte/finalizar: `whereIn('MaquinaId', asignadas)` sustituye `Depto = area` y el filtro TelTelaresOperador de Tejedores.
- Combo de Departamento en Catálogo de Fallas: 15 de `URDCatalogoMaquinas` + Atadores + Calidad.

## Fase 3 — Formulario de usuario
- Visibles: Área, No. empleado, Nombre, Puesto (select), Turno, Contraseña. Fuera: Teléfono, Correo, Foto.
- "Copiar permisos de…" arriba de permisos (reusa la lógica de `UsuarioService::duplicar`); alta sin nada marcado; Seleccionar/Deseleccionar todos se quedan.
- Sección "Máquinas": departamento (casilla = todas) → máquinas; una casilla Supervisor. Vacío = no reporta paros (se avisa).
- Guardado en la misma transacción que el usuario. Código en `resources/js/modulos/usuarios/...` (TS, sin JS inline nuevo).

## Fase 4 — Tests (feature, sqlite)
1. Asignado Costura → departamentos [Costura], máquinas las suyas.
2. Sin asignación → lista vacía.
3. Atadores / Calidad → igual que hoy.
4. `store` con máquina ajena → 422.
5. Reporte/finalizar filtran por MaquinaId (incluye paro histórico con Depto "Tejedores").
6. Update de usuario sin teléfono/correo/foto en el request no los borra.
7. Duplicar copia máquinas y Supervisor.

## Fuera de alcance
- Migrar los otros consumidores de TelTelaresOperador (Mecánicos, BPM, Desarrolladores, Notificar montado).
- Unificar `SysDepartamentos` con los departamentos de máquina.
