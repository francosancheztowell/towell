@extends('layouts.app')

@section('page-title', 'Reporte Marcas Finales')

@section('content')
@php
    use Carbon\Carbon;
    // Indexar por turno para acceso directo
    $porTurno = collect($tablas)->keyBy('turno');
    // Unificar lista de telares (de la secuencia cargada en cada tabla)
    $telares = collect([]);
    foreach ($tablas as $t) { $telares = $telares->merge($t['telares']); }
    $telares = $telares->unique()->sort()->values();
    $fmtEfi = function($linea){
        if(!$linea) return '';
        $e = $linea->Eficiencia ?? $linea->EficienciaSTD ?? $linea->EficienciaStd ?? null;
        if($e === null || $e === '') return '';
        if(is_numeric($e) && $e <= 1) $e = $e * 100; // fracción a %
        return intval(round($e)).'%';
    };
    $get = function($turno, $telar) use ($porTurno){
        return optional(optional($porTurno->get($turno))['lineas'])->get($telar);
    };
    // Marca de cobertura: el turno 4 (comodín) no se guarda; el registro vive en el
    // turno que cubrió, pero el encabezado se muestra como "Turno 4".
    $tituloTurno = function($turno) use ($porTurno) {
        $t = $porTurno->get($turno);
        if (! ($t['coberturaT4'] ?? false)) { return 'Turno '.$turno; }
        return '<span title="Turno '.$turno.' cubierto por personal de turno 4: '.e($t['empleado'] ?? '').'">Turno 4</span>';
    };
@endphp

<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Marcas Finales de Turno</h2>
        </div>
        <div class="flex gap-2">
            <button onclick="exportarExcel()" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar Excel
            </button>
            <button onclick="descargarPDF()" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Descargar PDF
            </button>
            <button onclick="notificarTelegram()" id="btn-telegram" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-100 text-sky-600 text-sm font-medium rounded-md transition-colors border border-sky-200">
                <span class="mr-2 text-blue-600 text-lg"><svg class="inline-block h-[1em] w-[1em] align-[-0.125em] fill-current" viewBox="0 0 496 512" aria-hidden="true"><path d="M248 8C111 8 0 119 0 256S111 504 248 504 496 393 496 256 385 8 248 8zM363 176.7c-3.7 39.2-19.9 134.4-28.1 178.3-3.5 18.6-10.3 24.8-16.9 25.4-14.4 1.3-25.3-9.5-39.3-18.7-21.8-14.3-34.2-23.2-55.3-37.2-24.5-16.1-8.6-25 5.3-39.5 3.7-3.8 67.1-61.5 68.3-66.7 .2-.7 .3-3.1-1.2-4.4s-3.6-.8-5.1-.5q-3.3 .7-104.6 69.1-14.8 10.2-26.9 9.9c-8.9-.2-25.9-5-38.6-9.1-15.5-5-27.9-7.7-26.8-16.3q.8-6.7 18.5-13.7 108.4-47.2 144.6-62.3c68.9-28.6 83.2-33.6 92.5-33.8 2.1 0 6.6 .5 9.6 2.9a10.5 10.5 0 0 1 3.5 6.7A43.8 43.8 0 0 1 363 176.7z"/></svg></span> Notificar Telegram
            </button>
        </div>
    </div>

    <div class="flex-1 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-auto h-full">
            <table class="w-full text-xs border-collapse">
                <thead class="bg-blue-600 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" rowspan="2">Telar</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" colspan="7">{!! $tituloTurno(1) !!}</th>
                        <th class="px-3 py-2 border-r border-blue-500 text-center align-middle" colspan="7">{!! $tituloTurno(2) !!}</th>
                        <th class="px-3 py-2 text-center align-middle" colspan="7">{!! $tituloTurno(3) !!}</th>
                    </tr>
                    <tr class="bg-blue-700/90">
                        @for ($i=0;$i<3;$i++)
                            <th class="px-2 py-1 text-center align-middle font-semibold">% Ef</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">Marcas</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">Horas</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">TRAMA</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">PIE</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold">RIZO</th>
                            <th class="px-2 py-1 text-center align-middle font-semibold {{ $i < 2 ? 'border-r border-blue-500' : '' }}">OTROS</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($telares as $telar)
                        @php
                            $t1 = $get(1, $telar);
                            $t2 = $get(2, $telar);
                            $t3 = $get(3, $telar);
                            $val = function($l,$c){ return $l ? ($l->$c ?? '') : ''; };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-semibold text-gray-800 border-r border-gray-200 text-center align-middle">{{ $telar }}</td>
                            <!-- Turno 1 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t1) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Horas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t1,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle border-r border-gray-200">{{ $val($t1,'Otros') }}</td>
                            <!-- Turno 2 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t2) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Horas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t2,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle border-r border-gray-200">{{ $val($t2,'Otros') }}</td>
                            <!-- Turno 3 -->
                            <td class="px-2 py-2 text-center align-middle">{{ $fmtEfi($t3) }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Marcas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Horas') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Trama') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Pie') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Rizo') }}</td>
                            <td class="px-2 py-2 text-center align-middle">{{ $val($t3,'Otros') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="22" class="px-4 py-6 text-center text-gray-500 text-sm">Sin datos para la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 bg-gray-50 border-t text-xs text-gray-500">
            <span>Folio Turno 1: {{ optional($porTurno->get(1))['folio'] ?? '—' }}</span>
            <span class="mx-3">Folio Turno 2: {{ optional($porTurno->get(2))['folio'] ?? '—' }}</span>
            <span>Folio Turno 3: {{ optional($porTurno->get(3))['folio'] ?? '—' }}</span>
        </div>
    </div>
</div>

<script>
function exportarExcel() {
    const fecha = '{{ $fecha }}';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("marcas.reporte.excel") }}';
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    
    const fechaInput = document.createElement('input');
    fechaInput.type = 'hidden';
    fechaInput.name = 'fecha';
    fechaInput.value = fecha;
    
    form.appendChild(csrfToken);
    form.appendChild(fechaInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

async function descargarPDF() {
    const fecha = '{{ $fecha }}';
    const url = '{{ route("marcas.reporte.pdf") }}';
    const token = '{{ csrf_token() }}';

    try {
        const body = new URLSearchParams({ fecha });

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/pdf'
            },
            body
        });

        if (!response.ok) {
            const text = await response.text();
            console.error('Error al descargar PDF:', response.status, text);
            alert('No se pudo generar el PDF. Revisa la consola para más detalle.');
            return;
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = `marcas_finales_${String(fecha).replaceAll('/', '-')}.pdf`;
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        console.error('Excepción al descargar PDF:', e);
        alert('Ocurrió un error al intentar descargar el PDF.');
    }
}
async function notificarTelegram() {
    const btn = document.getElementById('btn-telegram');
    const originalHtml = btn.innerHTML;
    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Enviando...';

        const fecha = '{{ $fecha }}';
        const url = '{{ route("marcas.reporte.telegram") }}';
        const token = '{{ csrf_token() }}';

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ fecha })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            console.error('Error al notificar por Telegram:', data);
            alert(data.message || 'No se pudo enviar la notificación por Telegram.');
            return;
        }

        alert('Reporte enviado por Telegram exitosamente.');
    } catch (error) {
        console.error('Excepción al notificar por Telegram:', error);
        alert('Ocurrió un error al intentar enviar la notificación por Telegram.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
</script>
@endsection