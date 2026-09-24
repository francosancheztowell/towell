{{-- Panel /admin · En línea (MON-22). Se refresca solo mientras la pestaña está visible. --}}
<div class="space-y-3" @if ($renombrando === null) wire:poll.visible.{{ $pollSeconds }}s @endif>
    {{-- Resumen: cada tarjeta filtra la tabla --}}
    <div class="grid grid-cols-3 gap-2 sm:gap-3">
        @foreach ([
            ['en_linea', 'En línea', 'text-emerald-600', 'fa-circle'],
            ['inactivo', 'Inactivos', 'text-amber-500', 'fa-circle-half-stroke'],
            ['desconectado', 'Desconectados (24 h)', 'text-slate-400', 'fa-circle-notch'],
        ] as [$clave, $titulo, $color, $icono])
            <button type="button" wire:click="$set('estado', '{{ $estado === $clave ? '' : $clave }}')"
                    aria-pressed="{{ $estado === $clave ? 'true' : 'false' }}"
                    @class([
                        'rounded-xl border bg-white p-3 text-left shadow-sm transition hover:border-blue-300',
                        'border-blue-500 ring-2 ring-blue-200' => $estado === $clave,
                        'border-slate-200' => $estado !== $clave,
                    ])>
                <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <i class="fa-solid {{ $icono }} {{ $color }}"></i> {{ $titulo }}
                </span>
                <span class="mt-1 block text-2xl font-bold text-slate-800">{{ $conteos[$clave] }}</span>
            </button>
        @endforeach
    </div>

    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :seleccion-inmediata="true"
             vacio="No hay dispositivos con ese estado"
             vacio-icono="fa-tablet-screen-button"
             buscar-placeholder="Buscar dispositivo, usuario, IP o página…">

        <x-slot:acciones>
            <x-navbar.button-edit wire:click="abrirRenombrar" icon="fa-tag" text="Renombrar"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona un dispositivo' : 'Renombrar dispositivo' }}" />

            <x-navbar.button-delete icon="fa-right-from-bracket" text="Cerrar sesión"
                                    :disabled="$seleccionado === null"
                                    title="{{ $seleccionado === null ? 'Selecciona un dispositivo' : 'Cerrar la sesión de este dispositivo' }}"
                                    x-on:click="notify.confirm({ title: '¿Cerrar la sesión de este dispositivo?', text: 'Se cierra solo en este equipo, en su siguiente actividad. Las demás sesiones del usuario siguen.', confirmText: 'Cerrar sesión', confirmColor: '#dc2626' }).then(ok => ok && $wire.cerrarSesion())" />
        </x-slot:acciones>

        <x-slot:filtros>
            <select wire:model.live="estado" aria-label="Filtrar por estado"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[13rem] sm:flex-none">
                <option value="">En línea e inactivos</option>
                <option value="en_linea">Solo en línea</option>
                <option value="inactivo">Solo inactivos</option>
                <option value="desconectado">Desconectados</option>
            </select>
        </x-slot:filtros>
    </x-tabla>

    @if ($renombrando !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true" aria-labelledby="titulo-renombrar">
            <div class="flex min-h-full items-center justify-center p-4">
                <form wire:submit="guardarNombre" class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 id="titulo-renombrar" class="text-base font-bold uppercase tracking-wide text-slate-800">Renombrar dispositivo</h2>
                        <button type="button" wire:click="cancelarRenombrar" aria-label="Cerrar"
                                class="rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                    <label class="block p-4">
                        <span class="mb-1 block text-xs font-semibold text-slate-500">Nombre (vacío = sin nombre)</span>
                        <input type="text" wire:model="nombre" maxlength="80" autofocus
                               placeholder="Ej. Tablet telar 12"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                        @error('nombre') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <x-ui.button variant="secondary" wire:click="cancelarRenombrar">Cancelar</x-ui.button>
                        <x-ui.button type="submit">Guardar</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
