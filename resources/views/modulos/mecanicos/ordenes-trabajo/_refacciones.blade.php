{{--
    Refacciones EasyMaint (Tow_Tow) ligadas al folio de paro de la OT.
    Cabecera TwRefacionesTable.OrdenEasyMaint = FolioParo; líneas TwRefaccionesLine por Folio.
--}}
@php
    $refacciones = $refacciones ?? [
        'estado' => 'sin_paro',
        'folioParo' => '',
        'mensaje' => 'Esta orden no tiene folio de paro; no hay refacciones que consultar.',
        'filas' => [],
        'totalCantidad' => 0,
        'totalImporte' => 0,
    ];
    $estadoRefacciones = $refacciones['estado'] ?? 'sin_paro';
    $filasRefacciones = $refacciones['filas'] ?? [];
    $folioParoRefacciones = $refacciones['folioParo'] ?? '';
    $mensajeRefacciones = $refacciones['mensaje'] ?? null;
    $totalCantidad = (float) ($refacciones['totalCantidad'] ?? 0);
    $totalCantidadVisible = abs($totalCantidad - round($totalCantidad)) < 0.00001
        ? (string) (int) round($totalCantidad)
        : rtrim(rtrim(number_format($totalCantidad, 4, '.', ''), '0'), '.');
@endphp
<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="titulo-refacciones-paro">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-3 py-2.5 sm:px-4">
        <div>
            <h2 id="titulo-refacciones-paro" class="text-sm font-semibold text-gray-900 sm:text-base">Refacciones</h2>
            <p class="mt-0.5 text-xs text-gray-500">
                @if ($folioParoRefacciones !== '')
                    EasyMaint · paro <span class="font-semibold text-gray-700">{{ $folioParoRefacciones }}</span>
                @else
                    EasyMaint · sin folio de paro
                @endif
            </p>
        </div>
        @if ($estadoRefacciones === 'ok')
            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                {{ count($filasRefacciones) }} {{ count($filasRefacciones) === 1 ? 'partida' : 'partidas' }}
            </span>
        @endif
    </div>

    @if ($estadoRefacciones !== 'ok')
        <p class="px-3 py-6 text-center text-sm {{ $estadoRefacciones === 'error' ? 'text-amber-800' : 'text-gray-500' }} sm:px-4">
            @if ($estadoRefacciones === 'error')
                <i class="fas fa-triangle-exclamation mr-1 text-amber-600"></i>
            @endif
            {{ $mensajeRefacciones }}
        </p>
    @else
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500 md:hidden">
            <i class="fas fa-arrows-alt-h mr-1"></i> Desliza horizontalmente para ver artículo, cantidad e importe.
        </div>
        <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabla de refacciones del paro">
            <table class="min-w-[720px] w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th class="whitespace-nowrap px-3 py-2.5 text-left">Folio</th>
                        <th class="whitespace-nowrap px-3 py-2.5 text-center">Fecha</th>
                        <th class="whitespace-nowrap px-3 py-2.5 text-left">Status</th>
                        <th class="whitespace-nowrap px-3 py-2.5 text-left">Artículo</th>
                        <th class="min-w-52 px-3 py-2.5 text-left">Nombre</th>
                        <th class="whitespace-nowrap px-3 py-2.5 text-right">Cantidad</th>
                        <th class="whitespace-nowrap px-3 py-2.5 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($filasRefacciones as $fila)
                        <tr class="transition hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <span class="inline-flex rounded-md bg-gray-900 px-2 py-0.5 text-xs font-bold text-white">{{ $fila['folio'] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-center text-gray-700">{{ $fila['fecha'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-800">{{ $fila['status'] !== '' ? $fila['status'] : '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-medium text-gray-800">{{ $fila['articulo'] !== '' ? $fila['articulo'] : '—' }}</td>
                            <td class="px-3 py-2.5 text-gray-800">{{ $fila['nombre'] !== '' ? $fila['nombre'] : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-800">{{ $fila['cantidad'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-800">{{ $fila['importe'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 text-sm font-semibold text-gray-800">
                    <tr>
                        <td colspan="5" class="px-3 py-2.5 text-right text-xs uppercase tracking-wide text-gray-500">Total</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ $totalCantidadVisible }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $refacciones['totalImporte'], 2, '.', ',') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>
