<div class="flex min-h-0 flex-1 flex-col">
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirEdicion"
             vacio="No se encontraron operadores"
             vacio-icono="fa-inbox"
             buscar-placeholder="Buscar clave, nombre, departamento o teléfono…">

        {{-- Acciones: se teletransportan al navbar. Permisos por idrol 53 (Mantenimiento). --}}
        <x-slot:acciones>
            <x-navbar.button-create wire:click="abrirAlta" :module-id="53" title="Nuevo operador" />

            <x-navbar.button-edit wire:click="abrirEdicion" :module-id="53"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona una fila para editar' : 'Editar operador' }}" />

            <x-navbar.button-delete wire:click="confirmarBorrado" :module-id="53"
                                    :disabled="$seleccionado === null"
                                    title="{{ $seleccionado === null ? 'Selecciona una fila para eliminar' : 'Eliminar operador' }}" />
        </x-slot:acciones>

        <x-slot:filtros>
            <select wire:model.live="turnoFiltro" aria-label="Filtrar por turno"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[11rem] sm:flex-none">
                <option value="">Turno: todos</option>
                @foreach (\App\Livewire\Mantenimiento\CatalogoOperadores::TURNOS as $turno)
                    <option value="{{ $turno }}">Turno {{ $turno }}</option>
                @endforeach
            </select>

            <select wire:model.live="deptoFiltro" aria-label="Filtrar por departamento"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[11rem] sm:flex-none">
                <option value="">Depto: todos</option>
                @foreach ($departamentos as $departamento)
                    <option value="{{ $departamento }}">{{ $departamento }}</option>
                @endforeach
            </select>

            @if ($turnoFiltro !== '' || $deptoFiltro !== '' || $buscar !== '')
                <button type="button" wire:click="limpiarFiltros" aria-label="Limpiar filtros" title="Limpiar filtros"
                        class="inline-flex min-h-touch items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-bold text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600">
                    <i class="fa-solid fa-eraser" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Limpiar</span>
                </button>
            @endif
        </x-slot:filtros>
    </x-tabla>

    {{-- Alta / edición --}}
    @if ($editando !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true" aria-labelledby="titulo-operador">
            <div class="flex min-h-full items-center justify-center p-4">
                <form wire:submit="guardar"
                      class="w-full max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 id="titulo-operador" class="text-base font-bold uppercase tracking-wide text-slate-800">
                            {{ $editando === '' ? 'Nuevo operador' : 'Editar operador' }}
                        </h2>
                        <button type="button" wire:click="cerrar" aria-label="Cerrar"
                                class="inline-flex size-touch items-center justify-center rounded text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">
                            <i class="fa-solid fa-times" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                        @foreach ([
                            ['CveEmpl', 'Clave de empleado', true, 'text'],
                            ['NomEmpl', 'Nombre', true, 'text'],
                            ['Depto', 'Departamento', true, 'text'],
                            ['Telefono', 'Teléfono', false, 'tel'],
                        ] as [$campo, $etiqueta, $requerido, $tipo])
                            <label>
                                <span class="mb-1 block text-xs font-semibold text-slate-500">
                                    {{ $etiqueta }} @if ($requerido)<span class="text-red-500">*</span>@endif
                                </span>
                                <input type="{{ $tipo }}" wire:model="form.{{ $campo }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       @error("form.{$campo}") aria-invalid="true" @enderror>
                                @error("form.{$campo}")
                                    <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach

                        <label>
                            <span class="mb-1 block text-xs font-semibold text-slate-500">Turno <span class="text-red-500">*</span></span>
                            <select wire:model="form.Turno"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    @error('form.Turno') aria-invalid="true" @enderror>
                                <option value="">Seleccione un turno</option>
                                @foreach (\App\Livewire\Mantenimiento\CatalogoOperadores::TURNOS as $turno)
                                    <option value="{{ $turno }}">Turno {{ $turno }}</option>
                                @endforeach
                            </select>
                            @error('form.Turno')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <button type="button" wire:click="cerrar"
                                class="min-h-touch rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="inline-flex min-h-touch items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60">
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="guardar" aria-hidden="true"></i>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Confirmación de borrado --}}
    @if ($confirmandoBorrado)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true" aria-labelledby="titulo-borrar-operador">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-5 text-center shadow-xl">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <i class="fa-solid fa-trash text-lg" aria-hidden="true"></i>
                    </span>
                    <p id="titulo-borrar-operador" class="mt-3 font-bold text-slate-800">¿Eliminar este operador?</p>
                    <p class="mt-1 text-sm text-slate-500">La acción no se puede deshacer.</p>
                    <div class="mt-4 flex justify-center gap-2">
                        <button type="button" wire:click="cerrar"
                                class="min-h-touch rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="button" wire:click="eliminar"
                                class="min-h-touch rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
