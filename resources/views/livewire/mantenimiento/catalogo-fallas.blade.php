<div>
    {{-- Filtros --}}
    <div class="mb-3 flex flex-wrap items-end gap-2">
        <label class="min-w-0 flex-1 sm:max-w-[220px]">
            <span class="mb-1 block text-xs font-semibold text-slate-500">Tipo de falla</span>
            <select wire:model.live="tipoFallaFiltro"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="">Todos</option>
                @foreach ($tiposFalla as $tipo)
                    <option value="{{ $tipo }}">{{ $tipo }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-0 flex-1 sm:max-w-[220px]">
            <span class="mb-1 block text-xs font-semibold text-slate-500">Departamento</span>
            <select wire:model.live="departamentoFiltro"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="">Todos</option>
                @foreach ($departamentos as $departamento)
                    <option value="{{ $departamento }}">{{ $departamento }}</option>
                @endforeach
            </select>
        </label>

        @if ($tipoFallaFiltro !== '' || $departamentoFiltro !== '' || $buscar !== '')
            <button type="button" wire:click="limpiarFiltros"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                <i class="fa-solid fa-eraser mr-1"></i>Limpiar
            </button>
        @endif
    </div>

    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirEdicion"
             vacio="No se encontraron fallas"
             vacio-icono="fa-triangle-exclamation"
             buscar-placeholder="Buscar falla, descripción o abreviado…">
        <x-slot:acciones>
            @if ($puedeCrear)
                <button type="button" wire:click="abrirAlta"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-600">
                    <i class="fa-solid fa-plus"></i>Crear
                </button>
            @endif
            @if ($puedeModificar)
                <button type="button" wire:click="abrirEdicion" @disabled($seleccionado === null)
                        title="{{ $seleccionado === null ? 'Selecciona una fila' : 'Editar' }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fa-solid fa-pen-to-square"></i>Editar
                </button>
            @endif
            @if ($puedeEliminar)
                <button type="button" wire:click="confirmarBorrado" @disabled($seleccionado === null)
                        title="{{ $seleccionado === null ? 'Selecciona una fila' : 'Eliminar' }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <i class="fa-solid fa-trash"></i>Eliminar
                </button>
            @endif
        </x-slot:acciones>
    </x-tabla>

    {{-- Alta / edición --}}
    @if ($editando !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <form wire:submit="guardar"
                      class="w-full max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-bold uppercase tracking-wide text-slate-800">
                            {{ $editando === '' ? 'Nueva falla' : 'Editar falla' }}
                        </h2>
                        <button type="button" wire:click="cerrar" aria-label="Cerrar"
                                class="rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                        @foreach ([
                            ['TipoFallaId', 'Tipo de falla', true],
                            ['Departamento', 'Departamento', true],
                            ['Falla', 'Falla', true],
                            ['Abreviado', 'Abreviado', false],
                            ['Seccion', 'Sección', false],
                        ] as [$campo, $etiqueta, $requerido])
                            <label>
                                <span class="mb-1 block text-xs font-semibold text-slate-500">
                                    {{ $etiqueta }} @if ($requerido)<span class="text-red-500">*</span>@endif
                                </span>
                                <input type="text" wire:model="form.{{ $campo }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       @error("form.{$campo}") aria-invalid="true" @enderror>
                                @error("form.{$campo}")
                                    <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach

                        <label class="sm:col-span-2">
                            <span class="mb-1 block text-xs font-semibold text-slate-500">Descripción</span>
                            <textarea wire:model="form.Descripcion" rows="2"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"></textarea>
                            @error('form.Descripcion')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                        <button type="button" wire:click="cerrar"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60">
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="guardar"></i>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Confirmación de borrado --}}
    @if ($confirmandoBorrado)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-5 text-center shadow-xl">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <i class="fa-solid fa-trash text-lg"></i>
                    </span>
                    <p class="mt-3 font-bold text-slate-800">¿Eliminar esta falla?</p>
                    <p class="mt-1 text-sm text-slate-500">La acción no se puede deshacer.</p>
                    <div class="mt-4 flex justify-center gap-2">
                        <button type="button" wire:click="cerrar"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="button" wire:click="eliminar"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
