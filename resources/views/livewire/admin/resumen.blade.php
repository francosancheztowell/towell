{{-- Panel /admin · Resumen: lo que se rompe y quién está conectado, con el mismo peso. --}}
@php
    $panel = 'rounded-xl border border-(--adm-line) bg-(--adm-panel)';
    $cabecera = 'flex items-center justify-between gap-3 border-b border-(--adm-line) px-4 py-3';
    $enlace = 'text-caption font-medium text-(--adm-accent) hover:underline underline-offset-2';
    $totalVistas = array_sum($vistasHora);
    $totalEventos = array_sum($eventosHora);
    $maxLenta = max(1, (int) $lentas->max('p95'));
    $ventana = $rango === '7d' ? '7 días' : '24 horas';
@endphp
<div class="space-y-5" wire:poll.visible.{{ $pollSeconds }}s>

    {{-- Cabecera: estado en vivo y ventana de la gráfica. --}}
    @teleport('#tabla-navbar-acciones')
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 text-caption text-(--adm-ink-3)" title="Se actualiza solo cada {{ $pollSeconds }} s mientras la pestaña está visible">
                <span class="adm-vivo" aria-hidden="true"></span> En vivo · {{ $actualizado }}
            </span>
            <div class="inline-flex rounded-lg border border-(--adm-line) bg-(--adm-panel) p-0.5" role="group" aria-label="Ventana de la gráfica">
                @foreach (['24h' => '24 h', '7d' => '7 días'] as $valor => $texto)
                    <button type="button" wire:click="$set('rango', '{{ $valor }}')" aria-pressed="{{ $rango === $valor ? 'true' : 'false' }}"
                            @class(['min-h-7 rounded-md px-2.5 text-caption font-medium transition-colors',
                                    'bg-(--adm-hover) text-(--adm-ink)' => $rango === $valor,
                                    'text-(--adm-ink-3) hover:text-(--adm-ink)' => $rango !== $valor])>{{ $texto }}</button>
                @endforeach
            </div>
        </div>
    @endteleport

    {{-- Cifras: una franja, cada una lleva a su sección. --}}
    <dl class="{{ $panel }} grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-(--adm-line) [&>*:nth-child(-n+2)]:border-b [&>*:nth-child(-n+2)]:border-(--adm-line) lg:[&>*:nth-child(-n+2)]:border-b-0">
        <a href="{{ route('admin.errores') }}" class="block px-4 py-3.5 transition-colors hover:bg-(--adm-hover)">
            <dt class="text-caption text-(--adm-ink-3)">Errores abiertos</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-tight" data-cifra>{{ number_format($cifras['abiertos']) }}</dd>
        </a>
        <a href="{{ route('admin.errores') }}" class="block px-4 py-3.5 transition-colors hover:bg-(--adm-hover)">
            <dt class="text-caption text-(--adm-ink-3)">Nuevos en 24 h</dt>
            <dd @class(['mt-1 text-2xl font-semibold tracking-tight', 'text-(--adm-err)' => $cifras['nuevos'] > 0]) data-cifra>{{ number_format($cifras['nuevos']) }}</dd>
        </a>
        <a href="{{ route('admin.en-linea') }}" class="block px-4 py-3.5 transition-colors hover:bg-(--adm-hover)">
            <dt class="flex items-center gap-2 text-caption text-(--adm-ink-3)">
                @if ($cifras['enLinea'] > 0)<span class="adm-vivo" aria-hidden="true"></span>@endif En línea ahora
            </dt>
            <dd class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-semibold tracking-tight" data-cifra>{{ number_format($cifras['enLinea']) }}</span>
                <span class="text-caption text-(--adm-ink-3)">{{ $cifras['inactivos'] }} {{ $cifras['inactivos'] === 1 ? 'inactivo' : 'inactivos' }}</span>
            </dd>
        </a>
        <a href="{{ route('admin.navegacion') }}" class="block px-4 py-3.5 transition-colors hover:bg-(--adm-hover)">
            <dt class="text-caption text-(--adm-ink-3)">Vistas en {{ $ventana }}</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-tight" data-cifra>{{ number_format($totalVistas) }}</dd>
        </a>
    </dl>

    {{-- Gráfica: vistas y eventos de error por hora, mismo eje. --}}
    <section class="{{ $panel }}" aria-labelledby="titulo-grafica">
        <div class="{{ $cabecera }}">
            <h2 id="titulo-grafica" class="text-sm font-semibold">Últimas {{ $ventana }} <span class="font-normal text-(--adm-ink-3)">· por hora</span></h2>
        </div>
        <div class="grid gap-x-6 gap-y-7 px-4 pb-4 pt-7 sm:grid-cols-[9rem_minmax(0,1fr)]">
            <div class="flex items-baseline justify-between gap-2 sm:block">
                <p class="text-sm text-(--adm-ink-2)">Vistas</p>
                <p class="text-lg font-semibold" data-cifra>{{ number_format($totalVistas) }}</p>
            </div>
            <x-admin.barras :valores="$vistasHora" :alto="104" etiqueta="vistas" :escala="true" class="self-end text-(--adm-ink-2)" />

            <div class="flex items-baseline justify-between gap-2 sm:block">
                <p class="text-sm text-(--adm-ink-2)">Eventos de error</p>
                <p @class(['text-lg font-semibold', 'text-(--adm-err)' => $totalEventos > 0]) data-cifra>{{ number_format($totalEventos) }}</p>
            </div>
            <x-admin.barras :valores="$eventosHora" :alto="64" etiqueta="eventos de error" :escala="true" :ejes="true" class="text-(--adm-err)" />
        </div>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-3">
        {{-- Errores que necesitan atención --}}
        <section class="{{ $panel }} xl:col-span-2" aria-labelledby="titulo-atencion">
            <div class="{{ $cabecera }}">
                <h2 id="titulo-atencion" class="text-sm font-semibold">Necesitan atención</h2>
                <a href="{{ route('admin.errores') }}" class="{{ $enlace }}">Ver todos los errores</a>
            </div>
            @if ($atencion->isEmpty())
                <div class="flex items-center gap-3 px-4 py-10 text-sm text-(--adm-ink-2)">
                    <flux:icon.check-circle class="size-5 text-(--adm-ok)" />
                    Sin errores abiertos. Lo nuevo aparecerá aquí en cuanto ocurra.
                </div>
            @else
                <ul class="divide-y divide-(--adm-line)">
                    @foreach ($atencion as $e)
                        <li wire:key="atencion-{{ $e->Id }}">
                            <a href="{{ route('admin.errores.show', $e->Id) }}"
                               class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 px-4 py-3 transition-colors hover:bg-(--adm-hover) sm:grid-cols-[minmax(0,1fr)_6rem_3.5rem]">
                                <div class="min-w-0">
                                    <p class="flex min-w-0 items-center gap-2">
                                        @include('livewire.admin.partials.estado-punto', ['estado' => $e->Estado])
                                        <span class="truncate text-sm font-semibold text-(--adm-ink)">{{ \Illuminate\Support\Str::afterLast((string) $e->Clase, '\\') }}</span>
                                    </p>
                                    <p class="mt-0.5 truncate ps-4 text-sm text-(--adm-ink-2)">{{ $e->Mensaje }}</p>
                                    <p class="mt-0.5 truncate ps-4 text-caption text-(--adm-ink-3)"><span class="adm-mono">{{ $e->Origen }}</span>@if ($e->Ruta) · <span class="adm-mono">{{ $e->Ruta }}</span>@endif</p>
                                </div>
                                <x-admin.barras :valores="$barras[$e->Id] ?? array_fill(0, 24, 0)" class="hidden text-(--adm-err) sm:block" />
                                <div class="text-right">
                                    <p class="text-sm font-semibold" data-cifra>{{ number_format((int) $e->Ocurrencias) }}</p>
                                    <p class="whitespace-nowrap text-caption text-(--adm-ink-3)">{{ $e->UltimaVez?->locale('es')->diffForHumans(short: true) }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Quién está y quién estuvo: dispositivos del día y los últimos accesos. --}}
        <div class="space-y-5">
        <section class="{{ $panel }}" aria-labelledby="titulo-conectados">
            <div class="{{ $cabecera }}">
                <h2 id="titulo-conectados" class="text-sm font-semibold">Dispositivos <span class="font-normal text-(--adm-ink-3)">· activos en 24 h</span></h2>
                <a href="{{ route('admin.en-linea') }}" class="{{ $enlace }}">Ver todos</a>
            </div>
            @if ($dispositivos->isEmpty())
                <p class="px-4 py-10 text-sm text-(--adm-ink-2)">Ningún dispositivo se ha conectado en las últimas 24 horas.</p>
            @else
                <ul class="divide-y divide-(--adm-line)">
                    @foreach ($dispositivos as $d)
                        @php $estado = $d->EstadoActual; @endphp
                        <li wire:key="dispositivo-{{ $d->Id }}"
                            class="grid grid-cols-[1rem_minmax(0,1fr)_auto] items-center gap-x-3 px-4 py-3 sm:grid-cols-[1rem_minmax(0,1fr)_6rem_3.5rem]">
                            <span class="flex justify-center" title="{{ ['en_linea' => 'En línea', 'inactivo' => 'Inactivo'][$estado] ?? 'Desconectado' }}">
                                @if ($estado === 'en_linea')
                                    <span class="adm-vivo"></span>
                                @elseif ($estado === 'inactivo')
                                    <span class="size-2 rounded-full bg-(--adm-warn)"></span>
                                @else
                                    <span class="size-2 rounded-full border border-(--adm-ink-3)"></span>
                                @endif
                                <span class="sr-only">{{ ['en_linea' => 'En línea', 'inactivo' => 'Inactivo'][$estado] ?? 'Desconectado' }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-(--adm-ink)">{{ $d->UsuarioNombre ?: 'Sin usuario' }} <span class="font-normal text-(--adm-ink-3)">· {{ $d->UsuarioArea ?: '—' }}</span></p>
                                <p class="mt-0.5 truncate text-caption text-(--adm-ink-3)">
                                    {{ $d->Nombre ?: trim(($d->Modelo ?: '').' '.$d->Tipo) }}@if ($d->UltimaRuta) · <span class="adm-mono">{{ $d->UltimaRuta }}</span>@endif
                                </p>
                            </div>
                            <x-admin.barras :valores="$barrasDispositivo[$d->Id] ?? array_fill(0, 24, 0)" etiqueta="vistas" class="hidden text-(--adm-ink-2) sm:block" />
                            <p class="whitespace-nowrap text-right text-caption text-(--adm-ink-3)">
                                {{ $estado === 'en_linea' ? 'ahora' : $d->UltimaActividad?->locale('es')->diffForHumans(short: true) }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="{{ $panel }}" aria-labelledby="titulo-accesos">
            <div class="{{ $cabecera }}">
                <h2 id="titulo-accesos" class="text-sm font-semibold">Accesos recientes</h2>
                <a href="{{ route('admin.accesos') }}" class="{{ $enlace }}">Ver accesos</a>
            </div>
            @if ($accesos->isEmpty())
                <p class="px-4 py-8 text-sm text-(--adm-ink-2)">Sin accesos registrados.</p>
            @else
                <ul class="divide-y divide-(--adm-line)">
                    @foreach ($accesos as $a)
                        @php
                            $alerta = in_array($a->Tipo, ['login_fallido', 'bloqueo', 'authz_denegaria'], true);
                            $icono = match ($a->Tipo) {
                                'login', 'recordarme' => 'arrow-right-end-on-rectangle',
                                'logout', 'logout_remoto' => 'arrow-right-start-on-rectangle',
                                'admin_accion' => 'wrench-screwdriver',
                                default => 'shield-exclamation',
                            };
                        @endphp
                        <li wire:key="acceso-{{ $loop->index }}-{{ $a->Fecha?->timestamp }}" class="flex items-center gap-3 px-4 py-2.5">
                            <flux:icon :icon="$icono" variant="micro" @class(['size-4 shrink-0', 'text-(--adm-err)' => $alerta, 'text-(--adm-ink-3)' => ! $alerta]) />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-(--adm-ink)">{{ $a->UsuarioNombre ?: ($a->NumeroEmpleado ? '#'.$a->NumeroEmpleado : 'Desconocido') }}</p>
                                <p @class(['truncate text-caption', 'text-(--adm-err)' => $alerta, 'text-(--adm-ink-3)' => ! $alerta])>{{ ucfirst(str_replace('_', ' ', (string) $a->Tipo)) }}@if ($a->Motivo) · {{ $a->Motivo }}@endif</p>
                            </div>
                            <span class="shrink-0 text-caption text-(--adm-ink-3)">{{ $a->Fecha?->locale('es')->diffForHumans(short: true) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        </div>
    </div>

    {{-- Rutas más lentas --}}
    <section class="{{ $panel }}" aria-labelledby="titulo-lentas">
        <div class="{{ $cabecera }}">
            <h2 id="titulo-lentas" class="text-sm font-semibold">Rutas más lentas <span class="font-normal text-(--adm-ink-3)">· servidor p95, 7 días</span></h2>
            <a href="{{ route('admin.rendimiento') }}" class="{{ $enlace }}">Ver rendimiento</a>
        </div>
        @if ($lentas->isEmpty())
            <p class="px-4 py-10 text-sm text-(--adm-ink-2)">Sin vistas medidas en los últimos 7 días.</p>
        @else
            <ul class="divide-y divide-(--adm-line)">
                @foreach ($lentas as $l)
                    @php $excede = $l['p95'] > $umbralServidor; @endphp
                    <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-1.5 px-4 py-2.5 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)_5.5rem]">
                        <span class="adm-mono truncate text-(--adm-ink)" title="{{ $l['ruta'] }}">{{ $l['ruta'] }}</span>
                        <span class="relative order-last col-span-2 h-1.5 rounded-full bg-(--adm-hover) md:order-none md:col-span-1" aria-hidden="true">
                            <span @class(['absolute inset-y-0 start-0 rounded-full', 'bg-(--adm-err)' => $excede, 'bg-(--adm-ink-3)' => ! $excede])
                                  style="width: {{ round(100 * $l['p95'] / $maxLenta) }}%"></span>
                        </span>
                        <span @class(['text-right text-sm font-semibold tabular-nums', 'text-(--adm-err)' => $excede])>{{ number_format($l['p95']) }} ms</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
