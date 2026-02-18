# Documentacion Tecnica - Modulo Cortado de Rollo

## 1) Alcance analizado
- Vista principal activa: `resources/views/layouts/app.blade.php`
- Vista especifica del modulo (actualmente inactiva/comentada): `resources/views/modulos/tejedores/notificar-mont-rollos/index.blade.php`
- Controlador: `app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php`
- Rutas: `routes/modules/tejedores.php`
- Modelos relacionados:
  - `app/Models/Tejedores/TelTelaresOperador.php`
  - `app/Models/Tejedores/TelMarbeteLiberadoModel.php`
  - `app/Models/Tejido/TejInventarioTelares.php`

## 2) Conclusiones rapidas
- Si hay modelo. No es solo vista+controlador.
- El modulo "Cortado de Rollo" usa mezcla de:
  - Eloquent (tablas locales/operativas)
  - Query Builder con conexion externa (`sqlsrv_ti`) para datos de produccion.
- La UI de este modulo esta embebida en el layout global (`app.blade.php`) en un modal.

## 3) Rutas del modulo
Definidas en `routes/modules/tejedores.php`:
- `GET /tejedores/cortadoderollo` -> `index` (`notificar.cortado.rollo`)
- `GET /tejedores/cortadoderollo/telares` -> `telares` (`notificar.cortado.rollo.telares`)
- `GET /tejedores/cortadoderollo/detalle` -> `detalle` (`notificar.cortado.rollo.detalle`)
- `POST /tejedores/cortadoderollo/notificar` -> `notificar` (`notificar.cortado.rollo.notificar`)
- `GET /tejedores/cortadoderollo/orden-produccion` -> `getOrdenProduccion` (`notificar.cortado.rollo.orden.produccion`)
- `GET /tejedores/cortadoderollo/datos-produccion` -> `getDatosProduccion` (`notificar.cortado.rollo.datos.produccion`)
- `POST /tejedores/cortadoderollo/insertar` -> `insertarMarbetes` (`notificar.cortado.rollo.insertar`)

Tambien existen redirects legacy para URLs antiguas.

## 4) Vista (Frontend) - como opera realmente

### 4.1 Donde esta la UI activa
En `resources/views/layouts/app.blade.php`:
- Modal "Atado de Julio"
- Modal "Cortado de Rollo"
- JS inline con toda la logica para:
  - cargar telares del operador
  - buscar orden de produccion activa
  - consultar marbetes en produccion
  - seleccionar y liberar marbete

Nota: `resources/views/modulos/tejedores/notificar-mont-rollos/index.blade.php` esta completamente comentada, por lo que no es la vista operativa actual.

### 4.2 Flujo UI del modal "Cortado de Rollo"
1. `abrirModalCortadoRollos()` abre modal y llama `cargarTelaresCortadoRollos()`.
2. `cargarTelaresCortadoRollos()` hace `GET notificar.cortado.rollo?listado=1`.
3. Al seleccionar telar:
   - consulta orden activa: `GET notificar.cortado.rollo.orden.produccion?no_telar=...`
   - si existe orden, consulta datos de produccion:
     `GET notificar.cortado.rollo.datos.produccion?no_produccion=...&no_telar=...&salon=...`
4. Renderiza tabla de marbetes disponibles (filtrados de los ya liberados).
5. Usuario selecciona una fila (marbete) y presiona Notificar.
6. Envia `POST notificar.cortado.rollo.insertar` con `marbetes: [marbeteSeleccionado]`.
7. Si exito, muestra confirmacion y cierra modal.

## 5) Controlador (Backend) - analisis metodo por metodo

Archivo: `app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php`

### 5.1 `index(Request $request)`
Responsabilidad:
- Obtener telares del operador autenticado.
- Responder JSON para flujos AJAX (listado y detalle por telar/tipo).
- Si no es AJAX, renderiza vista `modulos.tejedores.notificar-mont-rollos.index`.

Consultas:
- `TelTelaresOperador` por `numero_empleado`.
- `TejInventarioTelares` (distintos `no_telar`, `tipo`) filtrado por telares del operador.
- En modo detalle AJAX, trae 1 registro de `TejInventarioTelares` con:
  `id, no_telar, cuenta, calibre, tipo, tipo_atado, no_orden, no_rollo, metros, horaParo`.

### 5.2 `telares(Request $request)`
Responsabilidad:
- Devolver lista de telares/tipos asignados al operador.

Consultas:
- `TelTelaresOperador` -> `NoTelarId`.
- `TejInventarioTelares` con `distinct(no_telar, tipo)` y filtro por telares del operador.

### 5.3 `detalle(Request $request)`
Responsabilidad:
- Devolver detalle operativo de un telar (`no_telar`) del operador.

Consultas:
- `TelTelaresOperador` para validar pertenencia del telar al usuario.
- `TejInventarioTelares` para detalle del telar.

### 5.4 `notificar(Request $request)`
Responsabilidad:
- Marcar hora de paro (`horaParo`) del registro en `tej_inventario_telares`.

Operacion:
- Busca por `id` (`TejInventarioTelares::find($request->id)`).
- Actualiza `horaParo` con hora actual (`Carbon::now()->format('H:i:s')`).

### 5.5 `getOrdenProduccion(Request $request)`
Responsabilidad:
- Buscar orden activa para un telar en `ReqProgramaTejido`.

Consultas:
- Debug conectividad: `DB::connection('sqlsrv_ti')->select('SELECT @@VERSION as version')`.
- Orden activa: `DB::table('ReqProgramaTejido')` con filtros:
  - `NoTelarId = no_telar`
  - `EnProceso = 1`
  - selecciona `NoProduccion, NoTelarId, SalonTejidoId`.

Observacion:
- La consulta de `ReqProgramaTejido` usa `DB::table(...)` sin conexion explicita.
  Eso significa que depende de la conexion por defecto (`config/database.php`, `DB_CONNECTION`).

### 5.6 `getDatosProduccion(Request $request)`
Responsabilidad:
- Consultar marbetes disponibles para liberar desde base externa.

Consulta principal (conexion `sqlsrv_ti`):
- Tabla `ProdTable as P`
- `INNER JOIN InventDim as I` por `InventDimId`
- Filtros:
  - `P.impreso = 'SI'`
  - `P.ProdStatus = 0`
  - `P.DATAAREAID = 'PRO'`
  - `I.InventBatchId = no_produccion`
  - `I.WMSLocationId = no_telar`
  - `I.DATAAREAID = 'PRO'`
- Campos:
  - `P.PurchBarCode, P.ItemId, P.QtySched, P.CUANTAS`
  - `I.InventSizeId, I.InventBatchId, I.WMSLocationId`

Despues:
- Carga `PurchBarCode` ya liberados desde `TelMarbeteLiberado`.
- Excluye en memoria los ya liberados.
- Regresa solo marbetes aun no liberados.

### 5.7 `insertarMarbetes(Request $request)`
Responsabilidad:
- Insertar marbetes seleccionados en `TelMarbeteLiberado`.

Proceso:
- Recibe array `marbetes`.
- Por cada marbete:
  - valida existencia por `PurchBarCode`
  - si no existe: inserta (`create`)
  - si ya existe: lo contabiliza en `yaExistian`.
- Retorna resumen: insertados, ya existentes, errores.

## 6) Modelos involucrados

### 6.1 `TelTelaresOperador`
- Archivo: `app/Models/Tejedores/TelTelaresOperador.php`
- Tabla: `TelTelaresOperador`
- Conexion: `sqlsrv`
- Uso: limita los telares visibles/operables por usuario (`numero_empleado`).

### 6.2 `TejInventarioTelares`
- Archivo: `app/Models/Tejido/TejInventarioTelares.php`
- Tabla: `tej_inventario_telares`
- Conexion: por defecto del modelo (no fija explicitamente aqui).
- Uso en modulo:
  - listar detalle operativo de telar
  - actualizar `horaParo`
  - mostrar no_orden/no_rollo/metros/etc.

### 6.3 `TelMarbeteLiberadoModel`
- Archivo: `app/Models/Tejedores/TelMarbeteLiberadoModel.php`
- Tabla: `TelMarbeteLiberado`
- Conexion: no fija explicitamente (usa default).
- Uso:
  - saber que marbetes ya fueron liberados
  - persistir nuevos marbetes liberados

## 7) Bases de datos / conexiones que consulta

Segun implementacion actual:

1. Conexion `sqlsrv_ti` (explicita):
- `ProdTable`
- `InventDim`
- test `SELECT @@VERSION`
- Filtro `DATAAREAID='PRO'`.

2. Conexion por defecto Laravel (`DB_CONNECTION`) para:
- `ReqProgramaTejido` (en `getOrdenProduccion`, por `DB::table(...)` sin connection).
- Modelos sin conexion explicita (por ejemplo `TelMarbeteLiberadoModel`).

3. Conexion `sqlsrv` en modelo `TelTelaresOperador`.

Implicacion tecnica:
- El modulo depende de mas de una base/conexion. Si cambias `DB_CONNECTION`, puede cambiar de donde sale `ReqProgramaTejido` y `TelMarbeteLiberado`.

## 8) Matriz de consultas (que tabla toca cada endpoint)

- `index`:
  - `TelTelaresOperador` (lectura)
  - `tej_inventario_telares` (lectura)

- `telares`:
  - `TelTelaresOperador` (lectura)
  - `tej_inventario_telares` (lectura)

- `detalle`:
  - `TelTelaresOperador` (lectura)
  - `tej_inventario_telares` (lectura)

- `notificar`:
  - `tej_inventario_telares` (lectura + update `horaParo`)

- `getOrdenProduccion`:
  - `ReqProgramaTejido` (lectura, conexion default)
  - `sqlsrv_ti` (solo check de conexion con `@@VERSION`)

- `getDatosProduccion`:
  - `sqlsrv_ti.ProdTable` + `sqlsrv_ti.InventDim` (lectura)
  - `TelMarbeteLiberado` (lectura para exclusiones)

- `insertarMarbetes`:
  - `TelMarbeteLiberado` (lectura + insert)

## 9) Reglas de negocio observadas
- Solo se muestran telares asignados al operador autenticado.
- Para liberar marbete:
  - debe existir orden activa (`EnProceso=1`) en `ReqProgramaTejido`.
  - se toman marbetes de produccion pendientes (`impreso='SI'`, `ProdStatus=0`).
  - no se vuelven a liberar marbetes ya registrados en `TelMarbeteLiberado`.
- La insercion de marbetes es idempotente a nivel logico (si existe barcode, no inserta de nuevo).

## 10) Riesgos/puntos delicados para modificar
- `ReqProgramaTejido` se consulta sin conexion explicita: un cambio de `DB_CONNECTION` puede romper resultados.
- La UI del modulo esta en `layouts/app.blade.php` (archivo global); tocarla impacta toda la aplicacion.
- El endpoint `notificar` usa `id` sin validar explicitamente ownership contra operador (actualiza por id encontrado).
- Filtrado de duplicados se hace por `PurchBarCode` en codigo; idealmente reforzar con indice unico en BD si no existe.
- La vista especifica del modulo esta comentada, puede confundir mantenimientos futuros.

## 11) Recomendaciones de mantenimiento (tecnicas)
- Mover JS del modulo a archivo dedicado (`public/js/...`) para evitar crecer `app.blade.php`.
- Hacer explicita la conexion para `ReqProgramaTejido` en `getOrdenProduccion` (siempre la base correcta).
- Agregar validaciones de request con FormRequest (params requeridos y tipos).
- En `notificar`, validar que el `id` pertenezca a un telar asignado al operador.
- Considerar transaccion en insercion de marbetes si despues se agregan mas escrituras.

## 12) Resumen digerido (que hace el modulo)
- El operador abre "Cortado de Rollo", elige un telar que tiene asignado.
- El sistema busca la orden activa de ese telar.
- Con esa orden consulta marbetes de produccion en la base externa (`sqlsrv_ti`).
- Quita los marbetes que ya fueron liberados antes.
- Muestra los disponibles, el operador selecciona uno y confirma.
- El sistema guarda ese marbete en `TelMarbeteLiberado` para dejar trazabilidad y evitar doble liberacion.

En paralelo, el mismo controlador tambien soporta "Atado de Julio" (hora de paro en `tej_inventario_telares`), pero para "Cortado de Rollo" el corazon funcional es: orden activa -> consulta de marbetes -> filtro de ya liberados -> insercion de liberacion.
