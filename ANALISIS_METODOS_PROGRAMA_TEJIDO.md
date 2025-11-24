# Análisis de Métodos - ProgramaTejidoController

## 📊 Resumen
Este documento analiza qué métodos del `ProgramaTejidoController` se utilizan realmente desde `req-programa-tejido.blade.php` y otros formularios relacionados.

---

## ✅ MÉTODOS USADOS (Mantener)

### Desde `req-programa-tejido.blade.php`:

1. **`index()`** ✅
   - Ruta: `GET /planeacion/programa-tejido`
   - Uso: Vista principal de la tabla

2. **`destroy()`** ✅
   - Ruta: `DELETE /planeacion/programa-tejido/{id}`
   - Uso: Eliminar registro (línea 887, 919)

3. **`moveToPosition()`** ✅
   - Ruta: `POST /planeacion/programa-tejido/{id}/prioridad/mover`
   - Uso: Drag and drop - mover a posición específica (línea 1222)

4. **`verificarCambioTelar()`** ✅
   - Ruta: `POST /planeacion/programa-tejido/{id}/verificar-cambio-telar`
   - Uso: Validar cambio de telar antes de mover (línea 1268)

5. **`cambiarTelar()`** ✅
   - Ruta: `POST /planeacion/programa-tejido/{id}/cambiar-telar`
   - Uso: Cambiar registro a otro telar (línea 1375)

6. **`edit()`** ✅
   - Ruta: `GET /planeacion/programa-tejido/{id}/editar`
   - Uso: Redirección a formulario de edición (línea 1600)

### Desde formularios (create/edit):

7. **`store()`** ✅
   - Ruta: `POST /planeacion/programa-tejido`
   - Uso: Crear nuevo registro

8. **`update()`** ✅
   - Ruta: `PUT /planeacion/programa-tejido/{id}`
   - Uso: Actualizar registro existente

9. **`getSalonTejidoOptions()`** ✅
   - Ruta: `GET /programa-tejido/salon-tejido-options`
   - Uso: Cargar opciones de salón (config.js línea 12)

10. **`getTamanoClaveBySalon()`** ✅
    - Ruta: `GET /programa-tejido/tamano-clave-by-salon`
    - Uso: Cargar claves modelo por salón (config.js línea 14)

11. **`getFlogsIdOptions()`** ✅
    - Ruta: `GET /programa-tejido/flogs-id-options`
    - Uso: Cargar opciones de FlogsId (config.js línea 17)

12. **`getFlogsIdFromTwFlogsTable()`** ✅
    - Ruta: `GET /programa-tejido/flogs-id-from-twflogs`
    - Uso: Cargar FlogsId desde tabla TwFlogs (config.js línea 18)

13. **`getDescripcionByIdFlog()`** ✅
    - Ruta: `GET /programa-tejido/descripcion-by-idflog/{idflog}`
    - Uso: Obtener descripción por FlogsId (config.js línea 19)

14. **`getCalendarioIdOptions()`** ✅
    - Ruta: `GET /programa-tejido/calendario-id-options`
    - Uso: Cargar opciones de calendario (config.js línea 20)

15. **`getCalendarioLineas()`** ✅
    - Ruta: `GET /programa-tejido/calendario-lineas/{calendarioId}`
    - Uso: Obtener líneas de calendario (config.js línea 21)

16. **`getAplicacionIdOptions()`** ✅
    - Ruta: `GET /programa-tejido/aplicacion-id-options`
    - Uso: Cargar opciones de aplicación (config.js línea 22)

17. **`getDatosRelacionados()`** ✅
    - Ruta: `POST /programa-tejido/datos-relacionados`
    - Uso: Obtener datos del modelo codificado (config.js línea 23)

18. **`getTelaresBySalon()`** ✅
    - Ruta: `GET /programa-tejido/telares-by-salon`
    - Uso: Obtener telares por salón (config.js línea 13)

19. **`getUltimaFechaFinalTelar()`** ✅
    - Ruta: `GET /programa-tejido/ultima-fecha-final-telar`
    - Uso: Obtener última fecha final del telar (config.js línea 15)

20. **`getHilosOptions()`** ✅
    - Ruta: `GET /programa-tejido/hilos-options`
    - Uso: Cargar opciones de hilos (config.js línea 16)

21. **`getEficienciaStd()`** ✅
    - Ruta: `GET /programa-tejido/eficiencia-std`
    - Uso: Obtener eficiencia estándar (config.js línea 24)

22. **`getVelocidadStd()`** ✅
    - Ruta: `GET /programa-tejido/velocidad-std`
    - Uso: Obtener velocidad estándar (config.js línea 25)

---

## ❌ MÉTODOS NO USADOS (Candidatos para eliminar o mover)

### Métodos públicos no utilizados:

1. **`showJson()`** ❌
   - Líneas: 161-165
   - Ruta: No tiene ruta definida
   - **Acción**: ELIMINAR (no se usa en ninguna parte)

2. **`getTamanoClaveOptions()`** ❌
   - Líneas: 449-456
   - Ruta: No tiene ruta definida
   - **Acción**: ELIMINAR (no se usa, se usa `getTamanoClaveBySalon` en su lugar)

3. **`getUltimoRegistroSalon()`** ❌
   - Líneas: 715-736
   - Ruta: `GET /programa-tejido/ultimo-registro-salon` (línea 788 routes/web.php)
   - **Acción**: VERIFICAR si se usa en otros módulos antes de eliminar

4. **`calcularFechaFin()`** ❌
   - Líneas: 759-809
   - Ruta: `POST /programa-tejido/calcular-fecha-fin` (línea 790 routes/web.php)
   - **Nota**: El cálculo se hace en el frontend (form-manager.js)
   - **Acción**: ELIMINAR (cálculo se hace en JavaScript)

### Métodos de prioridad no usados desde la vista principal:

5. **`moveUp()`** ⚠️
   - Líneas: 870
   - Ruta: `POST /planeacion/programa-tejido/{id}/prioridad/subir` (línea 766)
   - **Nota**: No se usa desde req-programa-tejido.blade.php (se usa drag & drop)
   - **Acción**: VERIFICAR si se usa en otros lugares antes de eliminar

6. **`moveDown()`** ⚠️
   - Líneas: 871
   - Ruta: `POST /planeacion/programa-tejido/{id}/prioridad/bajar` (línea 767)
   - **Nota**: No se usa desde req-programa-tejido.blade.php (se usa drag & drop)
   - **Acción**: VERIFICAR si se usa en otros lugares antes de eliminar

---

## 🔧 MÉTODOS PRIVADOS (Helpers - Mantener)

Estos métodos son helpers internos y deben mantenerse:

- `applyCantidad()` - Usado por `update()`
- `setSafeDate()` - Usado por `update()`
- `applyCalculados()` - Usado por `update()`
- `applyEficienciaVelocidad()` - Usado por `update()`
- `applyColoresYCalibres()` - Usado por `update()`
- `applyFlogYTipoPedido()` - Usado por `update()`
- `extractResumen()` - Usado por `update()`
- `resolveTipoPedidoFromFlog()` - Usado por `store()`
- `resolverAliases()` - Usado por `store()`
- `resolverStdSegunTelar()` - Usado por `cambiarTelar()`
- `marcarCambioHiloAnterior()` - Usado por `store()`
- `aplicarCamposFormulario()` - Usado por `store()`
- `aplicarAliasesEnNuevo()` - Usado por `store()`
- `aplicarFallbackModeloCodificado()` - Usado por `store()`
- `cascadeFechas()` - Usado por `update()`
- `recalcularFechasSecuencia()` - Usado por múltiples métodos
- `moverPrioridad()` - Usado por `moveUp()` y `moveDown()`
- `moverAposicion()` - Usado por `moveToPosition()`
- `sumarHorasCalendario()` - Usado por `calcularFechaFin()` (pero calcularFechaFin no se usa)
- `sumarHorasSinDomingo()` - Usado por `sumarHorasCalendario()`
- `sumarHorasTej3()` - Usado por `sumarHorasCalendario()`

---

## 📋 RECOMENDACIONES

### Eliminar inmediatamente:

1. **`showJson()`** - No tiene ruta y no se usa
2. **`getTamanoClaveOptions()`** - No tiene ruta y no se usa
3. **`calcularFechaFin()`** y sus helpers relacionados:
   - `calcularFechaFin()` (líneas 759-809)
   - `sumarHorasCalendario()` (líneas 811-837)
   - `sumarHorasSinDomingo()` (líneas 839-850)
   - `sumarHorasTej3()` (líneas 852-865)
   - **Nota**: El cálculo se hace completamente en el frontend

### Verificar antes de eliminar:

1. **`getUltimoRegistroSalon()`** ❌ CONFIRMADO NO USADO
   - Tiene ruta pero no se usa en ningún archivo JavaScript o vista
   - **Acción**: ELIMINAR

2. **`moveUp()`** y **`moveDown()`** ❌ CONFIRMADO NO USADO
   - Se usan en el módulo de SIMULACIÓN, pero ese módulo tiene su propio controlador (`SimulacionProgramaTejidoController`)
   - El módulo principal usa drag & drop (`moveToPosition`)
   - **Acción**: ELIMINAR (el módulo de simulación tiene sus propios métodos)

### Mantener pero considerar refactorizar:

- Los métodos privados de helpers están bien organizados
- Considerar mover algunos métodos de catálogo a un controlador separado si el controlador sigue creciendo

---

## 📊 Estadísticas

- **Total de métodos públicos**: ~30
- **Métodos usados**: ~22
- **Métodos no usados**: ~4-6 (dependiendo de verificación)
- **Métodos privados (helpers)**: ~20

---

## 🎯 Plan de Acción

1. ✅ **VERIFICADO**: `getUltimoRegistroSalon()`, `moveUp()`, `moveDown()` NO se usan
2. ✅ **ELIMINADOS** los siguientes métodos:
   - ✅ `showJson()` (líneas 161-165)
   - ✅ `getTamanoClaveOptions()` (líneas 449-456)
   - ✅ `getUltimoRegistroSalon()` (líneas 715-736)
   - ✅ `calcularFechaFin()` (líneas 759-809)
   - ✅ `sumarHorasCalendario()` (líneas 811-837) - helper de calcularFechaFin
   - ✅ `sumarHorasSinDomingo()` (líneas 839-850) - helper de calcularFechaFin
   - ✅ `sumarHorasTej3()` (líneas 852-865) - helper de calcularFechaFin
   - ✅ `moveUp()` (línea 870)
   - ✅ `moveDown()` (línea 871)
   - ✅ `move()` (líneas 1245-1254) - helper privado usado solo por moveUp/moveDown
   - ✅ `moverPrioridad()` (líneas 1152-1224) - helper privado usado solo por move()
3. ✅ **ELIMINADAS** rutas asociadas de `routes/web.php`:
   - ✅ Línea 766: `Route::post('/planeacion/programa-tejido/{id}/prioridad/subir', ...)`
   - ✅ Línea 767: `Route::post('/planeacion/programa-tejido/{id}/prioridad/bajar', ...)`
   - ✅ Línea 788: `Route::get('/programa-tejido/ultimo-registro-salon', ...)`
   - ✅ Línea 790: `Route::post('/programa-tejido/calcular-fecha-fin', ...)`

## 📉 Reducción Realizada

- ✅ **Líneas eliminadas**: ~300 líneas
- ✅ **Métodos públicos eliminados**: 6 métodos
- ✅ **Métodos privados eliminados**: 5 métodos (incluyendo moverPrioridad)
- ✅ **Rutas eliminadas**: 4 rutas
- ✅ **Reducción del controlador**: ~15% más pequeño

## ✅ Estado: COMPLETADO

Todos los métodos no utilizados han sido eliminados exitosamente del controlador y sus rutas asociadas han sido removidas de `routes/web.php`.

