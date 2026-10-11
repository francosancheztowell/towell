@extends('layouts.app')

@section('page-title', 'Departamentos')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create id="btn-nuevo" title="Nuevo departamento" text="Nuevo" />
    <x-navbar.button-edit id="btn-editar" title="Editar departamento" text="Editar" :disabled="true" />
    <x-navbar.button-delete id="btn-eliminar" title="Eliminar departamento" text="Eliminar" :disabled="true" />
</div>
@endsection

@section('content')
<div class="w-full px-4 py-4">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-12rem)]">
            <table class="min-w-full text-base" id="tabla-departamentos"
                   data-crud='@json([
                       'store' => route('configuracion.departamentos.store'),
                       'update' => route('configuracion.departamentos.update', ['id' => ':id']),
                       'destroy' => route('configuracion.departamentos.destroy', ['id' => ':id']),
                       'campos' => ['Depto', 'Descripcion'],
                       'llaveId' => 'id',
                       'tituloNuevo' => 'Nuevo departamento',
                       'tituloEditar' => 'Editar departamento',
                       'tituloEliminar' => '¿Eliminar departamento?',
                       'campoNombre' => 'Depto',
                       'nombrePorDefecto' => 'este registro',
                       'vacio' => 'No hay departamentos registrados.',
                   ])'>
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Id</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Descripción</th>
                    </tr>
                </thead>
                <tbody id="tbody-departamentos">
                    @forelse($departamentos as $d)
                        <tr class="depto-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $d->id }}"
                            data-depto="{{ e($d->Depto) }}"
                            data-descripcion="{{ e($d->Descripcion ?? '') }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $d->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $d->Depto }}</td>
                            <td class="px-4 py-3 text-gray-600 text-base">{{ $d->Descripcion ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500 text-base">No hay departamentos registrados.</td>
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
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nuevo departamento</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-depto" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Depto" class="block text-sm font-medium text-gray-700 mb-1">Depto <span class="text-red-500">*</span></label>
                    <input type="text" name="Depto" id="Depto" required maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. Atadores">
                </div>
                <div>
                    <label for="Descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripcion</label>
                    <input type="text" name="Descripcion" id="Descripcion" maxlength="255" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Opcional">
                </div>
            </div>
            <div class="mt-6 flex gap-2 justify-end">
                <button type="button" id="form-cancelar" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" id="form-submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/configuracion/catalogo-simple/index.ts')
@endpush
