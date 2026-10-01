<div class="flex min-h-0 flex-1 flex-col gap-2">
    {{-- Las dos tablas en una pantalla: la pestaña elige cuál se ve y se edita. --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:radio.group wire:model.live="tabla" variant="segmented" aria-label="Tabla de cuotas" class="w-full sm:w-auto">
            {{-- Activa en azul, como el encabezado de la tabla (Flux la marca con data-checked). --}}
            <flux:radio value="std" label="Cuotas estándar (STD)" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
            <flux:radio value="real" label="Cuotas reales" class="min-h-touch data-checked:bg-blue-600! data-checked:text-white!" />
        </flux:radio.group>

        <span wire:loading.delay wire:target="tabla,anio,costeo" class="text-xs font-semibold text-zinc-500">Cargando…</span>

        {{-- Costeo: absorbente (todas las columnas) o directo (sin los costos fijos). Las pills usan el
             acento de Flux (morado); aquí se pasa a azul, como la pestaña activa. --}}
        <flux:radio.group wire:model.live="costeo" variant="pills" aria-label="Método de costeo" class="ms-auto gap-2! [--color-accent:var(--color-blue-600)]">
            <flux:radio value="directo" class="min-h-touch px-3!" title="Sin costos fijos">Directo</flux:radio>
            <flux:radio value="absorbente" class="min-h-touch px-3!" title="Todos los costos">Absorbente</flux:radio>
        </flux:radio.group>

        {{-- Años de la tabla (pills de Flux, con cuántas cuotas tiene cada uno): filtran al elegirlos. --}}
        @if ($anios !== [])
            <flux:separator vertical class="my-2" />
            <flux:radio.group wire:model.live="anio" variant="pills" aria-label="Filtrar por año" class="gap-2! [--color-accent:var(--color-blue-600)]">
                <flux:radio value="" class="min-h-touch px-3!">Todos</flux:radio>
                @foreach ($anios as $valor => $total)
                    <flux:radio value="{{ $valor }}" class="min-h-touch px-3! tabular-nums">
                        {{ $valor }}
                        <flux:badge size="sm" :color="$anio === (string) $valor ? 'zinc' : 'blue'"
                                    :variant="$anio === (string) $valor ? 'solid' : null" class="tabular-nums">{{ $total }}</flux:badge>
                    </flux:radio>
                @endforeach
            </flux:radio.group>
        @endif
    </div>

    <x-tabla :columnas="$this->columnas()"
             :filas="$filas"
             :seleccionado="$seleccionado"
             :orden-por="$ordenPor"
             :orden-dir="$ordenDir"
             :al-editar="$puede['modificar'] ? 'abrirEdicion' : null"
             objetivos-extra="tabla"
             :filtro-arriba="true"
             :seleccion-inmediata="true"
             :mostrar-filtros="false"
             :mostrar-pie="false"
             fijar-columnas="costos-cuotas"
             vacio="No hay cuotas registradas">

        {{-- Acciones: se teletransportan al navbar. --}}
        <x-slot:acciones>
            @if ($puede['crear'])
                <flux:button variant="primary" color="blue" icon="plus" class="min-h-touch" wire:click="abrirAlta">Crear</flux:button>
            @endif
            @if ($puede['modificar'])
                <flux:button icon="pencil-square" class="min-h-touch" wire:click="abrirEdicion" :disabled="$seleccionado === null">Editar</flux:button>
            @endif
            @if ($puede['crear'])
                <flux:button icon="document-duplicate" class="min-h-touch" wire:click="duplicar" :disabled="$seleccionado === null">Duplicar</flux:button>
            @endif
            @if ($puede['eliminar'])
                <flux:button variant="danger" icon="trash" class="min-h-touch" :disabled="$seleccionado === null"
                             x-on:click="notify.confirm({ title: '¿Eliminar la cuota?', text: 'Se borra la fila seleccionada. No se puede deshacer.', confirmText: 'Sí, eliminar', confirmColor: '#dc2626' }).then(ok => ok && $wire.eliminar())">
                    Eliminar
                </flux:button>
            @endif
        </x-slot:acciones>
    </x-tabla>

    {{-- Alta / edición: <dialog> con el aspecto de los demás diálogos (.ui-dialogo). Se abre al
         renderizarse; wire:ignore.self evita que un refresco le quite `open`. Esc, clic fuera o
         Cancelar lo cierran y avisan a Livewire. --}}
    @if ($editando !== null)
        <dialog wire:key="cuota-{{ $tabla }}-{{ $editando }}"
                wire:ignore.self
                x-data
                x-init="$el.showModal()"
                x-on:close="$wire.cerrar()"
                x-on:click="if ($event.target === $el) $el.close()"
                aria-labelledby="cuota-titulo"
                class="ui-dialogo ui-dialogo--2xl ui-dialogo--formulario">
            <form wire:submit="guardar" class="ui-dialogo__cuerpo" novalidate>
                <h2 id="cuota-titulo" class="ui-dialogo__titulo">
                    {{ $duplicando ? 'Duplicar cuota' : ($editando === '' ? 'Nueva cuota' : 'Editar cuota') }}
                    <span class="font-normal text-zinc-500">· {{ $tabla === 'real' ? 'Real' : 'Estándar (STD)' }}</span>
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach (['Depto' => 'Depto', 'Año' => 'Año', 'Mes' => 'Mes'] as $campo => $etiqueta)
                        <label class="block">
                            <span class="mb-1 block">{{ $etiqueta }} <span class="text-red-500">*</span></span>
                            @if ($campo === 'Mes')
                                <select wire:model="form.Mes" @error('form.Mes') aria-invalid="true" class="border-red-500!" @enderror>
                                    @foreach (\App\Models\Costos\CosCuota::MESES as $numero => $nombre)
                                        <option value="{{ $numero }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{-- data-solo (componentes/entrada-filtrada.ts): el campo no acepta otros caracteres. --}}
                                <input type="text" wire:model="form.{{ $campo }}" autocomplete="off"
                                       @if ($campo === 'Depto') data-solo="texto" maxlength="50" autofocus placeholder="Ej: Urdido" @endif
                                       @if ($campo === 'Año') data-solo="entero" maxlength="4" inputmode="numeric" placeholder="{{ now()->year }}" @endif
                                       @error("form.$campo") aria-invalid="true" class="border-red-500!" @enderror>
                            @endif
                            @error("form.$campo")
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                    @foreach ($campos as $campo)
                        <label class="block">
                            <span class="mb-1 block">{{ \App\Models\Costos\CosCuota::ETIQUETAS[$campo] }}</span>
                            <input type="text" inputmode="decimal" wire:model="form.{{ $campo }}" autocomplete="off"
                                   data-solo="decimal" data-decimales="4" data-enteros="14"
                                   class="text-right tabular-nums @error("form.$campo") border-red-500! @enderror"
                                   @error("form.$campo") aria-invalid="true" @enderror>
                            @error("form.$campo")
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>

                {{-- Depto + Año + Mes ya existen: no se guarda otra línea; se ofrece reemplazar (editar) esa. --}}
                @if ($conflicto !== null)
                    <flux:callout variant="warning" icon="exclamation-triangle" role="alert"
                                  heading="Este mes y año ya existe">
                        <flux:callout.text>
                            Ya hay una cuota de <strong>{{ $form['Depto'] ?? '' }}</strong> para
                            <strong>{{ mb_strtolower(\App\Models\Costos\CosCuota::MESES[(int) ($form['Mes'] ?? 0)] ?? '') }} de {{ $form['Año'] ?? '' }}</strong>.
                            Reemplazar la sobrescribe con estos valores.
                            @if ($editando !== '')
                                La cuota que estabas editando se quita para que no queden dos.
                            @endif
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="ui-dialogo__botones">
                    <button type="button" class="ui-dialogo__boton ui-dialogo__boton--secundario"
                            x-on:click="$el.closest('dialog').close()">Cancelar</button>
                    @if ($conflicto !== null)
                        <button type="button" wire:click="reemplazar" class="ui-dialogo__boton ui-dialogo__boton--peligro"
                                wire:loading.attr="disabled" wire:target="reemplazar">Reemplazar</button>
                    @else
                        <button type="submit" class="ui-dialogo__boton ui-dialogo__boton--primario"
                                wire:loading.attr="disabled" wire:target="guardar">Guardar</button>
                    @endif
                </div>
            </form>
        </dialog>
    @endif
</div>
