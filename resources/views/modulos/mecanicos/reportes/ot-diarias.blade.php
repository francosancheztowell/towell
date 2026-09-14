@extends('layouts.app')

@section('page-title', 'Órdenes de Trabajo Diarias')

@section('navbar-right')
    @if ($reporte)
        <button type="button" onclick="descargarOtDiarias('excel')"
            class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button type="button" onclick="descargarOtDiarias('pdf')"
            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button type="button" onclick="descargarImagenOtDiarias()"
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

        <form method="GET" action="{{ route('mecanicos.reportes.ot-diarias') }}"
              class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-4">
            <div>
                <label for="fecha" class="block text-sm font-semibold text-gray-700 mb-1">Fecha de inicio</label>
                <input type="date" id="fecha" name="fecha" value="{{ $fecha }}" required
                       class="px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <button type="submit" id="btn-consultar"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    {{ $fecha === '' ? 'disabled' : '' }}>
                <i class="fas fa-search mr-1"></i> Consultar
            </button>
        </form>

        @if (! $reporte)
            <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-16 text-center">
                <i class="fa-solid fa-calendar-week text-gray-300 text-4xl mb-3"></i>
                <p class="text-gray-600 font-medium">Pulsa Consultar para armar el reporte de 7 días</p>
                <p class="text-sm text-gray-500 mt-1">La fecha de inicio suele ser un lunes; el rango cubre ese día y los seis siguientes.</p>
            </div>
        @else
            <form id="form-export-ot-diarias" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="fecha" value="{{ $reporte['desde'] }}">
                <div id="inputs-export-ot-diarias"></div>
            </form>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div id="hoja-ot-diarias" class="bg-white p-5">
                    <div class="flex flex-col items-start gap-1 mb-5">
                        <img src="{{ asset('images/fondosTowell/logo.png') }}" alt="Towell" class="h-14 mb-1">
                        <h1 class="text-lg font-bold text-gray-900 tracking-wide">ÓRDENES DE TRABAJO DIARIAS</h1>
                        <p class="text-sm text-gray-600">
                            Periodo: {{ \Carbon\Carbon::parse($reporte['desde'])->format('d/m/Y') }}
                            al {{ \Carbon\Carbon::parse($reporte['hasta'])->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        @include('modulos.mecanicos.reportes._ot-diarias-tabla', ['reporte' => $reporte, 'editable' => true])
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($reporte)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    @endif
    <script>
        const excelUrl = @json(route('mecanicos.reportes.ot-diarias.excel'));
        const pdfUrl = @json(route('mecanicos.reportes.ot-diarias.pdf'));
        const imagenTelegramUrl = @json(route('mecanicos.reportes.ot-diarias.telegram-imagen'));
        const fechaActual = @json($fecha);
        const hayReporte = @json((bool) $reporte);
        const nombreImagen = @json($reporte ? 'ot-diarias_'.$reporte['desde'].'_'.$reporte['hasta'].'.png' : 'ot-diarias.png');
        const filas = @json($reporte['mecanicos'] ?? []);

        const fechaInput = document.getElementById('fecha');
        const btnConsultar = document.getElementById('btn-consultar');

        function actualizarConsultar() {
            btnConsultar.disabled = !fechaInput.value;
        }
        fechaInput.addEventListener('change', actualizarConsultar);
        actualizarConsultar();

        function numero(valor) {
            const n = Number.parseFloat(String(valor ?? '').replace(',', '.'));
            return Number.isFinite(n) ? n : 0;
        }

        function fmt1(n) {
            return n.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        }

        function inputsPayload() {
            const payload = {};
            document.querySelectorAll('.js-ot-input').forEach((input) => {
                const cve = input.dataset.cve;
                const campo = input.dataset.campo;
                if (!cve || !campo) return;
                payload[cve] = payload[cve] || {};
                payload[cve][campo] = input.value;
            });
            return payload;
        }

        function recalcularFila(cve) {
            const fila = filas.find((item) => item.cve === cve);
            if (!fila) return;
            const payload = inputsPayload()[cve] || {};
            const otTrama = numero(payload.ot_trama);
            const cumplidasTrama = numero(payload.cumplidas_trama);
            const ocupacionPct = numero(payload.ocupacion_pct);
            const totalRealizadas = numero(fila.realizadas_semana) + otTrama;
            const totalCumplidas = numero(fila.firmadas_semana) + cumplidasTrama;
            const pctCumplimiento = totalRealizadas === 0 ? 0 : (totalCumplidas / totalRealizadas) * 100;
            const pctOtFinal = (ocupacionPct + Math.round(pctCumplimiento * 10) / 10) / 2;
            const celdaR = document.querySelector(`.js-total-realizadas[data-cve="${cve}"]`);
            const celdaC = document.querySelector(`.js-total-cumplidas[data-cve="${cve}"]`);
            const celdaP = document.querySelector(`.js-pct-cumplimiento[data-cve="${cve}"]`);
            const celdaF = document.querySelector(`.js-pct-ot-final[data-cve="${cve}"]`);
            if (celdaR) celdaR.textContent = fmt1(totalRealizadas);
            if (celdaC) celdaC.textContent = fmt1(totalCumplidas);
            if (celdaP) celdaP.textContent = fmt1(Math.round(pctCumplimiento * 10) / 10);
            if (celdaF) celdaF.textContent = fmt1(Math.round(pctOtFinal * 10) / 10);
            fila._otFinal = Math.round(pctOtFinal * 10) / 10;
            fila._otTrama = otTrama;
            fila._cumplidasTrama = cumplidasTrama;
            actualizarPie();
        }

        function actualizarPie() {
            let ot = 0;
            let cumplidas = 0;
            let otFinal = 0;
            filas.forEach((fila) => {
                const payload = inputsPayload()[fila.cve] || {};
                ot += numero(payload.ot_trama);
                cumplidas += numero(payload.cumplidas_trama);
                const ocupacionPct = numero(payload.ocupacion_pct);
                const totalRealizadas = numero(fila.realizadas_semana) + numero(payload.ot_trama);
                const totalCumplidas = numero(fila.firmadas_semana) + numero(payload.cumplidas_trama);
                const pctCumplimiento = totalRealizadas === 0 ? 0 : (totalCumplidas / totalRealizadas) * 100;
                otFinal += (ocupacionPct + Math.round(pctCumplimiento * 10) / 10) / 2;
            });
            const n = filas.length;
            const pieOt = document.querySelector('.js-pie-ot-trama');
            const pieC = document.querySelector('.js-pie-cumplidas-trama');
            const pieF = document.querySelector('.js-pie-ot-final');
            if (pieOt) pieOt.textContent = fmt1(ot);
            if (pieC) pieC.textContent = fmt1(cumplidas);
            if (pieF) pieF.textContent = fmt1(n === 0 ? 0 : Math.round((otFinal / n) * 10) / 10);
        }

        document.querySelectorAll('.js-ot-input').forEach((input) => {
            input.addEventListener('input', () => recalcularFila(input.dataset.cve));
        });

        function descargarOtDiarias(tipo) {
            const form = document.getElementById('form-export-ot-diarias');
            const holder = document.getElementById('inputs-export-ot-diarias');
            holder.innerHTML = '';
            Object.entries(inputsPayload()).forEach(([cve, campos]) => {
                Object.entries(campos).forEach(([campo, valor]) => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `inputs[${cve}][${campo}]`;
                    hidden.value = valor;
                    holder.appendChild(hidden);
                });
            });
            form.action = tipo === 'pdf' ? pdfUrl : excelUrl;
            form.submit();
        }

        async function descargarImagenOtDiarias() {
            if (!hayReporte || typeof html2canvas !== 'function') {
                window.notify.error('No se puede generar la imagen todavía.');
                return;
            }

            const hoja = document.getElementById('hoja-ot-diarias');
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
                formData.append('fecha', fechaActual);
                Object.entries(inputsPayload()).forEach(([cve, campos]) => {
                    Object.entries(campos).forEach(([campo, valor]) => {
                        formData.append(`inputs[${cve}][${campo}]`, valor);
                    });
                });
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

        window.descargarOtDiarias = descargarOtDiarias;
        window.descargarImagenOtDiarias = descargarImagenOtDiarias;
    </script>
@endsection
