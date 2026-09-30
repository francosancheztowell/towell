{{-- Tarjeta Montado / Enhebrado de Karl Mayer. El JS está en resources/js/modulos/atadores/comun/proceso-km.ts
     (lo cargan calificar/index.ts y proceso-km/index.ts); la config llega en el data-pagina de la página. --}}
@php
    $bloqueado = in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado']);
    $fechaInicio = $registro?->FechaInicio ? \Carbon\Carbon::parse($registro->FechaInicio)->format('Y-m-d\TH:i') : '';
    $fechaFin = $registro?->FechaFin ? \Carbon\Carbon::parse($registro->FechaFin)->format('Y-m-d\TH:i') : '';
@endphp
<div class="bg-white rounded-lg shadow-md p-4">
    <h3 id="titulo-{{ $prefijo }}" class="text-xl font-bold text-center text-gray-800 mb-1 border-b pb-2">{{ $titulo }}</h3>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_inicio">Fecha inicio</label>
                <input type="datetime-local" id="{{ $prefijo }}_inicio" value="{{ $fechaInicio }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    data-km-fecha="{{ $prefijo }}"
                    @disabled($bloqueado)>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_fin">Fecha fin</label>
                <input type="datetime-local" id="{{ $prefijo }}_fin" value="{{ $fechaFin }}"
                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    data-km-fecha="{{ $prefijo }}"
                    @disabled($bloqueado)>
            </div>
        </div>
        @foreach ([1, 2, 3] as $n)
            @php
                $cveGuardada = trim((string) ($registro?->{'CveEmpl'.$n} ?? ''));
                $nomGuardado = trim((string) ($registro?->{'NomEmpl'.$n} ?? ''));
            @endphp
            <div class="rounded-lg border border-gray-200 p-2">
                <div class="flex items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="block text-xs font-semibold text-gray-600 mb-1" for="{{ $prefijo }}_cve{{ $n }}">Empleado {{ $n }}</label>
                        <select id="{{ $prefijo }}_cve{{ $n }}" data-km-empleado="{{ $prefijo }}" data-fila="{{ $n }}"
                            data-nombre-destino="{{ $prefijo }}_nombre{{ $n }}" data-valor-actual="{{ $cveGuardada }}"
                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @disabled($bloqueado)>
                            <option value="">Seleccione…</option>
                            @if($cveGuardada !== '')
                                <option value="{{ $cveGuardada }}" data-nombre="{{ $nomGuardado }}" selected>{{ $cveGuardada }}{{ $nomGuardado !== '' ? ' - '.$nomGuardado : '' }}</option>
                            @endif
                        </select>
                    </div>
                    @unless($bloqueado)
                        <button type="button" data-accion="km-asignarme" data-prefijo="{{ $prefijo }}" data-fila="{{ $n }}" title="Asignarme a mí en empleado {{ $n }}" aria-label="Asignarme a mí en empleado {{ $n }}"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                            <i class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                        </button>
                    @endunless
                </div>
                <input type="hidden" id="{{ $prefijo }}_nombre{{ $n }}" value="{{ $nomGuardado }}">
            </div>
        @endforeach
    </div>
</div>

