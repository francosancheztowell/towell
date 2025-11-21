/**
 * Configuración global para el módulo de Programa de Tejido
 */
// Detectar si estamos en simulación
const isSimulacion = window.location.pathname.includes('/simulacion');
const basePath = isSimulacion ? '/simulacion' : '/planeacion/programa-tejido';
const apiBasePath = isSimulacion ? '/simulacion' : '/programa-tejido';

window.ProgramaTejidoConfig = {
    // URLs de API
    api: {
        salon: `${apiBasePath}/salon-tejido-options`,
        telaresBySalon: `${apiBasePath}/telares-by-salon`,
        tamanoClaveBySalon: `${apiBasePath}/tamano-clave-by-salon`,
        ultimaFechaFinalTelar: `${apiBasePath}/ultima-fecha-final-telar`,
        hilos: `${apiBasePath}/hilos-options`,
        flogsId: `${apiBasePath}/flogs-id-options`,
        flogsIdFromTwFlogs: `${apiBasePath}/flogs-id-from-twflogs`,
        descripcionByIdFlog: `${apiBasePath}/descripcion-by-idflog`,
        calendarioId: `${apiBasePath}/calendario-id-options`,
        calendarioLineas: `${apiBasePath}/calendario-lineas`,
        aplicacionId: `${apiBasePath}/aplicacion-id-options`,
        datosRelacionados: `${apiBasePath}/datos-relacionados`,
        eficienciaStd: `${apiBasePath}/eficiencia-std`,
        velocidadStd: `${apiBasePath}/velocidad-std`,
        guardar: basePath,
        actualizar: `${basePath}/`
    },

    // Mapeo de campos entre DB y UI
    fieldMappings: {
        // Campos principales básicos
        'CuentaRizo': 'cuenta-rizo',
        'CalibreRizo2': 'calibre-rizo',  // ⭐ CRÍTICO: Solo CalibreRizo2 debe mapearse a calibre-rizo, NO CalibreRizo
        // 'CalibreRizo': 'calibre-rizo',  // ⭐ ELIMINADO: CalibreRizo NO debe mapearse a calibre-rizo (es campo base/rosa)
        'FibraRizo': 'hilo-rizo',
        'FlogsId': 'idflog-select',
        'InventSizeId': 'tamano',
        'Nombre': 'nombre-modelo',
        'NombreProyecto': 'nombre-proyecto',
        'Rasurado': 'rasurado',
        'CalibreTrama': 'calibre-trama',
        'FibraTramaFondoC1': 'hilo-trama',  // ⭐ Campo correcto: FibraTramaFondoC1 de ReqModelosCodificados
        'CalibrePie': 'calibre-pie',
        'CalibrePie2': 'calibre-pie',
        'CuentaPie': 'cuenta-pie',
        'FibraPie': 'hilo-pie',
        'AnchoToalla': 'ancho',  // ⭐ Mostrar en el input el Ancho original del modelo (se guardará en campo Ancho)
        'EficienciaSTD': 'eficiencia-std',
        'VelocidadSTD': 'velocidad-std',
        'Maquina': 'maquina',
        'LargoToalla': 'largo-toalla',
        'PesoCrudo': 'peso-crudo',
        'Luchaje': 'luchaje',
        'Peine': 'peine',
        'NoTiras': 'no-tiras',
        'Repeticiones': 'repeticiones',
        'Total': 'total',
        'MedidaPlano': 'medida-plano',
        // Colores Trama
        'CodColorTrama': 'cod-color',  // ⭐ Mapeo corregido: cod-color (sin -1)
        'ColorTrama': 'nombre-color',  // ⭐ Mapeo corregido: nombre-color (sin -1)
        // Colores C1-C5
        'CodColorC1': 'cod-color-1',  // ⭐ Corregido: C1 -> cod-color-1
        'NomColorC1': 'nombre-color-1',  // ⭐ Corregido: C1 -> nombre-color-1
        'CodColorC2': 'cod-color-2',  // ⭐ Corregido: C2 -> cod-color-2
        'NomColorC2': 'nombre-color-2',  // ⭐ Corregido: C2 -> nombre-color-2
        'CodColorC3': 'cod-color-3',  // ⭐ Corregido: C3 -> cod-color-3
        'NomColorC3': 'nombre-color-3',  // ⭐ Corregido: C3 -> nombre-color-3
        'CodColorC4': 'cod-color-4',  // ⭐ Corregido: C4 -> cod-color-4
        'NomColorC4': 'nombre-color-4',  // ⭐ Corregido: C4 -> nombre-color-4
        'CodColorC5': 'cod-color-5',  // ⭐ Corregido: C5 -> cod-color-5
        'NomColorC5': 'nombre-color-5',  // ⭐ Corregido: C5 -> nombre-color-5
        // Calibres C1-C5
        'CalibreComb12': 'calibre-c1',
        'CalibreComb22': 'calibre-c2',
        'CalibreComb32': 'calibre-c3',
        'CalibreComb42': 'calibre-c4',
        'CalibreComb52': 'calibre-c5',
        // Fibras C1-C5
        'FibraComb1': 'hilo-c1',
        'FibraComb2': 'hilo-c2',
        'FibraComb3': 'hilo-c3',
        'FibraComb4': 'hilo-c4',
        'FibraComb5': 'hilo-c5'
    },

    // Configuración de calendarios
    calendarios: {
        'Calendario Tej1': {
            laboraDomingo: true,
            laboraSabado: true,
            horasSabado: 24
        },
        'Calendario Tej2': {
            laboraDomingo: false,
            laboraSabado: true,
            horasSabado: 24
        },
        'Calendario Tej3': {
            laboraDomingo: false,
            laboraSabado: true,
            horasSabado: 18.483333 // Hasta 18:29
        }
    },

    // Configuración de UI
    ui: {
        autocompletadoDelay: 150,
        minimoCaracteresAutocompletado: 1,
        clasesBotonHabilitado: 'px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2 text-sm',
        clasesBotonDeshabilitado: 'px-3 py-2 bg-gray-400 text-white rounded cursor-not-allowed flex items-center gap-2 text-sm',
        clasesBotonEliminarHabilitado: 'px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 flex items-center gap-2 text-sm',
        clasesInputSeleccionado: 'ring-2 ring-blue-500',
        // Array de clases para facilitar el uso con classList
        clasesInputSeleccionadoArray: ['ring-2', 'ring-blue-500']
    }
};
