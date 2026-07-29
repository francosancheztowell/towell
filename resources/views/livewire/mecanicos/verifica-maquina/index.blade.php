<div class="space-y-4 p-4" wire:key="verifica-maquina-index">
    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900 md:text-2xl">Estado de máquina</h1>
                <p class="mt-1 text-sm text-gray-600">Verificaciones de telares capturadas por mecánicos.</p>
            </div>

            @if ($puedeCrear)
                <button type="button" wire:click="abrirModal"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black sm:w-auto">
                    <i class="fas fa-plus"></i>
                    Nueva verificación
                </button>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500 lg:hidden">
            <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para consultar todas las columnas.
        </div>
        <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabla de verificaciones de máquina; desplázate horizontalmente para ver todas las columnas">
            <table class="min-w-[980px] divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3">Folio</th>
                        <th class="whitespace-nowrap px-4 py-3">Status</th>
                        <th class="whitespace-nowrap px-4 py-3">Fecha y Hr</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center">Turno</th>
                        <th class="whitespace-nowrap px-4 py-3">Clave</th>
                        <th class="min-w-44 px-4 py-3">Nombre</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center">Hr Inicio</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center">Hr Fin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($verificaciones as $verificacion)
                        <tr class="cursor-pointer transition hover:bg-gray-50"
                            onclick="window.location.href = '{{ route('mecanicos.estado-maquina.show', $verificacion->Folio) }}'">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                                <a href="{{ route('mecanicos.estado-maquina.show', $verificacion->Folio) }}" wire:navigate class="hover:underline">
                                    {{ $verificacion->Folio }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-green-100 text-green-800' => $verificacion->Estatus === 'Terminado',
                                    'bg-blue-100 text-blue-800' => $verificacion->Estatus !== 'Terminado',
                                ])>{{ $verificacion->Estatus ?: 'Activo' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                {{ optional($verificacion->Fecha)->format('d/m/Y') ?? '—' }}
                                @if ($verificacion->HoraInicio)
                                    {{ \Illuminate\Support\Str::of((string) $verificacion->HoraInicio)->substr(0, 5) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-700">{{ $verificacion->TurnoRecibe ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $verificacion->CveOperador ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $verificacion->NomOperador ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">
                                {{ $verificacion->HoraInicio ? \Illuminate\Support\Str::of((string) $verificacion->HoraInicio)->substr(0, 5) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">
                                {{ $verificacion->HoraFin ? \Illuminate\Support\Str::of((string) $verificacion->HoraFin)->substr(0, 5) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">No hay verificaciones con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($verificaciones->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $verificaciones->links() }}
            </div>
        @endif
    </section>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-verifica-maquina" wire:click.self="cerrarModal">
            <div class="w-full max-w-md rounded-lg bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
                    <h2 id="titulo-modal-verifica-maquina" class="text-lg font-bold text-gray-900">Nueva verificación</h2>
                    <button type="button" wire:click="cerrarModal" class="rounded p-1 text-xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Cerrar">&times;</button>
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
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="cerrarModal" class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="crear"
                            class="w-full rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                            <span wire:loading.remove wire:target="crear">Crear verificación</span>
                            <span wire:loading wire:target="crear">Creando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
