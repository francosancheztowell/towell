# HANDOFF — PT 01.1 → 02 (rama `claude/pt-01.1-02`)

Cambios que la sesión PT **necesita en archivos que no son suyos**. Se separan en dos grupos: los que ya se hicieron (mínimos, para no dejar la suite en rojo) y los que se piden al dueño.

## A. Ya hechos fuera de la propiedad PT (el integrador debe aceptarlos o rehacerlos)

| Archivo (dueño) | Cambio | Por qué |
|---|---|---|
| `tests/Feature/PlaneacionMutationAuthorizationTest.php` (fuera de glob PT) | 1 fila: `'liberar muestras'` pasa de `module.permission:crear,2` a `crear,5`, y la línea del docblock | Decisión del owner (liberar Muestras exige crear del módulo 5). Sin el cambio, la suite queda en rojo |
| `tests/Unit/BalancearTejidoTest.php`, `tests/Feature/{MoverOrdenesFechaFinalizaTest,ProgramaTejidoUpdateTest,ProgramaTejidoEliminarSellaFechaFinalizaTest}.php` | Una línea al final de `setUp()`: `createTablaDesdeModelo(ReqProgramaTejidoLine::class)` y, en Balancear, también `CatCodificados` | Sus fixtures no creaban tablas que en live existen y pasaban **gracias** a los catches silenciosos que PT-02 contiene (hallazgos 4 y 5). No se tocó ninguna aserción |

## B. Pedidos al dueño

| # | Archivo (dueño) | Cambio pedido | Por qué |
|---|---|---|---|
| 1 | `resources/views/components/navbar/sections/programa-tejido.blade.php` (UX-global / DS) | Envolver el botón "Descargar programa" en `@if(\App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface::actual()->soporta('descarga'))`. Después, PT quita el ocultamiento por JS de `resources/js/programa-tejido/index.js` (bloque "Descarga es exclusiva de Programa") | Descarga es B en Muestras. Hoy se oculta por JS con el selector `button[title="Descargar programa"]`, que es frágil ante un cambio de texto y hace parpadear el botón |
| 2 | `resources/views/components/navbar/navbar.blade.php` (UX-global) | `$programaTejidoModulePermission` pasa a ser `'Muestras'` cuando `$isMuestras` (hoy siempre vale `'Programa Tejido'`) | En Muestras, los botones del navbar (arrastrar, descargar, vincular, recalcular…) se muestran u ocultan con permisos de Programa, mientras el servidor exige los de Muestras (idrol 5) |
| 3 | `resources/views/modulos/programa-tejido/req-programa-tejido.blade.php` (**PT**, fase PT-04 de UI) | `$moduloPT = 'Programa Tejido'` fijo en el menú contextual debe pasar a `$superficie->moduloPermiso()` | Mismo desfase del punto 2 en el menú contextual. No se hizo en PT-02 por la regla "no tocar UI salvo ocultar acciones B" |
| 4 | Owner / DBA | Correr `database/sql/pt_muestras_{marbetes,produccion,longitudes}.sql` en staging y luego en live, con los pasos de la "NOTA PARA EL DBA" de cada script | Alternativas A de la decisión 01.3. Después, PT actualiza `columnas_ausentes` y `longitudes` en `config/planeacion.php` |
| 5 | Owner | Decidir si el observer debe leer el maestro real de pesos (ver `02-SUMMARY.md` §4, D-1) | Posible cambio de números en planta |
