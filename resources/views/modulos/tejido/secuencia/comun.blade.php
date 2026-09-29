{{--
    Secuencias de Tejido (19-02): una vista para las 4 variantes (inv-telas, inv-trama,
    corte-eficiencia, marcas-finales). Las diferencias viven en el mapa $variantes; el JS en
    resources/js/modulos/tejido/secuencia/index.ts. Las vistas originales solo hacen @include.
--}}
@extends('layouts.app')

@php
    $tiposTelar = ['JACQUARD', 'ITEMA', 'SULZER', 'SMIT'];
    $camposTelar = [
        ['nombre' => 'NoTelar', 'tipo' => 'number', 'requerido' => true, 'placeholder' => 'Ej: 201'],
        ['nombre' => 'TipoTelar', 'tipo' => 'select', 'requerido' => true, 'opciones' => $tiposTelar],
        ['nombre' => 'Secuencia', 'tipo' => 'number', 'requerido' => true, 'placeholder' => 'Ej: 1'],
    ];
    $camposSalon = [
        ['nombre' => 'NoTelarId', 'tipo' => 'number', 'requerido' => true, 'placeholder' => 'Ej: 201'],
        ['nombre' => 'SalonTejidoId', 'tipo' => 'text', 'requerido' => true, 'placeholder' => 'Ej: Jacquard', 'max' => 100],
        // Al crear, vacío = el servidor lo pone al final; al editar es obligatorio.
        ['nombre' => 'Orden', 'tipo' => 'number', 'requerido' => 'editar', 'placeholder' => 'Auto', 'min' => 1],
    ];
    $variantes = [
        'inv-telas' => [
            'titulo' => 'Secuencia de Telas',
            'nombre' => 'Secuencia Inv Telas',
            'modulo' => 'Secuencia Inv Telas',
            'ruta' => 'tejido.secuencia-inv-telas',
            'llave' => 'Id',
            'columnas' => ['NoTelar' => 'Telar', 'TipoTelar' => 'Tipo telar', 'Secuencia' => 'Secuencia'],
            'campos' => array_merge($camposTelar, [
                ['nombre' => 'Observaciones', 'tipo' => 'textarea', 'requerido' => false, 'placeholder' => 'Observaciones (opcional)', 'max' => 500],
            ]),
            'orden' => ['llave' => 'Id', 'campo' => 'Secuencia'],
        ],
        'inv-trama' => [
            'titulo' => 'Secuencia de Trama',
            'nombre' => 'Secuencia Inv Trama',
            'modulo' => 'Secuencia Inv Trama',
            'ruta' => 'tejido.secuencia-inv-trama',
            'llave' => 'Id',
            'columnas' => ['NoTelar' => 'Telar', 'TipoTelar' => 'Tipo telar', 'Secuencia' => 'Secuencia'],
            'campos' => $camposTelar,
            'orden' => ['llave' => 'Id', 'campo' => 'Secuencia'],
        ],
        'corte-eficiencia' => [
            'titulo' => 'Secuencia Corte de Eficiencia',
            'nombre' => 'Secuencia Corte Eficiencia',
            'modulo' => 'Secuencia Corte de Eficiencia',
            'ruta' => 'tejido.secuencia-corte-eficiencia',
            'llave' => 'NoTelarId',
            'columnas' => ['NoTelarId' => 'NoTelarId', 'SalonTejidoId' => 'SalonTejidoId', 'Orden' => 'Orden'],
            'campos' => $camposSalon,
            'orden' => ['llave' => 'NoTelarId', 'campo' => 'Orden'],
        ],
        'marcas-finales' => [
            'titulo' => 'Secuencia Marcas Finales',
            'nombre' => 'Secuencia Marcas Finales',
            'modulo' => 'Secuencia Marcas Finales',
            'ruta' => 'tejido.secuencia-marcas-finales',
            'llave' => 'NoTelarId',
            'columnas' => ['NoTelarId' => 'Telar', 'SalonTejidoId' => 'Salón', 'Orden' => 'Orden'],
            'campos' => $camposSalon,
            'orden' => ['llave' => 'NoTelarId', 'campo' => 'Orden'],
        ],
    ];
    $cfg = $variantes[$variante];
    $puedeCrear = userCan('crear', $cfg['modulo']);
    $puedeEditar = userCan('modificar', $cfg['modulo']);
    $puedeEliminar = userCan('eliminar', $cfg['modulo']);
    $campoDescripcion = array_keys($cfg['columnas'])[1];
    $configPagina = [
        'variante' => $variante,
        'nombre' => $cfg['nombre'],
        'campos' => $cfg['campos'],
        'orden' => $cfg['orden'],
        'descripcion' => [array_keys($cfg['columnas'])[0], $campoDescripcion],
        'permisos' => ['editar' => $puedeEditar, 'eliminar' => $puedeEliminar],
        'rutas' => [
            'store' => route($cfg['ruta'].'.store'),
            'orden' => route($cfg['ruta'].'.orden'),
            'update' => route($cfg['ruta'].'.update', ['id' => '__ID__']),
            'destroy' => route($cfg['ruta'].'.destroy', ['id' => '__ID__']),
        ],
    ];
@endphp

@section('page-title', $cfg['titulo'])

@section('navbar-right')
    <div class="flex items-center gap-1">
        <button type="button" id="btn-agregar"
            @if ($puedeCrear)
                data-accion="crear"
                class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                title="Crear" aria-label="Crear"
            @else
                disabled
                class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
                title="No tiene permiso para crear" aria-label="Crear (sin permiso)"
            @endif>
            <i class="fas fa-plus text-lg" aria-hidden="true"></i>
        </button>
        <button type="button" id="btn-editar" disabled
            @if ($puedeEditar)
                data-accion="editar"
                class="p-2 text-gray-400 hover:text-gray-600 rounded-md transition-colors cursor-not-allowed"
                title="Editar" aria-label="Editar"
            @else
                class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
                title="No tiene permiso para editar" aria-label="Editar (sin permiso)"
            @endif>
            <i class="fas fa-edit text-lg" aria-hidden="true"></i>
        </button>
        <button type="button" id="btn-eliminar" disabled
            @if ($puedeEliminar)
                data-accion="eliminar"
                class="p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed"
                title="Eliminar" aria-label="Eliminar"
            @else
                class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
                title="No tiene permiso para eliminar" aria-label="Eliminar (sin permiso)"
            @endif>
            <i class="fas fa-trash text-lg" aria-hidden="true"></i>
        </button>
    </div>
@endsection

@section('content')
<div id="pagina-secuencia" class="container-fluid px-4 py-6" data-pagina='@json($configPagina)'>
    <div class="rounded-xl shadow-sm overflow-hidden bg-white">
        <div class="overflow-x-auto overflow-y-auto max-h-[600px]">
            <table id="mainTable" class="w-full border-collapse">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-blue-500 text-white text-sm font-medium">
                        <th class="py-3 px-4 text-left w-12"><span class="sr-only">Arrastrar</span></th>
                        @foreach ($cfg['columnas'] as $etiqueta)
                            <th class="py-3 px-4 text-center">{{ $etiqueta }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="secuencia-body">
                    @foreach ($registros as $index => $item)
                        @php
                            // InvSecuenciaTrama: la vista anterior leía `id` en minúsculas.
                            $llave = $item->{$cfg['llave']} ?? $item->{strtolower($cfg['llave'])};
                            $valores = [];
                            foreach ($cfg['campos'] as $campo) {
                                $valores[$campo['nombre']] = $item->{$campo['nombre']} ?? '';
                            }
                        @endphp
                        <tr class="secuencia-row text-center transition cursor-pointer {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-100' }} hover:opacity-90"
                            draggable="true"
                            data-id="{{ $llave }}"
                            data-valores='@json($valores)'>
                            <td class="py-3 px-2 text-gray-400 cursor-grab active:cursor-grabbing" title="Arrastrar para reordenar">
                                <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                            </td>
                            @foreach (array_keys($cfg['columnas']) as $columna)
                                <td class="py-3 px-4" data-columna="{{ $columna }}">{{ $item->{$columna} }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-ui.modal-base id="modal-secuencia" :title="'Crear '.$cfg['nombre']" size="md">
    <form id="form-secuencia" class="grid grid-cols-2 gap-3 text-sm" novalidate>
        @foreach ($cfg['campos'] as $campo)
            <div class="{{ $campo['tipo'] === 'textarea' ? 'col-span-2' : '' }}">
                <label for="campo-{{ $campo['nombre'] }}" class="block text-xs font-medium text-gray-600 mb-1">
                    {{ $campo['nombre'] }}<span data-requerido="{{ $campo['requerido'] === true ? 'siempre' : ($campo['requerido'] ?: 'nunca') }}">{{ $campo['requerido'] === true ? ' *' : '' }}</span>
                </label>
                @if ($campo['tipo'] === 'select')
                    <select id="campo-{{ $campo['nombre'] }}" name="{{ $campo['nombre'] }}" class="w-full px-2 py-2 border border-gray-300 rounded text-center">
                        <option value="">Seleccione...</option>
                        @foreach ($campo['opciones'] as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                @elseif ($campo['tipo'] === 'textarea')
                    <textarea id="campo-{{ $campo['nombre'] }}" name="{{ $campo['nombre'] }}" rows="3" maxlength="{{ $campo['max'] }}"
                        class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="{{ $campo['placeholder'] }}"></textarea>
                @else
                    <input id="campo-{{ $campo['nombre'] }}" name="{{ $campo['nombre'] }}" type="{{ $campo['tipo'] }}"
                        @if ($campo['tipo'] === 'number') step="1" @endif
                        @isset($campo['min']) min="{{ $campo['min'] }}" @endisset
                        @isset($campo['max']) maxlength="{{ $campo['max'] }}" @endisset
                        class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="{{ $campo['placeholder'] }}">
                @endif
            </div>
        @endforeach
        <p id="form-secuencia-error" class="col-span-2 text-sm text-red-600 hidden" role="alert"></p>
    </form>
    <x-slot:footer>
        <button type="button" data-accion="cancelar" class="min-h-touch px-4 py-2 rounded-md bg-gray-500 text-white hover:bg-gray-600">
            <i class="fas fa-times me-2" aria-hidden="true"></i>Cancelar
        </button>
        <button type="submit" form="form-secuencia" id="btn-guardar-secuencia" class="min-h-touch px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
            <i class="fas fa-save me-2" aria-hidden="true"></i><span>Crear</span>
        </button>
    </x-slot:footer>
</x-ui.modal-base>

<style>
.secuencia-row { transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, opacity 0.2s ease; }
.secuencia-row.dragging { opacity: 0.6; transform: scale(0.98); box-shadow: 0 8px 24px rgba(0,0,0,0.15); background-color: #dbeafe !important; z-index: 10; }
.secuencia-row.drag-over-top { box-shadow: 0 -4px 0 0 #3b82f6 inset; background-color: #eff6ff !important; }
.secuencia-row.drag-over-bottom { box-shadow: 0 4px 0 0 #3b82f6 inset; background-color: #eff6ff !important; }
.secuencia-row.drop-success { animation: dropSuccess 0.5s ease; }
@keyframes dropSuccess { 0% { background-color: #bbf7d0 !important; } 100% { background-color: inherit; } }
</style>

@endsection

@push('scripts')
    @vite('resources/js/modulos/tejido/secuencia/index.ts')
@endpush
