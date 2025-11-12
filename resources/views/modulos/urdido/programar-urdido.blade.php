@extends('layouts.app')

@section('page-title', 'Programar Urdido')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create onclick="console.log('Cargar Información')" title="Cargar Información" icon="fa-download" iconColor="text-blue-500" hoverBg="hover:bg-blue-100" />
    <x-navbar.button-edit onclick="console.log('Subir Prioridad')" title="Subir Prioridad" icon="fa-arrow-up" iconColor="text-green-500" hoverBg="hover:bg-green-100" />
    <x-navbar.button-edit onclick="console.log('Bajar Prioridad')" title="Bajar Prioridad" icon="fa-arrow-down" iconColor="text-red-500" hoverBg="hover:bg-red-100" />

</div>
@endsection

@section('content')
<div class="w-full">
    @for($i = 1; $i <= 3; $i++)
        {{-- Sección MC Coy {{ $i }} --}}
        <div>
            <h2 class="text-base font-semibold text-white text-center bg-blue-500 py-1">MC Coy {{ $i }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full h-[170px] table-auto">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-1  text-center font-semibold text-xs">Prioridad</th>
                            <th class="px-4 py-1  text-center font-semibold text-xs">Folio</th>
                            <th class="px-4 py-1  text-center font-semibold text-xs">Tipo</th>
                            <th class="px-4 py-1  text-center font-semibold text-xs">Cuenta</th>
                            <th class="px-4 py-1  text-center font-semibold text-xs">Calibre</th>
                            <th class="px-4 py-1  text-center font-semibold text-xs">Metros</th>
                        </tr>
                    </thead>
                    <tbody id="mcCoy{{ $i }}TableBody" class="bg-white divide-y">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No hay órdenes pendientes
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endfor
</div>

<script>
// Función para formatear badge de tipo (igual que en reservar-programar.blade.php)
const tipoBadge = (tipo) => {
    const tipoUpper = String(tipo || '').toUpperCase().trim();
    if (tipoUpper === 'RIZO') {
        return '<span class="px-2 py-1 rounded text-xs font-medium bg-rose-100 text-rose-700">Rizo</span>';
    } else if (tipoUpper === 'PIE') {
        return '<span class="px-2 py-1 rounded text-xs font-medium bg-teal-100 text-teal-700">Pie</span>';
    }
    return '<span class="px-2 py-1 rounded text-xs font-medium bg-gray-200 text-gray-800">' + (tipo || '-') + '</span>';
};

// Función para renderizar tabla
const renderTable = (tbodyId, ordenes) => {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (!ordenes || ordenes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="border border-gray-300 px-4 py-8 text-center text-gray-500">No hay órdenes pendientes</td></tr>';
        return;
    }

    const html = ordenes.map(orden => {
        return `
            <tr class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-2 text-sm">${orden.prioridad || ''}</td>
                <td class="border border-gray-300 px-4 py-2 text-sm">${orden.folio || ''}</td>
                <td class="border border-gray-300 px-4 py-2 text-sm">${tipoBadge(orden.tipo)}</td>
                <td class="border border-gray-300 px-4 py-2 text-sm">${orden.cuenta || ''}</td>
                <td class="border border-gray-300 px-4 py-2 text-sm">${orden.calibre || ''}</td>
                <td class="border border-gray-300 px-4 py-2 text-sm">${orden.metros || ''}</td>
            </tr>
        `;
    }).join('');

    tbody.innerHTML = html;
};

// Inicializar (por ahora vacío, se llenará con datos del backend)
document.addEventListener('DOMContentLoaded', () => {
    // Aquí se cargarán los datos desde el backend
    // renderTable('mcCoy1TableBody', []);
    // renderTable('mcCoy2TableBody', []);
    // renderTable('mcCoy3TableBody', []);
});
</script>
@endsection

