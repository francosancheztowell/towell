# Planeacion (ProgramaTejido + Utileria) - Auditoria Tecnica y Roadmap

Fecha: 2026-03-03  
Repositorio: `C:\xampp\htdocs\Towell`  
Stack: Laravel 12.53, PHP 8.2, SQL Server

## 1) Objetivo

Definir mejoras concretas para:

- Hacer controllers mas legibles.
- Reducir deuda tecnica en backend.
- Reutilizar helpers y reglas compartidas.
- Fortalecer debugging y observabilidad.
- Estabilizar el sistema con pruebas de contrato y regresion.

## 2) Alcance de esta ola

- Modulo `Planeacion` enfocado en `ProgramaTejido` y `Utileria`.
- Revisión de controllers, helpers/funciones, routes y blades clave.
- Definicion de roadmap ejecutable por fases.

## 3) Baseline medible (estado actual)

### 3.1 Controladores/archivos sobredimensionados

- `ProgramaTejidoController.php` ~2047 lineas.
- `LiberarOrdenesController.php` ~1306 lineas.
- `DividirTejido.php` ~1179 lineas.
- `BalancearTejido.php` ~1009 lineas.
- `UpdateTejido.php` ~878 lineas.
- `DuplicarTejido.php` ~868 lineas.

### 3.2 Hotspots funcionales (alto riesgo)

- Cambio de telar, dividir/duplicar, liberar ordenes.
- Recalculo de fechas, posiciones y `EnProceso`.
- Flujos con transacciones y `lockForUpdate`.
- Sincronizaciones cruzadas con modelos relacionados.

### 3.3 Rutas

- 173 rutas bajo `planeacion` (segun `route:list`).
- Duplicidad fuerte entre familias `planeacion/programa-tejido/*` y `planeacion/muestras/*`.
- Mezcla de naming canónico y naming legacy.
- Existen rutas auxiliares fuera del prefijo `planeacion/*`.

### 3.4 Vistas/JS

- JS embebido pesado en blades de utileria y alineacion.
- Uso de handlers inline y funciones globales.
- Render imperativo con `innerHTML` en piezas criticas.

### 3.5 Testing

- No existia estructura base de tests en el repo.
- No habia cobertura automatica de contratos de rutas criticas.

## 4) Deuda de arquitectura backend

### 4.1 Problemas detectados

- Controllers con demasiadas responsabilidades (HTTP + negocio + persistencia + integraciones).
- Duplicacion de logica de calculo (fechas, formula, sanitizacion).
- Manejo de errores no estandarizado entre endpoints.
- Comentarios heterogeneos (mucho ruido, poca intencion de diseño).

### 4.2 Arquitectura objetivo

- `Http/Controllers`: capa delgada.
- `UseCases` por operacion critica:
  - `CambiarTelar`
  - `DividirOrden`
  - `DuplicarOrden`
  - `LiberarOrdenes`
  - `MoverOrdenes`
  - `FinalizarOrdenes`
- `Domain Services` para reglas de fechas/formulas/posicion.
- `Repositories/Query Services` para acceso consistente a datos.
- DTOs/FormRequests para contratos claros.

## 5) Reutilizacion de helpers (backend)

### 5.1 Oportunidades

- Unificar sanitizacion numerica.
- Unificar calculo de horas/formulas de eficiencia.
- Unificar funciones de recálculo de secuencia de fechas.
- Unificar respuestas de error API con `trace_id`.

### 5.2 Estandar implementado en esta iteracion

- Se agrego helper reutilizable para errores API:
  - `app/Support/Http/Concerns/HandlesApiErrors.php`
- Ya aplicado en utileria:
  - `FinalizarOrdenesController`
  - `MoverOrdenesController`

## 6) Legibilidad y comentarios en controllers

### 6.1 Regla de legibilidad

- Controller no debe superar 500 lineas.
- Metodo publico no debe superar 60 lineas sin delegar.
- Comentarios deben explicar decision/intencion, no obviedades.

### 6.2 Estandar de comentarios recomendado

- PHPDoc en metodos publicos con:
  - proposito
  - entradas
  - salidas
  - efectos secundarios
- Evitar banners excesivos (`PASO`, `IMPORTANTE`) si no agregan contexto real.

## 7) Debug y observabilidad

### 7.1 Mejoras aplicadas en esta iteracion

- Respuestas de error con `trace_id` para correlacion de incidentes.
- Logging contextual en errores de utileria.

### 7.2 Siguientes quick wins

- Estandarizar payload de error en todo Planeacion:
  - `success`, `message`, `trace_id`, `errors`.
- Prohibir `catch` vacio y capturas sin contexto.
- Incluir contexto minimo en logs:
  - `route`, `user_id`, `salon`, `telar`, `ids`.

## 8) Rutas: deuda y normalizacion

### 8.1 Hallazgos

- Hay contrato estable pero naming inconsistente.
- Existen endpoints duplicados por contexto.
- Faltan nombres en rutas auxiliares.

### 8.2 Plan de rutas

- Definir familia canónica de nombres.
- Mantener alias legacy temporales.
- Migrar frontend a canónico.
- Retirar legacy por fases con pruebas de contrato.

## 9) Frontend Blade/JS (utileria)

### 9.1 Hallazgos

- Logica de UI y datos mezclada en un solo blade.
- Dificulta testear y mantener.

### 9.2 Mejora aplicada en esta iteracion

- Se corrigio bug de contador inexistente en `finalizar-ordenes.blade.php`.

### 9.3 Proxima fase

- Extraer scripts de utileria a `resources/js/planeacion/...`.
- Reducir eventos inline y globales.
- Mantener Blade como vista y no como controlador de estado.

## 10) Testing: estado y entregables implementados

### 10.1 Entregado en esta iteracion

- Base PHPUnit creada:
  - `tests/TestCase.php`
  - `tests/Feature/`
  - `tests/Unit/.gitkeep`
- Contratos de rutas agregados:
  - `tests/Feature/PlaneacionUtileriaRouteContractTest.php`
  - `tests/Feature/ProgramaTejidoRouteContractTest.php`
  - `tests/Feature/PlaneacionProgramaMuestrasRouteParityTest.php`

### 10.2 Resultado de ejecucion

- `php artisan test` -> 4 tests passed, 77 assertions.

## 11) Roadmap por fases (accionable)

### Fase A - Blindaje (completada parcialmente)

- Base de tests y contratos criticos de rutas.
- Error handling utileria con `trace_id`.
- Bugfix UI puntual en finalizar.

### Fase B - Refactor estructural controlado

- Extraer casos de uso desde controllers mas grandes.
- Mantener compatibilidad funcional total.
- Introducir FormRequests y DTOs en endpoints de alto riesgo.

### Fase C - Consolidacion de rutas

- Naming canónico + alias legacy.
- Eliminacion de duplicidad progresiva.
- Pruebas de paridad entre rutas legacy/canónicas.

### Fase D - Modularizacion frontend

- Extraer JS embebido en blades de utileria/alineacion.
- Reducir acoplamiento con markup.
- Preparar base de pruebas de frontend.

### Fase E - Calidad continua

- Expandir cobertura Feature para mutaciones criticas.
- Agregar Unit en calculos de fecha/formula.
- Regla de PR: sin tests, no merge en flujos criticos.

## 12) Criterios de exito

- Controllers criticos divididos por responsabilidad.
- Ningun controller >500 lineas en nuevos cortes.
- Contratos HTTP y rutas protegidos por pruebas.
- Errores trazables por `trace_id`.
- Menor duplicacion de logica de calculo y respuesta API.
