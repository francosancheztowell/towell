/**
 * Configuración global para el módulo de Programa de Tejido
 */
window.ProgramaTejidoConfig = {
    // URLs de API
    api: {
        salon: '/programa-tejido/salon-options',
        telaresBySalon: '/programa-tejido/telares-by-salon',
        tamanoClaveBySalon: '/programa-tejido/tamano-clave-by-salon',
        ultimaFechaFinalTelar: '/programa-tejido/ultima-fecha-final-telar',
        hilos: '/programa-tejido/hilos-options',
        flogsId: '/programa-tejido/flogs-id-options',
        calendarioId: '/programa-tejido/calendario-id-options',
        aplicacionId: '/programa-tejido/aplicacion-id-options',
        datosRelacionados: '/programa-tejido/datos-relacionados',
        eficienciaStd: '/programa-tejido/eficiencia-std',
        velocidadStd: '/programa-tejido/velocidad-std',
        guardar: '/planeacion/programa-tejido',
        actualizar: '/planeacion/programa-tejido/'
    },

    // Mapeo de campos entre DB y UI
    fieldMappings: {
        // Campos principales básicos
        'CuentaRizo': 'cuenta-rizo',
        'CalibreRizo2': 'calibre-rizo',
        'CalibreRizo': 'calibre-rizo',
        'FibraRizo': 'hilo-rizo',
        'FlogsId': 'idflog-select',
        'InventSizeId': 'tamano',
        'Nombre': 'nombre-modelo',
        'NombreProyecto': 'nombre-proyecto',
        'Rasurado': 'rasurado',
        'CalibreTrama': 'calibre-trama',
        'FibraId': 'hilo-trama',
        'CalibrePie': 'calibre-pie',
        'CalibrePie2': 'calibre-pie',
        'CuentaPie': 'cuenta-pie',
        'FibraPie': 'hilo-pie',
        'AnchoToalla': 'ancho',
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
        'CodColorTrama': 'cod-color-1',
        'ColorTrama': 'nombre-color-1',
        // Colores C1-C5
        'CodColorC1': 'cod-color-2',
        'NomColorC1': 'nombre-color-2',
        'CodColorC2': 'cod-color-3',
        'NomColorC2': 'nombre-color-3',
        'CodColorC3': 'cod-color-4',
        'NomColorC3': 'nombre-color-4',
        'CodColorC4': 'cod-color-5',
        'NomColorC4': 'nombre-color-5',
        'CodColorC5': 'cod-color-6',
        'NomColorC5': 'nombre-color-6',
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
