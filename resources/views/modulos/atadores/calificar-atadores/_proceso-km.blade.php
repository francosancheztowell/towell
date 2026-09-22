@php
    $bloqueado = in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado']);
    $fechaInicio = $registro?->FechaInicio ? $registro->FechaInicio->format('Y-m-d') : '';
    $fechaFin = $registro?->FechaFin ? $registro->FechaFin->format('Y-m-d') : '';
@endphp
<div class="bg-white rounded-lg shadow-md p-4">
    <h3 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ $titulo }}</h3>
    <div class="space-y-3">
        @foreach ([1, 2, 3] as $n)
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_cve{{ $n }}">Clave {{ $n }}</label>
                    <input type="text" id="{{ $prefijo }}_cve{{ $n }}" maxlength="30"
                        value="{{ $registro?->{'CveEmpl'.$n} }}"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @disabled($bloqueado)>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_nombre{{ $n }}">Nombre {{ $n }}</label>
                    <input type="text" id="{{ $prefijo }}_nombre{{ $n }}" maxlength="150"
                        value="{{ $registro?->{'NomEmpl'.$n} }}"
                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @disabled($bloqueado)>
                </div>
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_inicio">Fecha inicio</label>
                <input type="date" id="{{ $prefijo }}_inicio" value="{{ $fechaInicio }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @disabled($bloqueado)>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_fin">Fecha fin</label>
                <input type="date" id="{{ $prefijo }}_fin" value="{{ $fechaFin }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @disabled($bloqueado)>
            </div>
        </div>
    </div>
    <div class="mt-4 flex items-center justify-end gap-3">
        <span id="{{ $prefijo }}_estado" class="text-xs text-green-600 hidden">Guardado</span>
        @unless($bloqueado)
            <button type="button" onclick="guardarProcesoKm('{{ $prefijo }}')"
                class="px-3 py-1.5 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded">
                Guardar
            </button>
        @endunless
    </div>
</div>
