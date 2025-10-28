@extends('layouts.app')

@section('page-title', 'Formulario de Codificación')

@section('navbar-right')
<div class="flex items-center gap-3">
    <button onclick="history.back()"
       class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors text-sm">
        Cancelar
    </button>
    <button type="submit" form="codificacion-form"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
        <span id="submit-text">{{ isset($codificacion) ? 'Actualizar' : 'Guardar' }}</span>
    </button>
</div>
@endsection

@section('content')
<div class="min-h-screen ">
    <div class=" mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Formulario principal -->
        <form id="codificacion-form" class="bg-white rounded-lg shadow-sm border border-gray-200">
            @csrf
            <input type="hidden" id="codificacion-id" name="Id" value="{{ isset($codificacion) ? $codificacion->Id : '' }}">

            @if(isset($codificacion))
                <script>
                    window.codificacionData = @json($codificacion);
                    console.log('Datos de codificación cargados:', window.codificacionData);
                </script>
            @endif

            @php
            // Helper de input compacto y elegante con navegación
            $render = function($name, $label, $opts = []) {
                $type     = $opts['type']     ?? 'text';
                $step     = $opts['step']     ?? null;
                $required = $opts['required'] ?? false;
                $placeholder = $opts['placeholder'] ?? '';

                // Obtener valor: primero old() (para errores de validación), luego datos existentes, luego valor por defecto
                $value = old($name);
                if (empty($value) && isset($codificacion) && isset($codificacion->$name)) {
                    $value = $codificacion->$name;
                }
                if (empty($value)) {
                    $value = $opts['value'] ?? '';
                }

                // Debug: mostrar valores para campos importantes
                if (isset($codificacion) && in_array($name, ['TamanoClave', 'OrdenTejido', 'Nombre'])) {
                    echo '<script>console.log("Campo ' . $name . ' valor: ' . $value . '");</script>';
                }

                // Debug: mostrar en consola si estamos en modo edición
                if (isset($codificacion) && $name === 'TamanoClave') {
                    echo '<script>console.log("Modo edición activado para registro ID: ' . $codificacion->Id . '");</script>';
                }

                // Formatear fechas para input type="date"
                if ($type === 'date' && !empty($value) && $value !== '0000-00-00') {
                    try {
                        $value = \Carbon\Carbon::parse($value)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $value = '';
                    }
                }

                $attrs = $step ? "step=\"{$step}\"" : '';
                $req   = $required ? 'required' : '';
                $classes = 'w-full px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 hover:border-gray-400 tab-navigation';
                echo '<div class="space-y-0.5">';
                echo "  <label class=\"block text-xs font-medium text-gray-600\">{$label}".($required?' *':'')."</label>";
                echo "  <input {$req} {$attrs} type=\"{$type}\" name=\"{$name}\" value=\"{$value}\" placeholder=\"{$placeholder}\" class=\"{$classes}\" />";
                echo '</div>';
            };

            // Todos los campos en un solo array para distribución uniforme
            $todosLosCampos = [
                // Datos Básicos
                ['TamanoClave','Tamaño Clave'],
                ['OrdenTejido','Orden Tejido'],
                ['FechaTejido','Fecha Tejido', ['type'=>'date']],
                ['FechaCumplimiento','Fecha Cumplimiento', ['type'=>'date']],
                ['SalonTejidoId','Salón Tejido'],
                ['NoTelarId','No. Telar'],
                ['Prioridad','Prioridad'],
                ['Nombre','Nombre'],
                ['ClaveModelo','Clave Modelo'],
                ['ItemId','Item ID'],
                ['InventSizeId','Invent Size ID'],
                ['Tolerancia','Tolerancia'],
                ['CodigoDibujo','Código Dibujo'],
                ['FechaCompromiso','Fecha Compromiso', ['type'=>'date']],
                ['FlogsId','Flogs ID'],
                ['NombreProyecto','Nombre Proyecto'],
                ['Clave','Clave'],

                // Producción & Medidas
                ['Pedido','Pedido', ['type'=>'number','step'=>'0.0001']],
                ['Peine','Peine', ['type'=>'number']],
                ['AnchoToalla','Ancho Toalla', ['type'=>'number']],
                ['LargoToalla','Largo Toalla', ['type'=>'number']],
                ['PesoCrudo','Peso Crudo', ['type'=>'number']],
                ['Luchaje','Luchaje', ['type'=>'number']],
                ['CalibreTrama','Calibre Trama', ['type'=>'number','step'=>'0.0001']],
                ['CalibreTrama2','Calibre Trama 2', ['type'=>'number','step'=>'0.0001']],
                ['VelocidadSTD','Velocidad STD', ['type'=>'number']],
                ['TotalMarbetes','Total Marbetes', ['type'=>'number']],
                ['Repeticiones','Repeticiones', ['type'=>'number']],
                ['NoTiras','No. Tiras', ['type'=>'number']],

                // Trama & Rizo
                ['CodColorTrama','Código Color Trama'],
                ['ColorTrama','Color Trama'],
                ['FibraId','Fibra ID'],
                ['DobladilloId','Tipo plano (Dobladillo Id)'],
                ['MedidaPlano','Medida plano', ['type'=>'number']],
                ['TipoRizo','Tipo de rizo'],
                ['AlturaRizo','Altura de rizo'],
                ['CalibreRizo','Calibre Rizo', ['type'=>'number','step'=>'0.0001']],
                ['CalibreRizo2','Calibre Rizo 2', ['type'=>'number','step'=>'0.0001']],
                ['CuentaRizo','Cuenta Rizo'],
                ['FibraRizo','Fibra Rizo'],
                ['CalibrePie','Calibre Pie', ['type'=>'number','step'=>'0.0001']],
                ['CalibrePie2','Calibre Pie 2', ['type'=>'number','step'=>'0.0001']],
                ['CuentaPie','Cuenta Pie'],
                ['FibraPie','Fibra Pie'],
                ['Obs','Observaciones'],

                // Combinaciones
                ['Comb1','C1 (Comb1)'],
                ['Obs1','Obs C1'],
                ['Comb2','C2 (Comb2)'],
                ['Obs2','Obs C2'],
                ['Comb3','C3 (Comb3)'],
                ['Obs3','Obs C3'],
                ['Comb4','C4 (Comb4)'],
                ['Obs4','Obs C4'],

                // Cenefa & Calidad
                ['MedidaCenefa','Med. de Cenefa'],
                ['MedIniRizoCenefa','Med. de inicio de rizo a cenefa'],
                ['Rasurado','Rasurada (Sí/No)'],
                ['CambioRepaso','Cambio de repaso'],
                ['Vendedor','Vendedor'],
                ['CatCalidad','No. Orden (Cat. Calidad)'],
                ['Obs5','Observaciones (Obs5)'],

                // Trama & Lucha
                ['AnchoPeineTrama','TRAMA (Ancho Peine)', ['type'=>'number']],
                ['LogLuchaTotal','LOG. de Lucha Total', ['type'=>'number']],

                // Fondo C1
                ['CalTramaFondoC1','C1 trama de Fondo', ['type'=>'number','step'=>'0.0001']],
                ['CalTramaFondoC12','Hilo Fondo C1', ['type'=>'number','step'=>'0.0001']],
                ['FibraTramaFondoC1','OBS Fondo C1'],
                ['PasadasTramaFondoC1','Pasadas Fondo C1', ['type'=>'number']],

                // Detalle Comb1
                ['CalibreComb1','C1', ['type'=>'number','step'=>'0.0001']],
                ['CalibreComb12','Hilo C1', ['type'=>'number','step'=>'0.0001']],
                ['FibraComb1','OBS C1'],
                ['CodColorC1','Cod Color C1'],
                ['NomColorC1','Nombre Color C1'],
                ['PasadasComb1','Pasadas C1', ['type'=>'number']],

                // Detalle Comb2
                ['CalibreComb2','C2', ['type'=>'number','step'=>'0.0001']],
                ['CalibreComb22','Hilo C2', ['type'=>'number','step'=>'0.0001']],
                ['FibraComb2','OBS C2'],
                ['CodColorC2','Cod Color C2'],
                ['NomColorC2','Nombre Color C2'],
                ['PasadasComb2','Pasadas C2', ['type'=>'number']],

                // Detalle Comb3
                ['CalibreComb3','C3', ['type'=>'number','step'=>'0.0001']],
                ['CalibreComb32','Hilo C3', ['type'=>'number','step'=>'0.0001']],
                ['FibraComb3','OBS C3'],
                ['CodColorC3','Cod Color C3'],
                ['NomColorC3','Nombre Color C3'],
                ['PasadasComb3','Pasadas C3', ['type'=>'number']],

                // Detalle Comb4
                ['CalibreComb4','C4', ['type'=>'number','step'=>'0.0001']],
                ['CalibreComb42','Hilo C4', ['type'=>'number','step'=>'0.0001']],
                ['FibraComb4','OBS C4'],
                ['CodColorC4','Cod Color C4'],
                ['NomColorC4','Nombre Color C4'],
                ['PasadasComb4','Pasadas C4', ['type'=>'number']],

                // Detalle Comb5
                ['CalibreComb5','C5', ['type'=>'number','step'=>'0.0001']],
                ['CalibreComb52','Hilo C5', ['type'=>'number','step'=>'0.0001']],
                ['FibraComb5','OBS C5'],
                ['CodColorC5','Cod Color C5'],
                ['NomColorC5','Nombre Color C5'],
                ['PasadasComb5','Pasadas C5', ['type'=>'number']],

                // Totales & Métricas
                ['Total','Total', ['type'=>'number','step'=>'0.0001']],
                ['PasadasDibujo','Pasadas Dibujo'],
                ['Contraccion','Contracción'],
                ['TramasCMTejido','Tramas cm/Tejido'],
                ['ContracRizo','Contrac. Rizo'],
                ['ClasificacionKG','Clasificación (KG)'],
                ['KGDia','KG/Día', ['type'=>'number','step'=>'0.0001']],
                ['Densidad','Densidad', ['type'=>'number','step'=>'0.0001']],
                ['PzasDiaPasadas','Pzas/Día/pasadas', ['type'=>'number','step'=>'0.0001']],
                ['PzasDiaFormula','Pzas/Día/fórmula', ['type'=>'number','step'=>'0.0001']],
                ['DIF','DIF', ['type'=>'number','step'=>'0.0001']],
                ['EFIC','EFIC', ['type'=>'number','step'=>'0.0001']],
                ['Rev','Rev', ['type'=>'number','step'=>'0.0001']],
                ['TIRAS','TIRAS', ['type'=>'number','step'=>'0.0001']],
                ['PASADAS','PASADAS', ['type'=>'number','step'=>'0.0001']],
                ['ColumCT','ColumCT', ['type'=>'number','step'=>'0.0001']],
                ['ColumCU','ColumCU', ['type'=>'number','step'=>'0.0001']],
                ['ColumCV','ColumCV', ['type'=>'number','step'=>'0.0001']],
                ['ComprobarModDup','COMPROBAR modelos duplicados'],
            ];
            @endphp

            <!-- Render horizontal (por filas) -->
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                    @foreach($todosLosCampos as $c)
                        @php
                            $render($c[0], $c[1], $c[2] ?? []);
                        @endphp
                    @endforeach
                </div>
            </div>
        </form>
    </div>
</div>

<style>
/* Estilos adicionales para inputs más elegantes */
input[type="text"],
input[type="date"],
input[type="number"] {
    transition: all 0.2s ease-in-out;
}

input:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Scroll suave para formularios largos */
html {
    scroll-behavior: smooth;
}

/* Mejoras visuales para labels */
label {
    font-weight: 600;
    letter-spacing: 0.025em;
}

/* Hover effects para inputs */
input:hover:not(:focus) {
    border-color: #9ca3af;
}

/* Optimización para tablets */
@media (min-width: 768px) and (max-width: 1024px) {
    input[type="text"],
    input[type="date"],
    input[type="number"] {
        padding: 0.5rem;
        font-size: 0.75rem;
    }

    label {
        font-size: 0.6875rem;
        margin-bottom: 0.125rem;
    }
}

/* Estilos ultra compactos */
input[type="text"],
input[type="date"],
input[type="number"] {
    padding: 0.375rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1.2;
}

label {
    font-size: 0.6875rem;
    line-height: 1.2;
    margin-bottom: 0.125rem;
}

/* Mejoras para navegación con teclado */
.tab-navigation:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Indicador visual para inputs requeridos */
input[required] {
    border-left: 3px solid #ef4444;
}

input[required]:focus {
    border-left-color: #3b82f6;
}
</style>

<script>
(function () {
    const form = document.getElementById('codificacion-form');
    const idEl = document.getElementById('codificacion-id');
    const isEdit = idEl && idEl.value !== '';

    // Debug: mostrar información del modo
    console.log('Modo edición:', isEdit);
    if (isEdit) {
        console.log('ID del registro:', idEl.value);

        // Mostrar notificación de que se cargaron los datos
        setTimeout(() => {
            Swal.fire({
                title: 'Datos cargados',
                text: 'Los datos del registro se han cargado correctamente',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }, 500);
    }

    // Prefill en edición
    @if(isset($codificacion))
        const dateFields = ['FechaTejido','FechaCumplimiento','FechaCompromiso'];
        dateFields.forEach(name => {
            const el = document.querySelector(`[name="${name}"]`);
            const raw = el && el.value ? new Date(el.value) : null;
            if (el && raw && !isNaN(raw)) el.value = new Date(raw).toISOString().substring(0,10);
        });
    @endif

    // Navegación con Enter - ir al siguiente input
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.classList.contains('tab-navigation')) {
            e.preventDefault();

            // Obtener todos los inputs del formulario
            const inputs = Array.from(form.querySelectorAll('input.tab-navigation'));
            const currentIndex = inputs.indexOf(e.target);

            if (currentIndex !== -1 && currentIndex < inputs.length - 1) {
                // Ir al siguiente input
                inputs[currentIndex + 1].focus();
                inputs[currentIndex + 1].select(); // Seleccionar texto para reemplazar fácilmente
            } else if (currentIndex === inputs.length - 1) {
                // Si es el último input, enviar el formulario
                form.dispatchEvent(new Event('submit'));
            }
        }
    });

    // Envío con fetch
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Mostrar confirmación antes de enviar
        const confirmResult = await Swal.fire({
            title: isEdit ? '¿Actualizar registro?' : '¿Guardar nuevo registro?',
            text: isEdit
                ? '¿Estás seguro de que quieres actualizar este registro?'
                : '¿Estás seguro de que quieres guardar este nuevo registro?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: isEdit ? 'Sí, actualizar' : 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmResult.isConfirmed) {
            return;
        }

        const data = Object.fromEntries(new FormData(form).entries());
        const url = isEdit
            ? `/planeacion/catalogos/codificacion-modelos/${idEl.value}`
            : `/planeacion/catalogos/codificacion-modelos`;
        const method = isEdit ? 'PUT' : 'POST';

        // Mostrar loading
        Swal.fire({
            title: isEdit ? 'Actualizando registro...' : 'Guardando registro...',
            text: 'Por favor espera mientras se procesa la información',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const json = await res.json();

            if (!res.ok || json.success === false) {
                throw new Error(json.message || 'No fue posible guardar el registro');
            }

            // Éxito - Mostrar SweetAlert2
            Swal.fire({
                title: isEdit ? '¡Registro actualizado!' : '¡Registro creado!',
                text: isEdit
                    ? 'El registro se ha actualizado correctamente'
                    : 'El nuevo registro se ha guardado exitosamente',
                icon: 'success',
                confirmButtonText: 'Continuar',
                confirmButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/planeacion/catalogos/codificacion-modelos';
                }
            });

        } catch (err) {
            // Error - Mostrar SweetAlert2
            Swal.fire({
                title: 'Error',
                text: err.message || 'Ocurrió un error inesperado',
                icon: 'error',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#d33'
            });
        }
    }, { passive: false });

    // Auto-focus en el primer input al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const firstInput = form.querySelector('input.tab-navigation');
        if (firstInput) {
            firstInput.focus();
        }

        // Cargar datos en modo edición
        if (isEdit && window.codificacionData) {
            console.log('Cargando datos en formulario...');
            loadFormData(window.codificacionData);
        }
    });

    // Función para cargar datos en el formulario
    function loadFormData(data) {
        // Mapear campos del objeto a inputs del formulario
        const fieldMappings = {
            'TamanoClave': 'TamanoClave',
            'OrdenTejido': 'OrdenTejido',
            'FechaTejido': 'FechaTejido',
            'FechaCumplimiento': 'FechaCumplimiento',
            'SalonTejidoId': 'SalonTejidoId',
            'NoTelarId': 'NoTelarId',
            'Prioridad': 'Prioridad',
            'Nombre': 'Nombre',
            'ClaveModelo': 'ClaveModelo',
            'ItemId': 'ItemId',
            'InventSizeId': 'InventSizeId',
            'Tolerancia': 'Tolerancia',
            'CodigoDibujo': 'CodigoDibujo',
            'FechaCompromiso': 'FechaCompromiso',
            'FlogsId': 'FlogsId',
            'NombreProyecto': 'NombreProyecto',
            'Clave': 'Clave',
            'Pedido': 'Pedido',
            'Peine': 'Peine',
            'AnchoToalla': 'AnchoToalla',
            'LargoToalla': 'LargoToalla',
            'PesoCrudo': 'PesoCrudo',
            'Luchaje': 'Luchaje',
            'CalibreTrama': 'CalibreTrama',
            'CodColorTrama': 'CodColorTrama',
            'ColorTrama': 'ColorTrama',
            'FibraId': 'FibraId',
            'DobladilloId': 'DobladilloId',
            'MedidaPlano': 'MedidaPlano',
            'TipoRizo': 'TipoRizo',
            'AlturaRizo': 'AlturaRizo',
            'VelocidadSTD': 'VelocidadSTD',
            'CalibreRizo': 'CalibreRizo',
            'CalibrePie': 'CalibrePie',
            'NoTiras': 'NoTiras',
            'Repeticiones': 'Repeticiones',
            'TotalMarbetes': 'TotalMarbetes',
            'CambioRepaso': 'CambioRepaso',
            'Vendedor': 'Vendedor',
            'CatCalidad': 'CatCalidad',
            'Obs5': 'Obs5',
            'AnchoPeineTrama': 'AnchoPeineTrama',
            'LogLuchaTotal': 'LogLuchaTotal',
            'CalTramaFondoC1': 'CalTramaFondoC1',
            'PasadasTramaFondoC1': 'PasadasTramaFondoC1',
            'CodColorC1': 'CodColorC1',
            'NomColorC1': 'NomColorC1',
            'PasadasComb1': 'PasadasComb1',
            'CodColorC2': 'CodColorC2',
            'NomColorC2': 'NomColorC2',
            'PasadasComb2': 'PasadasComb2',
            'CodColorC3': 'CodColorC3',
            'NomColorC3': 'NomColorC3',
            'PasadasComb3': 'PasadasComb3',
            'CodColorC4': 'CodColorC4',
            'NomColorC4': 'NomColorC4',
            'PasadasComb4': 'PasadasComb4',
            'CodColorC5': 'CodColorC5',
            'NomColorC5': 'NomColorC5',
            'PasadasComb5': 'PasadasComb5',
            'Total': 'Total',
            'PasadasDibujo': 'PasadasDibujo',
            'Contraccion': 'Contraccion',
            'TramasCMTejido': 'TramasCMTejido',
            'ContracRizo': 'ContracRizo',
            'ClasificacionKG': 'ClasificacionKG',
            'KGDia': 'KGDia',
            'Densidad': 'Densidad',
            'PzasDiaPasadas': 'PzasDiaPasadas',
            'PzasDiaFormula': 'PzasDiaFormula',
            'DIF': 'DIF',
            'EFIC': 'EFIC',
            'Rev': 'Rev',
            'TIRAS': 'TIRAS',
            'PASADAS': 'PASADAS',
            'ColumCT': 'ColumCT',
            'ColumCU': 'ColumCU',
            'ColumCV': 'ColumCV',
            'ComprobarModDup': 'ComprobarModDup'
        };

        // Cargar datos en los campos
        Object.keys(fieldMappings).forEach(dataKey => {
            const fieldName = fieldMappings[dataKey];
            const input = form.querySelector(`[name="${fieldName}"]`);
            if (input && data[dataKey] !== null && data[dataKey] !== undefined) {
                input.value = data[dataKey];
                console.log(`Cargado ${fieldName}: ${data[dataKey]}`);
            }
        });
    }
})();
</script>
@endsection
