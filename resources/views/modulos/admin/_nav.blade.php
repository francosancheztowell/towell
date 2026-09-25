{{-- Pestañas del panel de administración (solo Sistemas). --}}
@php
    $pestanas = [
        ['admin.index', 'En línea', 'fa-tower-broadcast'],
        ['admin.sesiones', 'Sesiones', 'fa-clock-rotate-left'],
        ['admin.navegacion', 'Navegación', 'fa-route'],
        ['admin.rendimiento', 'Rendimiento', 'fa-gauge-high'],
        ['admin.errores', 'Errores', 'fa-bug'],
        ['admin.accesos', 'Accesos', 'fa-door-open'],
    ];
@endphp
<nav aria-label="Panel de administración" class="flex items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
    <span class="sr-only">Panel de administración</span>
    @foreach ($pestanas as [$ruta, $texto, $icono])
        @php $activa = request()->routeIs($ruta) || ($ruta === 'admin.errores' && request()->routeIs('admin.errores.show')); @endphp
        <a href="{{ route($ruta) }}" @if ($activa) aria-current="page" @endif
           @class([
               'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition',
               'bg-blue-600 text-white shadow-sm' => $activa,
               'text-slate-600 hover:bg-slate-100' => ! $activa,
           ])>
            <i class="fa-solid {{ $icono }}"></i> {{ $texto }}
        </a>
    @endforeach
    @if (config('pulse.enabled'))
        <a href="{{ url(config('pulse.path')) }}" class="ms-auto inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
            <i class="fa-solid fa-heart-pulse text-rose-500"></i> Pulse
        </a>
    @endif
</nav>
