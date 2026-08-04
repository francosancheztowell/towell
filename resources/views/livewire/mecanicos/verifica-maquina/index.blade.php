<div
    class="flex min-h-0 flex-1 flex-col overflow-hidden"
    wire:key="verifica-maquina-index"
    x-data="{
        open: false,
        filtrosOpen: false,
        abrir() { this.open = true },
        cerrar() { this.open = false },
        abrirFiltros() { this.filtrosOpen = true },
        cerrarFiltros() { this.filtrosOpen = false },
    }"
    @verifica-maquina-abrir-modal.window="abrir()"
    @verifica-maquina-abrir-filtros.window="abrirFiltros()"
>
    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">

        <div
            class="min-h-0 flex-1 overflow-auto overscroll-contain"
            tabindex="0"
            aria-label="Tabla de verificaciones de máquina"
        >
            <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 shadow-sm">
                    <tr>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Folio</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Status</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Fecha y Hr</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-center">Turno</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4">Clave</th>
                        <th class="min-w-52 bg-gray-50 px-5 py-4">Nombre</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-center">Hr Inicio</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-center">Hr Fin</th>
                        <th class="whitespace-nowrap bg-gray-50 px-5 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($verificaciones as $verificacion)
                        <tr class="transition hover:bg-gray-50">
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="inline-flex items-center rounded-md bg-gray-900 px-2.5 py-1 text-sm font-bold text-white">
                                    {{ $verificacion->Folio }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                @php $estatusFila = $verificacion->Estatus ?: 'Activo'; @endphp
                                <span @class([
                                    'inline-flex rounded-full px-3 py-1.5 text-xs font-bold',
                                    'bg-blue-100 text-blue-800' => $estatusFila === 'Activo',
                                    'bg-amber-100 text-amber-800' => $estatusFila === 'Terminado',
                                    'bg-green-100 text-green-800' => $estatusFila === 'Autorizado',
                                    'bg-gray-100 text-gray-700' => ! in_array($estatusFila, ['Activo', 'Terminado', 'Autorizado'], true),
                                ])>{{ $estatusFila === 'Terminado' ? 'Finalizado' : $estatusFila }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                <div class="font-semibold text-gray-900">{{ optional($verificacion->Fecha)->format('d/m/Y') ?? '—' }}</div>
                                @if ($verificacion->HoraInicio)
                                    <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::of((string) $verificacion->HoraInicio)->substr(0, 5) }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">
                                    {{ $verificacion->TurnoRecibe ?: '—' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-gray-700">{{ $verificacion->CveOperador ?: '—' }}</td>
                            <td class="px-5 py-4 font-semibold text-gray-900">{{ $verificacion->NomOperador ?: '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-center text-gray-700">
                                {{ $verificacion->HoraInicio ? \Illuminate\Support\Str::of((string) $verificacion->HoraInicio)->substr(0, 5) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-center text-gray-700">
                                {{ $verificacion->HoraFin ? \Illuminate\Support\Str::of((string) $verificacion->HoraFin)->substr(0, 5) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('mecanicos.estado-maquina.show', ['folio' => $verificacion->Folio, 'modo' => 'ver']) }}" wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100"
                                        title="Ver (solo lectura)">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    @if ($puedeEditar)
                                        <a href="{{ route('mecanicos.estado-maquina.show', $verificacion->Folio) }}" wire:navigate
                                            class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-black"
                                            title="Editar / capturar">
                                            <i class="fas fa-pen"></i> Editar
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-sm text-gray-500">No hay verificaciones con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($verificaciones->hasPages())
            <div class="shrink-0 border-t border-gray-100 px-4 py-3">
                {{ $verificaciones->links() }}
            </div>
        @endif
    </section>

    {{-- Panel de filtros por estatus --}}
    <div
        x-show="filtrosOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-modal-filtros-verifica"
        @keydown.escape.window="if (filtrosOpen) cerrarFiltros()"
        @click.self="cerrarFiltros()"
    >
        <div class="w-full max-w-md rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl" @click.stop>
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-5">
                <h2 id="titulo-modal-filtros-verifica" class="text-lg font-bold text-gray-900">Filtrar por estatus</h2>
                <button type="button" @click="cerrarFiltros()" class="flex h-10 w-10 items-center justify-center rounded-full text-2xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-3 p-4 sm:p-5">
                <button
                    type="button"
                    wire:click="filtrarEstatus('')"
                    @click="cerrarFiltros()"
                    @class([
                        'min-h-14 rounded-xl px-3 py-3 text-base font-bold transition active:scale-[0.98]',
                        'bg-gray-900 text-white shadow' => $estatus === '',
                        'border border-gray-300 bg-white text-gray-700' => $estatus !== '',
                    ])
                >
                    Todos
                </button>
                <button
                    type="button"
                    wire:click="filtrarEstatus('Activo')"
                    @click="cerrarFiltros()"
                    @class([
                        'min-h-14 rounded-xl px-3 py-3 text-base font-bold transition active:scale-[0.98]',
                        'bg-blue-600 text-white shadow' => $estatus === 'Activo',
                        'border border-blue-200 bg-blue-50 text-blue-800' => $estatus !== 'Activo',
                    ])
                >
                    Activo
                </button>
                <button
                    type="button"
                    wire:click="filtrarEstatus('Terminado')"
                    @click="cerrarFiltros()"
                    @class([
                        'min-h-14 rounded-xl px-3 py-3 text-base font-bold transition active:scale-[0.98]',
                        'bg-amber-500 text-white shadow' => $estatus === 'Terminado',
                        'border border-amber-200 bg-amber-50 text-amber-800' => $estatus !== 'Terminado',
                    ])
                >
                    Finalizado
                </button>
                <button
                    type="button"
                    wire:click="filtrarEstatus('Autorizado')"
                    @click="cerrarFiltros()"
                    @class([
                        'min-h-14 rounded-xl px-3 py-3 text-base font-bold transition active:scale-[0.98]',
                        'bg-green-600 text-white shadow' => $estatus === 'Autorizado',
                        'border border-green-200 bg-green-50 text-green-800' => $estatus !== 'Autorizado',
                    ])
                >
                    Autorizado
                </button>
            </div>
        </div>
    </div>

    {{-- Modal nueva verificación --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 sm:p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-modal-verifica-maquina"
        @keydown.escape.window="if (open) cerrar()"
        @click.self="cerrar()"
    >
        <div class="w-full max-w-md rounded-lg bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
                <h2 id="titulo-modal-verifica-maquina" class="text-lg font-bold text-gray-900">Nueva verificación</h2>
                <button type="button" @click="cerrar()" class="rounded p-1 text-xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
            </div>

            <form wire:submit="crear" class="p-4 sm:p-5">
                <dl class="mb-4 grid grid-cols-2 gap-3 rounded-md border border-gray-100 bg-gray-50 p-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ now('America/Mexico_City')->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Hora</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ now('America/Mexico_City')->format('H:i') }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Mecánico</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $operadorClave }} · {{ $operadorNombre }}</dd>
                    </div>
                </dl>

                <label for="modal-turno" class="mb-1 block text-xs font-medium text-gray-700">Turno <span class="text-red-600">*</span></label>
                <select id="modal-turno" wire:model="turno"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                    <option value="">Seleccione</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                </select>
                @error('turno')
                    <p class="mt-1 text-xs text-red-600" x-init="open = true">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="cerrar()" class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="crear"
                        class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                        <span wire:loading.remove wire:target="crear">Crear verificación</span>
                        <span wire:loading wire:target="crear">Creando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
