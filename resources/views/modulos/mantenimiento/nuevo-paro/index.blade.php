@extends('layouts.app')

@section('page-title', 'Reportar Paro')

@section('content')
@php
    $configPagina = [
        'rutas' => [
            'departamentos' => route('api.mantenimiento.departamentos'),
            'maquinas' => route('api.mantenimiento.maquinas', ['departamento' => '__DEPTO__']),
            'tiposFalla' => route('api.mantenimiento.tipos-falla', ['departamento' => '__DEPTO__']),
            'fallasPorTipo' => route('api.mantenimiento.fallas', ['departamento' => '__DEPTO__', 'tipoFallaId' => '__TIPO__']),
            'ordenTrabajo' => route('api.mantenimiento.orden-trabajo', ['departamento' => '__DEPTO__', 'maquina' => '__MAQ__']),
            'guardar' => route('api.mantenimiento.paros.store'),
            'solicitudes' => route('mantenimiento.solicitudes'),
            'nuevoParo' => route('mantenimiento.nuevo-paro'),
        ],
        'areaUsuario' => $areaUsuario ?? null,
        'departamentos' => $departamentos ?? [],
    ];
    $spinner = 'hidden size-5 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent';
    // Campos táctiles (min-h-touch = 44 px), texto de 16 px y borde con contraste ≥ 3:1 (WCAG 1.4.11).
    $etiqueta = 'block text-sm font-medium text-gray-800 md:text-base';
    $ayuda = 'text-sm text-gray-700';
    $error = 'text-sm font-medium text-red-700';
    $campo = 'min-h-touch w-full rounded-md border-2 border-gray-500 bg-white px-3 text-base outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-500 aria-[invalid=true]:border-red-700 disabled:bg-gray-100 disabled:text-gray-600';
@endphp
<div id="pagina-nuevo-paro" class="w-full p-3 md:p-6 lg:p-8" data-pagina='@json($configPagina)'>
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-4 md:p-6 lg:p-8 max-w-5xl mx-auto">

        <div class="mb-3 flex justify-end md:mb-4">
            <a href="{{ route('mantenimiento.solicitudes') }}"
                data-carga class="inline-flex min-h-touch items-center gap-2 rounded-md bg-blue-600 px-4 text-base font-medium text-white transition-colors hover:bg-blue-700">
                <span data-spinner class="{{ $spinner }}" aria-hidden="true"></span>
                <i class="fas fa-list" aria-hidden="true"></i> Ver solicitudes
            </a>
        </div>

        <!-- Anuncia a lectores de pantalla las cargas y el envío -->
        <p id="estado-formulario" role="status" aria-live="polite" class="sr-only"></p>

        <form id="form-paro" novalidate>
            @csrf
            <!-- Fecha y hora: texto, no campos (las estampa el servidor al guardar) -->
            <dl class="mb-3 grid grid-cols-2 gap-3 rounded-md bg-gray-50 p-3 md:mb-4">
                <div>
                    <dt class="{{ $etiqueta }}">Fecha</dt>
                    <dd id="fecha" class="text-base text-gray-900">{{ date('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="{{ $etiqueta }}">Hora</dt>
                    <dd id="hora" class="text-base text-gray-900">{{ date('H:i') }}</dd>
                </div>
            </dl>
            <p class="{{ $ayuda }} -mt-2 mb-3 md:mb-4">La fecha y la hora las registra el sistema al guardar.</p>

            <div class="grid grid-cols-1 items-start gap-3 md:grid-cols-2 md:gap-4">

                <!-- Depto -->
                <div>
                    <label for="depto" class="{{ $etiqueta }}">Departamento</label>
                    <select id="depto" name="depto" class="{{ $campo }}" aria-describedby="error-depto" aria-required="true" required>
                        <option value="">Cargando...</option>
                    </select>
                    <p id="error-depto" aria-live="polite" class="{{ $error }}"></p>
                </div>

                <!-- Maquina -->
                <div>
                    <label for="maquina" class="{{ $etiqueta }}">Máquina</label>
                    <p id="ayuda-maquina" class="{{ $ayuda }}">Seleccione primero un departamento para habilitar este campo.</p>
                    <select id="maquina" name="maquina" class="{{ $campo }}" aria-describedby="ayuda-maquina error-maquina" aria-required="true" required disabled>
                        <option value="">Seleccione primero un departamento</option>
                    </select>
                    <p id="error-maquina" aria-live="polite" class="{{ $error }}"></p>
                </div>

                <!-- Tipo Falla -->
                <div>
                    <label for="tipo_falla" class="{{ $etiqueta }}">Tipo de falla</label>
                    <p id="ayuda-tipo-falla" class="{{ $ayuda }}">Seleccione primero una máquina para habilitar este campo.</p>
                    <select id="tipo_falla" name="tipo_falla" class="{{ $campo }}" aria-describedby="ayuda-tipo-falla error-tipo-falla" aria-required="true" required disabled>
                        <option value="">Seleccione primero una máquina</option>
                    </select>
                    <p id="error-tipo-falla" aria-live="polite" class="{{ $error }}"></p>
                </div>

                <!-- Falla (una sola lista: "Falla — Descripción", el valor es el Id del catálogo) -->
                <div>
                    <label for="falla" class="{{ $etiqueta }}">Falla</label>
                    <p id="ayuda-falla" class="{{ $ayuda }}">Seleccione primero un tipo de falla para habilitar este campo.</p>
                    <select id="falla" name="falla" class="{{ $campo }}" aria-describedby="ayuda-falla error-falla" aria-required="true" required disabled>
                        <option value="">Seleccione primero un tipo de falla</option>
                    </select>
                    <p id="error-falla" aria-live="polite" class="{{ $error }}"></p>
                </div>

                <!-- Orden de Trabajo -->
                <div>
                    <label for="orden_trabajo" class="{{ $etiqueta }}">Orden de trabajo (opcional)</label>
                    <p id="ayuda-orden-trabajo" class="{{ $ayuda }}">
                        Se sugiere sola al elegir la máquina. Puede escribirla o corregirla a mano. Máximo 20 caracteres, sin espacios.
                    </p>
                    <input type="text" id="orden_trabajo" name="orden_trabajo" maxlength="20" class="{{ $campo }}" aria-describedby="ayuda-orden-trabajo error-orden-trabajo">
                    <p id="error-orden-trabajo" aria-live="polite" class="{{ $error }}"></p>
                </div>
            </div>

            <!-- Obs - Ancho completo -->
            <div class="mt-3 md:mt-4">
                <label for="obs" class="{{ $etiqueta }}">Observaciones (opcional)</label>
                <textarea id="obs" name="obs" rows="3" maxlength="255" class="{{ $campo }} py-2 resize-none" aria-describedby="error-obs"></textarea>
                <p id="error-obs" aria-live="polite" class="{{ $error }}"></p>
            </div>

            <!-- Botones -->
            <div class="mt-3 grid grid-cols-2 gap-3 border-t border-gray-200 pt-3 md:mt-4 md:gap-4">
                <a href="{{ route('produccion.index') }}" id="btn-cancelar" data-carga
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border-2 border-gray-500 bg-white px-4 text-base font-medium text-gray-800 transition-colors hover:bg-gray-50">
                    <span data-spinner class="{{ $spinner }}" aria-hidden="true"></span>
                    Cancelar
                </a>
                <button type="submit" id="btn-aceptar"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-blue-600 px-4 text-lg font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span data-spinner class="{{ $spinner }}" aria-hidden="true"></span>
                    <span id="texto-aceptar">Reportar</span>
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/nuevo-paro/index.ts')
@endpush
