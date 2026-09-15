---
phase: 04-ux-grid
tipo: medicion
fecha: 2026-09-15
entorno: ProdTowel @ 192.168.2.28 (datos reales), render via artisan tinker
status: cortes_1_3_aplicados
---

# Fase 04 — Qué cuesta realmente la grilla de Programa Tejido

Toda cifra de aquí se midió contra los datos reales, no se estimó. El método está
al final para que se pueda repetir.

## Las cifras

| Medida | Valor |
|---|---|
| Filas hoy | **85** (SMIT 50 / JACQUARD 28 / KARL MAYER 7 — 38 telares) |
| Columnas | 92 |
| Celdas `<td>` | **7 820** |
| HTML de la página | **3 495 KB** plano / **304 KB** gzip |
| └ tabla (thead+tbody) | 2 761 KB = **79 %** |
| └ JS inline (16 bloques) | 687 KB = 20 % — el mayor, solo, **539 KB** |
| └ los 6 modales + menús + layout | **47 KB = 1,3 %** |
| Servidor | 133 ms query+hydrate + 378 ms render Blade ≈ **510 ms** antes del primer byte |

Dentro del tbody (2 722 KB, **32 KB por fila**, **356 bytes por celda**):

| Qué | Peso | ¿Lo lee el JS? |
|---|---|---|
| Celdas **vacías** (2 155 de 7 820 = 28 %) | 584 KB | — |
| Clases utilitarias repetidas (`px-3 py-2 text-sm text-gray-700` + `whitespace-nowrap`) | **371 KB** | **no, 0 lectores** |
| `data-column` | 188 KB | sí, 124 usos |
| `data-value` | 136 KB | sí, 32 usos |
| `column-N` | 68 KB | sí, es el selector de ocultar/fijar |

Y el dato que decide la fase:

> El usuario que más oculta tiene **59 de 92 columnas ocultas** (`OrdColProgramaTejido`).
> Recibe **64 % de esos 2,7 MB en columnas que nunca ve**, y al cargar se le escriben
> **59 × 86 = 5 074 `style.display='none'`** uno por uno, **después** del primer paint.

## Dónde estaba equivocado el análisis anterior

| Premisa | Medido |
|---|---|
| "250 órdenes → ~23 000 celdas" | 85 filas, **7 820 celdas**. 3× menos. Un navegador no se atraganta con 7 800 celdas. |
| "Virtualizar filas es el techo de fluidez" | **Falso a este N.** No hay nada que virtualizar: sobran bytes por celda, no celdas. |
| "Recalcula `left` con `getBoundingClientRect()` por columna" | **5 llamadas**, una por columna fijada (`columns.blade.php:901`). No es el problema. El problema vecino sí existe: `clearPinnedStyles` hace `void el.offsetHeight` **por elemento** → **86 reflows forzados** al despinnear una columna. |
| "Clic de fila recorre 92 tds" | Recorre **todas las filas**: `clearSelectionStyles` → 85 filas × 92 tds × 2 toggles = **15 640 `classList.toggle` por clic** (`selection.blade.php:17-22`). Peor de lo denunciado. |
| "No incluir calendarios/repaso/redbooth hasta abrirlos" | **1,3 % de la página.** Descartar: el trabajo no se paga. |
| "El HTML pesado tarda en viajar" | `mod_deflate` está activo (`public/.htaccess:21`): **304 KB** por el cable, en LAN. No es red. Es 510 ms de servidor + parse de 3,4 MB descomprimidos + 687 KB de JS. |

Lo que el análisis anterior **no vio**:

- **539 KB de JS en un solo `<script>` inline.** Inline ⇒ el navegador no puede reusar
  su code cache entre cargas: se recompila cada vez. Pesa 11× más que todos los modales juntos.
- **378 ms de render Blade** para 85 filas. La mitad del TTFB es el `@foreach` de 92 columnas.

## Cortes, ordenados por lo que se gana / lo que cuesta

1. **Color de texto de la selección a CSS.** Borrar el loop de tds de
   `updateRowSelectionStyles` y poner `#mainTable tr.row-selected td { color:#fff }`.
   15 640 ops por clic → ~5. Y borrar `td { transition: background-color .3s }`
   (`main.css:281`), que hoy le agenda una transición a cada uno de esos toggles.
   *~10 líneas, se nota el mismo día.*
2. **Columnas ocultas y fijadas antes del primer paint.** El servidor ya sabe el estado
   (`OrdColProgramaTejido`): emitir un `<style>` con `#mainTable .column-N{display:none}`
   en el Blade. Mata las 5 074 escrituras inline, el salto de layout y la carrera
   localStorage→GET de `loadPersistedHiddenColumns()`.
   *~5 líneas de Blade. El GET queda solo para cuando el usuario cambia algo.*
3. **371 KB de clases utilitarias → una regla CSS.** `#mainTable td { padding:…; font-size:… }`.
   Nadie en JS las lee, así que es recorte puro: −13 % del peso de la tabla.
   No tocar `data-column`, `data-value` ni `column-N`: son load-bearing (124 / 32 / selector).
4. **`Promise.all` en duplicar/dividir.** `duplicar-dividir.blade.php:140` y `:169` son dos
   `await` **en serie** antes del `Swal.fire` de `:173`. Paralelizarlos es 2 líneas y
   ahorra un round-trip completo. Re-montar el modal como panel fijo: otro corte, si molesta.
5. **Sacar el bloque de 539 KB a un `.js` versionado.** Solo las constantes (`PT_BASE_PATH`…)
   se quedan inline. Gana code cache del navegador en cada recarga.
6. **Click delegado en tbody**, borrando los tres `assignClickEvents` (0/100/500 ms,
   `main.blade.php:3408-3412`). A N=85 son 255 ops: **no es perf**. Vale por el bug de
   cierre sobre `i` que arrastra `selectedRowIndex` — es trabajo de la Fase 2, no de esta.
7. **Techo real: no emitir las columnas ocultas** en el `@foreach`. −64 % del tbody para el
   usuario pesado y −378 ms de Blade a la mitad. Pero "mostrar columna" pasaría a necesitar
   recarga, y `column-N` es índice posicional. Solo si 1–3 no alcanzan.

**No hacer:** virtualizar filas, paginar server-side, o reescribir la grilla en Livewire
*por rendimiento*. A 85 filas no hay nada que virtualizar. El criterio de éxito #1 de la
Fase 4 en `ROADMAP.md` ("pagina server-side, no carga todo el dataset al DOM") está
apuntando a un problema que no se midió. Si la grilla se migra a Livewire, que sea por las
otras razones de la fase (estado único, accesibilidad), no por esto — y aun así los
cortes 1–3 hay que hacerlos, porque el HTML por celda viaja igual.

## Resultado de los cortes 1–3 (aplicado)

| | Antes | Después |
|---|---|---|
| HTML total | 3 495 KB | **1 436 KB** (−59 %) |
| tbody | 2 722 KB | **663 KB** (−76 %) |
| gzip | 304 KB | **191 KB** (−37 %) |
| Render Blade (mejor de 5) | 378 ms | **257 ms** (−32 %) |
| `classList.toggle` por clic de fila | ~15 640 | **~5** |
| Escrituras `display:none` al cargar (usuario 74) | 5 074 | **0** |
| GET `/programa-tejido/columnas` al cargar | 1 | **0** |

Apareció un cuarto costo que el desglose por atributos no veía: **la sangría del
propio Blade**. El `<td>` repartido en 7 líneas dejaba ~150 bytes de espacios y
saltos por celda — más de 1 MB, más que las clases y los atributos juntos. La celda
se arma ahora en un closure PHP (`$celda`) y se emite en una línea.

El corte 1 terminó siendo **borrado puro**: `main.css` ya forzaba el color con
`!important` en los dos lados (`tbody td:not(.pinned-column)` y
`.selectable-row.bg-blue-700 td`), así que los ~15 600 `classList.toggle` por clic
no pintaban nada. Cero CSS nuevo, cero cambio visual.

Sigue pendiente lo que no se tocó: los cortes 4–7 (Promise.all en el modal, sacar
los 539 KB de JS inline, click delegado, no emitir las columnas ocultas).

## Cómo repetir la medición

```bash
php artisan tinker --execute="
\$reg = \App\Models\Planeacion\ReqProgramaTejido::query()->ordenado()->get();
\$h = view('modulos.programa-tejido.req-programa-tejido', [
  'registros'=>\$reg,
  'columns'=>\App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers::getTableColumns(),
  'basePath'=>'/x','apiPath'=>'/x','linePath'=>'/x','pageTitle'=>'x',
])->render();
echo round(strlen(\$h)/1024).' KB plano / '.round(strlen(gzencode(\$h,6))/1024).' KB gzip'.PHP_EOL;
echo substr_count(\$h,'<td').' celdas'.PHP_EOL;
"
```

Ojo con dos supuestos sin verificar:
- El render se midió con `tinker` (sin usuario en sesión). Los 378 ms no incluyen
  middleware ni el menú de módulos; el TTFB real es **≥** 510 ms.
- `mod_deflate` se leyó de `public/.htaccess`. Producción es **Laragon** en 192.168.2.15;
  confirmar ahí que el módulo está cargado antes de dar los 304 KB por buenos.
