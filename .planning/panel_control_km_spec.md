# Spec — Reporte "Panel de Control" (Telares KM) en Urdido

Origen: `C:\Users\fsanchez\Downloads\daniel (1).xlsx` (hojas `Dashboard` + `Datos`).
Los telares 401 y 402 pertenecen al salón `Karl Mayer` (KM) -> por eso el reporte vive en Urdido.

## Fuente de datos (SQL Server, conexión default `sqlsrv`)

Tabla `TejEficienciaLine` (modelo `App\Models\Tejido\TejEficienciaLine`). Ya trae `Date`
propio, así que NO hace falta unir con `TejEficiencia` salvo que se necesite otro campo.

Columnas relevantes:
`Folio, Date, Turno, NoTelarId, SalonTejidoId, RpmStd, EficienciaSTD,
 RpmR1, EficienciaR1, RpmR2, EficienciaR2, RpmR3, EficienciaR3,
 ObsR1, ObsR2, ObsR3, StatusOB1, StatusOB2, StatusOB3`

Filtro base: `NoTelarId IN ('401','402')` (equivalente a `SalonTejidoId = 'Karl Mayer'`).
Los valores numéricos vienen como VARCHAR -> castear en PHP con `(float)`.
Rango disponible hoy: 2026-01-15 .. 2026-08-18, ~952 filas.

## Agregación semanal (equivale a la hoja `Datos` del Excel)

Una fila por (`NoTelarId`, `Anio`, `Semana` ISO). Semana ISO calculada de `Date`
(`Carbon::parse($date)->isoWeek()` / `isoWeekYear()`), zona `America/Mexico_City`.

| Campo | Regla |
|---|---|
| `NoTelarId` | telar |
| `Anio` / `Semana` | ISO year / ISO week de `Date` |
| `DesdeFecha` / `HastaFecha` | MIN / MAX `Date` de la semana con dato |
| `Dias` | conteo de fechas distintas con dato en la semana |
| `EficienciaProm` | promedio de EficienciaR1/R2/R3 **no nulas y > 0**, primero por fila, luego promedio de filas |
| `RpmProm` | idem con RpmR1/R2/R3 |
| `EficienciaStd` | promedio de `EficienciaSTD` **excluyendo ceros/nulos** |
| `RpmStd` | promedio de `RpmStd` **excluyendo ceros/nulos** |
| `DifVsStd` | `EficienciaProm - EficienciaStd` (null si falta alguno) |
| `Comentarios` | ObsR1/ObsR2/ObsR3 no vacías, `trim`, unidas con ` \| ` |
| `NumEventos` | cantidad de observaciones no vacías (1 por cada ObsRn con texto) |

Si una semana no tiene ningún dato de eficiencia, `EficienciaProm` = null (NO 0).

## Filtro de usuario

- `telar`: `401` | `402` | `ambos` (default `ambos`).
- `anio`: default año actual.
- Opcional `desde` / `hasta` (fechas). Si no vienen, todo el año.
- Umbrales por query string, defaults: `umbral_verde = 0.90`, `umbral_amarillo = 0.75`.

## Contrato de salida del servicio

`App\Services\Urdido\PanelControlKmService::build(array $filtros): array`

```php
[
  'telar'            => '401'|'402'|'ambos',
  'anio'             => 2026,
  'desde'            => '2026-01-01',   // string Y-m-d o null
  'hasta'            => '2026-12-31',
  'umbral_verde'     => 0.90,
  'umbral_amarillo'  => 0.75,

  'kpis' => [
    'eficiencia_prom' => 62.4,   // float|null, promedio simple de las semanas con dato
    'estandar_prom'   => 71.0,   // float|null, excluye ceros
    'brecha'          => -8.6,   // eficiencia_prom - estandar_prom, float|null
    'rpm_prom'        => 412.3,  // float|null
    'semanas'         => 18,     // int, semanas con dato de eficiencia
    'eventos'         => 134,    // int, suma de NumEventos
  ],

  // DETALLE SEMANAL — una fila por semana ISO presente (ordenado asc)
  'semanas_detalle' => [
    [
      'semana'   => 3,
      'efic'     => 87.0,      // float|null
      'est'      => 56.0,      // float|null
      'rpm'      => 540.0,     // float|null
      'rpm_est'  => 530.0,     // float|null
      'dif'      => 31.0,      // float|null  (efic - est)
      'dias'     => 1,         // int
      'eventos'  => 0,         // int
      'desde'    => '2026-01-12',  // string Y-m-d o null
      'hasta'    => '2026-01-12',
      'estado'   => 'En meta', // 'En meta' | 'Atención' | 'Crítico' | 'Sin dato'
      'comentarios' => ['Repaso barra 1', 'INCAS'], // array<string>
    ],
  ],

  // ANÁLISIS DE OBSERVACIONES — categorías fijas
  'categorias' => [
    ['categoria' => 'Repaso', 'menciones' => 12, 'porcentaje' => 0.18],
  ],

  // HALLAZGOS AUTOMÁTICOS, texto listo para mostrar
  'hallazgos' => ['Telar analizado: ...', 'Mejor semana: S12 con 87.0% ...'],
]
```

### Reglas de `estado`

```
efic === null || est === null            -> 'Sin dato'
efic >= est * umbral_verde               -> 'En meta'
efic >= est * umbral_amarillo            -> 'Atención'
si no                                    -> 'Crítico'
```

### Categorías fijas (orden exacto)

`Repaso, Calidad, Enhebrado, Montado, Plomo, Aguja, Platina, Falla, Rotura, Hilos sueltos`

Conteo = número de ocurrencias de la categoría (case-insensitive) dentro de TODAS las cadenas
`Comentarios` del rango filtrado. Replica la fórmula
`(LEN(txt) - LEN(SUBSTITUTE(UPPER(txt), UPPER(cat), ""))) / LEN(cat)` del Excel, es decir
`substr_count(mb_strtoupper($txt), mb_strtoupper($cat))`.
`porcentaje` = menciones / suma de menciones de todas las categorías (0 si la suma es 0).

### Hallazgos (strings, replican B61:B67 del Dashboard)

1. `"Telar analizado: {telar}  |  Semanas con dato de eficiencia: {n}  |  Periodo: {dd/mm/yyyy} a {dd/mm/yyyy}"`
2. `"Mejor semana: S{n} con {x.x}% de eficiencia."`
3. `"Peor semana: S{n} con {x.x}% de eficiencia."`
4. `"Semana con más eventos: S{n} con {k} registros; total del periodo: {total}."`
5. `"Causa más mencionada: {cat} ({k} menciones, {p}% del total)."`
6. `"Semanas en meta: {a}   |   Atención: {b}   |   Críticas: {c}   |   Sin dato: {d}"`
7. `"Brecha promedio vs estándar: {+/-x.x} puntos porcentuales."`

Todos deben tolerar conjuntos vacíos sin lanzar excepción.

## Rutas (en `routes/modules/urdido.php`, dentro del grupo `urdido.`)

```php
Route::get('/reportesurdido/panel-control', [ReportesUrdidoController::class, 'reportePanelControl'])
    ->name('reportes.urdido.panel-control');
Route::get('/reportesurdido/panel-control/excel', [ReportesUrdidoController::class, 'exportarPanelControlExcel'])
    ->name('reportes.urdido.panel-control.excel');
```

Y agregar la entrada al array `$reportes` de `ReportesUrdidoController::index()`:

```php
[
    'nombre' => 'Panel de Control KM',
    'accion' => 'Seleccionar Telar y Año',
    'url' => route('urdido.reportes.urdido.panel-control'),
    'disponible' => true,
],
```

## Vista

`resources/views/modulos/urdido/reportes-panel-control.blade.php`
Seguir el estilo de `reportes-resumen-urdido.blade.php` (layout `layouts.app`, Tailwind v4,
Chart.js ya disponible). Secciones en este orden:

1. Header `PANEL DE CONTROL — TELARES KM {anio}` + subtítulo `Eficiencia · RPM · Eventos por semana`.
2. Filtros: select Telar (Ambos/401/402), select Año, umbrales, botón Exportar Excel.
3. Fila de 6 KPI cards: EFICIENCIA PROM. / ESTÁNDAR PROM. / BRECHA VS EST. / RPM PROM. / SEMANAS /
   EVENTOS REGISTRADOS, cada una con su leyenda (`% real de operación`, `meta de eficiencia`,
   `puntos por debajo|encima`, `revoluciones por minuto`, `semanas con registro`,
   `paros y observaciones`).
4. Tabla DETALLE SEMANAL: Semana | Efic. % | Est. % | RPM | RPM Est. | Dif. (pp) | Días | Eventos |
   Desde | Hasta | Estado (badge de color por estado).
5. Panel ANÁLISIS DE OBSERVACIONES: tabla Categoría | Menciones | % del total + barra horizontal.
6. Gráficas Chart.js: (a) línea Eficiencia real vs Estándar por semana, (b) línea RPM real vs
   RPM estándar, (c) barras Eventos por semana.
7. Bloque HALLAZGOS AUTOMÁTICOS con los strings de `hallazgos`.
8. Nota al pie: "Los promedios de estándar excluyen registros en cero. 'Eventos' cuenta las
   observaciones capturadas por turno."

## Export Excel

`app/Exports/PanelControlKmExport.php` — clase Maatwebsite v3 que replica la hoja `Dashboard`
(KPIs, detalle semanal, análisis de observaciones, hallazgos) más una hoja `Datos` con el
detalle semanal. Recibe en el constructor exactamente el array del contrato de arriba.
