#!/usr/bin/env bash
# Uso: servir.sh <arbol> [puerto]   (p. ej. servir.sh /home/user/antes 8125)
# Resiembra la BD del arnés con los modelos de <arbol> y sirve <arbol> con php -S.
# ARNES_DATOS: carpeta de los sqlite (default /tmp/towell-arnes-1905). No usar el mismo ARNES_DATOS
# para dos árboles a la vez: cada uno siembra su propio esquema.
set -euo pipefail
ARNES="$(cd "$(dirname "$0")" && pwd)"
export ARNES_REPO="$(cd "${1:?falta el árbol}" && pwd)"
PUERTO="${2:-8125}"
export ARNES_DATOS="${ARNES_DATOS:-/tmp/towell-arnes-1905}"
php "$ARNES/setup.php"
echo "sirviendo $ARNES_REPO en http://127.0.0.1:$PUERTO (datos: $ARNES_DATOS)"
exec env PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$PUERTO" -t "$ARNES_REPO/public" "$ARNES/index.php"
