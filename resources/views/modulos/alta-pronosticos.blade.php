@extends('layouts.app')

@section('page-title', 'Alta de Pronósticos')

@section('navbar-right')
    <button id="btnFiltros" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-blue-600 hover:bg-blue-700" title="Filtros">
        <i class="fa-solid fa-filter"></i>
    </button>
    <button id="btnRestablecer" type="button" class="inline-flex items-center justify-center w-9 h-9 text-base rounded-full text-white bg-gray-600 hover:bg-gray-700 ml-2" title="Restablecer">
        <i class="fa-solid fa-rotate"></i>
    </button>
@endsection

@section('content')
<div class="w-full px-0 py-0">
    <div class="bg-white shadow overflow-hidden w-full">
        <!-- Tabla Unificada -->
        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 600px;">
                <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs leading-tight" id="tablaPronosticos">
                    <thead class="bg-blue-500 text-white sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Tipo</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-24">Id Flog</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-32">Cliente</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Item Id</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Talla</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-40">Item</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">TipoHilo</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Rasurado</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">VA</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Ancho</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-24">Por Entregar</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-24">Total Resultado</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20">InvQty</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20">Suma BOM</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-16">N Factores</th>
                            <th class="px-2 py-2 text-right font-semibold whitespace-nowrap w-20">Prom BOM</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-16">Año</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-20">Mes</th>
                            <th class="px-2 py-2 text-left font-semibold whitespace-nowrap w-28">Fecha</th>
                        </tr>
                    </thead>
                    <tbody id="tablaBody" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const tablaBody = document.getElementById('tablaBody');

    async function cargarPronosticos() {
        const mesActual = '{{ $mesActual }}';
        const params = new URLSearchParams();

        if (mesActual) {
            params.set('meses', mesActual);
        }

        try {
            const res = await fetch(`{{ route('pronosticos.get') }}?` + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                method: 'GET',
            });

            const data = await res.json();

            if (!res.ok) {
                pintar([], []);
                return;
            }

            pintar(data.otros ?? [], data.batas ?? []);

        } catch (err) {
            pintar([], []);
            console.error(err);
        }
    }

    function td(txt, isNumeric = false, isRight = false) {
        const d = document.createElement('td');
        d.className = 'px-2 py-2 whitespace-nowrap truncate text-gray-700';
        if (isRight) {
            d.classList.add('text-right');
        }
        if (isNumeric && txt !== null && txt !== '' && txt !== undefined) {
            const num = parseFloat(txt);
            if (!isNaN(num)) {
                d.textContent = number_format(num, 2);
            } else {
                d.textContent = '';
            }
        } else {
            d.textContent = txt ?? '';
        }
        return d;
    }

    function badgeBata() {
        const d = document.createElement('td');
        d.className = 'px-2 py-2 whitespace-nowrap';
        d.innerHTML = `
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-100 text-indigo-800">
                Bata
            </span>
        `;
        return d;
    }

    function badgeOtro() {
        const d = document.createElement('td');
        d.className = 'px-2 py-2 whitespace-nowrap';
        return d;
    }

    function number_format(num, decimals) {
        return num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function pintar(otros, batas) {
        tablaBody.innerHTML = '';

        // Combinar todos los registros: primero otros, luego batas
        const todos = [
            ...otros.map(x => ({...x, esBata: false})),
            ...batas.map(x => ({...x, esBata: true}))
        ];

        if (todos.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.className = 'px-6 py-10 text-center';
            td.colSpan = 19;
            td.innerHTML = `
                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron pronósticos.</p>
            `;
            tr.appendChild(td);
            tablaBody.appendChild(tr);
        } else {
            todos.forEach((x, index) => {
                const tr = document.createElement('tr');
                tr.className = 'select-row cursor-pointer even:bg-gray-50 hover:bg-blue-50 transition-colors';

                // Tipo (badge para batas)
                if (x.esBata) {
                    tr.appendChild(badgeBata());
                } else {
                    tr.appendChild(badgeOtro());
                }

                // Campos comunes
                tr.appendChild(td(x.IDFLOG));
                tr.appendChild(td(x.CUSTNAME));
                tr.appendChild(td(x.ITEMID));
                tr.appendChild(td(x.INVENTSIZEID));
                tr.appendChild(td(x.ITEMNAME));
                tr.appendChild(td(x.TIPOHILOID));
                tr.appendChild(td(x.RASURADOCRUDO));
                tr.appendChild(td(x.VALORAGREGADO));
                tr.appendChild(td(x.ANCHO, true));

                // Por Entregar (solo para otros)
                if (x.esBata) {
                    tr.appendChild(td(''));
                } else {
                    tr.appendChild(td(x.PORENTREGAR, true, true));
                }

                // Campos específicos de batas
                tr.appendChild(td(x.TOTAL_RESULTADO, true, true));
                tr.appendChild(td(x.TOTAL_INVENTQTY, true, true));
                tr.appendChild(td(x.SUM_BOMQTY, true, true));
                tr.appendChild(td(x.N_FACTORES, false, true));
                tr.appendChild(td(x.PROM_BOMQTY, true, true));

                // Año y Mes (solo para otros)
                if (x.esBata) {
                    tr.appendChild(td(''));
                    tr.appendChild(td(''));
                } else {
                    tr.appendChild(td(x.ANIO));
                    tr.appendChild(td(x.MES));
                }

                // Fecha (solo para batas)
                if (x.esBata) {
                    tr.appendChild(td(x.FECHA));
                } else {
                    tr.appendChild(td(''));
                }

                tablaBody.appendChild(tr);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Botón filtros
        const btnFiltros = document.getElementById('btnFiltros');
        if (btnFiltros) {
            btnFiltros.onclick = () => {
                Swal.fire({
                    title: 'Filtros',
                    text: 'Funcionalidad de filtros próximamente',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            };
        }

        // Botón restablecer
        const btnRestablecer = document.getElementById('btnRestablecer');
        if (btnRestablecer) {
            btnRestablecer.onclick = () => {
                cargarPronosticos();
                Swal.fire({
                    icon: 'success',
                    title: 'Filtros restablecidos',
                    toast: true,
                    position: 'top-end',
                    timer: 2000,
                    showConfirmButton: false
                });
            };
        }

        // Cargar pronósticos automáticamente al cargar la página
        cargarPronosticos();
    });
</script>
@endsection
