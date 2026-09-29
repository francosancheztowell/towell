# Arnés de pantallas sin SQL Server (19-01)

Sirve la app real con **todas** las conexiones `sqlsrv*` apuntando a un sqlite en archivo, con un usuario que tiene todos los permisos, y toma capturas + errores de consola con Playwright. Sirve para el "antes/después" de cualquier 19-xx.

```bash
export ARNES_DATOS=/tmp/towell-arnes           # sqlite + capturas (fuera del repo)
php .planning/phases/19-modulos/19-01-arnes/setup.php          # esquema desde app/Models + semilla
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8123 -t public .planning/phases/19-modulos/19-01-arnes/index.php &
node .planning/phases/19-modulos/19-01-arnes/shoot.mjs despues .planning/phases/19-modulos/19-01-arnes/urls.txt
# → $ARNES_DATOS/shots/despues/*.png + reporte.json (status y errores de consola por pantalla)
```

- **Esquema:** una tabla por modelo de `app/Models` (`$fillable` + `$casts` + PK), en `main` y en el esquema `dbo` (attach) para el SQL que escribe el prefijo. Columnas que falten en un modelo: `extra-cols.json` (`{"Tabla": ["Col"]}`).
- **Permisos:** `SYSRoles` 1..400 + los nombres de `modulos.txt`, todos concedidos al usuario 1. Login: `GET /__login/1?to=/ruta` (solo existe en el arnés).
- **Semilla del módulo:** `seed-modulo.php` (órdenes de urdido/engomado, BPM, catálogos). Cada 19-xx agrega la suya.
- **"Antes" de algo ya migrado:** `git worktree add /tmp/antes <commit>`, `npm run build` ahí, y `ARNES_REPO=/tmp/antes php -S … -t /tmp/antes/public …/index.php`.
- `urls.txt`: `nombre | ruta | pasos JS opcionales (Playwright, variable page)`.
- Debugbar y monitoreo apagados; caché en memoria.
