<div class="flex min-h-0 flex-1 flex-col">
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

    {{-- Alta / edicion. <dialog> nativo como los demas catalogos (Esc, foco y fondo los da el
         navegador); wire:ignore.self evita que un refresco le quite `open`. --}}
    @if ($editando !== null)
        <dialog wire:key="calibre-{{ $editando }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="calibre-titulo" class="ui-dialogo ui-dialogo--lg ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <flux:heading id="calibre-titulo" size="xl">{{ $editando === '' ? 'Nuevo calibre' : 'Editar calibre' }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.Codigo" label="Codigo AX" maxlength="20" required autofocus autocomplete="off"
                                class:input="uppercase"
                                description:trailing="El ItemId tal como esta en AX: 600/1T. Con el se piden fibra y color." />
                    <flux:input wire:model="form.Nombre" label="Nombre" maxlength="60" required autocomplete="off"
                                description:trailing="Lo que ve el operador en el desplegable." />
                    <flux:input wire:model="form.CodigoInterno" label="Calibre" maxlength="20" required autocomplete="off"
                                description:trailing="Lo que se escribe en la orden: 600/1T se guarda como 600.1." />
                    <flux:input type="number" step="0.01" min="0.01" inputmode="decimal" wire:model="form.Divisor"
                                label="Hilo (divisor)" required autocomplete="off"
                                description:trailing="El denominador con el que L.Mat calcula el peso. Dos calibres iguales con divisor distinto hacen que la captura ofrezca elegir el hilo." />

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

                <x-dialogo-botones />
            </form>
        </dialog>
    @endif

    {{-- Baja / reactivacion: mismo aspecto que notify.confirm. --}}
    @if ($confirmandoBaja && $seleccion)
        <dialog wire:key="calibre-baja-{{ $seleccion->getKey() }}" wire:ignore.self x-data x-init="$el.showModal()"
                x-on:close="$wire.cerrar()" x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="calibre-baja-titulo" class="ui-dialogo">
            <div class="ui-dialogo__cuerpo">
                <span class="ui-dialogo__icono {{ $seleccion->Vigente ? 'ui-dialogo__icono--warning' : 'ui-dialogo__icono--success' }}">
                    <i class="fa-solid {{ $seleccion->Vigente ? 'fa-ban' : 'fa-rotate-left' }} text-3xl" aria-hidden="true"></i>
                </span>
                <div class="ui-dialogo__textos">
                    <p id="calibre-baja-titulo" class="ui-dialogo__titulo">
                        {{ $seleccion->Vigente ? '¿Dar de baja' : '¿Reactivar' }} {{ $seleccion->Nombre }} ({{ $seleccion->Codigo }})?
                    </p>
                    <p class="ui-dialogo__texto">{{ $seleccion->Vigente
                        ? 'Deja de ofrecerse en la captura. Las ordenes que ya lo traian quedan marcadas en rojo y no se pueden guardar hasta reelegir el hilo. Se puede reactivar.'
                        : 'Vuelve a aparecer en el desplegable de la captura.' }}</p>
                </div>
                <div class="ui-dialogo__botones">
                    <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
                            x-on:click="$el.closest('dialog').close()">Cancelar</button>
                    <button type="button" wire:click="alternarVigencia" wire:loading.attr="data-ocupado" wire:target="alternarVigencia"
                            class="ui-dialogo__boton {{ $seleccion->Vigente ? 'ui-dialogo__boton--peligro' : 'ui-dialogo__boton--primario' }}">
                        {{ $seleccion->Vigente ? 'Dar de baja' : 'Reactivar' }}
                    </button>
                </div>
            </div>
        </dialog>
    @endif
</div>
