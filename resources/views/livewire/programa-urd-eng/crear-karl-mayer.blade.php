@php
    $inputBase = 'w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
    $inputTabla = 'w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
    $error = 'border-red-500 focus:ring-red-500';
@endphp

{{--
    Karl Mayer: los campos son de Livewire; la tabla de materiales del ERP y el
    buscador de tamanos los lleva resources/js/programa-urd-eng/karl-mayer.
    Los data-km-* son los enganches del TS: no quitarlos al retocar clases.
--}}
<div data-km-root wire:submit="confirmar">
    @teleport('#karl-mayer-navbar-acciones')
        <x-navbar.button-create
            wire:click="confirmar"
            wire:loading.attr="disabled"
            wire:target="confirmar,guardar"
            id="btnCrearOrden"
            type="button"
            title="Crear Orden"
            icon="fa-save"
            iconColor="text-white"
            hoverBg="hover:bg-blue-600"
            bg="bg-blue-500"
            text="Crear Orden"
            :disabled="! $puedeCrear"
        />
    @endteleport

    @if ($dataError)
        <div class="mb-3 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ $dataError }}
        </div>
    @endif

    <div class="w-full">
        <div class="bg-white p-3 mb-4 shadow-sm border border-gray-200">
            {{-- No. Telar, Barras, Fibra, Tamaño, Cuenta, Calibre, Metros --}}
            <div class="grid gap-1.5 mb-1.5" style="grid-template-columns: 0.7fr 1.2fr 1fr 0.9fr 0.7fr 0.7fr 0.7fr;">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-no-telar">No. Telar</label>
                    <select id="km-no-telar" wire:model="noTelar" class="{{ $inputBase }} @error('noTelar') {{ $error }} @enderror">
                        <option value="">Seleccionar...</option>
                        @foreach ($telares as $telar)
                            <option value="{{ $telar }}">{{ $telar }}</option>
                        @endforeach
                    </select>
                    @error('noTelar') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-barras">Barras</label>
                    <select id="km-barras" wire:model="barras" class="{{ $inputBase }} @error('barras') {{ $error }} @enderror">
                        <option value="">Seleccionar...</option>
                        @foreach ($opcionesBarras as $barra)
                            <option value="{{ $barra }}">{{ $barra }}</option>
                        @endforeach
                    </select>
                    @error('barras') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-fibra">Fibra</label>
                    {{-- Las opciones las carga el TS desde el catalogo de JULIO-URDIDO. --}}
                    <select id="km-fibra" data-km-fibra wire:model="fibra" class="{{ $inputBase }} @error('fibra') {{ $error }} @enderror">
                        <option value="">Seleccionar...</option>
                        @if ($fibra !== '')
                            <option value="{{ $fibra }}" selected>{{ $fibra }}</option>
                        @endif
                    </select>
                    @error('fibra') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-tamano">Tamaño</label>
                    <div class="relative">
                        <input type="text" id="km-tamano" data-km-tamano wire:model.blur="tamano"
                               autocomplete="off" placeholder="Buscar..."
                               class="{{ $inputBase }} @error('tamano') {{ $error }} @enderror">
                        <div data-km-tamano-dropdown
                             class="hidden fixed z-[9999] bg-white border border-gray-300 rounded shadow-lg overflow-y-auto text-sm"
                             style="max-height:200px;"></div>
                    </div>
                    @error('tamano') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-cuenta">Cuenta</label>
                    <input type="text" id="km-cuenta" wire:model="cuenta" readonly
                           class="{{ $inputBase }} bg-gray-100" title="Se completa al elegir Tamaño">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-calibre">Calibre</label>
                    <input type="text" id="km-calibre" wire:model="calibre" readonly
                           class="{{ $inputBase }} bg-gray-100" title="Se completa al elegir Tamaño">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-metros">Metros</label>
                    <input type="number" step="0.01" id="km-metros" wire:model="metros"
                           class="{{ $inputBase }} @error('metros') {{ $error }} @enderror">
                    @error('metros') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Fecha Programada, Tipo Atado, Bom Urdido, Lote Proveedor --}}
            <div class="grid gap-1.5" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-fecha">Fecha Programada</label>
                    <input type="date" id="km-fecha" wire:model="fechaProgramada"
                           class="{{ $inputBase }} @error('fechaProgramada') {{ $error }} @enderror">
                    @error('fechaProgramada') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-atado">Tipo Atado</label>
                    <select id="km-atado" wire:model="tipoAtado" class="{{ $inputBase }}">
                        <option value="Normal">Normal</option>
                        <option value="Especial">Especial</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-bom">Bom Urdido</label>
                    <input type="text" id="km-bom" data-km-bom wire:model.blur="bomId"
                           autocomplete="off" list="km-bom-opciones"
                           class="{{ $inputBase }} @error('bomId') {{ $error }} @enderror">
                    <datalist id="km-bom-opciones" data-km-bom-opciones></datalist>
                    @error('bomId') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5" for="km-lote">Lote Proveedor</label>
                    <input type="text" id="km-lote" data-km-lote wire:model="loteProveedor" readonly
                           class="{{ $inputBase }} bg-gray-100"
                           title="Se completa al seleccionar un registro del inventario">
                </div>
            </div>

            {{-- Resumen de materiales + detalle de inventario. Los pinta el TS. --}}
            <div class="mt-2 grid gap-2" style="grid-template-columns: minmax(160px, 0.22fr) minmax(0, 1fr);">
                <div class="border border-gray-400 rounded overflow-hidden">
                    <div class="overflow-auto" style="height: 220px;">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="px-2 py-1.5 text-center font-semibold">Articulo</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Config</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Consumo</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Kilos</th>
                                </tr>
                            </thead>
                            <tbody data-km-resumen class="divide-y divide-gray-200 bg-white">
                                <tr>
                                    <td colspan="4" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border border-gray-400 rounded overflow-hidden flex flex-col" style="height: 260px;">
                    <div class="overflow-auto flex-1 min-h-0">
                        <table class="min-w-full text-sm">
                            <thead class="text-white sticky top-0 z-10 whitespace-nowrap">
                                <tr>
                                    @foreach ([
                                        'itemId' => 'Articulo', 'configId' => 'Config', 'inventSizeId' => 'Tamaño',
                                        'inventColorId' => 'Color', 'inventLocationId' => 'Almacen', 'inventBatchId' => 'Lote',
                                        'wmsLocationId' => 'Localidad', 'inventSerialId' => 'Serie', 'noProv' => 'No Prov.',
                                        'loteProv' => 'Lote Prov.', 'prodDate' => 'Fecha', 'conos' => 'Conos', 'kilos' => 'Kilos',
                                    ] as $campo => $titulo)
                                        <th class="px-1.5 py-1.5 text-center font-semibold {{ $loop->even ? 'bg-blue-600' : 'bg-blue-500' }} cursor-pointer hover:bg-blue-600"
                                            data-km-ordenar="{{ $campo }}" aria-sort="none">
                                            {{ $titulo }} <i class="fa-solid fa-sort sort-icon ml-1"></i>
                                        </th>
                                    @endforeach
                                    <th class="px-1.5 py-1.5 text-center font-semibold bg-blue-600 w-10">Seleccionar</th>
                                </tr>
                            </thead>
                            <tbody data-km-detalle class="divide-y divide-gray-200 bg-white">
                                <tr>
                                    <td colspan="14" class="px-2 py-3 text-center text-gray-500 text-sm">Ingrese Bom Urdido (lmat)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="shrink-0 border-t border-gray-200 text-sm py-1.5 px-1.5 flex gap-4 bg-blue-50">
                        <span data-km-total-registros class="font-medium">Total: 0</span>
                        <span data-km-total-conos class="font-semibold"></span>
                        <span data-km-total-kilos class="font-semibold"></span>
                    </div>
                    @error('materiales') <p class="px-1.5 pb-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Julios / hilos y observaciones --}}
            <div class="mt-2 grid gap-2" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="px-2 py-1.5 text-center font-semibold">No. Julio</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Hilos</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Obs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($julios as $i => $julio)
                                    <tr wire:key="julio-{{ $i }}">
                                        <td class="px-2 py-1.5 text-center">
                                            <input type="number" min="1" wire:model="julios.{{ $i }}" class="{{ $inputTabla }}">
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <input type="number" min="1" wire:model="hilos.{{ $i }}" class="{{ $inputTabla }}">
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input type="text" wire:model="obs.{{ $i }}" class="{{ $inputTabla }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('julios') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col">
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="km-obs">Observaciones del Programa</label>
                    <textarea id="km-obs" rows="3" wire:model="observaciones" class="{{ $inputTabla }} resize-none"></textarea>
                    @error('observaciones') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Fecha de requerimiento: reemplaza el Swal con input inyectado a mano. --}}
    @if ($pidiendoFechaRequerimiento)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             wire:click.self="cancelarFechaRequerimiento"
             wire:keydown.escape.window="cancelarFechaRequerimiento"
             role="dialog" aria-modal="true" aria-labelledby="km-fechareq-titulo">
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h2 id="km-fechareq-titulo" class="text-lg font-semibold text-gray-900">¿Cuándo se requiere el material?</h2>
                <p class="mt-1 text-sm text-gray-500">Selecciona la fecha y hora en que se necesita el material.</p>

                <input type="datetime-local" wire:model="fechaRequerimiento"
                       class="mt-3 w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                @error('fechaRequerimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="cancelarFechaRequerimiento"
                            class="px-3 py-1.5 rounded border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="button" wire:click="guardar"
                            wire:loading.attr="disabled" wire:target="guardar"
                            class="px-3 py-1.5 rounded bg-violet-600 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="guardar">Confirmar</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
