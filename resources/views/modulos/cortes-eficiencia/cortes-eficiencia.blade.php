@extends('layouts.app')

@php
    $soloLectura = $soloLectura ?? false;
    $folioInicial = $folioInicial ?? request()->query('folio');
    $tituloPagina = $soloLectura ? 'Visualizar Corte de Eficiencia' : 'Cortes de Eficiencia';
@endphp

@section('page-title', $tituloPagina)

{{-- Sin botones superiores ni barra de datos --}}
@section('navbar-right')
    <!-- Badge del folio actual -->
    <div id="badge-folio" class="hidden items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg shadow-md">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span class="text-sm font-semibold">Folio:</span>
        <span id="folio-text" class="text-sm font-bold">--</span>
    </div>
@endsection

@section('content')
@php
    // Paleta por horario (evita duplicación de clases)
    $horarios = [
        1 => ['title' => 'HORARIO 1', 'shade' => 'bg-blue-400',   'hover' => 'hover:bg-blue-500',   'check' => 'text-blue-600',   'cellHover' => 'hover:bg-blue-100'],
        2 => ['title' => 'HORARIO 2', 'shade' => 'bg-green-400',  'hover' => 'hover:bg-green-500',  'check' => 'text-green-600',  'cellHover' => 'hover:bg-green-100'],
        3 => ['title' => 'HORARIO 3', 'shade' => 'bg-yellow-400', 'hover' => 'hover:bg-yellow-500', 'check' => 'text-yellow-600', 'cellHover' => 'hover:bg-yellow-100'],
    ];
@endphp
 
@if(session('warning'))
    <div class="container mx-auto px-4 pt-4">
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded" role="alert">
            <p class="font-bold">Atención</p>
            <p>{{ session('warning') }}</p>
        </div>
    </div>
@endif

<div class="container mx-auto px-4 py-6">
    <!-- Tabla principal, sin encabezado de folio/turno -->
    <div id="tabla-cortes" class="bg-white shadow overflow-hidden">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto max-h-[80vh]">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-white">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[80px]">TELAR</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[100px]">STD</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 bg-blue-500 min-w-[120px]">% EF STD</th>
                            @foreach($horarios as $h => $c)
                                <th colspan="3" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider sticky top-0 z-30 {{ $c['shade'] }}">
                                    <div class="flex items-center justify-center gap-2">
                                        <span>{{ $c['title'] }}</span>
                                        <button type="button" class="p-1 rounded focus:outline-none {{ $c['hover'] }} text-white" data-action="tomar-hora" data-horario="{{ $h }}" title="Tomar hora Horario {{ $h }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <span id="hora-horario-{{ $h }}" class="text-[11px] opacity-75">--:--</span>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                            <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                            <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 bg-blue-500"></th>
                            @foreach($horarios as $h => $c)
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[100px]">RPM</th>
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[100px]">% EF</th>
                                <th class="px-4 py-3 text-xs font-medium text-white sticky top-0 z-30 {{ $c['shade'] }} min-w-[80px]">Obs</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="telares-body" class="bg-white divide-y divide-gray-100">
                        @foreach(($telares ?? []) as $telar)
                            <tr class="hover:bg-blue-50">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $telar }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed" placeholder="Cargando..." data-telar="{{ $telar }}" data-field="rpm_std" readonly>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded text-sm bg-gray-100 text-gray-600 text-center cursor-not-allowed" placeholder="Cargando..." data-telar="{{ $telar }}" data-field="eficiencia_std" readonly>
                                </td>
                                @foreach($horarios as $h => $c)
                                    <!-- RPM -->
                                    <td class="border border-gray-300 px-1 py-2">
                                        <input type="number" 
                                            class="valor-input rpm-input w-full px-2 py-1 border border-gray-200 rounded text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400" 
                                            data-telar="{{ $telar }}" 
                                            data-horario="{{ $h }}" 
                                            data-type="rpm" 
                                            value="0" 
                                            min="0" 
                                            max="400"
                                            placeholder="0">
                                    </td>
                                    <!-- EF -->
                                    <td class="border border-gray-300 px-1 py-2">
                                        <input type="number" 
                                            class="valor-input ef-input w-full px-2 py-1 border border-gray-200 rounded text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400" 
                                            data-telar="{{ $telar }}" 
                                            data-horario="{{ $h }}" 
                                            data-type="eficiencia" 
                                            value="0" 
                                            min="0" 
                                            max="100"
                                            placeholder="0%">
                                    </td>
                                    <!-- Obs -->
                                    <td class="border border-gray-300 px-0 py-2 w-10 text-center">
                                        <input type="checkbox" class="obs-checkbox w-3 h-3 {{ $c['check'] }} bg-gray-100 border-gray-300 rounded focus:ring-offset-0 focus:ring-0" data-telar="{{ $telar }}" data-horario="{{ $h }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    'use strict';

    /** Estado interno (sin inputs visibles) */
    const state = {
        folio: null,
        fecha: new Date().toISOString().split('T')[0],
        turno: '',
        usuario: '',
        noEmpleado: '',
        status: 'En Proceso',
        isNewRecord: true,
        observaciones: {},
        debounceTimer: null,
        fallasCache: null,
        fallasLoading: null,
    };
    const PAGE_MODE = @json([
        'soloLectura' => $soloLectura,
        'folioInicial' => $folioInicial,
    ]);

    /** Rutas */
    const routes = {
        store: @json(route('cortes.eficiencia.store')),
        consultar: @json(route('cortes.eficiencia.consultar')),
        turnoInfo: '/modulo-cortes-de-eficiencia/turno-info',
        datosPrograma: '/modulo-cortes-de-eficiencia/datos-programa-tejido',
        datosTelares: '/modulo-cortes-de-eficiencia/datos-telares',
        fallasCe: '/modulo-cortes-de-eficiencia/fallas',
        generarFolio: '/modulo-cortes-de-eficiencia/generar-folio',
        getCorte: (folio) => `/modulo-cortes-de-eficiencia/${encodeURIComponent(folio)}`,
        guardarHora: '/modulo-cortes-de-eficiencia/guardar-hora',
    };

    /** Utilidades */
    const csrf = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const fetchJSON = async (url, opts = {}) => { const r = await fetch(url, opts); if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); };
    const baseHeaders = () => ({ 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' });
    const debounce = (fn, ms=1000) => (...a) => { clearTimeout(state.debounceTimer); state.debounceTimer = setTimeout(() => fn(...a), ms); };
    const showToast = (o={}) => Swal.fire({ toast:true, position:'top-end', timer:o.timer??2000, showConfirmButton:false, icon:o.icon??'success', title:o.title??'', html:o.html, text:o.text });
    const parsePct = (s) => { if (s==null) return null; const n = parseFloat(String(s).replace('%','')); return Number.isFinite(n) ? n : null; };
    const pad2 = (n) => String(n).padStart(2,'0');
    const horaActualStr = () => { const d=new Date(); return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`; };
    const emojiHorario = (h) => (h===1?'':(h===2?'<i class="fa-solid fa-sun"></i>':'<i class="fa-solid fa-moon"></i>'));

    const els = {
        body: () => document.getElementById('telares-body'),
        horaH: (h) => document.getElementById(`hora-horario-${h}`),
        badgeFolio: () => document.getElementById('badge-folio'),
        folioText: () => document.getElementById('folio-text'),
    };

    const horarioTomado = (h) => {
        const txt = els.horaH(h)?.textContent?.trim();
        return txt && txt !== '--:--' && txt !== '--';
    };

    const requireHorario = (h) => {
        if (horarioTomado(h)) return true;
        showToast({ icon:'warning', title:`Toma primero la hora del horario ${h}` });
        return false;
    };

    const leerHorarios = () => {
        const val = (h) => {
            const t = els.horaH(h)?.textContent?.trim() || '';
            return (t && t !== '--:--' && t !== '--') ? t : null;
        };
        return { 1: val(1), 2: val(2), 3: val(3) };
    };

    async function asegurarTurno(){
        if (!state.turno) {
            try { await cargarTurnoActual(); } catch {}
        }
        return state.turno;
    }

    /** Actualizar badge del folio */
    function actualizarBadgeFolio() {
        const badge = els.badgeFolio();
        const text = els.folioText();
        if (!badge || !text) return;

        if (state.folio) {
            text.textContent = state.folio;
            badge.classList.remove('hidden');
            badge.classList.add('flex');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('flex');
        }
    }

    /** Init */
    document.addEventListener('DOMContentLoaded', async () => {
        // Verificar si hay mensaje de warning de sesión
        const warningMessage = @json(session('warning'));
        if (warningMessage) {
            await Swal.fire({
                icon: 'warning',
                title: 'Folio en proceso',
                text: warningMessage,
                confirmButtonText: 'Ir a consultar',
                confirmButtonColor: '#3085d6',
                allowOutsideClick: false
            });
            window.location.href = '{{ route("cortes.eficiencia.consultar") }}';
            return;
        }

        bindEvents();
        if (PAGE_MODE.soloLectura) aplicarModoSoloLectura();
        try { await Promise.all([cargarTurnoActual(), cargarDatosTelaresStd()]); } catch {}
        const qp = PAGE_MODE.folioInicial || new URLSearchParams(location.search).get('folio');
        try {
            if (qp) {
                await cargarCorteExistente(qp);
            } else if (!PAGE_MODE.soloLectura) {
                await generarNuevoFolio();
            } else {
                Swal.fire('Aviso', 'No se encontró el folio solicitado.', 'warning').then(() => {
                    window.location.href = routes.consultar;
                });
            }
        } catch (e) {
            if (!qp && !PAGE_MODE.soloLectura) {
                await generarNuevoFolio();
            }
        }
        // Precargar catálogo de fallas para agilizar modales
        if (!PAGE_MODE.soloLectura) { try { await preloadFallas(); } catch {} }
    });

    function bindEvents(){
        // Tomar hora desde headers
        document.querySelectorAll('[data-action="tomar-hora"]').forEach(btn => btn.addEventListener('click', () => actualizarYGuardarHoraHorario(parseInt(btn.dataset.horario,10))));

        // Delegación en tabla para observaciones
        els.body().addEventListener('click', (e) => {
            const cb = e.target.closest('.obs-checkbox');
            if (cb) {
                if (PAGE_MODE.soloLectura || cb.dataset.readonly === '1') {
                    const key = `${cb.dataset.telar}-${cb.dataset.horario}`;
                    cb.checked = !!(state.observaciones[key] || '').trim();
                }
                return abrirModalObservaciones(cb);
            }
        });

        // Eventos para inputs de valor (RPM y Eficiencia)
        document.querySelectorAll('.valor-input').forEach(input => {
            // Al hacer focus, sugerir el valor del horario anterior
            input.addEventListener('focus', (e) => {
                if (PAGE_MODE.soloLectura) return;
                const telar = input.dataset.telar;
                const horario = parseInt(input.dataset.horario, 10);
                const tipo = input.dataset.type;
                
                // Verificar que el horario esté tomado
                if (!horarioTomado(horario)) {
                    showToast({ icon:'warning', title:`Toma primero la hora del horario ${horario}` });
                    input.blur();
                    return;
                }
                
                // Si el valor es 0, sugerir el valor del horario anterior
                const currentVal = parseInt(input.value, 10) || 0;
                if (currentVal === 0) {
                    const sugerido = obtenerValorHorarioAnterior(telar, horario, tipo);
                    if (sugerido > 0) {
                        input.value = sugerido;
                        input.select(); // Seleccionar todo para fácil reemplazo
                    }
                } else {
                    input.select();
                }
            });
            
            // Al cambiar, validar y auto-guardar
            input.addEventListener('input', () => {
                if (PAGE_MODE.soloLectura) return;
                const tipo = input.dataset.type;
                const max = tipo === 'rpm' ? 400 : 100;
                let val = parseInt(input.value, 10) || 0;
                if (val < 0) val = 0;
                if (val > max) val = max;
                input.value = val;
            });
            
            // Al perder focus, guardar automáticamente
            input.addEventListener('blur', () => {
                if (PAGE_MODE.soloLectura) return;
                guardarAutomatico();
            });
            
            // Feedback visual al cambiar
            input.addEventListener('change', () => {
                if (PAGE_MODE.soloLectura) return;
                input.classList.add('bg-green-100');
                setTimeout(() => input.classList.remove('bg-green-100'), 300);
            });
        });

        // Cambios en STD => autoguardado
        document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]').forEach(i => i.addEventListener('input', () => guardarAutomatico()));
    }

    function aplicarModoSoloLectura(){
        document.querySelectorAll('[data-action="tomar-hora"]').forEach(btn => {
            btn.setAttribute('disabled', 'disabled');
            btn.classList.add('opacity-60', 'cursor-not-allowed');
        });
        document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]').forEach(input => {
            input.readOnly = true;
            input.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        });
        document.querySelectorAll('.valor-input').forEach(input => {
            input.readOnly = true;
            input.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        });
        document.querySelectorAll('.obs-checkbox').forEach(cb => {
            cb.dataset.readonly = '1';
            cb.classList.add('cursor-pointer', 'opacity-70');
        });
    }

    /** Datos base */
    async function cargarTurnoActual(){
        try { const d = await fetchJSON(routes.turnoInfo, { headers: { 'X-CSRF-TOKEN': csrf() } }); if (d.success && d.turno) state.turno = d.turno; } catch {}
    }

    async function cargarDatosTelaresStd(){
        try {
            const data = await fetchJSON(routes.datosPrograma, { headers: baseHeaders() });
            if (!(data.success && Array.isArray(data.telares))) throw new Error('Respuesta inválida');
            data.telares.forEach(t => {
                const n = t.NoTelar ?? t.noTelar ?? t.telar; if (!n) return;
                const rpm = (t.VelocidadSTD ?? t.VelocidadStd ?? t.RPM ?? t.rpm ?? 0);
                const efv = (t.EficienciaSTD ?? t.EficienciaStd ?? t.Eficiencia ?? t.eficiencia ?? 0);
                const ef  = Number(efv) ? (efv > 1 ? efv : (efv * 100)) : 0;
                const rpmInput = document.querySelector(`input[data-telar="${n}"][data-field="rpm_std"]`);
                const efInput  = document.querySelector(`input[data-telar="${n}"][data-field="eficiencia_std"]`);
                if (rpmInput) { rpmInput.value = rpm; rpmInput.placeholder = ''; }
                if (efInput)  { efInput.value  = `${Math.round(ef)}%`; efInput.placeholder = ''; }
                // Inicializar inputs de horarios en 0
                for (let h=1; h<=3; h++){ 
                    const rpmHInput = document.querySelector(`input.valor-input[data-telar="${n}"][data-horario="${h}"][data-type="rpm"]`); 
                    const efHInput = document.querySelector(`input.valor-input[data-telar="${n}"][data-horario="${h}"][data-type="eficiencia"]`); 
                    if (rpmHInput) rpmHInput.value = 0; 
                    if (efHInput) efHInput.value = 0; 
                }
            });
        } catch (err) {
            document.querySelectorAll('input[data-field="rpm_std"], input[data-field="eficiencia_std"]').forEach(i => { if (!i.value) { i.placeholder='Error al cargar'; i.title=String(err); } });
            showToast({ icon:'error', title:'No se pudo cargar Programa Tejido' });
        } finally { try { await completarStdDesdeHistorial(); } catch {} }
    }

    async function completarStdDesdeHistorial(){
        const data = await fetchJSON(routes.datosTelares, { headers: { 'X-CSRF-TOKEN': csrf() } });
        if (!(data.success && Array.isArray(data.telares))) return;
        data.telares.forEach(t => {
            const n = t.NoTelarId ?? t.NoTelar ?? t.telar; if (!n) return;
            const rpmInput = document.querySelector(`input[data-telar="${n}"][data-field="rpm_std"]`);
            const efInput  = document.querySelector(`input[data-telar="${n}"][data-field="eficiencia_std"]`);
            if (rpmInput && !rpmInput.value) rpmInput.value = (t.VelocidadStd ?? t.RpmStd ?? 0);
            if (efInput && !efInput.value) {
                const eVal = t.EficienciaStd ?? t.Eficiencia ?? 0; const pct = Number(eVal) ? (eVal>1?eVal:eVal*100) : 0; efInput.value = `${Math.round(pct)}%`;
            }
        });
    }

    /** Flujo de folio (interno) */
    async function generarNuevoFolio(){
        try {
            const response = await fetch(routes.generarFolio, {
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
            });
            const d = await response.json();

            // Si hay un folio en proceso (error 400)
            if (response.status === 400 && d.folio_existente) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Folio en proceso',
                    text: 'Ya existe un folio en proceso: ' + d.folio_existente + '. Debe finalizarlo antes de crear uno nuevo.',
                    confirmButtonText: 'Ir a consultar',
                    confirmButtonColor: '#3085d6',
                    allowOutsideClick: false
                });
                window.location.href = routes.consultar;
                return;
            }

            if (!d.success) throw new Error(d.message || 'No se pudo generar folio');
            state.folio = d.folio; state.usuario = d.usuario?.nombre || ''; state.noEmpleado = d.usuario?.numero_empleado || ''; state.turno = d.turno || state.turno; state.status = 'En Proceso'; state.isNewRecord = false;
            actualizarBadgeFolio();

            // Actualizar la URL con el folio para que al recargar se mantenga editando el mismo
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('folio', d.folio);
            window.history.replaceState({}, '', newUrl.toString());

        } catch (error) {
            console.error('Error al generar folio:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo generar el folio. Por favor, intente nuevamente.',
                confirmButtonColor: '#d33'
            });
            window.location.href = routes.consultar;
        }
    }

    async function cargarCorteExistente(folio){
        const r = await fetchJSON(routes.getCorte(folio), { headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
        if (!r.success) throw new Error(r.message || 'No se pudo cargar el corte');
        const info = r.data; state.folio = info.folio; state.fecha = info.fecha || state.fecha; state.turno = info.turno || state.turno; state.status = info.status || 'En Proceso'; state.usuario = info.usuario || ''; state.noEmpleado = info.noEmpleado || '';
        actualizarBadgeFolio();
        [1,2,3].forEach(h => { const hh = info[`horario_${h}`]; if (hh) els.horaH(h).textContent = String(hh).split('.')[0].slice(0,5); });
        if (Array.isArray(info.datos_telares)) info.datos_telares.forEach(t => {
            const id = t.NoTelar;
            const rpmStd = document.querySelector(`input[data-telar="${id}"][data-field="rpm_std"]`);
            const efStd  = document.querySelector(`input[data-telar="${id}"][data-field="eficiencia_std"]`);
            if (rpmStd) rpmStd.value = t.RpmStd ?? '';
            if (efStd)  efStd.value  = t.EficienciaStd == null ? '' : `${parseFloat(t.EficienciaStd).toFixed(0)}%`;
            setDisplay(id, 1, 'rpm', t.RpmR1); setDisplay(id, 1, 'eficiencia', t.EficienciaR1);
            setDisplay(id, 2, 'rpm', t.RpmR2); setDisplay(id, 2, 'eficiencia', t.EficienciaR2);
            setDisplay(id, 3, 'rpm', t.RpmR3); setDisplay(id, 3, 'eficiencia', t.EficienciaR3);
            setObs(id, 1, t.StatusOB1, t.ObsR1); setObs(id, 2, t.StatusOB2, t.ObsR2); setObs(id, 3, t.StatusOB3, t.ObsR3);
        });
        state.isNewRecord = false;
    }

    function setDisplay(telarId, h, type, v){ 
        const input = document.querySelector(`input.valor-input[data-telar="${telarId}"][data-horario="${h}"][data-type="${type}"]`); 
        if (!input || v==null || v==='') return; 
        input.value = type==='rpm' ? parseInt(v,10) : parseFloat(v).toFixed(0); 
    }
    function setObs(telarId, h, st, text){
        const cb = document.querySelector(`input.obs-checkbox[data-telar="${telarId}"][data-horario="${h}"]`);
        if (cb) cb.checked = !!st;
        if (text) {
            state.observaciones[`${telarId}-${h}`] = text;
            syncObsTitle(telarId, h, text);
        }
    }

    function obtenerValorHorarioAnterior(telar, horario, tipo){
        const readStd = () => { 
            const input=document.querySelector(`input[data-telar="${telar}"][data-field="${tipo==='rpm'?'rpm_std':'eficiencia_std'}"]`); 
            if(!input||!input.value) return 0; 
            const raw = tipo==='rpm'? parseFloat(input.value) : parsePct(input.value); 
            return Math.round(raw||0); 
        };
        const readInputVal = (h) => { 
            const input=document.querySelector(`input.valor-input[data-telar="${telar}"][data-horario="${h}"][data-type="${tipo}"]`); 
            if(!input) return 0; 
            return parseInt(input.value,10)||0; 
        };
        if(horario===1) return readStd(); 
        if(horario===2) return readInputVal(1)||readStd(); 
        if(horario===3) return readInputVal(2)||readInputVal(1)||readStd(); 
        return 0;
    }

    /** Hora por horario */
    async function actualizarYGuardarHoraHorario(h){
        if (PAGE_MODE.soloLectura) return;
        const turnoVal = await asegurarTurno();
        if (!state.folio || !turnoVal) return showToast({ icon:'warning', title:'Faltan datos internos para guardar hora' });
        const turno = Number.isFinite(parseInt(turnoVal,10)) ? parseInt(turnoVal,10) : turnoVal;
        const hora = horaActualStr();
        try {
            const data = await fetchJSON(routes.guardarHora, { method:'POST', headers: baseHeaders(), body: JSON.stringify({ folio:state.folio, turno, horario:h, hora, fecha: state.fecha }) });
            if (!data.success) throw new Error(data.message || 'Error al guardar hora');
            els.horaH(h).textContent = hora;
            showToast({ title:'Hora guardada', text:`Horario ${h} - ${hora}` });
        } catch (e) { showToast({ icon:'warning', title:'Hora no guardada', html:`<div class='text-center'><div class='text-2xl mb-2'>${emojiHorario(h)}</div><p class='font-mono text-lg'>${hora}</p><p class='text-xs text-red-500 mt-1'>${e.message || 'No guardado en BD'}</p></div>` }); }
    }

    /** Guardado automático */
    const guardarAutomatico = debounce(async () => {
        if (PAGE_MODE.soloLectura) return;
        if (!state.folio || !state.fecha || !state.turno) return;
        const datos = recopilarDatosTelares(); if (!datos.length) return;
        const horarios = leerHorarios();
        const payload = {
            folio: state.folio,
            fecha: state.fecha,
            turno: state.turno,
            status: state.status,
            usuario: state.usuario,
            noEmpleado: state.noEmpleado,
            datos_telares: datos,
            horario1: horarios[1],
            horario2: horarios[2],
            horario3: horarios[3],
        };
        try {
            const response = await fetch(routes.store, { method:'POST', headers: baseHeaders(), body: JSON.stringify(payload) });
            const r = await response.json();

            // Si hay un folio en proceso
            if (response.status === 400 && r.folio_existente) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Folio en proceso',
                    text: r.message || 'Ya existe un folio en proceso: ' + r.folio_existente,
                    confirmButtonText: 'Ir a consultar',
                    confirmButtonColor: '#3085d6',
                    allowOutsideClick: false
                });
                window.location.href = routes.consultar;
                return;
            }

            if (!r.success) throw new Error(r.message || 'Error al guardar');

            // Actualizar el folio del state con el folio real guardado en BD
            // Esto es importante porque el backend puede haber generado un folio diferente al sugerido
            if (r.folio && r.folio !== state.folio) {
                state.folio = r.folio;
                actualizarBadgeFolio();
            }

            state.isNewRecord = false;
            floatingBadge('Guardado automáticamente');
        } catch (e) {
            floatingBadge(`Error: ${e.message}`, true);
        }
    }, 900);

    function recopilarDatosTelares(){
        const rows = els.body().querySelectorAll('tr'); const out = [];
        rows.forEach(row => {
            const telar = row.querySelector('td:first-child')?.textContent?.trim(); if (!telar) return;
            const rpmStd = row.querySelector(`input[data-telar="${telar}"][data-field="rpm_std"]`);
            const efStd  = row.querySelector(`input[data-telar="${telar}"][data-field="eficiencia_std"]`);
            const readInput = (h,t) => {
                const input = row.querySelector(`input.valor-input[data-telar="${telar}"][data-horario="${h}"][data-type="${t}"]`);
                return input ? (parseInt(input.value,10)||0) : 0;
            };
            const k1=`${telar}-1`, k2=`${telar}-2`, k3=`${telar}-3`;
            const statusOB1 = row.querySelector(`input.obs-checkbox[data-telar="${telar}"][data-horario="1"]`)?.checked;
            const statusOB2 = row.querySelector(`input.obs-checkbox[data-telar="${telar}"][data-horario="2"]`)?.checked;
            const statusOB3 = row.querySelector(`input.obs-checkbox[data-telar="${telar}"][data-horario="3"]`)?.checked;
            out.push({
                NoTelar: parseInt(telar,10),
                SalonTejidoId: null,
                RpmStd: rpmStd ? (parseFloat(rpmStd.value) || null) : null,
                EficienciaStd: efStd ? parsePct(efStd.value) : null,
                RpmR1: statusOB1 ? readInput(1,'rpm') : (readInput(1,'rpm') || null),
                EficienciaR1: statusOB1 ? readInput(1,'eficiencia') : (readInput(1,'eficiencia') || null),
                RpmR2: statusOB2 ? readInput(2,'rpm') : (readInput(2,'rpm') || null),
                EficienciaR2: statusOB2 ? readInput(2,'eficiencia') : (readInput(2,'eficiencia') || null),
                RpmR3: statusOB3 ? readInput(3,'rpm') : (readInput(3,'rpm') || null),
                EficienciaR3: statusOB3 ? readInput(3,'eficiencia') : (readInput(3,'eficiencia') || null),
                ObsR1: state.observaciones[k1] || null,
                ObsR2: state.observaciones[k2] || null,
                ObsR3: state.observaciones[k3] || null,
                StatusOB1: statusOB1 ? 1 : 0,
                StatusOB2: statusOB2 ? 1 : 0,
                StatusOB3: statusOB3 ? 1 : 0,
            });
        });
        return out;
    }

    function syncObsTitle(telarId, horario, text){
        const cb = document.querySelector(`input.obs-checkbox[data-telar="${telarId}"][data-horario="${horario}"]`);
        if (!cb) return;
        const clean = String(text || '').trim();
        cb.title = clean ? `Obs: ${clean.length > 80 ? clean.slice(0, 80) + '…' : clean}` : '';
    }

    async function preloadFallas(){
        if (state.fallasCache) return state.fallasCache;
        if (state.fallasLoading) return state.fallasLoading;
        state.fallasLoading = fetchJSON(routes.fallasCe, { headers: { 'X-CSRF-TOKEN': csrf(), 'Accept':'application/json' } })
            .then(res => {
                state.fallasCache = (res.success && Array.isArray(res.data)) ? res.data : [];
                return state.fallasCache;
            })
            .catch(() => {
                state.fallasCache = [];
                return state.fallasCache;
            })
            .finally(() => { state.fallasLoading = null; });
        return state.fallasLoading;
    }

    async function abrirModalObservaciones(checkbox){
        const telar = checkbox.dataset.telar; const horario = checkbox.dataset.horario; const key = `${telar}-${horario}`; const cur = state.observaciones[key] || '';
        if (!requireHorario(parseInt(horario,10))) { checkbox.checked = !!cur; return; }
        if (PAGE_MODE.soloLectura || checkbox.dataset.readonly === '1') {
            if (!cur.trim()) {
                showToast({ icon:'info', title:'Sin observaciones', text:`Telar ${telar} - Horario ${horario}` });
                return;
            }
            await Swal.fire({
                title:'Observaciones',
                html:`<div class='text-left mb-3'><p class='text-sm text-gray-600'>Telar: <strong>${telar}</strong> | Horario: <strong>${horario}</strong></p></div>
                      <textarea class='w-full p-3 border border-gray-300 rounded-md bg-gray-100 text-gray-700 resize-none' rows='4' readonly>${cur}</textarea>`,
                confirmButtonText:'Cerrar',
                confirmButtonColor:'#2563eb',
                width:520
            });
            return;
        }
        // Cargar catálogo de fallas (cacheado)
        const fallas = await preloadFallas();

        const html = `
            <div class='text-left mb-4'>
                <p class='text-sm text-gray-600 mb-2'>Telar: <strong>${telar}</strong> | Horario: <strong>${horario}</strong></p>
                <label class='block text-xs text-gray-700 mb-1'>Falla (Clave)</label>
                <select id='swal-select-falla' class='w-full p-2 border border-gray-300 rounded mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500'>
                    <option value=''>-- Seleccione una clave --</option>
                    ${fallas.map(f => `<option value="${String(f.Clave).replace(/"/g,'&quot;')}" data-desc="${String(f.Descripcion ?? '').replace(/"/g,'&quot;')}">${String(f.Clave)}</option>`).join('')}
                </select>
            </div>
            <textarea id='swal-textarea' class='w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none' rows='4' placeholder='Escriba sus observaciones aquí...'>${cur}</textarea>
        `;

        const r = await Swal.fire({
            title:'Observaciones',
            html,
            width:520,
            showCancelButton:true,
            confirmButtonText:'Guardar',
            cancelButtonText:'Cancelar',
            confirmButtonColor:'#2563eb',
            cancelButtonColor:'#6b7280',
            focusConfirm:false,
            didOpen:()=>{
                const ta = document.getElementById('swal-textarea');
                const sel = document.getElementById('swal-select-falla');
                if (ta) ta.focus();
                if (sel) sel.addEventListener('change', (e)=>{
                    const opt = sel.options[sel.selectedIndex];
                    const desc = opt?.getAttribute('data-desc') || '';
                    if (desc) {
                        // Autocompletar la descripción en el textarea
                        const area = document.getElementById('swal-textarea');
                        if (area) area.value = desc;
                    }
                });
            },
            preConfirm:()=>document.getElementById('swal-textarea')?.value || ''
        });
        if (!r.isConfirmed) { checkbox.checked = !!cur; return; }
        state.observaciones[key] = r.value;
        checkbox.checked = r.value.trim() !== '';
        syncObsTitle(telar, horario, r.value);
        guardarAutomatico();
        showToast({ title:'Observación guardada', text:`Telar ${telar} - Horario ${horario}` });
    }

    function floatingBadge(text, isError=false){
        let n = document.getElementById(isError ? 'notificacion-error-guardado' : 'notificacion-guardado');
        if (!n) {
            n = document.createElement('div'); n.id = isError ? 'notificacion-error-guardado' : 'notificacion-guardado'; n.className = `fixed bottom-4 right-4 ${isError ? 'bg-red-500' : 'bg-green-500'} text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-opacity duration-300 z-50`; n.style.opacity = '0'; n.innerHTML = isError ? `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path></svg><span id='badge-text'></span>` : `<svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path></svg><span id='badge-text'></span>`; document.body.appendChild(n);
        }
        n.querySelector('#badge-text').textContent = text; n.style.opacity = '1'; setTimeout(()=>{ n.style.opacity = '0'; }, isError ? 3000 : 2000);
    }
})();
</script>

<style>
/* Tabla y sticky headers */
table{ border-collapse:separate; border-spacing:0; }
thead th{ position:sticky; top:0; z-index:30; box-shadow:0 2px 4px rgba(0,0,0,.08); }
tbody tr:hover{ background-color:#eff6ff !important; }

/* Inputs STD y valor */
tbody input{ transition:border-color .2s ease, background-color .2s ease; }
tbody input:focus{ border-color:#3b82f6; box-shadow:0 0 0 1px #3b82f6; outline:none; }

/* Inputs de valor (RPM y Eficiencia) */
.valor-input{ 
    min-width:60px; 
    max-width:80px;
    -moz-appearance:textfield; /* Firefox - ocultar spinners */
}
.valor-input::-webkit-outer-spin-button,
.valor-input::-webkit-inner-spin-button{ 
    -webkit-appearance:none; 
    margin:0; 
}
.valor-input:focus{ 
    background-color:#eff6ff; 
}
.valor-input.bg-green-100{ 
    background-color:#dcfce7 !important; 
}

/* Scrollbars */
.overflow-x-auto::-webkit-scrollbar, .overflow-y-auto::-webkit-scrollbar{ width:8px; height:8px; }
.overflow-x-auto::-webkit-scrollbar-track, .overflow-y-auto::-webkit-scrollbar-track{ background:#f1f1f1; }
.overflow-x-auto::-webkit-scrollbar-thumb, .overflow-y-auto::-webkit-scrollbar-thumb{ background:#c1c1c1; border-radius:4px; }
.overflow-x-auto::-webkit-scrollbar-thumb:hover, .overflow-y-auto::-webkit-scrollbar-thumb:hover{ background:#a8a8a8; }
</style>

@endsection
