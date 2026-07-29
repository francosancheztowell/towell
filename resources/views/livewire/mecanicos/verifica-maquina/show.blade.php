<div class="space-y-4" wire:key="verifica-maquina-show-{{ $verificacion->Folio }}">
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
                    <span @class([
                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                        'bg-green-100 text-green-800' => $verificacion->Estatus === 'Terminado',
                        'bg-blue-100 text-blue-800' => $verificacion->Estatus !== 'Terminado',
                    ])>{{ $verificacion->Estatus ?: 'Activo' }}</span>
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

        @if ($puedeFinalizar && $verificacion->Estatus !== 'Terminado')
            <div class="mt-5 flex justify-end border-t border-gray-100 pt-4">
                <button type="button" wire:click="finalizar"
                    wire:confirm="¿Marcar esta verificación como terminada?"
                    class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
                    <i class="fas fa-check"></i>
                    Finalizar verificación
                </button>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 class="font-bold text-gray-900">Telares y actividades</h2>
                <p class="mt-1 text-sm text-gray-600">Selecciona una calificación (1 a 3) por telar y actividad.</p>
            </div>
        </div>
        <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500"><i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar todos los telares.</div>
        <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Cuadrícula de verificación por telar y actividad; desplázate horizontalmente">
            <table class="divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50 font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="sticky left-0 z-10 min-w-48 bg-gray-50 px-3 py-3 text-left">Actividad</th>
                        @foreach ($telares as $telar)
                            <th class="px-2 py-3 text-center" title="{{ $telar->Nombre }}">{{ $telar->NoTelarId }}</th>
                        @endforeach
                        <th class="whitespace-nowrap bg-gray-100 px-3 py-3 text-center">Todos los telares</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($actividades as $actividad)
                        <tr wire:key="actividad-{{ $actividad->Id }}" class="hover:bg-gray-50">
                            <td class="sticky left-0 z-10 min-w-48 bg-white px-3 py-2 font-medium text-gray-800">{{ $actividad->Actividad }}</td>
                            @foreach ($telares as $telar)
                                @php $valorActual = $valores[$telar->NoTelarId.'|'.$actividad->Actividad] ?? null; @endphp
                                <td class="px-1.5 py-2 text-center" wire:key="celda-{{ $actividad->Id }}-{{ $telar->NoTelarId }}">
                                    <div class="inline-flex gap-0.5 rounded-md border border-gray-200 p-0.5">
                                        @foreach (['1', '2', '3'] as $opcion)
                                            <button type="button"
                                                @disabled(! $puedeCapturar)
                                                wire:click="capturar('{{ $telar->NoTelarId }}', {{ $actividad->Id }}, '{{ $opcion }}')"
                                                @class([
                                                    'h-6 w-6 rounded text-[11px] font-bold transition',
                                                    'bg-gray-900 text-white' => $valorActual === $opcion,
                                                    'text-gray-500 hover:bg-gray-100' => $valorActual !== $opcion,
                                                    'cursor-not-allowed opacity-40' => ! $puedeCapturar,
                                                ])>{{ $opcion }}</button>
                                        @endforeach
                                    </div>
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap bg-gray-50 px-3 py-2 text-center font-semibold text-gray-700">
                                {{ $promedios[$actividad->Actividad] ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $telares->count() + 2 }}" class="px-4 py-10 text-center text-sm text-gray-500">No hay actividades configuradas en el catálogo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
