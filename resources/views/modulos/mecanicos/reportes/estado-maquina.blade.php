@extends('layouts.app')

@section('page-title', 'Reporte Estado de Máquina')

@section('navbar-right')
    @if ($reporte)
        <button type="button" onclick="descargarEstadoMaquina('excel')"
            class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button type="button" onclick="descargarEstadoMaquina('pdf')"
            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button type="button" onclick="descargarImagenEstadoMaquina()"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-image"></i> Imagen
        </button>
    @endif
@endsection

@section('content')
    <div class="w-full p-4">
        @if ($error)
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">{{ $error }}</div>
        @endif

        <form method="GET" action="{{ route('mecanicos.reportes.estado-maquina') }}"
              class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <div>
                <label for="mes" class="block text-sm font-semibold text-gray-700 mb-1">Mes</label>
                <input type="month" id="mes" name="mes" value="{{ $mes }}" required
                       class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div>
                <label for="semana" class="block text-sm font-semibold text-gray-700 mb-1">Semana</label>
                <select id="semana" name="semana" required {{ $mes === '' ? 'disabled' : '' }}
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm min-w-[220px]">
                    <option value="">Seleccione una semana</option>
                    @foreach ($semanas as $opcion)
                        <option value="{{ $opcion['lunes'] }}" @selected($semana === $opcion['lunes'])>
                            {{ $opcion['etiqueta'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="btn-consultar"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $mes === '' || $semana === '' ? 'disabled' : '' }}>
                <i class="fas fa-search mr-1"></i> Consultar
            </button>
        </form>

        @if (! $reporte)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-calendar-week text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-600 font-medium">Selecciona mes y semana para crear el reporte</p>
            </div>
        @else
            <form id="form-export-estado-maquina" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="mes" value="{{ $reporte['mes'] }}">
                <input type="hidden" name="semana" value="{{ $reporte['lunes'] }}">
                <div id="prioridades-export"></div>
            </form>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div id="hoja-verificacion" class="bg-white p-5">
                    <div class="flex flex-col items-start gap-1 mb-5">
                        <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell" class="h-14 mb-1">
                        <h1 class="text-lg font-bold text-gray-900 tracking-wide">HOJA DE VERIFICACIÓN ESTADO MÁQUINA</h1>
                        <p class="text-sm text-gray-600">
                            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="border-collapse text-xs tabular-nums">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="border border-gray-400 bg-gray-100 px-3 py-2 text-center font-bold sticky left-0 z-20 min-w-[180px]">Control</th>
                                    <th rowspan="2" class="border border-gray-400 bg-gray-100 px-2 py-2 text-center font-bold sticky left-[180px] z-20 min-w-[84px]">Prioridad</th>
                                    @foreach ($reporte['salones'] as $salon)
                                        <th colspan="{{ count($salon['telares']) }}"
                                            class="border border-gray-400 px-2 py-2 text-center font-bold whitespace-nowrap"
                                            style="background-color: #{{ $salon['color'] }}">
                                            {{ $salon['nombre'] }}
                                        </th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach ($reporte['salones'] as $salon)
                                        @foreach ($salon['telares'] as $telar)
                                            <th class="border border-gray-400 px-0 py-1 align-bottom"
                                                style="background-color: #{{ $salon['color'] }}"
                                                title="{{ $telar['nombre'] }}">
                                                <div class="em-telar-stack">
                                                    <span class="em-telar-label">Telar</span>
                                                    <span class="em-telar-num">{{ $telar['id'] }}</span>
                                                    <span class="em-calificacion">calificación</span>
                                                </div>
                                            </th>
                                        @endforeach
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reporte['actividades'] as $actividad)
                                    <tr>
                                        <td class="border border-gray-300 bg-white px-3 py-1.5 sticky left-0 z-10 whitespace-nowrap">{{ $actividad['nombre'] }}</td>
                                        <td class="border border-gray-300 bg-white px-1 py-1.5 sticky left-[180px] z-10 text-center">
                                            <input type="number" min="1" max="3" step="1"
                                                   name="prioridad-{{ $actividad['id'] }}"
                                                   data-actividad-id="{{ $actividad['id'] }}"
                                                   value="{{ $actividad['prioridad'] }}"
                                                   class="js-prioridad w-14 mx-auto block border border-gray-300 rounded text-center text-xs py-0.5">
                                        </td>
                                        @foreach ($reporte['salones'] as $salon)
                                            @foreach ($salon['telares'] as $telar)
                                                @php $valor = $actividad['valores'][$telar['id']] ?? 0; @endphp
                                                <td class="border border-gray-300 px-2 py-1.5 text-center font-semibold min-w-[72px]"
                                                    style="{{ $valor === 0 ? 'color:#808080' : 'background-color:#'.(\App\Services\Mecanicos\ReporteEstadoMaquinaService::COLOR_CELDA[$valor] ?? 'FFFFFF') }}">
                                                    {{ $valor }}
                                                </td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <style>
                .em-telar-stack {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 2px;
                    min-width: 72px;
                    min-height: 132px;
                    padding: 4px 0 6px;
                }
                .em-telar-label {
                    font-weight: 700;
                    line-height: 1.1;
                    text-align: center;
                }
                .em-telar-num {
                    font-weight: 700;
                    font-size: 13px;
                    line-height: 1.1;
                    text-align: center;
                }
                .em-calificacion {
                    writing-mode: vertical-rl;
                    transform: rotate(180deg);
                    font-size: 10px;
                    font-weight: 500;
                    letter-spacing: 0.04em;
                    white-space: nowrap;
                    line-height: 1;
                    margin-top: 4px;
                }
            </style>
        @endif
    </div>

    @if ($reporte)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    @endif
    <script>
        const semanasUrl = @json(route('mecanicos.reportes.estado-maquina.semanas'));
        const excelUrl = @json(route('mecanicos.reportes.estado-maquina.excel'));
        const pdfUrl = @json(route('mecanicos.reportes.estado-maquina.pdf'));
        const imagenTelegramUrl = @json(route('mecanicos.reportes.estado-maquina.telegram-imagen'));
        const mesActual = @json($mes);
        const semanaActual = @json($semana);
        const hayReporte = @json((bool) $reporte);
        const nombreImagen = @json($reporte ? 'hoja-verificacion-estado-maquina_'.$reporte['desde'].'_'.$reporte['hasta'].'.png' : 'hoja-verificacion-estado-maquina.png');

        const mesInput = document.getElementById('mes');
        const semanaSelect = document.getElementById('semana');
        const btnConsultar = document.getElementById('btn-consultar');

        function actualizarConsultar() {
            btnConsultar.disabled = !mesInput.value || !semanaSelect.value;
        }

        async function cargarSemanas(mes, seleccionar) {
            semanaSelect.innerHTML = '<option value="">Seleccione una semana</option>';
            semanaSelect.disabled = true;
            actualizarConsultar();
            if (!mes) return;

            try {
                const data = await window.http.get(semanasUrl, { params: { mes } });
                (data.semanas || []).forEach((semana) => {
                    const option = document.createElement('option');
                    option.value = semana.lunes;
                    option.textContent = semana.etiqueta;
                    if (seleccionar && seleccionar === semana.lunes) option.selected = true;
                    semanaSelect.appendChild(option);
                });
                semanaSelect.disabled = false;
            } catch (err) {
                window.notify.error(err.message || 'No se pudieron cargar las semanas');
            }
            actualizarConsultar();
        }

        mesInput.addEventListener('change', () => {
            cargarSemanas(mesInput.value, null);
        });
        semanaSelect.addEventListener('change', actualizarConsultar);
        actualizarConsultar();

        if (mesActual && semanaSelect.options.length <= 1) {
            cargarSemanas(mesActual, semanaActual);
        }

        function prioridadesPayload() {
            const prioridades = {};
            document.querySelectorAll('.js-prioridad').forEach((input) => {
                const valor = String(input.value || '').trim();
                if (valor === '1' || valor === '2' || valor === '3') {
                    prioridades[input.dataset.actividadId] = valor;
                }
            });
            return prioridades;
        }

        function descargarEstadoMaquina(tipo) {
            const form = document.getElementById('form-export-estado-maquina');
            const holder = document.getElementById('prioridades-export');
            holder.innerHTML = '';
            Object.entries(prioridadesPayload()).forEach(([id, valor]) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `prioridades[${id}]`;
                hidden.value = valor;
                holder.appendChild(hidden);
            });
            form.action = tipo === 'pdf' ? pdfUrl : excelUrl;
            form.submit();
        }

        async function descargarImagenEstadoMaquina() {
            if (!hayReporte || typeof html2canvas !== 'function') {
                window.notify.error('No se puede generar la imagen todavía.');
                return;
            }

            const hoja = document.getElementById('hoja-verificacion');
            window.notify.loading('Generando imagen...');
            try {
                const canvas = await html2canvas(hoja, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    useCORS: true,
                    width: hoja.scrollWidth,
                    windowWidth: Math.max(hoja.scrollWidth, hoja.clientWidth),
                });
                const blob = await new Promise((resolve, reject) => {
                    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('No se pudo crear la imagen.'))), 'image/png');
                });
                const file = new File([blob], nombreImagen, { type: 'image/png' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = nombreImagen;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);

                const formData = new FormData();
                formData.append('imagen', file, file.name);
                formData.append('mes', mesActual);
                formData.append('semana', semanaActual);
                try {
                    await window.http.upload(imagenTelegramUrl, formData);
                } catch (err) {
                    window.notify.warning('La imagen se descargó, pero no se pudo enviar por Telegram.');
                    return;
                }
                window.notify.success('Imagen descargada y enviada por Telegram.');
            } catch (err) {
                window.notify.error(err.message || 'No se pudo generar la imagen.');
            } finally {
                window.notify.close();
            }
        }

        window.descargarEstadoMaquina = descargarEstadoMaquina;
        window.descargarImagenEstadoMaquina = descargarImagenEstadoMaquina;
    </script>
@endsection
