# 20-04 — Estructura del backend: controllers delgados y piezas reutilizables

**Origen:** el owner (2026-09-30) pide revisar los controllers demasiado largos, lo reutilizable y el uso de carpetas como Enums, Helpers y hooks (observers/eventos), para estructurar mejor. Se complementa con la auditoría del owner (`22-calidad/AUDITORIA-OWNER-2026-09-29.md`) y con la regla "ORM primero" (PROJECT 2026-09-30). Datos medidos por el integrador en la rama integradora (= `main` @ `b7114948`).

## Diagnóstico

| Medida | Valor |
|---|---|
| Controllers | 137 archivos, **50 558 líneas** (el 44 % de `app/`) |
| Controllers > 1 000 líneas | **11** · 501–1 000: 23 · 301–500: 22 |
| Métodos > 100 líneas en `app/` | **119** (9 de más de 300) |
| Controllers con `DB::` directo | **44** de 137 |
| Controllers con `$request->validate([...])` en línea | **68** (hay solo 25 FormRequests) |
| AuthZ por texto del puesto ("supervisor") | 20 archivos |
| Enums | **1** (`Enums/Crudo/CrudoMachineState`) |
| Status como texto | 'Activo' ×67, 'En Proceso' ×58, 'Finalizado' ×42, 'Programado' ×42, 'Terminado' ×40, 'Autorizado' ×35, 'Cancelado' ×18, 'Creado' ×14 |
| Actions / DTOs / Policies | 5 / 5 (repartidos en `app/Data` **y** `app/DTOs`) / 0 |
| Lógica de negocio dentro de `Controllers/` | `Planeacion/ProgramaTejido/{funciones,helper}/` (DividirTejido, DuplicarTejido, UpdateTejido, BalancearTejido, TejidoHelpers…), `ProgramaUrdEng/Concerns` |
| Traits de dominio | `app/Traits/ProduccionTrait.php` (809 líneas, compartido por Urdido y Engomado) |
| "Hooks" | 2 observers (`ReqProgramaTejidoObserver` 1 073 líneas con lógica de negocio pesada; `AtaMontadoTelasObserver`), 2 listeners (monitoreo), 3 jobs |

**Pares casi iguales** (jscpd, líneas duplicadas) que piden una sola implementación:

| Par | Duplicado | Destino |
|---|---:|---|
| `ReportesUrdidoController` ↔ `ReportesEngomadoController` | 280 | una base parametrizada por proceso (como los exports de CAL-05) |
| `ProcesarDesarrolladorService` ↔ `ProcesarMuestrasDesarrolladorService` | 257 | un servicio con superficie Programa/Muestras (patrón `ProgramaTejidoSurface`) |
| `ProgramarUrdidoController` ↔ `ProgramarEngomadoController` (incluido `actualizarStatus`) | 188 | acciones del tablero en `ProgramBoardActionService` + un controller parametrizado |
| `UrdBpmLineController` ↔ `EngBpmLineController` | 166 | la vista ya es compartida desde 19-01; falta el controller |
| `CortesEficienciaController` ↔ `MarcasController` | 152 | finalizar y folio compartidos (19-02 lo dejó anotado) |
| `ModuloProduccionUrdidoController` ↔ `ModuloProduccionEngomadoController` + `ProduccionTrait` | 138 + trait | un servicio de producción Urd/Eng; el trait desaparece |
| Catálogos Eficiencia ↔ Velocidad (vistas y controllers) | 431 (vistas) | 19-06b |

## Estructura destino (convención para todo código nuevo o tocado)

```
app/
  Http/Controllers/<Mod>/   delgados: FormRequest → Action/Service → respuesta. Meta ≤ 300 líneas por controller,
                            métodos ≤ 50 (PHPMD rechaza > 100 o complejidad ≥ 10 en archivos cambiados)
  Http/Requests/<Mod>/      validación + authorize() (Gate que envuelve userCan(); 20-04 AuthZ)
  Actions/<Mod>/            una mutación = una clase invocable, transaccional (patrón PT 05: ActualizarProgramaTejido…)
  Services/<Mod>/           consultas y cálculos reutilizables del módulo (lectura, reportes, reglas)
  Data/<Mod>/               DTOs readonly de entrada/salida (se unifica app/DTOs → app/Data)
  Enums/<Mod>/              backed enums de status/tipos (mismo string que la columna) + cast en el modelo
  Models/<Mod>/             relaciones, scopes, casts (enums), sin lógica de pantalla
  Integrations/Ax/          repositorios de AX (sqlsrv_ti) con DTOs; nada de sqlsrv_ti en controllers/Livewire
  Support/                  utilidades técnicas transversales (PaginacionCompat, HandlesApiErrors, …)
  Helpers/                  congelado: los archivos de funciones globales se quedan; las clases nuevas van a Support/Services
  Observers/ Listeners/     "hooks": solo delegan a Actions/Services (sin lógica propia de cientos de líneas)
  Livewire/<Mod>/           componentes delgados: #[Locked] en identidades, authorize() en cada acción, lógica en Services/Actions
```

Reglas prácticas:
1. **Un status, un enum.** Cada módulo que se toque crea `app/Enums/<Mod>/<Entidad>Status` con los valores reales de la BD (verificados con un `SELECT DISTINCT` en el runbook o con los valores ya usados en el código) y lo usa en el cast del modelo y en las comparaciones. Sin migración: el enum guarda el mismo string. Enums compartidos (status de programa Urd/Eng, BPM Creado/Terminado/Autorizado) se crean una vez en `app/Enums/Programas/` o `app/Enums/Bpm/` y los demás los reusan.
2. **Nada de `DB::` en controllers.** La consulta va al modelo (scope) o a un Service; AX a `Integrations/Ax`.
3. **Validación en FormRequest**, no `$request->validate([...])` en el controller.
4. **Pares Urdido/Engomado y Programa/Muestras**: una implementación parametrizada por proceso o superficie, no dos copias.
5. **Lógica de negocio fuera de `Controllers/`**: `funciones/` y `helper/` de Programa Tejido se mudan a `Actions/` y `Services/` (PT 05.1).
6. **Tests primero**: antes de mover un método largo, un test de caracterización que fije su comportamiento.

## Cómo se aplica

| Pieza | Quién | Cuándo |
|---|---|---|
| Regla general (esta convención) en cada módulo que se toque | Cada 19-xx de la tercera tanda y siguientes (va en sus prompts) | Ya |
| Gates nuevos del ratchet: "controllers con `DB::`" (44) y "`->validate([` en controllers" (68), sin poder subir | TS-base (dueña del ratchet en esta tanda) | Tercera tanda |
| Pares Urd/Eng (Reportes, Programar, BpmLine, ModuloProduccion + `ProduccionTrait`) y enum de status de programa | Sesión **20-05 Estructura Urd/Eng** | Cuarta tanda |
| `funciones/` y `helper/` de PT a Actions/Services; partir `dividir`/`duplicar`; imports de PT | **PT 05.1** | Cuarta tanda (tras CAL-04: tests + Infection) |
| Gates/Policies sobre `userCan()`, sin AuthZ por puesto | **20-04 AuthZ** | Cuarta tanda (primera) |
| `app/DTOs` → `app/Data`; ide-helper para bajar el baseline de phpstan | **22-09** | Cuarta tanda |
