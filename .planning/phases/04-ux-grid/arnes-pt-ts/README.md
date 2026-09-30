# Arnés PT-TS 1

Reusa el arnés de 19-01 (sqlite en archivo, `php -S`, Playwright) + la semilla de PT 03 y agrega lo que piden
Liberar Órdenes, Utilería y Alineación.

```bash
export ARNES_DATOS=/tmp/towell-arnes
php .planning/phases/19-modulos/19-01-arnes/setup.php
php .planning/phases/03-frontend-shell/arnes/seed-pt.php
php .planning/phases/04-ux-grid/arnes-pt-ts/seed.php
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8123 -t public .planning/phases/19-modulos/19-01-arnes/index.php &
for v in 768x1024 1280x800; do
  ARNES_VIEWPORT=$v node .planning/phases/04-ux-grid/arnes-pt-ts/shoot.mjs despues .planning/phases/04-ux-grid/arnes-pt-ts/urls.txt
done
# → $ARNES_DATOS/shots/despues-<viewport>/*.png + reporte.json (status y errores de consola)
```

`urls.txt` abre cada modal de la grilla (líneas, balanceo, repaso, marbetes, calendarios, Redbooth) en
Programa y Muestras, y las pantallas Liberar, Utilería (Mover/Finalizar) y Alineación.
