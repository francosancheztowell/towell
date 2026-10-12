# Paros: acceso por usuario a máquinas (SYSUsuariosMaquinas)

Fecha: 2026-10-12 · Estado: **plan, sin código**

## Objetivo
Detalle por usuario: cada uno ve en `/mantenimiento/nuevo-paro` (y en el reporte y en finalizar paro)
solo los departamentos y máquinas que tiene asignados. Ejemplo: "solo Costura" → no ve nada más.
A futuro, es la tabla principal de acceso usuario→máquina (el "TelTelaresOperador general"),
pensada para que otros módulos la reutilicen.

## Hechos medidos (BD ProdTowel, 2026-10-12)
- Los 15 departamentos (Corte Bata, Costura, Crudo, Engomado, Itema, Jacquard, Karl Mayer, Mat Prim,
  Prod Term, Rasurado, Secado, Smith, Tejido, Tintoreria, Urdido) son `URDCatalogoMaquinas.Departamento`
  (132 máquinas; PK `MaquinaId` nvarchar(20), `Id` identity único).
- Hoy nuevo-paro lista `SysDepartamentos` (Atadores, Calidad, Engomado, Urdido, Tejedores,
  Desarrolladores, Supervisores): **Costura y los demás no aparecen**.
- `ParosCatalogoService::maquinas()` solo consulta `URDCatalogoMaquinas` para Urdido y Engomado;
  Tejido sale de `TelTelaresOperador` y Atadores de `AtaMaquinas`.
- `CatParosFallas` solo tiene fallas de Atadores, Calidad, ENGOMADO, Tejido y URDIDO. Todos usan los
  mismos tipos: **Electrico / Mecanico / Tiempo Muerto** (Calidad tiene su propio tipo, Calidad).

## Decisiones tomadas
| Tema | Decisión |
|---|---|
| Almacenamiento | Tabla nueva, una fila por usuario-máquina |
| Pantallas | Nuevo paro, Reporte de paros, Finalizar paro |
| Dónde se asigna | Formulario de usuario (Configuración › Usuarios) |
| Tejido / TelTelaresOperador | Conviven por ahora; la tabla nueva será la principal más adelante |
| Usuario sin asignación | Mismo comportamiento de hoy |
| Fallas de los departamentos nuevos | "Conectar con los paros que tenemos" → ver Fase 3 (falta confirmar) |

## Fase 1 — Tabla y modelo
```sql
CREATE TABLE dbo.SYSUsuariosMaquinas (
    Id         INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_SYSUsuariosMaquinas PRIMARY KEY,
    idusuario  INT           NOT NULL,
    MaquinaId  NVARCHAR(20)  NOT NULL,
    CONSTRAINT UX_SYSUsuariosMaquinas UNIQUE (idusuario, MaquinaId),
    CONSTRAINT FK_SYSUsuariosMaquinas_Usuario FOREIGN KEY (idusuario) REFERENCES dbo.SYSUsuario(idusuario) ON DELETE CASCADE,
    CONSTRAINT FK_SYSUsuariosMaquinas_Maquina FOREIGN KEY (MaquinaId) REFERENCES dbo.URDCatalogoMaquinas(MaquinaId) ON UPDATE CASCADE
);
```
- No lleva columna de departamento: se deriva de `URDCatalogoMaquinas` (una sola fuente de verdad).
- Modelo `App\Models\Sistema\SYSUsuarioMaquina`; relación `Usuario::maquinas()` (belongsToMany a `URDCatalogoMaquina`).
- Migración con `Schema::hasTable` como guarda (patrón del repo); compatible con SQL 2008 R2.
- Pendiente revisar: que el FK a `URDCatalogoMaquinas` no choque con el flujo de renombrar máquinas en `CatalogoMaquinas` (Livewire).

## Fase 2 — Reglas en `ParosCatalogoService`
- `asignadas(Usuario): Collection|null` → `null` si no tiene filas (= comportamiento actual).
- `departamentosDe(Usuario)`: con asignación → `DISTINCT Departamento` de sus máquinas; sin asignación → `departamentos()` de hoy.
- `maquinas(depto, usuario)`: con asignación → solo sus máquinas de ese depto (desde `URDCatalogoMaquinas`); sin asignación → lógica actual (Tejido sigue con `TelTelaresOperador`).
- Se conecta en: `ParosController::nuevoParo`, `validarAlta` (store: 422 si el depto o la máquina no le corresponden),
  `ParosCatalogoController::departamentos|maquinas|ordenTrabajo`, listado del reporte y cierre (`ParosCierreController`).
- Reporte/finalizar: con asignación, `whereIn('MaquinaId', asignadas)` sustituye al filtro por `area` (Tejedores conserva su filtro por TelTelaresOperador).

## Fase 3 — Fallas para los departamentos nuevos (FALTA CONFIRMAR)
Propuesta: `catalogoDepartamentos()` ya hace este mapeo (Jacquard/Itema/Smith/Karl Mayer → "Tejido"). Se extiende:
- Jacquard, Itema, Smith, Karl Mayer, Tejido → fallas **Tejido** (ya existe).
- Urdido → URDIDO, Engomado → ENGOMADO (ya existe).
- Costura, Corte Bata, Crudo, Secado, Tintoreria, Rasurado, Prod Term, Mat Prim → su propio `Departamento` en
  `CatParosFallas` si tiene fallas capturadas; **si no tiene ninguna, usan un catálogo "General"** (Electrico / Mecanico /
  Tiempo Muerto, mismos tipos que el resto) que se captura una vez en el Catálogo de Fallas.
- Calidad sigue apareciendo en todos.
- El combo de Departamento en `CatalogoFallas` (Livewire) debe listar los 15 de `URDCatalogoMaquinas` + General + Calidad + Atadores.

## Fase 4 — Pantalla de asignación (formulario de usuario)
- Sección "Máquinas para paros" agrupada por departamento: casilla de departamento (marca todas sus máquinas) + máquinas sueltas.
- Vacío = sin restricción (se avisa con un texto).
- Se guarda dentro de la misma transacción que el usuario (`UsuarioService::create/update`); `duplicar` también copia las máquinas.
- TS en `resources/js/modulos/usuarios/...` (sin JS inline, por el ratchet).

## Fase 5 — Tests (feature, sqlite)
1. Sin asignación → misma lista que hoy.
2. Asignado Costura (todas) → departamentos = [Costura]; máquinas = las 37.
3. Asignado CVERT1+CVERT2 → solo esas dos.
4. `store` con depto/máquina ajena → 422.
5. Reporte y finalizar filtran por las máquinas asignadas.
6. Departamento sin fallas propias → recibe las de General.

## Fuera de alcance (por ahora)
- Migrar `TelTelaresOperador` a la tabla nueva (siguiente paso para que sea la principal).
- Unificar `SysDepartamentos` con los departamentos de `URDCatalogoMaquinas`.
