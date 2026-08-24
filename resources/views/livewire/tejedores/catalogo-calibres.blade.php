<div>
    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             al-editar="abrirEdicion"
             vacio="No se encontraron calibres"
             vacio-icono="fa-scissors"
             buscar-placeholder="Buscar codigo AX, calibre o nombre…">

        {{-- Acciones: se teletransportan al navbar. --}}
        <x-slot:acciones>
            <x-navbar.button-create wire:click="abrirAlta" module="Catalogo Calibres" title="Nuevo calibre" />

            <x-navbar.button-edit wire:click="abrirEdicion" module="Catalogo Calibres"
                                  :disabled="$seleccionado === null"
                                  title="{{ $seleccionado === null ? 'Selecciona una fila para editar' : 'Editar calibre' }}" />

            {{-- No hay borrado: dar de baja es poner Vigente en 0. Borrar el renglon
                 dejaria sin nombre el calibre de las ordenes que ya lo usan. --}}
            @if ($puedeModificar)
                @php($daDeBaja = $seleccion?->Vigente)
                <x-navbar.button-create wire:click="confirmarBaja"
                                        :check-permission="false"
                                        :disabled="$seleccion === null"
                                        :icon="$seleccion === null ? 'fa-toggle-on' : ($daDeBaja ? 'fa-ban' : 'fa-rotate-left')"
                                        :text="$seleccion === null ? 'Vigencia' : ($daDeBaja ? 'Dar de baja' : 'Reactivar')"
                                        :bg="$seleccion === null ? 'bg-slate-400' : ($daDeBaja ? 'bg-amber-500' : 'bg-emerald-600')"
                                        title="{{ $seleccion === null ? 'Selecciona una fila para cambiar su vigencia' : ($daDeBaja ? 'Sacarlo de los desplegables de captura' : 'Volver a ofrecerlo en captura') }}" />
            @endif
        </x-slot:acciones>

        {{-- Filtros: junto al buscador, dentro de la barra de la tabla. --}}
        <x-slot:filtros>
            <select wire:model.live="vigenciaFiltro" aria-label="Filtrar por vigencia"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:max-w-[11rem] sm:flex-none">
                <option value="">Vigencia: todas</option>
                <option value="vigentes">Solo vigentes</option>
                <option value="baja">Solo dados de baja</option>
            </select>

            @if ($vigenciaFiltro !== '' || $buscar !== '')
                <button type="button" wire:click="limpiarFiltros" title="Limpiar filtros"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-bold text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600">
                    <i class="fa-solid fa-eraser"></i>
                    <span class="hidden sm:inline">Limpiar</span>
                </button>
            @endif
        </x-slot:filtros>
    </x-tabla>

    {{-- Alta / edicion --}}
    @if ($editando !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <form wire:submit="guardar"
                      class="w-full max-w-2xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-bold uppercase tracking-wide text-slate-800">
                            {{ $editando === '' ? 'Nuevo calibre' : 'Editar calibre' }}
                        </h2>
                        <button type="button" wire:click="cerrar" aria-label="Cerrar"
                                class="rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                        <label>
                            <span class="mb-1 block text-xs font-semibold text-slate-500">
                                Codigo AX <span class="text-red-500">*</span>
                            </span>
                            <input type="text" wire:model="form.Codigo" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   @error('form.Codigo') aria-invalid="true" @enderror>
                            <span class="mt-1 block text-xs text-slate-500">
                                El ItemId tal como esta en AX: <code>600/1T</code>. Con el se piden fibra y color.
                            </span>
                            @error('form.Codigo')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-semibold text-slate-500">
                                Nombre <span class="text-red-500">*</span>
                            </span>
                            <input type="text" wire:model="form.Nombre" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   @error('form.Nombre') aria-invalid="true" @enderror>
                            <span class="mt-1 block text-xs text-slate-500">Lo que ve el operador en el desplegable.</span>
                            @error('form.Nombre')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-semibold text-slate-500">
                                Calibre <span class="text-red-500">*</span>
                            </span>
                            <input type="text" wire:model="form.CodigoInterno" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   @error('form.CodigoInterno') aria-invalid="true" @enderror>
                            <span class="mt-1 block text-xs text-slate-500">
                                Lo que se escribe en la orden: <code>600/1T</code> se guarda como <code>600.1</code>.
                            </span>
                            @error('form.CodigoInterno')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-semibold text-slate-500">
                                Hilo (divisor) <span class="text-red-500">*</span>
                            </span>
                            <input type="number" step="0.01" min="0.01" inputmode="decimal"
                                   wire:model="form.Divisor" autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   @error('form.Divisor') aria-invalid="true" @enderror>
                            <span class="mt-1 block text-xs text-slate-500">
                                El denominador con el que L.Mat calcula el peso. Dos calibres iguales con
                                divisor distinto hacen que la captura ofrezca elegir el hilo.
                            </span>
                            @error('form.Divisor')
                                <span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="sm:col-span-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <input type="checkbox" wire:model="form.Vigente" value="1"
                                   class="h-5 w-5 rounded border-slate-400 text-blue-600 focus:ring-2 focus:ring-blue-300">
                            <span>
                                <span class="block text-sm font-semibold text-slate-700">Vigente</span>
                                <span class="block text-xs text-slate-500">
                                    Solo los vigentes se ofrecen en la captura de desarrolladores.
                                </span>
                            </span>
                            @error('form.Vigente')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
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

    {{-- Baja / reactivacion --}}
    @if ($confirmandoBaja && $seleccion)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/40" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 text-center shadow-xl">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full {{ $seleccion->Vigente ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}">
                        <i class="fa-solid {{ $seleccion->Vigente ? 'fa-ban' : 'fa-rotate-left' }} text-lg"></i>
                    </span>
                    <p class="mt-3 font-bold text-slate-800">
                        {{ $seleccion->Vigente ? '¿Dar de baja' : '¿Reactivar' }} {{ $seleccion->Nombre }} ({{ $seleccion->Codigo }})?
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($seleccion->Vigente)
                            Deja de ofrecerse en la captura. Las ordenes que ya lo traian quedan marcadas
                            en rojo y no se pueden guardar hasta reelegir el hilo. Se puede reactivar.
                        @else
                            Vuelve a aparecer en el desplegable de la captura.
                        @endif
                    </p>
                    <div class="mt-4 flex justify-center gap-2">
                        <button type="button" wire:click="cerrar"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="button" wire:click="alternarVigencia"
                                class="rounded-lg px-4 py-2 text-sm font-bold text-white transition {{ $seleccion->Vigente ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                            {{ $seleccion->Vigente ? 'Dar de baja' : 'Reactivar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
