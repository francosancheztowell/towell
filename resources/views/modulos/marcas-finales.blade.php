@extends('layouts.app')

@section('page-title', 'Marcas Finales')

@php
    // Config centralizada para headers y colores
    $telares = range(207, 320);
    $horarios = [
        ['id' => 1, 'title' => 'Horario 1', 'color' => 'blue'],
        ['id' => 2, 'title' => 'Horario 2', 'color' => 'green'],
        ['id' => 3, 'title' => 'Horario 3', 'color' => 'yellow'],
    ];
    $tipos = ['trama','pie','rizo','otros'];
@endphp

@section('navbar-right')
<div class="flex items-center gap-1">
    <button id="btn-nuevo" class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors" title="Nuevo">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
    </button>
    <button id="btn-editar" class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors cursor-not-allowed" disabled title="Editar">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
    </button>
    <button id="btn-finalizar" class="p-2 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors cursor-not-allowed" disabled title="Finalizar">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
    </button>
</div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header Section -->
    <div id="header-section" class="bg-white shadow-sm -mt-4 p-3 hidden">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Folio:</span>
                <input type="text" id="folio" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-24" placeholder="F0001" readonly>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Fecha:</span>
                <input type="date" id="fecha" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-36" readonly>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Turno:</span>
                <select id="turno" class="px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 w-28">
                    <option value="">Seleccionar</option>
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Usuario:</span>
                <input type="text" id="usuario" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-40" placeholder="Usuario actual" readonly>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Status:</span>
                <select id="status" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-28" disabled>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En Proceso">En Proceso</option>
                    <option value="Finalizado">Finalizado</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">NoEmpleado:</span>
                <input type="text" id="noEmpleado" class="px-3 py-2 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed w-24" placeholder="12345" readonly>
            </div>
        </div>
    </div>

    <!-- Mensaje inicial -->
    <div id="mensaje-inicial" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center mb-6">
        <div class="flex flex-col items-center">
            <i class="fas fa-clipboard-list text-6xl text-blue-400 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Marcas Finales</h3>
            <p class="text-gray-600 mb-4">Haz clic en "Nuevo" para comenzar un nuevo registro de marcas finales</p>
            <div class="text-sm text-gray-500">
                <p>• Selecciona el turno correspondiente</p>
                <p>• Completa Trama, Pie, Rizo y Otros para cada telar</p>
                <p>• Guarda cuando termines</p>
            </div>
        </div>
    </div>

    <!-- Tabla principal -->
    <div id="segunda-tabla" class="bg-white shadow-sm rounded-lg overflow-hidden mb-6 hidden -mt-4">
        <div class="table-container">
            <table class="min-w-full border-collapse border border-gray-300">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="border border-gray-300 px-1 py-2 text-left text-xs font-semibold text-gray-700 w-16">Telar</th>
                        @foreach($horarios as $h)
                            <th colspan="{{ count($tipos) }}" class="border border-gray-300 px-2 py-2 text-center text-xs font-semibold text-gray-700 bg-{{ $h['color'] }}-100">{{ $h['title'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="border border-gray-300 px-1 py-2 text-xs font-medium text-gray-600 w-16"></th>
                        @foreach($horarios as $h)
                            @foreach($tipos as $t)
                                <th class="border border-gray-300 px-2 py-2 text-xs font-medium text-gray-600 bg-{{ $h['color'] }}-50 capitalize">{{ $t }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody id="telares-body">
                    @foreach($telares as $i)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-1 py-2 text-center text-sm font-semibold w-16">{{ $i }}</td>

                            @foreach($horarios as $h)
                                @foreach($tipos as $t)
                                    <td class="border border-gray-300 px-1 py-2">
                                        <div class="mf-cell flex items-center justify-between relative"
                                             data-telar="{{ $i }}" data-horario="{{ $h['id'] }}" data-tipo="{{ $t }}">
                                            <span class="mf-display text-sm text-gray-900 font-medium"
                                                  id="h{{ $h['id'] }}_{{ $t }}_display_{{ $i }}">0</span>

                                            <button type="button"
                                                    class="mf-edit-btn ml-1 p-1 text-gray-500 hover:text-{{ $h['color'] }}-600 transition-colors"
                                                    aria-label="Editar {{ ucfirst($t) }} H{{ $h['id'] }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <!-- Editor (se rellena on-demand) -->
                                            <div class="mf-editor hidden absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-2 w-36"
                                                 data-built="0" role="dialog" aria-modal="true">
                                                <div class="mf-scroll overflow-x-auto scrollbar-hide min-w-max"></div>
                                            </div>
                                        </div>
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

<!-- Librería -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ===============================
   Marcas Finales - Módulo Limpio
   =============================== */
(() => {
    'use strict';

    // --- Estado global controlado ---
    let currentFolio = null;
    let isEditing = false;

    // --- Helpers de DOM ---
    const $ = (sel, ctx=document) => ctx.querySelector(sel);
    const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
    const show = el => el && el.classList.remove('hidden');
    const hide = el => el && el.classList.add('hidden');
    const isHidden = el => el?.classList?.contains('hidden');

    // Colores por horario
    const COLOR_BY_H = { 1: 'blue', 2: 'green', 3: 'yellow' };

    // --- Inicialización ---
    document.addEventListener('DOMContentLoaded', () => {
        // Fecha de hoy
        const fecha = $('#fecha');
        if (fecha) fecha.value = new Date().toISOString().split('T')[0];

        // Usuario simulado (ajusta a tu backend si ya lo tienes)
        const usuario = $('#usuario');
        const noEmpleado = $('#noEmpleado');
        if (usuario) usuario.value = 'Usuario Actual';
        if (noEmpleado) noEmpleado.value = '12345';

        // Cargar turno
        cargarTurnoActual();

        // Botones topbar
        $('#btn-nuevo')?.addEventListener('click', nuevaMarca);
        $('#btn-editar')?.addEventListener('click', editarMarca);
        $('#btn-finalizar')?.addEventListener('click', finalizarMarca);

        // Folio activa botones cuando existe
        $('#folio')?.addEventListener('blur', (e) => {
            if (e.target.value && !currentFolio) {
                currentFolio = e.target.value;
                isEditing = true;
                enableActionButtons();
            }
        });

        // Delegación: abrir/cerrar editor
        document.addEventListener('click', onDocumentClick);

        // Delegación: cerrar con ESC si editor abierto
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') closeAllEditors();
        });

        // Estado inicial UI
        disableActionButtons();
        mostrarMensajeInicial();
        disableStatusField();
    });

    // --- Delegación de clicks (editores + opciones) ---
    function onDocumentClick(e) {
        // Click en botón editar
        const editBtn = e.target.closest('.mf-edit-btn');
        if (editBtn) {
            const cell = editBtn.closest('.mf-cell');
            toggleEditor(cell);
            return;
        }

        // Click en opción numérica
        const opt = e.target.closest('.mf-option');
        if (opt) {
            applyOption(opt);
            return;
        }

        // Click fuera de editores → cerrar
        if (!e.target.closest('.mf-editor') && !e.target.closest('.mf-edit-btn')) {
            closeAllEditors();
        }
    }

    // --- Editor: construir on-demand (0..100) ---
    function ensureEditorBuilt(editor, horario) {
        if (!editor || editor.dataset.built === '1') return;

        const scroll = editor.querySelector('.mf-scroll');
        const frag = document.createDocumentFragment();

        for (let j = 0; j <= 100; j++) {
            const span = document.createElement('span');
            span.className = `mf-option inline-block w-8 h-8 text-center leading-8 text-base font-medium cursor-pointer rounded transition-colors bg-gray-100 text-gray-700 mr-1`;
            span.textContent = j;
            span.dataset.value = j;
            // se leen telar/horario/tipo de su contenedor cuando se hace click
            frag.appendChild(span);
        }
        scroll.appendChild(frag);
        editor.dataset.built = '1';
    }

    // --- Abrir/Cerrar editor ---
    function toggleEditor(cell) {
        if (!cell) return;
        const editor = cell.querySelector('.mf-editor');
        const display = cell.querySelector('.mf-display');
        const horario = Number(cell.dataset.horario);

        // Cerrar otros y abrir este
        if (isHidden(editor)) {
            closeAllEditors();
            ensureEditorBuilt(editor, horario);
            highlightCurrentValue(editor, Number(display.textContent), horario);
            show(editor);
            // centrar en valor actual
            centerOnValue(editor, Number(display.textContent));
        } else {
            hide(editor);
        }
    }

    function closeAllEditors() {
        $$('.mf-editor').forEach(hide);
    }

    // --- UI selección actual + centrado ---
    function highlightCurrentValue(editor, value, horario) {
        const opts = $$('.mf-option', editor);
        const color = COLOR_BY_H[horario];

        opts.forEach(o => {
            o.classList.remove(`bg-${color}-500`, 'text-white');
            o.classList.remove('bg-blue-500','bg-green-500','bg-yellow-500'); // limpiar cualquiera
            o.classList.add('bg-gray-100','text-gray-700');
        });

        const target = opts.find(o => Number(o.dataset.value) === value);
        if (target) {
            target.classList.remove('bg-gray-100','text-gray-700');
            target.classList.add(`bg-${color}-500`, 'text-white');
        }
    }

    function centerOnValue(editor, value) {
        const scroll = editor.querySelector('.mf-scroll');
        const target = $$('.mf-option', editor).find(o => Number(o.dataset.value) === value);
        if (!scroll || !target) return;
        const containerWidth = scroll.offsetWidth;
        const left = target.offsetLeft - (containerWidth/2) + (target.offsetWidth/2);
        scroll.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
    }

    // --- Aplicar valor seleccionado ---
    function applyOption(optEl) {
        const editor = optEl.closest('.mf-editor');
        const cell = editor.closest('.mf-cell');
        const display = cell.querySelector('.mf-display');
        const horario = Number(cell.dataset.horario);
        const tipo = cell.dataset.tipo;
        const telar = Number(cell.dataset.telar);
        const valor = Number(optEl.dataset.value);

        display.textContent = String(valor);
        propagarValor(telar, horario, tipo, valor);

        // re-highlight y cerrar con pequeño delay
        highlightCurrentValue(editor, valor, horario);
        setTimeout(() => hide(editor), 200);

        // Ya estamos editando
        isEditing = true;
        enableActionButtons();
    }

    // --- Propagación de valores ---
    function propagarValor(telar, horario, tipo, valor) {
        const d = (h) => document.getElementById(`h${h}_${tipo}_display_${telar}`);
        if (horario === 1) {
            if (d(2)?.textContent === '0') d(2).textContent = String(valor);
            if (d(3)?.textContent === '0') d(3).textContent = String(valor);
        } else if (horario === 2) {
            if (d(3)?.textContent === '0') d(3).textContent = String(valor);
        }
    }

    // --- Estados de botones y Status ---
    function enableActionButtons() {
        setBtn('#btn-editar', true);
        setBtn('#btn-finalizar', true);
    }
    function disableActionButtons() {
        setBtn('#btn-editar', false);
        setBtn('#btn-finalizar', false);
    }
    function setBtn(sel, enabled) {
        const btn = $(sel);
        if (!btn) return;
        btn.disabled = !enabled;
        btn.classList.toggle('cursor-not-allowed', !enabled);
        btn.classList.toggle('bg-gray-400', !enabled);
    }
    function enableStatusField() {
        const el = $('#status');
        if (!el) return;
        el.disabled = false;
        el.className = 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500';
    }
    function disableStatusField() {
        const el = $('#status');
        if (!el) return;
        el.disabled = true;
        el.className = 'w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed';
    }

    // --- Secciones visibles ---
    function mostrarMensajeInicial() {
        show($('#mensaje-inicial'));
        hide($('#segunda-tabla'));
        hide($('#header-section'));
    }
    function mostrarSegundaTabla({ withHeader = false } = {}) {
        hide($('#mensaje-inicial'));
        if (withHeader) show($('#header-section'));
        const tabla = $('#segunda-tabla');
        show(tabla);
        tabla.style.transform = 'translateY(-20px)';
        tabla.style.opacity = '0';
        tabla.style.transition = 'all 0.3s ease-in-out';
        tabla.offsetHeight; // reflow
        tabla.style.transform = 'translateY(0)';
        tabla.style.opacity = '1';
    }

    // --- Backend: turno/folio (usa tus rutas reales) ---
    async function cargarTurnoActual() {
        try {
            const res = await fetch('/modulo-marcas-finales/turno-info', {
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''}
            });
            const data = await res.json();
            if (data?.success) {
                $('#turno').value = data.turno;
            }
        } catch (e) {
            console.error('Turno info error', e);
        }
    }

    async function generarNuevoFolio() {
        try {
            Swal.fire({ title:'Generando folio…', allowOutsideClick:false, showConfirmButton:false, didOpen: Swal.showLoading });
            const res = await fetch('/modulo-marcas-finales/generar-folio', {
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''}
            });
            const data = await res.json();

            if (!data?.success) throw new Error(data?.message || 'Error al generar folio');

            $('#folio').value = data.folio;
            $('#usuario').value = data.usuario?.nombre ?? 'Usuario';
            $('#noEmpleado').value = data.usuario?.numero_empleado ?? '';
            $('#turno').value = data.turno ?? '';
            $('#status').value = 'En Proceso';
            disableStatusField();

            currentFolio = data.folio;
            isEditing = true;

            Swal.close();
            mostrarSegundaTabla({ withHeader:false });
            enableActionButtons();

            Swal.fire({ title: 'Folio Generado', text: `Folio: ${data.folio}`, icon: 'success', timer: 1600, showConfirmButton: false });
        } catch (err) {
            Swal.close();
            Swal.fire({ title:'Error', text: String(err.message || err), icon:'error' });
        }
    }

    // --- Acciones ---
    async function nuevaMarca() {
        if (isEditing) {
            const r = await Swal.fire({
                title: '¿Guardar cambios?',
                text: 'Hay cambios sin guardar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Descartar'
            });
            if (r.isConfirmed) {
                await guardarMarca();
            }
        }
        await generarNuevoFolio();
    }

    function editarMarca() {
        if (!currentFolio) {
            Swal.fire({ title:'Sin folio', text:'No hay marca para editar', icon:'warning' });
            return;
        }
        isEditing = true;
        enableActionButtons();
        Swal.fire({ title:'Modo edición', icon:'info', timer:1200, showConfirmButton:false });
    }

    function finalizarMarca() {
        if (!currentFolio) {
            Swal.fire({ title:'Sin folio', text:'No hay marca para finalizar', icon:'warning' });
            return;
        }
        Swal.fire({
            title: '¿Finalizar Marca?',
            text: 'Esto marcará el folio como Finalizado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar'
        }).then(r => {
            if (!r.isConfirmed) return;
            enableStatusField();
            $('#status').value = 'Finalizado';
            setTimeout(disableStatusField, 100);
            disableActionButtons();
            Swal.fire({ title:'Marca Finalizada', icon:'success' });
        });
    }

    async function guardarMarca() {
        const folio = $('#folio')?.value;
        const fecha = $('#fecha')?.value;
        const turno = $('#turno')?.value;

        if (!folio || !fecha || !turno) {
            Swal.fire({ title:'Faltan datos', text:'Completa Folio, Fecha y Turno', icon:'error' });
            return;
        }

        const datos = recopilarDatosTelares();

        Swal.fire({ title:'Guardando…', allowOutsideClick:false, showConfirmButton:false, didOpen: Swal.showLoading });

        try {
            // Ajusta al endpoint real POST
            // await fetch('/modulo-marcas-finales/guardar', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN': $('meta[name="csrf-token]')?.content ?? ''}, body: JSON.stringify({ folio, fecha, turno, datos }) });

            // Simulación
            await new Promise(r => setTimeout(r, 800));

            isEditing = false;
            Swal.fire({ title:'Guardado', icon:'success', timer:1400, showConfirmButton:false });
            enableActionButtons();
        } catch (e) {
            Swal.fire({ title:'Error', text:String(e?.message || e), icon:'error' });
        }
    }

    function recopilarDatosTelares() {
        const out = [];
        for (let telar = 207; telar <= 320; telar++) {
            const row = { telar };
            for (let h = 1; h <= 3; h++) {
                for (const t of ['trama','pie','rizo','otros']) {
                    const el = document.getElementById(`h${h}_${t}_display_${telar}`);
                    row[`h${h}_${t}`] = el ? Number(el.textContent) || 0 : 0;
                }
            }
            out.push(row);
        }
        return out;
    }

    // Exponer algunas funciones si las necesitas en consola
    window.MF = { guardarMarca };
})();
</script>

<style>
/* Tabla */
.border-collapse { border-collapse: collapse; }

/* Sticky header */
thead th {
    background-color: #f9fafb !important;
    position: sticky; top: 0; z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}

/* Scroll contenedor */
.table-container { max-height: 80vh; overflow: auto; }
.table-container::-webkit-scrollbar { width: 8px; height: 8px; }
.table-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.table-container::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.table-container::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

/* Ocultar scroll horizontal interno */
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.scrollbar-hide::-webkit-scrollbar { display: none; }

/* Botón editar feedback */
.mf-edit-btn { transition: transform .15s ease; }
.mf-edit-btn:hover { transform: scale(1.1); }
</style>
@endsection
