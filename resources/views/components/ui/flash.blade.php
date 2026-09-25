{{--
    Flash de sesión (DS-09). Montado una vez en layouts/app.blade.php: muestra
    session('error'|'warning'|'success'|'info'|'status') y los errores de validación que
    dejó un redirect (p. ej. "No tienes acceso a este módulo." de UsuarioController).

    Anti-duplicado: muchas vistas ya pintan su propio flash (Swal o alert). Si el mensaje ya
    aparece en el HTML de la vista ($contenido: su sección content + @stack('scripts')), aquí
    no se repite.

    @prop string $contenido  HTML ya renderizado de la vista (lo pasa el layout)
--}}
@props(['contenido' => ''])

@php
    $claves = ['error' => 'error', 'warning' => 'warning', 'success' => 'success', 'info' => 'info', 'status' => 'success'];
    $avisos = [];

    // "Ya pintado" = el mensaje completo aparece como texto de un nodo (>msg<) o como literal
    // de JS/JSON ('msg', "msg", `msg`), en cualquiera de las formas en que Blade lo escapa.
    // Así "Guardado" no se descarta porque la página contenga "Guardado correctamente".
    $yaPintado = function (string $mensaje) use ($contenido): bool {
        if ($contenido === '') {
            return false;
        }
        $formas = array_unique([
            $mensaje,
            e($mensaje),
            substr((string) json_encode($mensaje), 1, -1),
            substr((string) json_encode($mensaje, JSON_UNESCAPED_UNICODE), 1, -1),
            substr((string) json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 1, -1),
            substr((string) json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), 1, -1),
        ]);
        foreach ($formas as $forma) {
            if ($forma === '') {
                continue;
            }
            $patron = '/(?:>\s*|[\'"`])'.preg_quote($forma, '/').'(?:\s*<|[\'"`])/u';
            if (preg_match($patron, $contenido) === 1) {
                return true;
            }
        }

        return false;
    };

    foreach ($claves as $clave => $tipo) {
        $valor = session($clave);
        if (is_string($valor) && trim($valor) !== '' && ! $yaPintado($valor)) {
            $avisos[] = ['tipo' => $tipo, 'mensaje' => $valor, 'items' => []];
        }
    }

    $errores = isset($errors) ? collect($errors->all())->reject(fn ($m) => $yaPintado((string) $m))->values()->all() : [];
    if ($errores !== []) {
        $avisos[] = count($errores) === 1
            ? ['tipo' => 'error', 'mensaje' => $errores[0], 'items' => []]
            : ['tipo' => 'error', 'mensaje' => 'Revisa los datos:', 'items' => $errores];
    }
@endphp

@if ($avisos !== [])
    <div data-ui-flash class="fixed right-4 z-[60] w-[min(28rem,calc(100vw-2rem))] space-y-2"
         style="top: calc(var(--pt-navbar-height, 64px) + 0.75rem);">
        @foreach ($avisos as $aviso)
            <x-ui.alert :type="$aviso['tipo']" :message="$aviso['mensaje']" :items="$aviso['items']"
                        class="mb-0! shadow-lg"
                        :data-ui-autoclose="in_array($aviso['tipo'], ['success', 'info'], true) ? 6000 : null" />
        @endforeach
    </div>
@endif
