{{--
    Layout del panel /admin (monitoreo, solo Sistemas): estructura propia con barra lateral,
    sin la navbar de la app. Tema oscuro por defecto; la cookie towell_admin_tema ('claro')
    lo cambia desde el servidor (resources/js/modulos/admin/index.ts la escribe).
--}}
@php
    $oscuro = request()->cookie('towell_admin_tema') !== 'claro';
    $navegacion = [
        'Salud' => [
            ['admin.index', 'Resumen', 'squares-2x2', ['admin.index']],
            ['admin.errores', 'Errores', 'bug-ant', ['admin.errores', 'admin.errores.show']],
            ['admin.rendimiento', 'Rendimiento', 'bolt', ['admin.rendimiento']],
        ],
        'Actividad' => [
            ['admin.en-linea', 'En línea', 'signal', ['admin.en-linea']],
            ['admin.sesiones', 'Sesiones', 'clock', ['admin.sesiones']],
            ['admin.navegacion', 'Navegación', 'map', ['admin.navegacion']],
            ['admin.accesos', 'Accesos', 'key', ['admin.accesos']],
        ],
    ];
    $usuario = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="es" style="--pt-navbar-height: 0px">
<head>
    <x-layout-head />
    <x-layout-styles />
    <x-layout-scripts />
    @vite('resources/js/modulos/admin/index.ts')
</head>
<body class="h-dvh overflow-hidden">
    <div data-admin-shell
         @class(['admin-shell bg-(--adm-bg) text-(--adm-ink) antialiased', 'dark' => $oscuro])
         style="height: 100dvh; grid-template-rows: auto minmax(0, 1fr) auto">

        <flux:sidebar sticky collapsible="mobile" aria-label="Panel de administración"
                      class="w-60! border-e border-(--adm-line) bg-(--adm-rail) px-3! py-4!">
            <flux:sidebar.header class="px-1">
                <a href="{{ route('admin.index') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg px-1 py-1">
                    <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell"
                         class="h-5 w-auto shrink-0 dark:brightness-0 dark:invert">
                    <span class="truncate text-sm font-semibold text-(--adm-ink-2)">Monitoreo</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-2 gap-5">
                @foreach ($navegacion as $grupo => $items)
                    <div>
                        <p class="mb-1 px-3 text-caption font-medium text-(--adm-ink-3)">{{ $grupo }}</p>
                        @foreach ($items as [$ruta, $texto, $icono, $activas])
                            <flux:sidebar.item :href="route($ruta)" :icon="$icono" :current="request()->routeIs(...$activas)">
                                {{ $texto }}
                            </flux:sidebar.item>
                        @endforeach
                    </div>
                @endforeach
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                @if (config('pulse.enabled'))
                    <flux:sidebar.item :href="url(config('pulse.path'))" icon="heart">Pulse</flux:sidebar.item>
                @endif
                <flux:sidebar.item href="/produccionProceso" icon="arrow-uturn-left">Volver a Towell</flux:sidebar.item>
                {{-- Nombra la acción según el tema actual: el texto y el icono cambian con .dark, sin JS. --}}
                <button type="button" data-admin-tema
                        class="my-px flex h-8 w-full items-center gap-3 rounded-lg border border-transparent px-3 text-start text-sm font-medium text-(--adm-ink-2) transition-colors hover:bg-(--adm-hover) hover:text-(--adm-ink)">
                    <flux:icon.moon class="size-4 dark:hidden" />
                    <flux:icon.sun class="hidden size-4 dark:block" />
                    <span class="dark:hidden">Cambiar a tema oscuro</span>
                    <span class="hidden dark:inline">Cambiar a tema claro</span>
                </button>
            </flux:sidebar.nav>

            @if ($usuario)
                <div class="flex items-center gap-2.5 border-t border-(--adm-line) px-2 pt-3">
                    <flux:avatar size="xs" :name="$usuario->nombre" class="shrink-0" />
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-(--adm-ink)">{{ $usuario->nombre }}</p>
                        <p class="truncate text-caption text-(--adm-ink-3)">{{ $usuario->area }}</p>
                    </div>
                </div>
            @endif
        </flux:sidebar>

        <flux:main class="min-h-0 overflow-y-auto p-0!">
            <header class="sticky top-0 z-30 flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-b border-(--adm-line) bg-(--adm-bg)/90 px-5 py-3 backdrop-blur-sm lg:px-8">
                {{-- Móvil: el menú vive en esta misma cabecera, no en una barra aparte. --}}
                <flux:sidebar.toggle class="-ms-2 lg:hidden" icon="bars-2" />
                <div class="min-w-0 flex-1">
                    @hasSection('migas')
                        <nav aria-label="Ruta" class="mb-0.5 flex items-center gap-1.5 text-caption text-(--adm-ink-3)">@yield('migas')</nav>
                    @endif
                    <h1 class="truncate text-lg font-semibold tracking-tight text-(--adm-ink)">@yield('encabezado')</h1>
                </div>
                {{-- x-tabla teletransporta aquí sus acciones (Renombrar, Ver detalle…). --}}
                <div id="tabla-navbar-acciones" class="flex items-center gap-2 max-sm:order-last max-sm:w-full"></div>
                @yield('acciones')
            </header>

            @if (session()->hasAny(['error', 'warning', 'success', 'info', 'status']) || (isset($errors) && $errors->any()))
                <div class="px-5 pt-4 lg:px-8"><x-ui.flash :contenido="$__env->yieldContent('content')" /></div>
            @endif

            <div class="px-5 py-6 lg:px-8">
                @yield('content')
            </div>
        </flux:main>
    </div>

    {!! app('flux')->scripts() !!}
    @vite(['resources/js/app-core.js'])
</body>
</html>
