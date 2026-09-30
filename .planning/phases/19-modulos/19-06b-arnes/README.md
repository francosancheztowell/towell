# Arnés 19-06b (catálogos de Planeación)

Copia del arnés de 19-01 (`../19-01-arnes/README.md`) con la semilla de los catálogos y `shoot.ts` (dos tamaños).

```bash
export ARNES_DATOS=/tmp/towell-arnes-cat
php .planning/phases/19-modulos/19-06b-arnes/setup.php
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8123 -t public .planning/phases/19-modulos/19-06b-arnes/index.php &
node .planning/phases/19-modulos/19-06b-arnes/shoot.ts despues .planning/phases/19-modulos/19-06b-arnes/urls.txt
```

"Antes": `git worktree add /tmp/antes 43e0f09` + `npm run build` ahí y `ARNES_REPO=/tmp/antes`.
