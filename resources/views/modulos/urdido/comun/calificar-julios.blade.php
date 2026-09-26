{{--
  Modal Calificar Julios (19-01): una vista para Urdido y Engomado.
  @param string      $variante       'urdido' (julios de UrdProduccionUrdido, desde Producción Engomado)
                                     | 'engomado' (registros de EngProduccionEngomado, desde Edición Engomado)
  @param string|null $folio          Folio fijo (urdido). En engomado llega al abrir: abrirModalCalificarJuliosEng(folio).
  @param string|null $usuarioNombre  Nombre a mostrar (default: usuario en sesión)
  JS: resources/js/modulos/urdido/comun/calificar-julios/index.ts
--}}
@php
    $esUrdido = $variante === 'urdido';
    $rutaBase = 'engomado.modulo.produccion.engomado.calificar.julios'.($esUrdido ? '' : '.eng');
    $configCalificar = [
        'variante' => $variante,
        'folio' => $folio ?? null,
        'zona' => config('app.timezone'),
        'rutas' => ['julios' => route($rutaBase.'.get'), 'calificar' => route($rutaBase.'.save')],
        'textos' => [
            'exito' => $esUrdido ? 'Julio calificado' : 'Calificado correctamente',
            'sufijoInfo' => $esUrdido ? ' (urdido)' : '',
        ],
    ];
@endphp
<div id="modalCalificarJulios{{ $esUrdido ? '' : 'Eng' }}"
     class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60"
     role="dialog" aria-modal="true"
     data-calificar-julios='@json($configCalificar)'>
    <div class="bg-white rounded-xl shadow-2xl w-[95%] max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-3 bg-purple-600 text-white">
            <h3 class="text-lg font-semibold flex flex-wrap items-center gap-2 min-w-0">
                <i class="fa-solid fa-clipboard-check shrink-0" aria-hidden="true"></i>
                <span>Calificar Julios &mdash; Folio <span data-cj="folio">{{ $folio ?? '' }}</span></span>
            </h3>
            <button type="button" class="text-white hover:text-gray-200 text-2xl leading-none shrink-0"
                    data-cj-cerrar aria-label="Cerrar">&times;</button>
        </div>

        <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                        <i class="fa-solid fa-user text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="text-xl font-semibold text-gray-900 truncate min-w-0">
                        {{ $usuarioNombre ?? auth()->user()->nombre ?? '—' }}
                    </div>
                </div>
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                        <i class="fa-solid fa-calendar text-xl" aria-hidden="true"></i>
                    </div>
                    <div data-cj="hoy" class="text-xl font-semibold text-gray-900 min-w-0">{{ now()->format('Y-m-d') }}</div>
                </div>
            </div>
        </div>

        <div class="p-4 overflow-auto flex-1">
            <div data-cj="cargando" class="text-center py-6 text-gray-500 hidden">
                <i class="fa-solid fa-spinner fa-spin text-2xl" aria-hidden="true"></i>
                <div class="mt-2">{{ $esUrdido ? 'Cargando julios...' : 'Cargando registros...' }}</div>
            </div>
            <div data-cj="vacio" class="text-center py-6 text-gray-500 hidden">
                {{ $esUrdido ? 'No se encontraron julios para este folio.' : 'No se encontraron registros para este folio.' }}
            </div>

            <table data-cj="tabla" class="w-full text-sm hidden">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="px-3 py-2 text-left">Folio</th>
                        <th class="px-3 py-2 text-left">Julio</th>
                        <th class="px-3 py-2 text-left">Defecto</th>
                    </tr>
                </thead>
                <tbody data-cj="filas"></tbody>
            </table>
        </div>

        <div class="px-5 py-3 bg-gray-50 border-t flex justify-end">
            <button type="button" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded-lg" data-cj-cerrar>Cerrar</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        @vite('resources/js/modulos/urdido/comun/calificar-julios/index.ts')
    @endpush
@endonce
