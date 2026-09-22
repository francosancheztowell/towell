@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Atado de Julio" />
@endsection

@section('content')
<div class="container mx-auto px-3 py-4 max-w-3xl">
    <div class="bg-white rounded-lg shadow-xl">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                <div>
                    <label for="selectTelar" class="block text-base font-semibold text-gray-700 mb-2">Seleccionar Telar</label>
                    <select id="selectTelar" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Seleccione un telar --</option>
                        @foreach ($telares as $telar)
                            <option value="{{ $telar['id'] }}"{!! $telar['km'] ? ' data-km="1"' : '' !!}>{{ $telar['id'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-base font-semibold text-gray-700 mb-2">Tipo</label>
                    <div id="tiposRizoPie" class="flex gap-6 items-center h-12">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="tipoTelar" id="radioRizo" value="rizo" class="form-radio h-6 w-6 text-blue-600">
                            <span class="ml-2 text-lg text-gray-700 font-medium">Rizo</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="tipoTelar" id="radioPie" value="pie" class="form-radio h-6 w-6 text-blue-600">
                            <span class="ml-2 text-lg text-gray-700 font-medium">Pie</span>
                        </label>
                    </div>
                    <div id="tiposBarras" class="hidden flex flex-wrap gap-x-4 gap-y-2 items-center min-h-12">
                        @foreach ([1, 2, 3, 4] as $barra)
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="tipoTelar" value="{{ $barra }}" class="form-radio h-6 w-6 text-blue-600">
                                <span class="ml-2 text-lg text-gray-700 font-medium">Barra {{ $barra }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Microinteraccion: mientras se consulta el telar, el bloque se atenua --}}
            <div id="detallesTelar" class="hidden bg-gradient-to-br from-blue-50 to-gray-50 rounded-lg p-6 border-2 border-blue-200 transition-opacity duration-200">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Información del Telar
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach ([
                        'no_telar' => 'No. Telar',
                        'cuenta' => 'Cuenta',
                        'calibre' => 'Calibre',
                        'tipo' => 'Tipo',
                        'tipo_atado' => 'Tipo Atado',
                        'no_orden' => 'No. Orden',
                        'no_julio' => 'No. Julio',
                        'metros' => 'Metros',
                    ] as $campo => $etiqueta)
                        <div>
                            <label class="block text-sm font-semibold text-gray-600 mb-1">{{ $etiqueta }}</label>
                            <input type="text" id="detalle_{{ $campo }}" readonly
                                   class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-md text-base font-medium text-gray-800">
                        </div>
                    @endforeach
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Hora Paro</label>
                        <input type="text" id="detalle_hora_paro" readonly
                               class="w-full py-3 bg-green-50 border border-green-300 rounded-md text-3xl font-bold text-green-700 text-center">
                    </div>
                </div>
            </div>

            <div id="mensajeNoData" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-400 text-lg flex-shrink-0"></i>
                    <p class="ml-3 text-base text-yellow-700">No se encontraron datos para el telar y tipo seleccionados.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 p-4 border-t border-gray-200">
            <button type="button" id="btnNotificar"
                    class="px-5 py-2.5 text-base bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
                    disabled>
                <i class="fas fa-bell mr-1"></i>
                <span data-label>Notificar</span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectTelar = document.getElementById('selectTelar');
        const detalles = document.getElementById('detallesTelar');
        const sinDatos = document.getElementById('mensajeNoData');
        const btnNotificar = document.getElementById('btnNotificar');
        const etiquetaBoton = btnNotificar.querySelector('[data-label]');
        const tiposRizoPie = document.getElementById('tiposRizoPie');
        const tiposBarras = document.getElementById('tiposBarras');
        const campos = ['no_telar', 'cuenta', 'calibre', 'tipo', 'tipo_atado', 'no_orden', 'no_julio', 'metros'];

        let registroActual = null;

        const esKm = () => selectTelar.selectedOptions[0]?.dataset.km === '1';

        const aplicarTipos = () => {
            const km = selectTelar.value !== '' && esKm();
            tiposRizoPie.classList.toggle('hidden', km);
            tiposBarras.classList.toggle('hidden', !km);
            document.querySelectorAll('input[name="tipoTelar"]').forEach((radio) => { radio.checked = false; });
        };

        const textoTipo = (valor) => /^[1-4]$/.test(String(valor).trim()) ? `Barra ${valor}` : (valor ?? '');

        const limpiar = () => {
            detalles.classList.add('hidden');
            sinDatos.classList.add('hidden');
            registroActual = null;
            btnNotificar.disabled = true;
        };

        async function buscarDetalles() {
            const telar = selectTelar.value;
            const tipo = document.querySelector('input[name="tipoTelar"]:checked')?.value;
            if (!telar || !tipo) return;

            detalles.classList.add('opacity-40');
            try {
                const data = await window.http.get(@json(route('notificar.atado.julio')), {
                    params: { no_telar: telar, tipo: tipo },
                });

                if (!data.detalles) {
                    limpiar();
                    sinDatos.classList.remove('hidden');
                    return;
                }

                registroActual = data.detalles;
                sinDatos.classList.add('hidden');

                for (const campo of campos) {
                    const valor = data.detalles[campo] ?? '';
                    document.getElementById('detalle_' + campo).value = campo === 'tipo' ? textoTipo(valor) : valor;
                }
                document.getElementById('detalle_hora_paro').value =
                    new Date().toLocaleTimeString('es-MX', { hour12: false });

                detalles.classList.remove('hidden');
                btnNotificar.disabled = false;
            } catch (error) {
                limpiar();
                window.notify.error('Ocurrió un error al buscar los detalles del telar');
            } finally {
                detalles.classList.remove('opacity-40');
            }
        }

        selectTelar.addEventListener('change', () => { limpiar(); aplicarTipos(); buscarDetalles(); });
        document.querySelectorAll('input[name="tipoTelar"]').forEach((radio) => {
            radio.addEventListener('change', () => { limpiar(); buscarDetalles(); });
        });

        btnNotificar.addEventListener('click', async () => {
            if (!registroActual) return;

            const horaParo = document.getElementById('detalle_hora_paro').value;
            if (!horaParo) {
                window.notify.warning('No hay hora registrada para notificar');
                return;
            }

            // Microinteraccion: el boton se bloquea y dice en que va.
            btnNotificar.disabled = true;
            etiquetaBoton.textContent = 'Notificando…';
            try {
                const data = await window.http.post(@json(route('notificar.atado.julio.notificar')), {
                    id: registroActual.id,
                    horaParo: horaParo,
                    no_telar: registroActual.no_telar,
                    tipo: registroActual.tipo,
                });

                if (!data.success) throw new Error(data.error || 'Error al notificar');

                window.notify.success(data.message || 'Telar notificado');
                selectTelar.value = '';
                aplicarTipos();
                limpiar();
            } catch (error) {
                window.notify.error(error.data?.error || error.message || 'Ocurrió un error al notificar el telar');
            } finally {
                etiquetaBoton.textContent = 'Notificar';
                btnNotificar.disabled = registroActual === null;
            }
        });
    });
</script>
@endpush
