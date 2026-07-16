@extends('layouts.app')

@section('page-title', 'Listas de Materiales')

@section('content')
    <div class="container-fluid p-4">
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">
                    <i class="fas fa-list text-blue-500 mr-1"></i>
                    Listas de Materiales (CatLMat)
                </h1>
                <span class="text-sm text-gray-500">{{ $grupos->count() }} lista(s)</span>
            </div>

            <div class="overflow-x-auto" style="max-height: calc(100vh - 160px);">
                <table class="w-full text-sm">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Orden</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Nombre (BomId)</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Descripción</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Salón</th>
                            <th class="px-3 py-2 text-center whitespace-nowrap">Líneas</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Fecha Registro</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grupos as $orden => $lineas)
                            @php $primera = $lineas->first(); @endphp
                            <tr class="cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-colors {{ $loop->even ? 'bg-gray-50' : 'bg-white' }}"
                                data-orden="{{ $orden }}">
                                <td class="px-3 py-2 font-semibold text-blue-700 whitespace-nowrap">{{ $orden }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->Nombre }}</td>
                                <td class="px-3 py-2">{{ $primera->Descrip }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->Salon }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ $lineas->count() }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->FechaRegistro?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $primera->UsuarioRegistro }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-gray-300 text-3xl block mb-2"></i>
                                    No hay listas de materiales registradas
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script type="application/json" id="lmat-lista-data">@json($grupos)</script>

    <script>
        (function () {
            let grupos = {};
            try {
                grupos = JSON.parse(document.getElementById('lmat-lista-data').textContent || '{}');
            } catch (e) {
                console.error('No se pudo leer los datos de listas de materiales.', e);
            }

            const esc = (t) => {
                const d = document.createElement('div');
                d.textContent = t ?? '';
                return d.innerHTML;
            };
            const num = (v, dec) => (v == null || v === '' || isNaN(Number(v))) ? '' : Number(v).toFixed(dec);

            document.querySelectorAll('tr[data-orden]').forEach(tr => {
                tr.addEventListener('click', () => {
                    const orden = tr.dataset.orden;
                    const lineas = grupos[orden] || [];
                    if (!lineas.length) return;

                    const cab = lineas[0];
                    let html = '<div class="text-left">';
                    html += '<p class="text-sm text-gray-600 mb-3">Orden <strong>' + esc(orden) + '</strong>'
                        + (cab.Nombre ? ' · ' + esc(cab.Nombre) : '')
                        + (cab.Descrip ? ' — ' + esc(cab.Descrip) : '')
                        + ' · ' + lineas.length + ' línea(s)</p>';
                    html += '<div class="max-h-96 overflow-auto border border-gray-200 rounded-lg">';
                    html += '<table class="w-full text-xs"><thead class="bg-blue-500 text-white sticky top-0"><tr>'
                        + '<th class="px-2 py-1.5 text-left">#</th>'
                        + '<th class="px-2 py-1.5 text-left">Artículo</th>'
                        + '<th class="px-2 py-1.5 text-left">Config</th>'
                        + '<th class="px-2 py-1.5 text-left">Tamaño</th>'
                        + '<th class="px-2 py-1.5 text-left">Color</th>'
                        + '<th class="px-2 py-1.5 text-left">Nombre Color</th>'
                        + '<th class="px-2 py-1.5 text-left">Almacén</th>'
                        + '<th class="px-2 py-1.5 text-right">Qty</th>'
                        + '<th class="px-2 py-1.5 text-right">%</th>'
                        + '<th class="px-2 py-1.5 text-right">Luchaje</th>'
                        + '<th class="px-2 py-1.5 text-left">Cód. Dibujo</th>'
                        + '</tr></thead><tbody>';

                    lineas.forEach((l, i) => {
                        html += '<tr class="' + (i % 2 ? 'bg-gray-50' : 'bg-white') + ' border-b border-gray-100">'
                            + '<td class="px-2 py-1.5 text-gray-400">' + (i + 1) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.ItemId) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.ConfigId) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.InventSizeId) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.InventColorId) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.NombreColor) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.InventLocationId) + '</td>'
                            + '<td class="px-2 py-1.5 text-right">' + num(l.Qty, 1) + '</td>'
                            + '<td class="px-2 py-1.5 text-right">' + num(l.Porcentaje, 1) + '</td>'
                            + '<td class="px-2 py-1.5 text-right">' + esc(l.Luchaje) + '</td>'
                            + '<td class="px-2 py-1.5 whitespace-nowrap">' + esc(l.CodigoDibujo) + '</td>'
                            + '</tr>';
                    });

                    html += '</tbody></table></div></div>';

                    Swal.fire({
                        title: 'Líneas de materiales',
                        html: html,
                        width: '950px',
                        showConfirmButton: true,
                        confirmButtonText: 'Cerrar',
                        confirmButtonColor: '#3b82f6',
                    });
                });
            });
        })();
    </script>
@endsection
