@extends('layouts.app')

@section('page-title', 'Base de Datos')

@section('navbar-right')
    <flux:button data-alternar-filtros="#usuariosTable" aria-pressed="false" icon="funnel"
                 class="min-h-touch min-w-touch" title="Filtrar por columna" aria-label="Filtrar por columna" />
@endsection

@section('content')
<div class="w-full px-4 py-6">
    {{-- flux:table + .tabla-cebra y filtros por columna (app.css, tabla-columnas.ts): reemplazan el
         menú de clic derecho y el modal de filtros que tenía la vista. --}}
    <div class="bg-white rounded-2xl overflow-hidden shadow-sm">
        <flux:table id="usuariosTable" data-filtros-columna class="tabla-cebra"
                    data-ruta-productivo="{{ route('configuracion.basededatos.update-productivo') }}">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column>Usuario</flux:table.column>
                <flux:table.column>Área</flux:table.column>
                <flux:table.column>Puesto</flux:table.column>
                <flux:table.column align="center">Estado</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($usuarios as $usuario)
                    <flux:table.row>
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" circle color="blue" initials:single :name="$usuario->nombre ?: '?'" />
                                {{ $usuario->nombre ?? 'Sin nombre' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $usuario->area ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $usuario->puesto ?? '-' }}</flux:table.cell>
                        <flux:table.cell align="center">
                            {{-- Interruptor Productivo/Prueba: lo guarda modulos/configuracion/basededatos/index.ts. --}}
                            <label class="inline-flex min-h-touch cursor-pointer items-center gap-2">
                                <input type="checkbox" data-user-id="{{ $usuario->idusuario }}" @checked(($usuario->Productivo ?? 0) == 1)
                                       aria-label="Productivo: {{ $usuario->nombre }}"
                                       class="peer relative h-6 w-11 cursor-pointer appearance-none rounded-full bg-zinc-300 transition-colors checked:bg-green-500 disabled:opacity-50
                                              before:absolute before:top-0.5 before:left-0.5 before:size-5 before:rounded-full before:bg-white before:shadow before:transition-transform checked:before:translate-x-5
                                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                                <span data-estado-texto class="w-20 text-start text-sm font-medium text-zinc-500 peer-checked:text-green-700">{{ ($usuario->Productivo ?? 0) == 1 ? 'Productivo' : 'Prueba' }}</span>
                            </label>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            <i class="fas fa-users text-gray-400 text-xl mb-3" aria-hidden="true"></i>
                            <p class="text-sm font-medium text-gray-900">No hay usuarios disponibles</p>
                            <p class="text-xs text-gray-500 mt-1">Los usuarios aparecerán aquí cuando estén registrados</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>

@push('scripts')
    @vite('resources/js/modulos/configuracion/basededatos/index.ts')
@endpush
@endsection
