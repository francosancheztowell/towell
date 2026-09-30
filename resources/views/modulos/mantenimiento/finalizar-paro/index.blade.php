@extends('layouts.app')

@section('page-title', 'Finalizar Paro')

@section('content')
<style>
    /* Estrellas de Calidad: son <input type="radio"> reales, sólo repintados.
       Se resuelve en CSS porque hay que reaccionar al estado marcado/foco de un
       hermano, y las variantes de Tailwind no alcanzan a los hijos del <label>. */
    /* El <fieldset> trae min-inline-size: min-content y ensancharía la columna en
       pantallas angostas; se neutraliza para conservar el ancho que tenía el div. */
    #calidad-fieldset {
        min-inline-size: 0;
    }

    .calidad-estrellas {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        align-items: center;
        padding: 0.25rem;
        border: 2px solid transparent;
        border-radius: 0.375rem;
    }

    /* El radio y el texto del label se ocultan a la vista, pero siguen siendo
       enfocables y anunciables por el lector de pantalla ("3 de 5"). */
    .calidad-estrellas input[type="radio"],
    .calidad-estrellas label > span {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        clip-path: inset(50%);
        white-space: nowrap;
        border: 0;
    }

    /* Estrella vacía: contorno en gris oscuro (#4b5563 = 7.56:1 sobre blanco). */
    .calidad-estrellas label {
        line-height: 1;
        color: #4b5563;
        border-radius: 0.375rem;
        -webkit-user-select: none;
        user-select: none;
    }

    /* El "/ """ es el texto alternativo del contenido generado: sin él, Chrome lo
       suma al nombre accesible y el lector anuncia "estrella blanca 3 de 5". */
    .calidad-estrellas label::before {
        content: "\2606" / "";
    }

    /* Estrella llena: sólida y ámbar oscuro (#b45309 = 5.02:1 sobre blanco).
       Llena y vacía se distinguen por forma (sólida vs contorno) además de por
       color, para que se lean bajo el reflejo de la tablet. */
    .calidad-estrellas input[type="radio"]:checked ~ label,
    .calidad-estrellas label:hover,
    .calidad-estrellas label:hover ~ label {
        color: #b45309;
    }

    .calidad-estrellas input[type="radio"]:checked ~ label::before,
    .calidad-estrellas label:hover::before,
    .calidad-estrellas label:hover ~ label::before {
        content: "\2605" / "";
    }

    /* Foco de teclado sobre la estrella enfocada (#1d4ed8 = 6.70:1 sobre blanco).
       Se incluye :focus además de :focus-visible porque el foco también se mueve por
       código al fallar la validación, y ahí Chrome puede no aplicar :focus-visible:
       como el radio está oculto, el operador se quedaría sin ninguna marca visible. */
    .calidad-estrellas input[type="radio"]:focus + label,
    .calidad-estrellas input[type="radio"]:focus-visible + label {
        outline: 3px solid #1d4ed8;
        outline-offset: 2px;
    }

    /* Marca de error al intentar finalizar sin calificar (#dc2626 = 4.83:1). */
    .calidad-estrellas.calidad-invalida {
        border-color: #dc2626;
        background-color: #fef2f2;
    }
</style>
@php
    $configPagina = [
        'rutas' => [
            'paro' => route('api.mantenimiento.paros.show', ['id' => '__ID__']),
            'finalizar' => route('api.mantenimiento.paros.finalizar', ['id' => '__ID__']),
            'operadores' => route('api.mantenimiento.operadores'),
            'solicitudes' => route('mantenimiento.solicitudes'),
            'nuevoParo' => route('mantenimiento.nuevo-paro'),
        ],
    ];
@endphp
<div id="pagina-finalizar-paro" class="w-full p-3 md:p-6 lg:p-8" data-pagina='@json($configPagina)'>
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 p-4 md:p-6 lg:p-8 -mt-3 max-w-5xl mx-auto">

        <!-- Formulario -->
        <form id="form-finalizar-paro">
            @csrf
            <input type="hidden" id="paro_id" name="paro_id">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-3">
                <!-- Fecha Fin (Columna 1) -->
                <div>
                    <label for="fecha" class="block text-xs md:text-sm font-medium text-gray-700">
                        Fecha Cierre
                    </label>
                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-blue-50 text-blue-700 cursor-not-allowed"
                        value="{{ date('Y-m-d') }}"
                        readonly
                    >
                </div>

                <!-- Hora (Columna 2) -->
                <div>
                    <label for="hora" class="block text-xs md:text-sm font-medium text-gray-700">
                        Hora Cierre
                    </label>
                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-blue-50 text-blue-700 cursor-not-allowed"
                        value="{{ date('H:i') }}"
                        readonly
                    >
                </div>

                <!-- Depto (Columna 3) -->
                <div>
                    <label for="depto" class="block text-xs md:text-sm font-medium text-gray-700">
                        Departamento
                    </label>
                    <input
                        type="text"
                        id="depto"
                        name="depto"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Maquina (Columna 1) -->
                <div>
                    <label for="maquina" class="block text-xs md:text-sm font-medium text-gray-700">
                        Máquina
                    </label>
                    <input
                        type="text"
                        id="maquina"
                        name="maquina"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Tipo Falla (Columna 2) -->
                <div>
                    <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">
                        Tipo Falla
                    </label>
                    <input
                        type="text"
                        id="tipo_falla"
                        name="tipo_falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Falla (Columna 3) -->
                <div>
                    <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">
                        Falla
                    </label>
                    <input
                        type="text"
                        id="falla"
                        name="falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Descripcion (Ocupa 2 columnas: 1 y 2) -->
                <div class="md:col-span-2">
                    <label for="descrip" class="block text-xs md:text-sm font-medium text-gray-700">
                        Descripción
                    </label>
                    <input
                        type="text"
                        id="descrip"
                        name="descrip"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Orden de Trabajo (Columna 3) -->
                <div>
                    <label for="orden_trabajo" class="block text-xs md:text-sm font-medium text-gray-700">
                        Orden de Trabajo
                    </label>
                    <input
                        type="text"
                        id="orden_trabajo"
                        name="orden_trabajo"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        readonly
                    >
                </div>

                <!-- Atendio (Columna 1) -->
                <div>
                    <label for="atendio" class="block text-xs md:text-sm font-medium text-gray-700">
                        Atendió <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="atendio"
                        name="atendio"
                        required
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 mt-1 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none mb-1"
                    >
                        <option value="">Seleccione un operador</option>
                        <!-- Los operadores se cargarán dinámicamente -->
                    </select>
                </div>

                <!-- Calidad (Ocupa 2 columnas: 2 y 3, misma fila que Atendio) -->
                <div class="md:col-span-2">
                    <fieldset id="calidad-fieldset" aria-describedby="calidad-ayuda">
                        <legend class="block text-sm md:text-md font-medium text-gray-700">
                            Calidad (1-5) <span class="text-red-600">*</span>
                        </legend>
                        <p id="calidad-ayuda" class="text-xs md:text-sm text-gray-700">
                            Califique la atención que le dio el personal de mantenimiento que resolvió el paro.
                        </p>
                        <!-- Radios reales para que el campo sea operable con teclado (WCAG 2.1.1).
                             Van en orden inverso (5 → 1) porque el CSS los voltea con row-reverse:
                             así se ven de 1 a 5 y el combinador de hermanos (~) puede pintar la
                             estrella marcada junto con todas las menores. -->
                        {{-- md:gap-2: con gap-12 la 5.ª estrella quedaba fuera a 768 px y en tablet no se podía calificar con 5. --}}
                        <div id="calidad-stars" class="calidad-estrellas gap-4 md:gap-2 lg:gap-12 w-full ml-2 md:ml-4">
                            <input type="radio" id="calidad-5" name="calidad" value="5">
                            <label for="calidad-5" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>5 de 5</span></label>

                            <input type="radio" id="calidad-4" name="calidad" value="4">
                            <label for="calidad-4" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>4 de 5</span></label>

                            <input type="radio" id="calidad-3" name="calidad" value="3">
                            <label for="calidad-3" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>3 de 5</span></label>

                            <input type="radio" id="calidad-2" name="calidad" value="2">
                            <label for="calidad-2" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>2 de 5</span></label>

                            <input type="radio" id="calidad-1" name="calidad" value="1">
                            <label for="calidad-1" class="text-5xl md:text-6xl cursor-pointer transition-colors flex-shrink-0 px-2"><span>1 de 5</span></label>
                        </div>
                        <!-- Sólo apoyo visual: el lector de pantalla ya anuncia el radio marcado. -->
                        <span id="calidad-value" class="text-xs text-gray-500 ml-2" aria-hidden="true">0/5</span>
                    </fieldset>
                </div>

                <!-- Turno (Oculto - se guardará pero no se mostrará) -->
                <input
                    type="hidden"
                    id="turno"
                    name="turno"
                    value=""
                >
            </div>

            <!-- Obs - ObsCierre - Ancho completo -->
            <div >
                <label for="obs_cierre" class="block text-xs md:text-sm font-medium text-gray-700">Observaciones</label>
                <textarea
                    id="obs_cierre"
                    name="obs_cierre"
                    rows="3"
                    class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none outline-none"
                    placeholder="Observaciones de cierre"
                ></textarea>
            </div>
            <p class="mt-3 md:mt-4 text-sm md:text-base text-gray-700">
                <i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>
                Al finalizar se enviará la notificación a Telegram.
            </p>
            <!-- Botones -->
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-4 md:mt-6 pt-3 md:pt-4 border-t border-gray-200">
                <button
                    type="button"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm md:text-base font-medium rounded-md transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Finalizar
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/finalizar-paro/index.ts')
@endpush
