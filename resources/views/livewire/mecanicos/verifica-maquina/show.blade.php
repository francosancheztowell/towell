<div class="flex flex-row items-start gap-3 sm:gap-4" wire:key="verifica-maquina-show-{{ $verificacion->Folio }}">
    {{-- Barra lateral fija a la izquierda --}}
    <aside class="sticky top-3 z-20 w-52 shrink-0 self-start sm:w-56 md:w-60">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
            <h2 class="text-sm font-bold text-gray-900">Filtros</h2>
            <p class="mt-0.5 text-[11px] text-gray-500">Máquina y rango de telares</p>

            <div class="mt-4 space-y-4">
                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Máquina</h3>
                    <div class="mt-2 space-y-1.5">
                        @php
                            $opcionesMaquina = [
                                '' => ['label' => 'Todas', 'count' => $totalTelares],
                                'Jacquard' => ['label' => 'Jacquard', 'count' => (int) ($conteoPorMaquina['Jacquard'] ?? 0)],
                                'Smith' => ['label' => 'Smith', 'count' => (int) ($conteoPorMaquina['Smith'] ?? 0)],
                                'KM' => ['label' => 'Karl Mayer', 'count' => (int) ($conteoPorMaquina['KM'] ?? 0)],
                            ];
                        @endphp
                        @foreach ($opcionesMaquina as $valor => $opcion)
                            <label @class([
                                'flex cursor-pointer items-center justify-between rounded-lg border px-2.5 py-2 text-sm font-semibold transition',
                                'border-gray-900 bg-gray-900 text-white' => $filtroMaquina === $valor,
                                'border-gray-200 bg-gray-50 text-gray-700 hover:border-gray-400' => $filtroMaquina !== $valor,
                            ])>
                                <span class="inline-flex items-center gap-2">
                                    <input type="radio" wire:model.live="filtroMaquina" value="{{ $valor }}" class="sr-only">
                                    {{ $opcion['label'] }}
                                </span>
                                <span @class([
                                    'rounded-full px-1.5 py-0.5 text-[11px] font-bold',
                                    'bg-white/20 text-white' => $filtroMaquina === $valor,
                                    'bg-white text-gray-600' => $filtroMaquina !== $valor,
                                ])>{{ $opcion['count'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Rango</h3>
                    <div class="mt-2 grid grid-cols-1 gap-2">
                        <div>
                            <label for="rango-desde" class="mb-1 block text-[11px] font-medium text-gray-600">Desde</label>
                            <input id="rango-desde" type="number" wire:model.live.debounce.400ms="rangoDesde" placeholder="201"
                                class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-semibold text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        </div>
                        <div>
                            <label for="rango-hasta" class="mb-1 block text-[11px] font-medium text-gray-600">Hasta</label>
                            <input id="rango-hasta" type="number" wire:model.live.debounce.400ms="rangoHasta" placeholder="215"
                                class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-semibold text-gray-900 outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        </div>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-snug text-gray-500">Ej. 201–215, 299–320, 401–402.</p>
                </div>

                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-2.5 py-2 text-center text-xs text-gray-600">
                    Mostrando <span class="font-bold text-gray-900">{{ $telares->count() }}</span> telar(es)
                </div>

                <button type="button" wire:click="limpiarFiltrosTelares"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
                    Limpiar filtros
                </button>
            </div>
        </div>
    </aside>

    {{-- Contenido principal --}}
    <div class="min-w-0 flex-1 space-y-4">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <a href="{{ route('mecanicos.estado-maquina.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 transition hover:text-gray-900">
                        <i class="fas fa-arrow-left"></i>
                        Volver a Estado de máquina
                    </a>
                    <h1 class="mt-2 text-xl font-bold text-gray-900 md:text-2xl">Verificación de máquina</h1>
                    <p class="mt-1 text-sm text-gray-600">Captura del 1 al 3 el estado de cada actividad por telar.</p>
                </div>
                <span class="inline-flex w-fit rounded-md bg-gray-900 px-3 py-2 text-sm font-bold text-white">Folio {{ $verificacion->Folio }}</span>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ optional($verificacion->Fecha)->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Mecánico</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $verificacion->NomOperador ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Turno</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $verificacion->TurnoRecibe ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Estatus</dt>
                    <dd class="mt-1">
                        @php $estatus = $verificacion->Estatus ?: 'Activo'; @endphp
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-blue-100 text-blue-800' => $estatus === 'Activo',
                            'bg-amber-100 text-amber-800' => $estatus === 'Terminado',
                            'bg-green-100 text-green-800' => $estatus === 'Autorizado',
                            'bg-gray-100 text-gray-700' => ! in_array($estatus, ['Activo', 'Terminado', 'Autorizado'], true),
                        ])>{{ $estatus }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Hr Inicio</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $verificacion->HoraInicio ? \Illuminate\Support\Str::of((string) $verificacion->HoraInicio)->substr(0, 5) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Hr Fin</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $verificacion->HoraFin ? \Illuminate\Support\Str::of((string) $verificacion->HoraFin)->substr(0, 5) : '—' }}</dd>
                </div>
            </dl>

            @if ($esSoloLectura)
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Este folio está en estatus <strong>{{ $verificacion->Estatus }}</strong>. Solo los folios <strong>Activo</strong> se pueden editar.
                </div>
            @endif

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                @if ($puedeFinalizar)
                    <button type="button" wire:click="abrirModalFinalizar"
                        class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
                        <i class="fas fa-check"></i>
                        Finalizar verificación
                    </button>
                @endif

                @if ($puedeAutorizar)
                    <button type="button" wire:click="abrirModalAutorizar"
                        class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">
                        <i class="fas fa-user-check"></i>
                        Autorizar
                    </button>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-1 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="font-bold text-gray-900">Telares y actividades</h2>
                    <p class="mt-1 text-sm text-gray-600">Captura la calificación (1, 2 o 3) en los telares filtrados.</p>
                </div>
            </div>

            <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500">
                <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar los telares filtrados.
            </div>
            <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Cuadrícula de verificación por telar y actividad; desplázate horizontalmente">
                <table class="divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="sticky left-0 z-10 min-w-80 max-w-md bg-gray-50 px-4 py-3.5 text-left text-sm">Actividad</th>
                            @forelse ($telares as $telar)
                                <th class="min-w-[4.5rem] px-2.5 py-3.5 text-center text-sm" title="{{ $telar->Nombre }} ({{ $telar->SalonTejidoId }})">{{ $telar->NoTelarId }}</th>
                            @empty
                                <th class="px-4 py-3.5 text-center text-xs font-medium normal-case text-gray-400">Sin telares</th>
                            @endforelse
                            <th class="whitespace-nowrap bg-gray-100 px-4 py-3.5 text-center text-sm">Todos los telares</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($actividades as $actividad)
                            <tr wire:key="actividad-{{ $actividad->Id }}" class="hover:bg-gray-50">
                                <td class="sticky left-0 z-20 min-w-80 max-w-md bg-white px-4 py-4">
                                    <span class="line-clamp-2 text-lg font-bold leading-snug text-gray-900" title="{{ $actividad->Actividad }}">
                                        {{ $actividad->Actividad }}
                                    </span>
                                </td>
                                @forelse ($telares as $telar)
                                    @php $valorActual = $valores[$telar->NoTelarId.'|'.$actividad->Actividad] ?? null; @endphp
                                    <td class="relative px-2 py-2.5 text-center" wire:key="celda-{{ $actividad->Id }}-{{ $telar->NoTelarId }}">
                                        <div
                                            x-data="{ open: false }"
                                            @keydown.escape.window="open = false"
                                            class="relative inline-flex justify-center"
                                        >
                                            <button
                                                type="button"
                                                @disabled(! $puedeCapturar)
                                                @click="open = !open"
                                                aria-haspopup="listbox"
                                                aria-label="Calificación telar {{ $telar->NoTelarId }} — {{ $actividad->Actividad }}"
                                                @class([
                                                    'inline-flex h-12 w-14 items-center justify-center gap-0.5 rounded-xl border-2 text-xl font-extrabold tabular-nums shadow-sm transition',
                                                    'border-gray-900 bg-gray-900 text-white' => filled($valorActual),
                                                    'border-gray-300 bg-white text-gray-400' => ! filled($valorActual),
                                                    'cursor-not-allowed opacity-40' => ! $puedeCapturar,
                                                    'cursor-pointer hover:scale-105 hover:border-gray-700' => $puedeCapturar,
                                                ])
                                            >
                                                <span>{{ $valorActual ?: '—' }}</span>
                                                @if ($puedeCapturar)
                                                    <i class="fas fa-caret-down text-[10px] opacity-70"></i>
                                                @endif
                                            </button>

                                            <div
                                                x-show="open"
                                                x-cloak
                                                x-transition.opacity.duration.100ms
                                                @click.outside="open = false"
                                                class="absolute left-1/2 top-full z-30 mt-1.5 w-16 -translate-x-1/2 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl"
                                                role="listbox"
                                            >
                                                @foreach (['1', '2', '3'] as $opcion)
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        wire:click="capturar('{{ $telar->NoTelarId }}', {{ $actividad->Id }}, '{{ $opcion }}')"
                                                        @click="open = false"
                                                        @class([
                                                            'flex h-11 w-full items-center justify-center text-xl font-extrabold tabular-nums transition',
                                                            'bg-gray-900 text-white' => $valorActual === $opcion,
                                                            'text-gray-800 hover:bg-gray-100' => $valorActual !== $opcion,
                                                        ])
                                                    >{{ $opcion }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                @empty
                                    <td class="px-4 py-6 text-center text-sm text-gray-400">—</td>
                                @endforelse
                                <td class="whitespace-nowrap bg-gray-50 px-4 py-3 text-center text-base font-bold text-gray-800">
                                    {{ $promedios[$actividad->Actividad] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max($telares->count(), 1) + 2 }}" class="px-4 py-10 text-center text-sm text-gray-500">No hay actividades configuradas en el catálogo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($telares->isEmpty())
                <div class="px-4 py-8 text-center text-sm text-gray-500">
                    No hay telares con los filtros seleccionados. Ajusta la máquina o el rango.
                </div>
            @endif
        </section>
    </div>

    {{-- Modal: confirmar finalizar --}}
    @if ($mostrarModalFinalizar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" wire:click.self="cancelarModales">
            <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-lg font-bold text-gray-900">Confirmar finalización</h2>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-relaxed text-gray-700">¿Está seguro que quieres finalizar este registro?</p>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelarModales"
                        class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                        No
                    </button>
                    <button type="button" wire:click="confirmarFinalizar"
                        class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black sm:w-auto">
                        Sí
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: incompletos --}}
    @if ($mostrarModalIncompletos)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" wire:click.self="cancelarModales">
            <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
                    <h2 class="text-lg font-bold text-amber-900">Telares incompletos</h2>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-relaxed text-gray-700">
                        Hay telares incompletos o que no se han llenado. ¿Está seguro de finalizar este registro?
                    </p>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelarModales"
                        class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                        No
                    </button>
                    <button type="button" wire:click="confirmarFinalizarConIncompletos"
                        class="w-full rounded-md bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700 sm:w-auto">
                        Sí, finalizar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: autorizar --}}
    @if ($mostrarModalAutorizar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="dialog" aria-modal="true" wire:click.self="cancelarModales">
            <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-lg font-bold text-gray-900">Autorizar verificación</h2>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-relaxed text-gray-700">¿Está seguro que quieres autorizar este registro?</p>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelarModales"
                        class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">
                        No
                    </button>
                    <button type="button" wire:click="autorizar"
                        class="w-full rounded-md bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 sm:w-auto">
                        Sí
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
