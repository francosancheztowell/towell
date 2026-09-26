{{--
  Catálogo Actividades BPM (19-01): una vista para Urdido y Engomado. La incluyen
  modulos/urdido/urd-actividades-bpm/index y modulos/engomado/eng-actividades-bpm/index.
  @param string $variante  'urdido' (con columna/selector Máquina MC/KM) | 'engomado' (paginado)
  Datos del controller: $items (Collection en Urdido, LengthAwarePaginator en Engomado), $q.
  JS: resources/js/modulos/urdido/comun/actividades-bpm/index.ts
--}}
@php
    $cfgVariante = [
        'urdido' => [
            'titulo' => 'Actividades BPM Urdido',
            'modulo' => 'Actividades BPM Urdido',
            'rutas' => 'urd-actividades-bpm',
            'parametro' => 'urdActividadesBpm',
            'conMaquina' => true,
            'thead' => 'bg-blue-600',
            'fondoModal' => 'bg-black/50',
            'cabeceraCrear' => 'bg-green-600',
            'cabeceraEditar' => 'bg-yellow-600',
        ],
        'engomado' => [
            'titulo' => 'Actividades BPM Engomado',
            'modulo' => 'Actividades BPM Engomado',
            'rutas' => 'eng-actividades-bpm',
            'parametro' => 'engActividadesBpm',
            'conMaquina' => false,
            'thead' => 'bg-gradient-to-r bg-blue-500',
            'fondoModal' => 'bg-gray-900/50',
            'cabeceraCrear' => 'bg-gradient-to-r from-green-600 to-green-500',
            'cabeceraEditar' => 'bg-gradient-to-r from-yellow-600 to-yellow-500',
        ],
    ][$variante];

    $maquinas = ['MC' => ['Mc Coy', 'bg-blue-100 text-blue-800'], 'KM' => ['Karl Mayer 1', 'bg-purple-100 text-purple-800']];
    $conMaquina = $cfgVariante['conMaquina'];
    // Crear y Editar comparten campos; cambian cabecera, color de foco y botón.
    $modales = [
        'createModal' => ['titulo' => 'Nueva Actividad BPM', 'icono' => 'fa-plus-circle', 'cabecera' => $cfgVariante['cabeceraCrear'], 'foco' => 'focus:ring-green-500', 'accion' => route($cfgVariante['rutas'].'.store')],
        'editModal' => ['titulo' => 'Editar Actividad BPM', 'icono' => 'fa-edit', 'cabecera' => $cfgVariante['cabeceraEditar'], 'foco' => 'focus:ring-yellow-500', 'accion' => ''],
    ];
    $configActividades = [
        'variante' => $variante,
        'conMaquina' => $conMaquina,
        'rutas' => [
            'actualizar' => route($cfgVariante['rutas'].'.update', [$cfgVariante['parametro'] => '__ID__']),
            'eliminar' => route($cfgVariante['rutas'].'.destroy', [$cfgVariante['parametro'] => '__ID__']),
        ],
    ];
@endphp
@extends('layouts.app')

@section('title', $cfgVariante['titulo'])
@section('page-title', $cfgVariante['titulo'])

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create
            :module="$cfgVariante['modulo']"
            data-actividades-accion="nueva"
            id="btn-nuevo"
            title="Nueva Actividad" />

        <x-navbar.button-edit
            :module="$cfgVariante['modulo']"
            data-actividades-accion="editar"
            id="btn-top-edit"
            title="Editar Actividad" />

        <x-navbar.button-delete
            :module="$cfgVariante['modulo']"
            data-actividades-accion="eliminar"
            id="btn-top-delete"
            title="Eliminar Actividad" />
    </div>
@endsection

@section('content')
<div id="actividades-bpm-pagina" class="container mx-auto px-4 py-6" data-actividades-bpm='@json($configActividades)'>
    <!-- Tabla de Actividades -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="{{ $cfgVariante['thead'] }} text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold w-24">Orden</th>
                        <th class="px-4 py-3 text-left font-semibold">Actividad</th>
                        @if($conMaquina)
                            <th class="px-4 py-3 text-left font-semibold w-40">Máquina</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150 cursor-pointer"
                            data-key="{{ $item->Id }}"
                            data-orden="{{ $item->Orden ?? '' }}"
                            data-actividad="{{ $item->Actividad }}"
                            @if($conMaquina) data-maquina="{{ $item->Maquina ?? '' }}" @endif
                            aria-selected="false">
                            <td class="px-4 py-3 align-middle font-medium text-gray-700">
                                {{ $item->Orden ?? '-' }}
                            </td>
                            <td class="px-4 py-3 align-middle text-gray-800">
                                {{ $item->Actividad }}
                            </td>
                            @if($conMaquina)
                                <td class="px-4 py-3 align-middle">
                                    @isset($maquinas[$item->Maquina])
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold {{ $maquinas[$item->Maquina][1] }}">{{ $maquinas[$item->Maquina][0] }}</span>
                                    @endisset
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $conMaquina ? 3 : 2 }}" class="px-4 py-8 text-center text-gray-500">
                                <i class="fa-solid fa-inbox text-4xl mb-2 text-gray-300" aria-hidden="true"></i>
                                <p class="text-lg">No se encontraron actividades</p>
                                @if($q)
                                    <p class="text-sm mt-2">Intenta con otro término de búsqueda</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items instanceof \Illuminate\Contracts\Pagination\Paginator && $items->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <!-- Formulario oculto para eliminación -->
    <form id="globalDeleteForm" action="#" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    @foreach($modales as $idModal => $modal)
        @php($esEdicion = $idModal === 'editModal')
        <div id="{{ $idModal }}" class="fixed inset-0 {{ $cfgVariante['fondoModal'] }} hidden z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-4 transform transition-all">
                <div class="{{ $modal['cabecera'] }} text-white px-6 py-4 rounded-t-lg">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid {{ $modal['icono'] }}" aria-hidden="true"></i>
                        {{ $modal['titulo'] }}
                    </h2>
                </div>

                <form @if($esEdicion) id="editForm" @endif action="{{ $modal['accion'] }}" method="POST" class="p-6">
                    @csrf
                    @if($esEdicion)
                        @method('PUT')
                    @endif
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Orden <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <input
                                type="number"
                                @if($esEdicion) id="editOrden" @endif
                                name="Orden"
                                min="1"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 {{ $modal['foco'] }} focus:border-transparent transition"
                                placeholder="Ej: 1, 2, 3...">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Actividad <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                @if($esEdicion) id="editActividad" @endif
                                name="Actividad"
                                maxlength="100"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 {{ $modal['foco'] }} focus:border-transparent transition"
                                placeholder="Nombre de la actividad">
                        </div>

                        @if($conMaquina)
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Máquina <span class="text-red-500">*</span>
                                </label>
                                <select @if($esEdicion) id="editMaquina" @endif name="Maquina" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 {{ $modal['foco'] }} focus:border-transparent transition">
                                    <option value="">Seleccione...</option>
                                    @foreach($maquinas as $clave => [$nombre])
                                        <option value="{{ $clave }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                        <button
                            type="button"
                            data-actividades-cerrar="{{ $idModal }}"
                            class="px-5 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                            <i class="fa-solid fa-times mr-1" aria-hidden="true"></i> Cancelar
                        </button>
                        @if($esEdicion)
                            <button type="submit" class="px-5 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium">
                                <i class="fa-solid fa-save mr-1" aria-hidden="true"></i> Actualizar
                            </button>
                        @else
                            <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                                <i class="fa-solid fa-check mr-1" aria-hidden="true"></i> Guardar
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>

<style>
    /* Estilos para filas seleccionadas */
    #actividades-bpm-pagina tbody tr {
        transition: all 0.15s ease;
    }

    #actividades-bpm-pagina tbody tr:hover {
        background-color: #eff6ff !important;
    }

    #actividades-bpm-pagina tbody tr[aria-selected="true"] {
        background-color: #dbeafe !important;
        box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.5);
    }

    #actividades-bpm-pagina tbody tr[aria-selected="true"] td:first-child {
        border-left: 4px solid #3b82f6;
    }

    /* Animación para modales */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    #createModal > div,
    #editModal > div {
        animation: modalFadeIn 0.2s ease-out;
    }
</style>
@endsection

@push('scripts')
    @vite('resources/js/modulos/urdido/comun/actividades-bpm/index.ts')
@endpush
