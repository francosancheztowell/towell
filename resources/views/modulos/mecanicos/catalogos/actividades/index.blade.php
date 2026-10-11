@extends('layouts.app')

@section('page-title', 'Actividades mecánicos')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-create id="btn-nuevo" title="Nueva actividad" text="Nuevo" :module="$moduloPermiso" />
        <x-navbar.button-edit id="btn-editar" title="Editar actividad" text="Editar" :module="$moduloPermiso" :disabled="true" />
        <x-navbar.button-delete id="btn-eliminar" title="Eliminar actividad" text="Eliminar" :module="$moduloPermiso" :disabled="true" />
    </div>
@endsection

@section('content')
<div class="w-full px-4 py-4 space-y-4" id="actividades"
     data-store-url="{{ route('mecanicos.catalogos.actividades.store') }}"
     data-update-url="{{ route('mecanicos.catalogos.actividades.update', ['id' => '__ID__']) }}"
     data-destroy-url="{{ route('mecanicos.catalogos.actividades.destroy', ['id' => '__ID__']) }}">

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-14rem)]">
            <table class="min-w-full text-base" id="tabla-actividades">
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base w-24">Orden</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Actividad</th>
                    </tr>
                </thead>
                <tbody id="tbody-actividades">
                    @forelse($actividades as $actividad)
                        <tr class="actividad-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $actividad->Id }}"
                            data-orden="{{ $actividad->Orden }}"
                            data-actividad="{{ e($actividad->Actividad) }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $actividad->Orden }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $actividad->Actividad }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="2" class="px-4 py-8 text-center text-gray-500 text-base">No hay actividades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Nuevo / Editar --}}
<div id="modal-form" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nueva actividad</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Cerrar">&times;</button>
        </div>
        <form id="form-actividad" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Orden" class="block text-sm font-medium text-gray-700 mb-1">Orden <span class="text-red-500">*</span></label>
                    <input type="number" name="Orden" id="Orden" required step="1"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="1, 2, 3…">
                </div>
                <div>
                    <label for="Actividad" class="block text-sm font-medium text-gray-700 mb-1">Actividad <span class="text-red-500">*</span></label>
                    <input type="text" name="Actividad" id="Actividad" required maxlength="100"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Nombre de la actividad">
                </div>
            </div>
            <div class="mt-6 flex gap-2 justify-end">
                <button type="submit" id="form-submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/modulos/mecanicos/actividades/index.ts')
@endpush
