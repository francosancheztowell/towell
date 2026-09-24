---
phase: 10-base
tipo: medicion
fecha: 2026-09-24
commit: claude/10-base antes de las tareas 10.6/10.7 (borrado de vistas muertas y QR)
entorno: nube (sin SQL Server) para (a) y (b); Laragon del owner para (c)
---

# Fase 10 — Línea base de rendimiento (BASE-04)

Contra esto se comparan las fases 11 (monitoreo), 15 (utils TS), 18 (perf infra) y PT.
(a) y (b) se midieron aquí y se repiten con los comandos de abajo. (c) necesita datos
reales: es un runbook con la tabla vacía para que el owner la llene en Laragon.

## (a) Bundle de Vite (`npm run build`)

Totales: **1 064 KB de JS** y **347 KB de CSS** en 28 archivos (sin sourcemaps).
Top por tamaño, KB sin comprimir / gzip nivel 9:

| Entrada / chunk | KB | gzip KB |
|---|---:|---:|
| (chunk) `vendor` (jQuery, Select2, SweetAlert2, Toastr, axios…) | 278.8 | 87.7 |
| `resources/js/programa-tejido/index.js` | 269.7 | 63.6 |
| `resources/js/charts.js` | 202.6 | 69.4 |
| `resources/css/app.css` | 149.1 | 21.4 |
| `resources/css/crudo/dashboard.css` | 68.3 | 12.3 |
| (chunk) `lmat-modal` | 58.2 | 15.6 |
| `resources/js/programa-urd-eng/reservar-programar.ts` | 51.4 | 14.0 |
| `resources/js/catcodificacion/index.js` | 45.2 | 12.1 |
| `resources/js/tejido/inventario-telas.ts` | 43.9 | 10.1 |
| `resources/js/urd-eng/program-board.ts` | 40.0 | 14.0 |
| `resources/js/trazabilidad/index.ts` | 25.2 | 7.7 |
| `resources/css/trazabilidad/index.css` | 24.4 | 4.8 |
| (chunk) `_vendor.css` | 22.0 | 4.7 |
| `resources/js/crudo/dashboard.ts` | 21.7 | 7.3 |
| `resources/css/urd-eng/program-board.css` | 10.3 | 2.6 |

`fa-solid-900.woff2` (112 KB) no se cuenta arriba.

```bash
npm run build
node -e '
const fs=require("fs"),z=require("zlib");const m=JSON.parse(fs.readFileSync("public/build/manifest.json"));
Object.entries(m).filter(([k,v])=>/\.(js|css)$/.test(v.file)).map(([k,v])=>{const b=fs.readFileSync("public/build/"+v.file);
return [v.isEntry?k:"(chunk) "+(v.name||k),b.length,z.gzipSync(b,{level:9}).length]}).sort((a,b)=>b[1]-a[1])
.forEach(r=>console.log("|",r[0],"|",(r[1]/1024).toFixed(1),"|",(r[2]/1024).toFixed(1),"|"))'
```

## (b) JS inline en Blade

**2 134 KB** de JS dentro de `<script>` sin `src`, repartidos en **135 vistas**.
Inline = el navegador lo recompila en cada carga (sin code cache) y no se puede tipar
ni probar. Top 15 por archivo Blade (un parcial cuenta aparte de la vista que lo incluye):

| Vista | bloques | KB |
|---|---:|---:|
| `modulos/engomado/captura-formula/index.blade.php` | 3 | 139.3 |
| `modulos/engomado/modulo-produccion-engomado.blade.php` | 1 | 135.1 |
| `modulos/urdido/produccion/_scripts.blade.php` | 1 | 122.5 |
| `catalagos/catalogoCodificacion.blade.php` | 1 | 86.6 |
| `modulos/programa-tejido/liberar-ordenes/index.blade.php` | 1 | 77.2 |
| `modulos/programa-tejido/balancear.blade.php` | 1 | 73.1 |
| `modulos/atadores/calificar-atadores/index.blade.php` | 1 | 69.9 |
| `modulos/programa_urd_eng/programacion-requerimientos.blade.php` | 1 | 63.6 |
| `modulos/urdido/programar-urdido.blade.php` ¹ | 1 | 52.6 |
| `modulos/cortes-eficiencia/cortes-eficiencia.blade.php` | 1 | 49.7 |
| `modulos/engomado/programar-engomado.blade.php` ¹ | 1 | 38.5 |
| `modulos/programa_urd_eng/karl-mayer/crear-karl-mayer.blade.php` | 1 | 38.2 |
| `modulos/tel-telares-operador/index.blade.php` | 3 | 36.7 |
| `modulos/tejido/reportes/saldos-2026.blade.php` | 1 | 35.7 |
| `modulos/atadores/programaAtadores/index.blade.php` | 1 | 32.4 |

¹ Vistas muertas: se borran en 10.6 (ver SUMMARY). Sin ellas el total baja a ~2 043 KB.

```bash
node -e '
const fs=require("fs"),p=require("path");function*w(d){for(const e of fs.readdirSync(d,{withFileTypes:true})){const f=p.join(d,e.name);
if(e.isDirectory())yield*w(f);else if(f.endsWith(".blade.php"))yield f}}
const re=/<script\b(?![^>]*\bsrc\s*=)[^>]*>([\s\S]*?)<\/script>/gi;const rows=[];let t=0;
for(const f of w("resources/views")){const s=fs.readFileSync(f,"utf8");let n=0,b=0,m;while((m=re.exec(s))){n++;b+=Buffer.byteLength(m[1])}if(b){rows.push([f,n,b]);t+=b}}
rows.sort((a,b)=>b[2]-a[2]);console.log("TOTAL KB",(t/1024).toFixed(1),"vistas",rows.length);
rows.slice(0,15).forEach(r=>console.log("|",r[0].replace("resources/views/",""),"|",r[1],"|",(r[2]/1024).toFixed(1),"|"))'
```

Los conteos de patrones (fetch, Swal, onclick, `<script>` inline…) viven en el ratchet:
`npm run ratchet` / `scripts/ratchet-baseline.json`.

## (c) Runbook para Laragon: TTFB y queries por pantalla

Se mide contra ProdTowel (o su copia) con Debugbar prendido. `php artisan db:profile`
(`app/Console/Commands/DbProfileCommand.php`) agrega lo que Debugbar ya volcó en
`storage/debugbar`. Método de fondo: `.planning/phases/04-ux-grid/04-PERF-MEDIDO.md`.

### Preparación (una vez)

1. En `.env`: `APP_DEBUG=true`, `DEBUGBAR_ENABLED=true`. `php artisan config:clear`.
2. Vaciar volcados previos: borrar `storage/debugbar/*.json`.
3. Navegador con caché deshabilitada (DevTools → Network → *Disable cache*), sesión
   iniciada con un usuario que tenga acceso a todas las pantallas.

### Por cada pantalla

1. Abrir la URL **3 veces** (descartar la primera: calienta OPcache y caché de módulos).
2. DevTools → Network → documento principal → *Timing* → **Waiting for server response**
   (= TTFB). Anotar la mediana de las cargas 2 y 3.
3. `php artisan db:profile --uri=<parte de la URI> --minutes=5` → anotar **queries por
   petición** y **ms totales de BD** que imprime.
4. Anotar el **tamaño del documento** (columna *Size*, transferido / recurso).

Al terminar: `APP_DEBUG` y `DEBUGBAR_ENABLED` de vuelta a su valor, `config:clear`.

| # | Pantalla | URL | TTFB (ms) | Queries | ms BD | HTML KB (transf./total) |
|---|---|---|---:|---:|---:|---|
| 1 | Programa Tejido | `/planeacion/programa-tejido` | | | | |
| 2 | Liberar órdenes | `/planeacion/programa-tejido/liberar-ordenes` | | | | |
| 3 | Balancear | `/planeacion/programa-tejido/balancear` | | | | |
| 4 | Codificación de modelos | `/planeacion/catalogos/codificacion-modelos` | | | | |
| 5 | Captura de fórmula (Engomado) | `/engomado/capturadeformula` | | | | |
| 6 | Producción Engomado | `/engomado/modulo-produccion-engomado` | | | | |
| 7 | Producción Urdido | `/urdido/modulo-produccion-urdido` | | | | |
| 8 | Calificar atadores | `/atadores/calificar` | | | | |
| 9 | Programa atadores | `/atadores/programaatadores` | | | | |
| 10 | Cortes de eficiencia | `/modulo-cortes-de-eficiencia` | | | | |
| 11 | Inventario de telas | `/tejido/inventario-telas` | | | | |
| 12 | Trazabilidad | `/trazabilidad` | | | | |
| 13 | Crudo (dashboard) | `/Crudo` | | | | |
| 14 | Paros (mantenimiento) | `/mantenimiento/reporte-fallos-paros` | | | | |
| 15 | Home | `/produccionProceso` | | | | |

Notas para quien llene la tabla:
- Pantallas que cargan datos por AJAX después del documento (Crudo, Trazabilidad,
  Inventario de telas) tienen el costo real en esas peticiones: correr además
  `db:profile --uri=<prefijo de la API>` y anotarlo aparte.
- Las URL salen de `php artisan route:list --method=GET`; si alguna cambió, corregirla
  aquí mismo.
- No inventar cifras: si una pantalla no se pudo medir, dejar la celda con "—" y el motivo.
