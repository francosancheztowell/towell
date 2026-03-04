{{-- ============================================================
     _header-orden.blade.php
     Muestra la información de la orden de urdido: Folio, Cuenta,
     Urdido, Metros, Proveedor, Tipo/Barras, Destino, Ordenado por,
     Hilo, Información de Julio, y Observaciones.
     Variables requeridas: $orden, $metros, $destino, $hilo,
     $observaciones, $loteProveedor, $isKarlMayer, $julios, $nomEmpl
     ============================================================ --}}

    <div class="w-full">
    <!-- Sección superior: Información General -->
        <div class="bg-white p-1">
        <div class="grid grid-cols-12 gap-2 items-stretch">
            <!-- Columna Izquierda -->
            <div class="col-span-12 md:col-span-2 flex flex-col space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Folio:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? $orden->Folio : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Cuenta:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? ($orden->Cuenta ?? '-') : '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Urdido:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $orden ? ($orden->MaquinaId ?? '-') : '-' }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Metros:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $metros ?? '0' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[55px]">Proveedor:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $loteProveedor ?? '-' }}</span>
                </div>
            </div>

            <!-- Columna Centro -->
                <div class="col-span-12 md:col-span-2 flex flex-col space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">{{ ($isKarlMayer ?? false) ? 'Barras' : 'Tipo' }}:</span>
                        @if($orden && $orden->RizoPie)
                            @php
                                $tipo = strtoupper(trim($orden->RizoPie));
                                $isRizo = $tipo === 'RIZO';
                                $isPie = $tipo === 'PIE';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isRizo ? 'bg-rose-100 text-rose-700' : ($isPie ? 'bg-teal-100 text-teal-700' : 'bg-gray-200 text-gray-800') }}">
                                {{ $orden->RizoPie }}
                    </span>
                        @else
                            <span class="text-md text-gray-500 italic">-</span>
                        @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Destino:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $destino ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Ordenado por:</span>
                        <span class="text-md text-gray-900 flex-1">{{ $nomEmpl ?? '-' }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-md font-semibold text-gray-700 whitespace-nowrap min-w-[70px]">Hilo:</span>
                        <span class="text-md text-gray-500 italic flex-1">{{ $hilo ?? '-' }}</span>
                </div>
                <div class="flex-1"></div>
            </div>

            <!-- Columna 3: Tabla No. JULIO y HILOS -->
                <div class="col-span-12 md:col-span-4 flex flex-col">
                <div class="flex-1 flex flex-col">
                        <label class="block text-md font-semibold text-gray-700 text-center">Información de Julio</label>
                    <div class="border border-gray-300 rounded overflow-hidden max-w-md mx-auto w-full">
                        <table class="w-full text-md" style="table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 border-gray-300 text-md px-2" style="width: 80px;">No. Julio</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-md px-2" style="width: 70px;">Hilos</th>
                                    <th class="text-center bg-gray-200 font-semibold text-gray-700 text-md px-2" style="width: 180px;">Obs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @if($julios && $julios->count() > 0)
                                        @foreach($julios as $julio)
                                <tr>
                                    <td class="border border-r border-gray-200 text-center py-1 px-2" style="width: 80px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Julios ?? '-' }}</span>
                                    </td>
                                    <td class="border text-center py-1 px-2" style="width: 70px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Hilos ?? '-' }}</span>
                                    </td>
                                    <td class="border text-center py-1 px-2" style="width: 180px;">
                                                    <span class="text-md text-gray-900">{{ $julio->Obs ?? '-' }}</span>
                                    </td>
                                </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="3" class="border text-center py-1 px-2 text-gray-500 italic">
                                                No hay información de julios
                                    </td>
                                </tr>
                                    @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex-1"></div>
            </div>

            <!-- Columna 4: Observaciones -->
            <div class="col-span-12 md:col-span-4 flex flex-col">
                <div class="flex-1 flex flex-col">
                    <label class="block text-md font-semibold text-gray-700">Observaciones:</label>
                    <div class="flex-1 w-full border border-gray-300 rounded px-2 text-md overflow-y-auto">
                            <span class="text-gray-500 whitespace-pre-wrap">{{ $observaciones ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
