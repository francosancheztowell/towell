#!/bin/bash
# SessionStart hook para Claude Code en la web (BASE-12).
# Deja vendor/, node_modules/, .env y public/build listos para correr
# php artisan test, phpstan, npm run typecheck/test:js/build sin pasos manuales.
# Idempotente: cada paso se salta si ya esta hecho. Solo corre en sesiones remotas.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/..}"

# Directorios que Laravel necesita y que no vienen en un checkout limpio.
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# phpstan/phpstan solo publica dist por api.github.com, que el proxy de la nube
# responde con 403 sin token. Si falta en la cache de composer, se arma el zip
# desde git (que si pasa) en la ruta exacta que composer busca.
sembrar_phpstan() {
    local ref cache url tmp
    ref=$(php -r '$l=json_decode(file_get_contents("composer.lock"),true); foreach($l["packages-dev"] as $p){ if($p["name"]==="phpstan/phpstan"){ echo $p["dist"]["reference"]; } }')
    [ -n "$ref" ] || return 0
    cache="$(composer config cache-files-dir)/phpstan/phpstan"
    url="https://api.github.com/repos/phpstan/phpstan/zipball/$ref"
    [ -f "$cache/$(php -r "echo sha1('$url');").zip" ] && return 0
    tmp=$(mktemp -d)
    git clone -q --filter=blob:none --no-checkout https://github.com/phpstan/phpstan.git "$tmp/phpstan"
    git -C "$tmp/phpstan" checkout -q "$ref"
    mkdir -p "$cache"
    git -C "$tmp/phpstan" archive --format=zip --prefix=phpstan/ -o "$cache/$(php -r "echo sha1('$url');").zip" HEAD
    rm -rf "$tmp"
}

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    export COMPOSER_ALLOW_SUPERUSER=1
    sembrar_phpstan
    # --prefer-source: los zip de api.github.com dan 403 aqui y composer los
    # reintenta uno por uno antes de caer a git; ir directo a git ahorra minutos.
    composer install --no-interaction --no-progress --prefer-source
fi

if [ ! -d node_modules ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
    npm ci --no-audit --no-fund
fi

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

# Varios tests de vistas renderizan @vite: sin manifest fallan con "Vite manifest not found".
if [ ! -f public/build/manifest.json ]; then
    npm run build
fi
