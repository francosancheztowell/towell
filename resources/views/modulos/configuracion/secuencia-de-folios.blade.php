@extends('layouts.app')

@section('page-title', 'Secuencia de folios')

@section('navbar-right')
<div class="flex items-center gap-2">
    <x-navbar.button-create id="btn-nuevo" title="Nueva secuencia" text="Nuevo" />
    <x-navbar.button-edit id="btn-editar" title="Editar secuencia" text="Editar" :disabled="true" />
    <x-navbar.button-delete id="btn-eliminar" title="Eliminar secuencia" text="Eliminar" :disabled="true" />
</div>
@endsection

@section('content')
<div class="w-full px-4 py-4">
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-auto max-h-[calc(100vh-12rem)]">
            @php
                $crud = [
                    'store' => route('configuracion.secuencia-folios.store'),
                    'update' => route('configuracion.secuencia-folios.update', ['id' => ':id']),
                    'destroy' => route('configuracion.secuencia-folios.destroy', ['id' => ':id']),
                    'campos' => ['Modulo', 'Prefijo', 'Consecutivo'],
                    'llaveId' => 'Id',
                    'tituloNuevo' => 'Nueva secuencia',
                    'tituloEditar' => 'Editar secuencia',
                    'tituloEliminar' => '¿Eliminar secuencia?',
                    'campoNombre' => 'Modulo',
                    'nombrePorDefecto' => 'esta secuencia',
                    'vacio' => 'No hay registros de secuencia.',
                ];
            @endphp
            <table class="min-w-full text-base" id="tabla-secuencia" data-crud='@json($crud)'>
                <thead class="sticky top-0 z-10 bg-blue-500 text-white shadow-sm">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Id</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Modulo</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Prefijo</th>
                        <th class="px-4 py-3 text-left font-semibold bg-blue-500 text-base">Consecutivo</th>
                    </tr>
                </thead>
                <tbody id="tbody-secuencia">
                    @forelse($items as $r)
                        @php
                            $modulo = $r->Modulo ?? $r->modulo ?? null;
                            $prefijo = $r->Prefijo ?? $r->prefijo ?? null;
                            $consecutivo = $r->Consecutivo ?? $r->consecutivo ?? null;
                        @endphp
                        <tr class="seq-row border-b border-gray-100 hover:bg-gray-50 cursor-pointer {{ $loop->even ? 'bg-gray-50/50' : '' }}"
                            data-id="{{ $r->Id }}"
                            data-modulo="{{ $modulo }}"
                            data-prefijo="{{ $prefijo }}"
                            data-consecutivo="{{ (int) $consecutivo }}">
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $r->Id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 text-base">{{ $modulo ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $prefijo ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 text-base">{{ $consecutivo !== null ? (int) $consecutivo : '—' }}</td>
                        </tr>
                    @empty
                        <tr id="tr-empty">
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-base">No hay registros de secuencia.</td>
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
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Nueva secuencia</h3>
            <button type="button" id="modal-cerrar" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="form-secuencia" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="space-y-4">
                <div>
                    <label for="Modulo" class="block text-sm font-medium text-gray-700 mb-1">Modulo</label>
                    <input type="text" name="Modulo" id="Modulo" maxlength="100" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. BPMTEjido">
                </div>
                <div>
                    <label for="Prefijo" class="block text-sm font-medium text-gray-700 mb-1">Prefijo</label>
                    <input type="text" name="Prefijo" id="Prefijo" maxlength="20" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej. BT">
                </div>
                <div>
                    <label for="Consecutivo" class="block text-sm font-medium text-gray-700 mb-1">Consecutivo <span class="text-red-500">*</span></label>
                    <input type="number" name="Consecutivo" id="Consecutivo" value="0" min="0" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0">
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
