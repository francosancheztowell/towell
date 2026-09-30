# Arnés 19-05 (Programa Urdido-Engomado) sin SQL Server

Derivado de `19-01-arnes/` (no lo modifica). Sirve la app real con todas las conexiones `sqlsrv*`
(incluidas `sqlsrv_ti` = TI_PRO y `sqlsrv_tow_pro`) sobre un sqlite en archivo, usuario 1 con todos
los permisos, y toma capturas + consola con Playwright (Chromium de `/opt/pw-browsers`).

## Levantar y sembrar

```bash
A=.planning/phases/19-modulos/19-05-arnes
export ARNES_DATOS=/tmp/towell-arnes-1905          # sqlite (uno por árbol servido)
$A/servir.sh /home/user/towell 8126 &              # = setup.php (esquema + semilla) + php -S
# resembrar sin reiniciar: ARNES_REPO=/home/user/towell php $A/setup.php
```

- `boot.php`: igual que 19-01 más `ArnesSqliteConnection`, que traduce el SQL Server que el módulo
  escribe a mano (`WITH (NOLOCK)`, `SET TRANSACTION …`, `ISNULL(` → `IFNULL(`, `CONVERT(VARCHAR(n), x, 23)`,
  `CAST(x AS DATE)` → `date(x)`), funciones `DATE_FORMAT/YEAR/MONTH/DAY` y un `INFORMATION_SCHEMA.COLUMNS`
  adjunto (lo usa `SSYSFoliosSecuencia` al sacar folios).
- `seed-modulo.php`: 8 telares activos (libres, reservado, programado, Karl Mayer 401), reserva en
  `InvTelasReservadas`, TI_PRO mínimo (`InventSum/InventDim/InventSerial`, `ConfigTable`, `InventSize`
  con formato real `3040-12.5/1`, `BOMTABLE/BOM/BOMVersion/INVENTTABLE`), `ReqTelares.Grupo`,
  `ReqProgramaTejido(+Line)` para el resumen de 5 semanas, catálogo de máquinas (MaquinaId = nombre
  largo; Karl Mayer con Nombre `KM1`), núcleos, anchos de balona, folios `URD/ENG` y `CambioHilo`,
  y órdenes `UrdProgramaUrdido`/`EngProgramaEngomado` en Programado/En Proceso/Parcial/Finalizado/Cancelado
  en MC Coy 1-3, Karl Mayer, West Point 2-3.
- `casos.json`: los 2 telares (201 y 202 Rizo) que manda reservar-programar a programación, y los
  requerimientos que manda programación a creación de órdenes (`{TELARES}`/`{REQUERIMIENTOS}` en `urls.txt`).

## Capturas antes / después

```bash
# antes: worktree en ff120339 (vendor propio, ver Limitaciones), servido en 8125
ARNES_DATOS=/tmp/towell-arnes-1905-antes $A/servir.sh /home/user/antes 8125 &
ARNES_URL=http://127.0.0.1:8125 ARNES_SALIDA=<scratchpad>/capturas/antes node $A/shoot.mjs antes
# después (npm run build antes en /home/user/towell)
ARNES_URL=http://127.0.0.1:8126 ARNES_SALIDA=<scratchpad>/capturas/despues node $A/shoot.mjs despues
```

Salida: `<pantalla>-768.png` y `<pantalla>-1280.png` + `consola.json` (status, errores de consola,
`pageerror`, respuestas ≥ 400 y warnings por pantalla y viewport). `VIEWPORTS=…` y `FULL=1` opcionales.

## Flujo scriptable

```bash
ARNES_REPO=/home/user/towell ARNES_DATOS=/tmp/towell-arnes-1905-despues php $A/setup.php   # el flujo escribe: resembrar antes
ARNES_DATOS=/tmp/towell-arnes-1905-despues ARNES_URL=http://127.0.0.1:8126 \
  ARNES_SALIDA=<scratchpad>/capturas/flujo-despues node $A/flujo.mjs despues
```

`ARNES_DATOS` debe ser el del servidor: el flujo consulta ese sqlite para verificar el folio en BD.
Tres grupos (un fallo omite solo el resto de su grupo):
- **orden:** reserva J-502 al telar 203 → marca 201+202 → Programar → tamaño y hilo → Siguiente →
  fila, destino, BOM `URD 3040-A12`, material, construcción, engomado → Crear Órdenes → fecha → folio
  `00111` → verifica `UrdProgramaUrdido`, `EngProgramaEngomado`, `UrdJuliosOrden`, `UrdConsumoHilo` y
  `no_orden` de los 2 telares.
- **tactil (768 px, touch):** long-press en encabezado (menú de columna), botón "⋮" (menú de fila, 44 px)
  y long-press en celda editable. En el "antes" se marca omitido (solo había clic derecho).
- **karl-mayer:** 401 / barra 1 / A12 / `1800-8/1` / `URD 1800-KM` / material / hilos → Crear Orden →
  fecha → folio `00112` → verifica en BD.

Sirve para antes y después: la fecha se confirma en el modal x-ui (`data-fecha-requerimiento-*`) o
en el SweetAlert, y el botón es `[data-accion="crear-ordenes"]` o `#btn-crear-ordenes`. Salida:
`NN-paso.png` + `flujo.json` (ok/omitido, detalle, errores de consola y respuestas ≥ 400 por paso).

Comparativas lado a lado (antes | después, una imagen por pantalla, sin ImageMagick):
`node $A/comparar.mjs <capturas/antes> <capturas/despues> <salida> [768|1280]`.

## Limitaciones

- **vendor del worktree:** un symlink a `/home/user/towell/vendor` hace que Composer cargue `App\` desde
  `/home/user/towell/app` (resuelve el symlink): controllers del "después" con vistas del "antes".
  En `/home/user/antes` se usó `cp -al` del vendor + copia real de `vendor/composer` y `vendor/autoload.php`
  + `composer dump-autoload --no-scripts`.
- sqlite ≠ SQL Server: tipos laxos, `CAST AS DATE` traducido por regex, sin bloqueos; no mide rendimiento.
- Sin Karl Mayer "crear orden", drag de prioridades, cambio de status ni calificar julios en el flujo
  (las pantallas sí se capturan). La fecha "hoy" de la semilla sale del reloj del servidor.
