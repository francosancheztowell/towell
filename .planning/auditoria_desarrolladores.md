# Auditoría — Pantalla Desarrolladores (`/tejedores/desarrolladores`)

Fecha: 2026-09-19. Alcance: componente Livewire `Desarrolladores\Captura`, sus 8 servicios
en `Funciones/`, el trait `ArmaDatosDesarrollador`, la vista `livewire/desarrolladores/captura.blade.php`
y su gemela de Muestras. Incluye el impacto de los telares Karl Mayer (401/402).

## 0. Resumen

La pantalla **ya es Livewire** (`modo="programa"` / `modo="muestras"` sobre un solo componente).
No hay migración pendiente: hay que **terminar** la que existe. Lo que queda es (a) bugs reales
en la rama de Muestras, (b) un servicio `ProcesarMuestras*` que es un clon al 85% del de programa
y que ya divergió, (c) consultas muertas que se pagan en cada carga, y (d) que **Karl Mayer hoy
no cabe en esta pantalla**, ni por datos ni por UI.

Medido en producción (tinker, hoy):

| Dato | Valor |
|---|---|
| Telares en `TelTelaresOperador` | 201-215, 299-320. **No existen 401/402** |
| Órdenes en `ReqProgramaTejido` para 401/402 | 6 |
| De esas, con `CalibreBarra1` capturado | **0** |
| Renglones `CatCodificados` de 401/402 | 24, todos con `Barra*` en NULL y `Tra` poblado |
| Julios montados en `AtaMontadoTelas` para KM | 4 |
| Muestras de 401/402 | 0 |

---

## 1. Bugs

### B-01 · Muestras: se borra la muestra sin haber guardado nada (pérdida de datos)
`ProcesarMuestrasDesarrolladorService::actualizarCatCodificados()` hace
`resolveCanonical(...)` y **`if (! $registro) return null;`**. Si no existe todavía un renglón
en `CatCodificados` para esa orden, no se crea ninguno: no se escribe codificación, ni julios,
ni eficiencias, ni detalle. Acto seguido `eliminarRegistroMuestra()` borra la muestra y la
pantalla dice *"Datos guardados correctamente"*. La captura se perdió y la muestra ya no existe.
La versión de programa sí hace `new CatCodificados` (`$esNuevo`) y rellena desde la orden.
**Severidad: alta.**

### B-02 · Muestras: al quitar una combinación, sus pasadas quedan pegadas
`aplicarDetalleDesdeRequest()` (trait, compartido) pone a NULL `CalibreCombN/FibraCombN/CodColorCN/NomColorCN`
de los slots que ya no se envían. Pero `ProcesarMuestras::buildPasadasPayload()` solo escribe las
claves que llegan, así que `PasadasComb3` conserva el valor viejo. Resultado: combinación sin
calibre pero con pasadas. La versión de programa sí nulifica `PasadasComb1..5` explícitamente.
**Severidad: media.**

### B-03 · Muestras: `ReqModelosCodificados` deja de actualizarse tras la primera vez
`ProcesarMuestras::actualizarModeloDestinoSiCorresponde()` tiene un `if ($codigoPrevioModelo !== '') return;`
que la versión de programa no tiene. Recapturar una muestra del mismo modelo no corrige el
renglón del catálogo. Divergencia no documentada entre dos copias de la misma función.
**Severidad: media.**

### B-04 · Muestras: sin contexto de auditoría
`ProcesarDesarrollador::store()` llama `AuditoriaHelper::contexto('FINALIZA_DESARROLLADORES'|'MOVER_...')`.
`ProcesarMuestras::store()` no. El middleware `SetSqlContextInfo` sella los cambios de muestras
sin operación identificable. **Severidad: media** (es el rastro de auditoría de un borrado).

### B-05 · Muestras: el selector "Al guardar" miente
`accion` se valida y se envía, pero `ProcesarMuestras` **nunca la usa**: siempre borra el registro.
`resumenGuardado` llega a decir *"Reprogramar la orden X al siguiente turno"* junto a
*"Eliminar la muestra del programa"*. En la práctica el banner de orden en proceso casi nunca
se pinta en muestras (consulta `EnProceso = 1` sobre una tabla que no usa ese estado), así que el
select rara vez es alcanzable — pero cuando lo es, promete algo que no ocurre. **Severidad: baja.**

### B-06 · Muestras: el mensaje de error de validación se traga el detalle
`ProcesarMuestras` devuelve `'message' => 'Error de validacion'` genérico; programa devuelve el
primer error concreto. El componente pinta `$datos['message']` en el toast. **Severidad: baja.**

### B-07 · En programa, sin orden en proceso no hay forma de reprogramar
El `<select wire:model.live="accion">` vive **dentro** del `@if ($this->ordenEnProceso)`. Si el
telar no tiene ninguna orden con `EnProceso = 1`, el select no se pinta y el operador se queda
solo con "finalizar", sin señal de por qué. **Severidad: baja** (UI).

### B-08 · Guard de permisos inefectivo en los controladores
`abort_if(Auth::check() && ! userCan(...))`: sin sesión el guard **no dispara**. Hoy lo tapa el
middleware `auth` del grupo de rutas, pero la condición está escrita al revés de lo que parece.
Lo correcto es `abort_unless(userCan(...))`, como ya hace `Captura::mount()`. **Severidad: baja.**

---

## 2. Código duplicado

### D-01 · `ProcesarMuestrasDesarrolladorService` es un clon de `ProcesarDesarrolladorService`
401 vs 579 líneas; `store()`, `resolverContextoOrigen()`, `actualizarCatCodificados()`,
`actualizarModeloDestinoSiCorresponde()` y `buildPasadasPayload()` están duplicados casi byte a byte.
De ahí salen B-01 a B-06: cada corrección se aplicó en un solo lado. El patrón correcto ya está
resuelto al lado, en `ConsultasMuestrasDesarrolladorService`, que **hereda y solo declara en qué difiere**
(35 líneas). Lo mismo aplica aquí: las únicas diferencias legítimas son
`modeloPrograma()`, el post-proceso (`EnProceso = 1` vs `delete()`) y la ruta de redirect.
**Ahorro estimado: ~330 líneas.**

### D-02 · `MovimientoDesarrolladorService`: la secuencia de `Posicion` se recalcula en dos sitios
El bloque de `+10000` / reordenar por `FechaInicio` aparece dentro de `moverRegistroEnProceso()`
(~líneas 108-175) y otra vez en `recalcularSecuenciaTelar()` (~359-404), con `DB::table` crudo en
ambos. Una sola función privada cubre los dos casos.

### D-03 · El payload de `CatCodificados` está escrito tres veces
El mismo array de ~30 claves con pares redundantes (`JulioRizo`/`NumeroJulioRizo`,
`EfiInicial`/`EficienciaInicio`, `Total`/`TotalPasadasDibujo`, `LogLuchaTotal`/`LongitudLuchaTot`)
aparece en los dos `actualizarCatCodificados()` y parcialmente en `actualizarModeloDestinoSiCorresponde()`.
Esos pares existen porque la tabla tiene columnas hermanas; el mapeo debería vivir una vez,
en un método del modelo `CatCodificados` o en el trait.

### D-04 · Marcado repetido en la vista
`captura.blade.php` (620 líneas) repite el mismo bloque `label + control + @error` doce veces.
Un componente Blade anónimo (`<x-campo name label>`) quita ~150 líneas sin cambiar nada visible.
El `$claseCampo` de la cabecera ya es un parche a ese mismo problema.

---

## 3. Consultas

### Q-01 · La consulta más cara del módulo se ejecuta para nada, en cada carga
`ConsultasDesarrolladorService::obtenerDatosIndex()` devuelve `juliosRizo` y `juliosPie`
(dos pasadas completas por `AtaMontadoTelas` + `unique()` en PHP). **El componente nunca las lee**:
las propiedades computadas `juliosRizo`/`juliosPie` salen de `$this->julios`, que llama a
`obtenerJuliosPorTelar($telarId)`. Quitar esas dos claves de `obtenerDatosIndex()` elimina dos
recorridos de tabla por cada expiración de caché (5 min), sin tocar la pantalla.

### Q-02 · Dos fuentes de verdad para "qué telares existen"
`obtenerTelares()` lee `TelTelaresOperador`; `obtenerTelaresDestino()` lee `DISTINCT SalonTejidoId, NoTelarId`
sobre el programa. Un telar puede ser destino válido y no ser seleccionable como origen — que es
exactamente lo que pasa con 401/402 (ver KM-01).

### Q-03 · `ordenEnProceso` duplica la consulta de `producciones`
Dos `SELECT` sobre la misma tabla y el mismo telar en cada render, una filtrando `EnProceso = 0`
y la otra `EnProceso = 1`. Una sola consulta sin el filtro, particionada en PHP, sirve a las dos
(la tabla ya está filtrada por telar, son decenas de filas).

### Q-04 · `resolveForRead()` hace hasta dos consultas para la misma clave
`WHERE OrdenTejido = ? AND TelarId = ?` y, si falla, `WHERE OrdenTejido = ?`. Se resuelve con una
sola consulta ordenando por `CASE WHEN TelarId = ? THEN 0 ELSE 1 END, Id DESC`.

### Q-05 · `Schema::getColumnListing()` en caliente
`actualizarModeloDestinoSiCorresponde()` (las dos copias) y `actualizarProgramasRelacionados()`
consultan `INFORMATION_SCHEMA` dentro de la transacción de guardado, contra SQL Server remoto.
`CatCodificadosDesarrolladorService` ya memoiza lo suyo por petición; estos tres no. Los modelos
declaran `$fillable`: en la mayoría de los casos basta con eso.

---

## 4. Estructura

- **Los servicios viven bajo `app/Http/Controllers/.../Funciones/`.** No son controladores; no
  tocan HTTP salvo por recibir un `Request`. Su sitio es `app/Services/Tejedores/Desarrolladores/`.
  La prueba de que la ubicación molesta: `Captura::guardarConServicio()` tiene que **fabricar un
  `Request::create()` falso con cabecera `X-Requested-With`** para hablar con ellos y recibir JSON.
- **La validación debería ser un `FormRequest`.** `validarYNormalizarEntrada()` está dentro del trait
  y obliga a ese Request sintético. Un `DesarrolladorCapturaRequest` (ya hay precedente:
  `app/Http/Requests/Tejido/` se creó esta semana) deja el servicio recibiendo un array tipado y
  el componente llamando a `$servicio->guardar($datos)` directo, sin simular HTTP.
- **`TelDesarrolladoresHelper::mapDetalleFila()`** tiene 7 parámetros posicionales de tipo string y
  un único llamador. Es un `foreach` sobre un mapa de columnas disfrazado de helper global.
- **Ruta muerta:** `POST /desarrolladores` y `TelDesarrolladoresController::store()` ya no los usa
  nadie (el componente llama al servicio). Conservados "por si hay que revertir": son 2 puntos de
  entrada extra a la escritura, con solo permiso `acceso`.
- **Sin cobertura donde más se necesita:** hay 9 archivos de test del módulo, ninguno cubre la rama
  de Muestras de `ProcesarMuestras*` — justo donde están B-01 a B-06.

---

## 5. Karl Mayer (401/402)

**Hoy la pantalla no puede capturar un telar KM, ni aunque se quisiera.**

### KM-01 · Los telares 401 y 402 no son seleccionables (bloqueante)
El `<select>` de telar se llena de `TelTelaresOperador`, y esa tabla contiene 201-215 y 299-320.
Sin dar de alta 401/402 ahí (con su operador/salón), no hay entrada posible al formulario.

### KM-02 · El detalle se arma desde columnas que KM no usa
`buildDetallesFromOrdenData()` mapea `CalibreTrama` + `CalibreComb1..5`. Para KM la construcción
son **cuatro barras**: `CuentaBarraN, CalibreBarraN, CodColorBarraN, ColorBarraN, FibraBarraN, PasadasBarraN`
(N = 1..4; **no existe Barra5** en base — ver `OrdenKarlMayerService::BARRAS` y `CodificacionController::CAMPOS_KM`).
Con la lógica actual una orden KM abre el formulario con el detalle vacío, o peor: hoy las 24 filas
de `CatCodificados` de 401/402 traen `Tra` poblado y `Barra*` en NULL, o sea que ya se está
escribiendo construcción estándar sobre órdenes KM.

### KM-03 · Campos obligatorios que en KM no aplican
`Captura::problemas()` exige **Julio Rizo** y **Altura de Rizo**. KM no teje rizo/pie: no hay julio
de rizo ni altura. Confirma el dato: las 24 filas KM tienen `AlturaRizo = "0"` y `JulioRizo` NULL.
El servidor también los exige (`'NumeroJulioRizo' => 'required'`, `'AlturaRizo' => 'required|numeric'`).
Con las reglas actuales una captura KM honesta es imposible de guardar.

### KM-04 · Escritura, notificación y pasadas también son rizo/pie
- `aplicarDetalleDesdeRequest()` y `buildPasadasPayload()` solo conocen `PasadasTrama*` / `PasadasCombN`.
- `actualizarProgramasRelacionados()` propaga 35 columnas `Trama/Comb*` y ninguna `Barra*`.
- El mensaje de Telegram imprime "Julio Rizo" y "Julio Pie".
- El sufijo `.JC5` de `normalizeCodigoDibujo()` ya está resuelto: `telar >= 300` no lo lleva, así
  que 401/402 quedan sin sufijo. Esa parte no hay que tocarla; la etiqueta `.JC5` **sí está
  hardcodeada en la vista** y habría que ocultarla en KM.

### Forma recomendada
Ya existe la pieza para decidir el modo: `TelarSalonResolver::esKarlMayer($salon, $telar)`, usada
en 6 sitios del proyecto. La captura debería resolver una sola vez **la construcción del telar**
y derivar de ahí las columnas, los campos obligatorios y la tabla:

| | Estándar (Jacquard/Smit) | Karl Mayer |
|---|---|---|
| Renglones | Trama + C1..C5 (6 máx.) | Barra 1..4 (4 fijos, ni se agregan ni se borran) |
| Columnas del renglón | Calibre, Hilo, Fibra, Color, Pasadas | Cuenta, Calibre, Fibra, Color, Pasadas |
| Julios | Rizo (obligatorio) + Pie | ninguno |
| Altura de rizo | obligatoria | no aplica |
| Sufijo código | `.JC5` si telar < 300 | sin sufijo |
| Columnas destino | `CalibreTrama`, `CalibreCombN`, `PasadasCombN`… | `CuentaBarraN`, `CalibreBarraN`, `CodColorBarraN`, `ColorBarraN`, `FibraBarraN`, `PasadasBarraN` |

Un solo mapa de columnas por construcción (igual que `CAMPOS_KM` / `CAMPOS_STD` de
`CodificacionController`, que ya existe y conviene **reutilizar, no volver a escribir**) alimenta
lectura, escritura y validación. Nada de un segundo componente `CapturaKm`: sería el tercer clon
del módulo.

---

## 6. Diseño / responsive

La pantalla es utilizable en escritorio y aceptable en tablet, pero falla donde se usa:
de pie, frente al telar.

1. **Selector de eficiencia: 101 botones en una tira con scroll horizontal**, × 2 campos. Llegar al
   valor 63 son varios gestos. Un `<input type="number">` con los valores frecuentes (75/80/85)
   como accesos directos hace el mismo trabajo con un toque.
2. **La tabla de detalle son 7 columnas de selects** dentro de `overflow-x-auto`: por debajo de
   ~900 px el operador captura desplazando lateralmente y pierde de vista de qué renglón se trata.
   En móvil/tablet vertical corresponde una tarjeta por renglón (2 columnas), no una tabla.
   En KM son solo 4 renglones fijos: **4 tarjetas "Barra 1..4" es el diseño natural**, y de paso
   desaparecen "Agregar fila" y el bote de basura.
3. **20 casillas de codificación de 44 px** también van con scroll horizontal (~940 px). Agruparlas
   de 5 en 5 con salto de línea entra en pantalla sin tocar nada del flujo.
4. **La tabla de producciones** mete un `<select>` de telar destino dentro de una celda: en móvil es
   la columna que provoca el scroll. Debería salir de la tabla, al panel del formulario.
5. Lo que sí está bien y conviene conservar: la lista única de `problemas`, la franja de confirmación,
   la barra de progreso global, los targets de 44 px, los `wire:key` de los checkboxes y el candado
   de doble envío.

---

## 7. Orden sugerido

1. **B-01** (pérdida de datos en muestras) — corrección puntual, hoy.
2. **D-01**: `ProcesarMuestras extends ProcesarDesarrollador`, dejando solo `modeloPrograma()`,
   el post-proceso y el redirect. Cierra B-02 a B-06 de un golpe. Test de la rama muestras primero.
3. **Q-01** (dos recorridos de tabla muertos) y **Q-03** — cambios de una línea cada uno.
4. **KM-01**: alta de 401/402 en `TelTelaresOperador`. Sin esto, nada de KM es verificable.
5. **KM-02/03/04**: mapa de columnas por construcción + reglas condicionales de obligatoriedad.
6. **Estructura**: `FormRequest` + mover servicios a `app/Services/`, eliminando el `Request::create()` sintético.
7. **Responsive**: tarjetas por renglón en el detalle (que además es la forma que pide KM) y
   selector de eficiencia.
