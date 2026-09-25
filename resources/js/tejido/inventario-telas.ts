// Inventario de telas: logica de requerimientos por telar.
//
// Antes vivia dentro de <script> en el componente telar-requerimiento, que se
// renderiza una vez por telar: 14 copias del mismo codigo en Jacquard (1.9 MB
// de HTML), las funciones globales sobrescribiendose entre si y 14 modales con
// el mismo id. Aqui hay una sola copia y el telar entra por parametro.
//
// Portado tal cual desde el Blade y tipado sin cambiar comportamiento (FE-06).
// Los datos del servidor (telar, inventario) llegan con forma abierta y nombres
// de campo variables, por eso se tipan como `Crudo`/`Registro`.

import { http } from '../utils/http.ts'
import type { HttpError } from '../utils/http.ts'

/** Valor tal como llega del servidor: string, número o null. */
type Crudo = any

/** Fila de tej_inventario_telares o datos de un telar (campos variables por fuente). */
type Registro = Record<string, Crudo>

type TelarId = number | string

type TelarConfig = {
  telarId: number
  telarData: Registro
  ordenSigData: Registro | null
  salonTelar: string
}

/** Lo que pinta la tabla del modal de seleccion. */
interface DatosModal {
  cuenta: Crudo
  calibre: Crudo
  fibra: Crudo
  ordenProd: Crudo
}

interface SeleccionGuardada {
  seleccion: string
  datos: DatosModal
  ordenProd: Crudo
}

interface ModalData {
  telarId: TelarId | null
  tipo: string | null
  datosProceso: DatosModal | null
  datosSiguiente: DatosModal | null
  salonTelar?: string
  /** Por telar y tipo. */
  seleccionGuardada?: Record<string, Record<string, SeleccionGuardada>>
}

interface FiltrosInventario {
  hilo?: string
  no_telar?: string
  tipo?: string
  salon?: string
}

/** Cache del inventario: campos fijos + llaves dinamicas `inventario_*`, `loading_*`, `promises_*`. */
interface InventarioCache {
  data: Registro[] | null
  timestamp: number | null
  loading: boolean
  promises: unknown[]
  maxAge: number
  [key: string]: any
}

interface DatosEliminar {
  no_telar: string
  tipo: string
  fecha: string
  turno: number
}

interface TelarPendiente {
  salon: string
  telarId: TelarId
}

/** Respuesta de /inventario-telares/verificar-estado. */
interface EstadoTelar {
  success?: boolean
  reservado?: boolean | number | string
  programado?: boolean | number | string
  status_urdido?: string | null
  puede_eliminar?: boolean
  registro_id?: number | string
  message?: string
}

/**
 * Estado compartido entre las tarjetas de telar y los onclick inline de los
 * modales. Vive en window porque el HTML lo lee; es de este modulo, no de utils.
 */
declare global {
  interface Window {
    _scrollToTelarDone?: boolean
    inventarioCargado?: boolean
    cargandoRequerimientosPorTelar: Record<string, boolean>
    inventarioCache: InventarioCache
    modalData: ModalData
    datosEliminacionPendiente: DatosEliminar | null
    checkboxEliminacionPendiente: HTMLInputElement | null
    checkboxPendienteCalendario: HTMLInputElement | null
    telarIdPendienteCalendario: TelarId | null
    tipoPendienteCalendario: string | null
    turnoPendienteCalendario: number | null
    fechaOriginalPendienteCalendario: string | null
    registroIdPendienteCalendario: number | string | null
    fechaSeleccionadaCalendario: string | null
    telarDataPendiente: TelarPendiente | null
    telarDataCompleto: Registro | null
    estadoModalTela?: string
    statusUrdido?: string | null
    puedeEliminar?: boolean
    salonTelar?: string
    mostrarModalTelaReservada: () => void
    mostrarModalCalendarioSemanal: () => void
    cerrarModalCalendarioSemanal: () => void
    mostrarSeleccionTurnos: (fechaISO: string, diaElement?: HTMLElement | null) => Promise<void>
    manejarCancelarModal2: () => void
    seleccionarTurno: (turno: number, btnElement?: HTMLButtonElement) => void
    cerrarModalTelaReservada: () => void
  }
}

// Scroll al telar después de reload (se ejecuta una sola vez, fuera del scope del telar)
if (!window._scrollToTelarDone) {
    window._scrollToTelarDone = true;
    document.addEventListener('DOMContentLoaded', function() {
        const scrollTelarId = sessionStorage.getItem('scrollToTelar');
        if (scrollTelarId) {
            sessionStorage.removeItem('scrollToTelar');
            // Pequeño delay para asegurar que el DOM esté completamente renderizado
            setTimeout(() => {
                const telarElement = document.getElementById('telar-' + scrollTelarId);
                if (telarElement) {
                    telarElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Resaltar brevemente el telar para que sea fácil de identificar
                    telarElement.style.transition = 'box-shadow 0.3s ease';
                    telarElement.style.boxShadow = '0 0 0 4px #3b82f6';
                    setTimeout(() => {
                        telarElement.style.boxShadow = '';
                    }, 2500);
                }
            }, 500);
        }
    });
}

/**
 * El tipo de un checkbox es 'rizo', 'pie' o '1'..'4' (barras de Karl Mayer).
 * esBarra() distingue; tipoServidor() es lo que se guarda en tej_inventario_telares,
 * con el mismo canon que UrdProgramaUrdido.RizoPie.
 */
const esBarra = (tipo: unknown): boolean => /^[1-4]$/.test(String(tipo))

const tipoServidor = (tipo: unknown): string => (esBarra(tipo) ? String(tipo) : (tipo === 'rizo' ? 'Rizo' : 'Pie'))

/** Cuenta, calibre y fibra del componente (barra N, rizo o pie) de un registro de telar. */
function datosComponente(datos: Registro | null | undefined, tipo: unknown) {
    if (esBarra(tipo)) {
        const barra = datos?.barras?.[String(tipo)]
        return { cuenta: barra?.Cuenta ?? '', calibre: barra?.Calibre ?? '', fibra: barra?.Fibra ?? '' }
    }

    return tipo === 'rizo'
        ? { cuenta: datos?.Cuenta ?? '', calibre: datos?.CalibreRizo2 ?? '', fibra: datos?.Fibra_Rizo ?? '' }
        : { cuenta: datos?.Cuenta_Pie ?? '', calibre: datos?.CalibrePie2 ?? '', fibra: datos?.Fibra_Pie ?? '' }
}


/** Lo que pinta la tabla del modal de seleccion: guion cuando el dato no viene. */
function datosParaModal(datos: Registro | null | undefined, tipo: unknown): DatosModal {
    const { cuenta, calibre, fibra } = datosComponente(datos, tipo)
    const oGuion = (v: Crudo) => (String(v ?? '').trim() !== '' ? v : '-')

    return {
        cuenta: oGuion(cuenta),
        calibre: oGuion(calibre),
        fibra: oGuion(fibra),
        ordenProd: datos?.Orden_Prod || '',
    }
}

/** Arranca una tarjeta de telar (antes era el IIFE por telar del Blade). */
function initTelar(cfg: TelarConfig): void {
    const { telarId, telarData, ordenSigData, salonTelar } = cfg

    function inicializarFechasTablas() {
        // Inicializar atributos data-fecha-completa en todas las tablas del telar
        const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
        const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
            const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"]`) !== null;
            return tieneCheckboxDelTelar;
        });

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        // Por defecto: columna 0 = hoy, 1 = hoy+1, ... (loadRequerimientos ajusta si hay fechas anteriores)
        todasLasTablasDelTelar.forEach((tabla, index) => {
            const thHeader = tabla.querySelector('th');
            if (thHeader && !thHeader.getAttribute('data-fecha-completa')) {
                const fechaOriginal = new Date(hoy);
                fechaOriginal.setDate(hoy.getDate() + index);
                const año = fechaOriginal.getFullYear();
                const mes = fechaOriginal.getMonth() + 1;
                const dia = fechaOriginal.getDate();
                const fechaCompleta = `${año}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
                thHeader.setAttribute('data-fecha-completa', fechaCompleta);
            }
        });
    }

    function inicializarTelar() {
        // Configurar event listeners para checkboxes con datos específicos de este telar
        setupRequerimientoCheckboxes(telarId, telarData, ordenSigData, salonTelar);

        // Inicializar fechas de las tablas primero
        inicializarFechasTablas();

        // Esperar a que las tablas estén renderizadas antes de cargar requerimientos
        // Usar setTimeout para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            // Obtener el tipo del componente desde los checkboxes (rizo o pie)
            const primerCheckbox = document.querySelector(`input[data-telar="${telarId}"]`);
            const tipoComponente = primerCheckbox ? primerCheckbox.getAttribute('data-tipo') : null;

            if (!tipoComponente) {
                // Si no se encuentra el tipo, cargar sin filtro
                loadRequerimientos(telarId, salonTelar);
                return;
            }

            // Verificar si hay una selección guardada para este telar y tipo
            // Si existe, usar esa fibra para filtrar desde el inicio
            const seleccionGuardada = window.modalData?.seleccionGuardada?.[String(telarId)]?.[tipoComponente];

            if (seleccionGuardada && seleccionGuardada.datos && seleccionGuardada.datos.fibra) {
                const fibraGuardada = seleccionGuardada.datos.fibra;
                const fibraNormalizada = fibraGuardada ? String(fibraGuardada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';


                if (fibraValida) {
                    // Cargar requerimientos filtrando por la fibra guardada
                    loadRequerimientosConFiltro(telarId, salonTelar, tipoComponente, fibraNormalizada);
                } else {
                    // Si no hay fibra válida, cargar todos los requerimientos sin filtro
                    loadRequerimientos(telarId, salonTelar);
                }
            } else {
                // No hay selección guardada, cargar todos los requerimientos sin filtro
                loadRequerimientos(telarId, salonTelar);
            }
        }, 100);

        // Precargar inventario al inicio (solo una vez) usando el sistema de caché
        if (!window.inventarioCargado) {
            obtenerInventarioConCache();
            window.inventarioCargado = true;
        }
    }

    setTimeout(inicializarTelar, 50)
}

function setupRequerimientoCheckboxes(telarId: TelarId, telarData: Registro, ordenSigData: Registro | null, salon: string) {
    const checkboxes = document.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"]`);

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function(this: HTMLInputElement) {
            handleRequerimientoChange(this, telarId, telarData, ordenSigData, salon);
        });
    });
}

function handleRequerimientoChange(checkbox: HTMLInputElement, telarId: TelarId, telarData: Registro, ordenSigData: Registro | null, salon: string) {
    // Evitar procesar cambios mientras se están cargando requerimientos para este telar
    const key = `${telarId}_${salon}`;
    if (window.cargandoRequerimientosPorTelar && window.cargandoRequerimientosPorTelar[key]) {
        // Revertir el cambio si estamos cargando
        checkbox.checked = !checkbox.checked;
        return;
    }

    const fila = checkbox.closest('tr');
    const tabla = fila!.closest('table');
    const fechaElement = tabla!.querySelector('th');

    // Verificar si el header tiene una fecha modificada (fecha antigua)
    let fecha = '';
    let fechaISO: string | null = null;

    if (fechaElement) {
        // Si el header tiene una fecha original (modificada), usar esa fecha
        const fechaOriginalAttr = fechaElement.getAttribute('data-fecha-original');
        const fechaCompletaAttr = fechaElement.getAttribute('data-fecha-completa');

        if (fechaOriginalAttr || fechaCompletaAttr) {
            // Usar la fecha completa del atributo si está disponible
            fechaISO = fechaCompletaAttr || fechaOriginalAttr;
            // Convertir fecha ISO a formato dd/mm para el texto
            if (fechaISO) {
                const [y, m, d] = fechaISO.split('-');
                fecha = `${d}/${m}`;
            }
        } else {
            // Usar el texto del header normalmente
            fecha = fechaElement.innerText.trim();
        }
    }

    const tipo = checkbox.dataset.tipo;

    // Usar datos pasados como parámetros (específicos de este telar)
    const datos = telarData;

    const cuentaRizo = datos.Cuenta || '';
    const cuentaPie = datos.Cuenta_Pie || '';
    const calibreRizo = datos.CalibreRizo2 || 0;
    const calibrePie = datos.CalibrePie2 || 0;

    // El turno lo declara el propio checkbox. Antes se sacaba quitandole las letras
    // al value ("rizo1" -> 1), y en Karl Mayer el tipo tambien es un numero, asi que
    // la barra 2 del turno 1 ("21") mandaba turno 21 y el guardado fallaba con 422.
    const numeroTurno = parseInt(checkbox.dataset.turno as string, 10);

    // Convertir fecha del formato dd/mm a formato ISO (YYYY-MM-DD)
    function convertirFecha(fechaTexto: string, fechaISOExistente: string | null) {
        // Si ya tenemos una fecha ISO (del header modificado), usarla directamente
        if (fechaISOExistente) {
            return fechaISOExistente;
        }

        // Extraer solo la fecha dd/mm del texto (puede incluir día de la semana)
        const fechaMatch = fechaTexto.match(/(\d{1,2})\/(\d{1,2})/);
        if (fechaMatch) {
            const dia = fechaMatch[1]!.padStart(2, '0');
            const mes = fechaMatch[2]!.padStart(2, '0');
            const año = new Date().getFullYear();
            return `${año}-${mes}-${dia}`;
        }
        return null;
    }

    // Validar que tenemos los datos necesarios
    const cuentaSeleccionada = esBarra(tipo) ? datosComponente(datos, tipo).cuenta : (tipo === 'rizo' ? cuentaRizo : cuentaPie);
    const calibreSeleccionado = esBarra(tipo) ? datosComponente(datos, tipo).calibre : (tipo === 'rizo' ? calibreRizo : calibrePie);

    // Convertir fecha y validar (usar fechaISO si está disponible del header modificado)
    const fechaConvertida = convertirFecha(fecha, fechaISO);
    if (!fechaConvertida) {
        alert('Error al extraer la fecha del calendario. Intente de nuevo.');
        checkbox.checked = false;
        return;
    }

    // Si el checkbox se deseleccionó, verificar estado antes de eliminar
    if (!checkbox.checked) {
        // Verificar si el checkbox ya fue eliminado (no debe validar de nuevo)
        if (checkbox.getAttribute('data-eliminado') === 'true') {
            // Si ya fue eliminado, no hacer nada
            return;
        }

        // Marcar este checkbox como cambio reciente para preservarlo durante la recarga
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        // Usar la fecha real del registro si existe (cuando el registro se mostró en otra columna, ej. 02/02 en columna de hoy 03/02)
        const fechaRealRegistro = checkbox.getAttribute('data-fecha-original');
        const fechaParaEliminar = (fechaRealRegistro && fechaRealRegistro.match(/^\d{4}-\d{2}-\d{2}$/)) ? fechaRealRegistro : fechaConvertida;

        const datosEliminar = {
            no_telar: String(telarId),
            tipo: tipoServidor(tipo),
            fecha: fechaParaEliminar,
            turno: parseInt(String(numeroTurno))
        };

        // Verificar estado del telar antes de eliminar
        // IMPORTANTE: La verificación siempre debe usar el backend para obtener el estado más reciente
        // No confiar en el caché del frontend después de invalidaciones
        // El backend siempre tiene la verdad sobre el estado del registro específico
        verificarEstadoTelarAntesDeEliminar(telarId, tipoServidor(tipo), datosEliminar, checkbox, telarData);

        return; // Salir de la función si se deseleccionó
    }

    // Si el checkbox se seleccionó, guardar el registro
    // Verificar que tenemos cuenta válida (calibre puede ser null)
    if (!cuentaSeleccionada || cuentaSeleccionada === '') {
        alert('No se encontró cuenta para este telar. Verifique los datos del telar.');
        checkbox.checked = false; // Desmarcar checkbox
        return;
    }

    // Verificar si hay una selección guardada en el modal para este tipo
    const seleccionGuardada = window.modalData?.seleccionGuardada?.[telarId]?.[tipo as string];

    // Determinar TODOS los datos según la selección del modal
    let cuentaFinal, calibreFinal, hiloSeleccionado, noOrden;

    if (seleccionGuardada) {
        // Usar datos del modal si hay selección guardada (proceso o siguiente)
        cuentaFinal = seleccionGuardada.datos?.cuenta || cuentaSeleccionada;
        calibreFinal = seleccionGuardada.datos?.calibre || calibreSeleccionado;
        hiloSeleccionado = seleccionGuardada.datos?.fibra || '';
        noOrden = String(seleccionGuardada.ordenProd || '');
    } else {
        // Por defecto: usar datos del proceso actual
        cuentaFinal = cuentaSeleccionada;
        calibreFinal = calibreSeleccionado;
        hiloSeleccionado = datosComponente(datos, tipo).fibra || '';
        noOrden = String(datos.Orden_Prod || '');
    }
    const datosInventario = {
        no_telar: String(telarId),
        tipo: tipoServidor(tipo),
        cuenta: String(cuentaFinal),
        calibre: calibreFinal ? parseFloat(calibreFinal) : null,
        fecha: fechaConvertida,
        turno: parseInt(String(numeroTurno)),
        salon: salon, // Usar el salón correcto del componente
        hilo: hiloSeleccionado || '',
        no_orden: noOrden || ''
    };

    // Marcar este checkbox como cambio reciente para preservarlo durante la recarga
    checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

    // Enviar datos a la nueva tabla de inventario
    http.post('/inventario-telares/guardar', datosInventario)
        .then(response => {
            // Invalidar caché para que se actualice en la próxima carga
            invalidarCacheInventario();

            // El registro vuelve a existir: si esta casilla se habia eliminado antes,
            // dejaria de poder borrarse (el guard de data-eliminado corta el desmarcado
            // y el renglon quedaba vivo en la base sin manera de quitarlo).
            checkbox.removeAttribute('data-eliminado');

            // El checkbox ya está marcado visualmente, mantenerlo así
            // Remover el atributo de cambio reciente después de un momento
            setTimeout(() => {
                checkbox.removeAttribute('data-cambio-reciente');
            }, 3000);

            // Mostrar notificación de éxito
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado con éxito',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    position: 'top-end',
                    toast: true
                });
            }
        })
    .catch(error => {
        // Mostrar notificación de error con más detalles
        if (typeof Swal !== 'undefined') {
            // http normaliza el error: .message, .status, .data y .errors (422)
            const errorMessage = error.errors
                ? Object.values(error.errors).flat().join(', ')
                : (error.message || 'Error desconocido');

            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: errorMessage,
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
        // Desmarcar checkbox si hubo error
        checkbox.checked = false;
    });

    // NOTA: Sistema anterior deshabilitado - ahora usamos solo /inventario-telares/guardar
    // El endpoint /guardar-requerimiento causaba errores de SQL y ya no es necesario
}

// Variable para evitar actualizaciones simultáneas por telar
if (typeof window.cargandoRequerimientosPorTelar === 'undefined') {
    window.cargandoRequerimientosPorTelar = {};
}

// Sistema de caché para inventario compartido entre todos los telares
if (typeof window.inventarioCache === 'undefined') {
    window.inventarioCache = {
        data: null,
        timestamp: null,
        loading: false,
        promises: [],
        maxAge: 5000 // 5 segundos de caché
    };
}

// Función para obtener inventario con caché y evitar múltiples peticiones simultáneas
async function obtenerInventarioConCache(filtros: FiltrosInventario = {}): Promise<Registro[]> {
    const ahora = Date.now();

    // Crear clave de caché basada en los filtros
    const filtrosKey = JSON.stringify(filtros);
    const cacheKey = `inventario_${filtrosKey}`;

    // Si hay datos en caché para estos filtros y son recientes, devolverlos
    if (window.inventarioCache[cacheKey] && window.inventarioCache[cacheKey].timestamp &&
        (ahora - window.inventarioCache[cacheKey].timestamp) < window.inventarioCache.maxAge) {
        return Promise.resolve(window.inventarioCache[cacheKey].data);
    }

    // Si ya hay una petición en curso para estos filtros, esperar a que termine
    if (window.inventarioCache[`loading_${cacheKey}`]) {
        return new Promise<Registro[]>((resolve) => {
            if (!window.inventarioCache[`promises_${cacheKey}`]) {
                window.inventarioCache[`promises_${cacheKey}`] = [];
            }
            window.inventarioCache[`promises_${cacheKey}`].push(resolve);
        });
    }

    // Iniciar nueva petición
    window.inventarioCache[`loading_${cacheKey}`] = true;

    try {
        // Construir URL con filtros
        let url = '/inventario-telares';
        const filtrosArray = [];

        if (filtros.hilo) {
            filtrosArray.push({ columna: 'hilo', valor: filtros.hilo });
        }
        if (filtros.no_telar) {
            filtrosArray.push({ columna: 'no_telar', valor: filtros.no_telar });
        }
        if (filtros.tipo) {
            filtrosArray.push({ columna: 'tipo', valor: filtros.tipo });
        }
        if (filtros.salon) {
            filtrosArray.push({ columna: 'salon', valor: filtros.salon });
        }

        if (filtrosArray.length > 0) {
            const params = new URLSearchParams();
            params.append('filtros', JSON.stringify(filtrosArray));
            url += '?' + params.toString();
        }

        const json = await http.get(url)

        {
            const registros = json?.data || [];  // http lanza si el servidor responde error

            // Actualizar caché para estos filtros
            if (!window.inventarioCache[cacheKey]) {
                window.inventarioCache[cacheKey] = {};
            }
            window.inventarioCache[cacheKey].data = registros;
            window.inventarioCache[cacheKey].timestamp = ahora;

            // Resolver todas las promesas pendientes para estos filtros
            if (window.inventarioCache[`promises_${cacheKey}`]) {
                window.inventarioCache[`promises_${cacheKey}`].forEach((resolve: (r: Registro[]) => void) => resolve(registros));
                window.inventarioCache[`promises_${cacheKey}`] = [];
            }
            window.inventarioCache[`loading_${cacheKey}`] = false;

            return registros;
        }
    } catch (error) {
        // Resolver todas las promesas pendientes con error
        if (window.inventarioCache[`promises_${cacheKey}`]) {
            window.inventarioCache[`promises_${cacheKey}`].forEach((resolve: (r: Registro[]) => void) => resolve([]));
            window.inventarioCache[`promises_${cacheKey}`] = [];
        }
        window.inventarioCache[`loading_${cacheKey}`] = false;

        // Si hay datos antiguos en caché, usarlos
        if (window.inventarioCache.data) {
            return window.inventarioCache.data;
        }

        return [];
    }
}

// Función para invalidar el caché (llamar después de guardar/eliminar)
function invalidarCacheInventario() {
    // Limpiar caché antiguo (compatibilidad)
    window.inventarioCache.data = null;
    window.inventarioCache.timestamp = null;

    // Limpiar todos los cachés filtrados
    Object.keys(window.inventarioCache).forEach(key => {
        if (key.startsWith('inventario_') || key.startsWith('loading_inventario_') || key.startsWith('promises_inventario_')) {
            delete window.inventarioCache[key];
        }
    });
}

function loadRequerimientos(telarId: TelarId, salon: string, tipo: string | null = null, fibraFiltro: string | null = null): void {
    // Si se proporciona tipo y fibra, usar la función con filtro
    if (tipo && fibraFiltro) {
        return loadRequerimientosConFiltro(telarId, salon, tipo, fibraFiltro);
    }

    // Evitar múltiples llamadas simultáneas para el mismo telar
    const key = `${telarId}_${salon}`;
    if (window.cargandoRequerimientosPorTelar[key]) {
        return;
    }

    window.cargandoRequerimientosPorTelar[key] = true;

    // El servidor filtra por telar y salon (InventarioTelaresController::getInventarioTelares)
    const filtrosTelar: FiltrosInventario = { no_telar: String(telarId) }
    if (salon && salon !== '') filtrosTelar.salon = String(salon).trim()

    obtenerInventarioConCache(filtrosTelar)
        .then(registros => {

            // Buscar todas las tablas que contienen checkboxes de este telar
            // IMPORTANTE: Las tablas deben estar en orden (primera = hoy)
            const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
            const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
                const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"]`) !== null;
                return tieneCheckboxDelTelar;
            });

            if (todasLasTablasDelTelar.length === 0) {
                window.cargandoRequerimientosPorTelar[key] = false;
                // Intentar buscar de nuevo después de un delay
                setTimeout(() => {
                    loadRequerimientos(telarId, salon);
                }, 500);
                return;
            }

            // Obtener todos los headers de fecha (th) de estas tablas en orden
            const fechasTablas = [];
            todasLasTablasDelTelar.forEach((table, index) => {
                const th = table.querySelector('th');
                if (th) {
                    fechasTablas.push(th);
                }
            });

            // La primera tabla es la del primer día (hoy) - debe ser la primera en el orden del DOM
            const primeraTabla = todasLasTablasDelTelar[0];

            if (!primeraTabla) {
                window.cargandoRequerimientosPorTelar[key] = false;
                return;
            }

            // Guardar el estado actual de los checkboxes con cambios recientes antes de limpiar
            // Esto nos permite preservar los cambios recientes del usuario
            const estadosCheckboxesRecientes = new Map();
            const checkboxesEliminados = new Set();

            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                    // Si el checkbox fue eliminado, preservarlo como desmarcado permanentemente
                    if (checkbox.getAttribute('data-eliminado') === 'true') {
                        checkboxesEliminados.add(checkbox.id);
                        estadosCheckboxesRecientes.set(checkbox.id, {
                            checked: false, // Siempre desmarcado si fue eliminado
                            timestamp: Date.now(),
                            eliminado: true
                        });
                        return; // No procesar más este checkbox
                    }

                    // Guardar el estado actual con un timestamp para saber si es un cambio reciente
                    const cambioReciente = checkbox.getAttribute('data-cambio-reciente');
                    if (cambioReciente) {
                        const timestampCambio = parseInt(cambioReciente);
                        const ahora = Date.now();
                        // Si el cambio fue hace menos de 10 segundos, preservarlo (aumentado de 3 a 10)
                        if (ahora - timestampCambio < 10000) {
                            estadosCheckboxesRecientes.set(checkbox.id, {
                                checked: checkbox.checked,
                                timestamp: timestampCambio
                            });
                        } else {
                            // Si el cambio es muy antiguo, remover el atributo (excepto si fue eliminado)
                            if (checkbox.getAttribute('data-eliminado') !== 'true') {
                                checkbox.removeAttribute('data-cambio-reciente');
                            }
                        }
                    }
                });
            });

            // Limpiar todos los checkboxes de este telar en todas las tablas
            // EXCEPTO los que tienen cambios recientes del usuario o fueron eliminados
            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"]`).forEach(checkbox => {
                    // Si este checkbox fue eliminado, mantenerlo desmarcado
                    if (checkboxesEliminados.has(checkbox.id)) {
                        checkbox.checked = false;
                        return;
                    }

                    // Si este checkbox tenía un cambio reciente, preservar su estado
                    if (estadosCheckboxesRecientes.has(checkbox.id)) {
                        const estado = estadosCheckboxesRecientes.get(checkbox.id);
                        checkbox.checked = estado.checked; // Preservar el estado (marcado o desmarcado)
                    } else {
                        // Si no tiene cambio reciente, limpiarlo normalmente
                        checkbox.checked = false;
                    }
                });
            });

            // Por defecto primera columna = hoy; si hay registros con fecha anterior, primerDia = min(esa fecha, hoy)
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            let primerDia = new Date(hoy);
            primerDia.setHours(0, 0, 0, 0);
            const ultimoDia = new Date(hoy);
            ultimoDia.setDate(hoy.getDate() + 6);
            ultimoDia.setHours(23, 59, 59, 999);

            // Función para convertir fecha ISO (YYYY-MM-DD o YYYY-MM-DDTHH:mm:ss.fffZ) a objeto Date
            function parseFechaISO(fechaISO: Crudo): Date | null {
                if (!fechaISO) return null;

                // Extraer solo la parte de la fecha (antes de la T si existe)
                let fechaStr = fechaISO;
                if (fechaISO.includes('T')) {
                    fechaStr = fechaISO.split('T')[0];
                }

                // También manejar si viene con espacio en lugar de T
                if (fechaStr.includes(' ')) {
                    fechaStr = fechaStr.split(' ')[0];
                }

                const partes = fechaStr.split('-');
                if (partes.length !== 3) return null;

                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1; // Los meses en JS van de 0-11
                const dia = parseInt(partes[2]);

                if (isNaN(año) || isNaN(mes) || isNaN(dia)) return null;

                try {
                    // Crear fecha en hora local (no UTC) para evitar problemas de zona horaria
                    const fecha = new Date(año, mes, dia);
                    fecha.setHours(0, 0, 0, 0);

                    // Validar que la fecha es válida
                    if (fecha.getFullYear() === año && fecha.getMonth() === mes && fecha.getDate() === dia) {
                        return fecha;
                    }
                } catch (e) {
                    console.error('Error al parsear fecha:', fechaISO, e);
                }
                return null;
            }

            // Función para obtener la primera tabla del calendario (hoy)
            function obtenerPrimeraTabla() {
                return primeraTabla; // Primera tabla = hoy (primer día del calendario)
            }

            // Marcar por coincidencia de telar+tipo+fecha+turno Y SALON
            // Filtrar registros del telar
            const registrosTelar = registros.filter(reg => {
                // Solo registros con status Activo
                if (reg.status && reg.status !== 'Activo') return false;

                const telarCoincide = String(reg.no_telar) === String(telarId);

                if (!telarCoincide) {
                    return false;
                }

                const salonRegistro = String(reg.salon || '').toLowerCase().trim();
                const salonEsperado = String(salon || '').toLowerCase().trim();

                // Comparación flexible de salón:
                // 1. Si el salón esperado está vacío o es null/undefined, aceptar TODOS los registros del telar
                // 2. Si no está vacío, hacer comparación case-insensitive y flexible
                let salonCoincide = true;
                if (salonEsperado && salonEsperado !== '') {
                    // Normalizar ambos salones para comparación (remover espacios extra, convertir a minúsculas)
                    const salonRegistroNormalizado = salonRegistro.replace(/\s+/g, ' ').trim();
                    const salonEsperadoNormalizado = salonEsperado.replace(/\s+/g, ' ').trim();

                    salonCoincide = salonRegistroNormalizado === salonEsperadoNormalizado ||
                                   salonRegistroNormalizado.includes(salonEsperadoNormalizado) ||
                                   salonEsperadoNormalizado.includes(salonRegistroNormalizado);
                }

                const coincide = telarCoincide && salonCoincide;

                return coincide;
            });

            function formatearFechaHeader(fechaObj: Date) {
                const dia = fechaObj.getDate();
                const mes = fechaObj.getMonth() + 1;
                const año = fechaObj.getFullYear();
                const fechaFormateada = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                const diaSemana = diasSemana[fechaObj.getDay()];
                return { fechaFormateada, diaSemana, año, mes, dia };
            }

            // Construir lista de fechas: [fechas pasadas CON registros] + [hoy, hoy+1, ...] = siempre 7 columnas
            const limiteAtras = new Date(hoy);
            limiteAtras.setDate(hoy.getDate() - 30); // 30 días de lookback para capturar registros antiguos
            limiteAtras.setHours(0, 0, 0, 0);

            const fechasPasadasSet = new Set<string>();
            registrosTelar.forEach(reg => {
                const f = parseFechaISO(reg.fecha);
                if (!f) return;
                if (f.getTime() < hoy.getTime() && f.getTime() >= limiteAtras.getTime()) {
                    fechasPasadasSet.add(`${f.getFullYear()}-${String(f.getMonth()+1).padStart(2,'0')}-${String(f.getDate()).padStart(2,'0')}`);
                }
            });

            const fechasPasadasOrdenadas = Array.from(fechasPasadasSet).sort().map(s => {
                const [y, m, d] = s.split('-').map(Number);
                const dt = new Date(y!, m! - 1, d); dt.setHours(0,0,0,0); return dt;
            });

            const numColumnas = todasLasTablasDelTelar.length;
            // Limitar fechas pasadas: máximo numColumnas - 2 para dejar espacio para hoy + al menos 1 futuro
            const maxPasadas = Math.max(0, numColumnas - 2);
            const fechasPasadasLimitadas = fechasPasadasOrdenadas.slice(-maxPasadas); // tomar las más recientes
            const fechasCalendario = [...fechasPasadasLimitadas];
            let _sig = new Date(hoy);
            while (fechasCalendario.length < numColumnas) {
                fechasCalendario.push(new Date(_sig));
                _sig.setDate(_sig.getDate() + 1);
            }

            // Map rápido fecha-ISO → índice de columna
            const fechaToCol = new Map();
            fechasCalendario.forEach((fc, idx) => {
                const key = `${fc.getFullYear()}-${String(fc.getMonth()+1).padStart(2,'0')}-${String(fc.getDate()).padStart(2,'0')}`;
                fechaToCol.set(key, idx);
            });

            // Actualizar headers de cada columna
            todasLasTablasDelTelar.forEach((tabla, index) => {
                tabla.style.display = '';
                const thHeader = tabla.querySelector('th');
                if (thHeader && index < fechasCalendario.length) {
                    const fechaColumna = fechasCalendario[index]!;
                    const fechaFormateada = formatearFechaHeader(fechaColumna);
                    const fechaCompletaEsperada = `${fechaFormateada.año}-${String(fechaFormateada.mes).padStart(2, '0')}-${String(fechaFormateada.dia).padStart(2, '0')}`;
                    thHeader.setAttribute('data-fecha-completa', fechaCompletaEsperada);
                    thHeader.innerHTML = `
                        <div class="text-xs leading-tight">${fechaFormateada.fechaFormateada}</div>
                        <div class="text-xs opacity-75 leading-tight">${fechaFormateada.diaSemana}</div>
                    `;
                    thHeader.classList.remove('fecha-modificada', 'calendario-fecha-anterior');
                    thHeader.style.backgroundColor = '';
                    thHeader.style.borderLeft = '';
                    if (fechaColumna.getTime() < hoy.getTime()) {
                        thHeader.classList.add('calendario-fecha-anterior');
                    }
                }
            });

            // PASO 3: Procesar registros y marcarlos
            registrosTelar.forEach(reg => {
                const tipo = (reg.tipo || '').toLowerCase();
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                let tablaDestino = null;

                if (fechaRegistro) {
                    const fechaNorm = `${fechaRegistro.getFullYear()}-${String(fechaRegistro.getMonth() + 1).padStart(2, '0')}-${String(fechaRegistro.getDate()).padStart(2, '0')}`;
                    const colIdx = fechaToCol.get(fechaNorm);
                    if (colIdx !== undefined) {
                        tablaDestino = todasLasTablasDelTelar[colIdx];
                    } else {
                        // Registro fuera del rango del calendario: NO amontonar en otra columna, omitir
                        return;
                    }
                } else {
                    // Fecha no válida: omitir registro
                    return;
                }

                // Marcar checkbox
                const turnoEsperado = String(reg.turno);
                const checkboxes = tablaDestino!.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                if (checkboxes.length === 0) {
                    return;
                }

                checkboxes.forEach(cb => {
                    if (cb.dataset.turno === turnoEsperado) {
                        // NO marcar si el checkbox fue eliminado
                        if (cb.getAttribute('data-eliminado') === 'true') {
                            cb.checked = false;
                            return;
                        }

                        // Solo marcar si no tiene un cambio reciente del usuario
                        // Si tiene un cambio reciente, preservar el estado del usuario
                        const cambioReciente = cb.getAttribute('data-cambio-reciente');
                        if (!cambioReciente) {
                            cb.checked = true;
                            // Guardar siempre la fecha real del registro (YYYY-MM-DD) para enviar al backend al desmarcar
                            const fechaNormCb = fechaRegistro ? `${fechaRegistro.getFullYear()}-${String(fechaRegistro.getMonth() + 1).padStart(2, '0')}-${String(fechaRegistro.getDate()).padStart(2, '0')}` : (fechaISO && fechaISO.split('T')[0]) || '';
                            if (fechaNormCb) cb.setAttribute('data-fecha-original', fechaNormCb);
                        } else {
                            // Si tiene cambio reciente, verificar si el timestamp aún es válido
                            const timestampCambio = parseInt(cambioReciente);
                            const ahora = Date.now();
                            if (ahora - timestampCambio > 10000) {
                                // El cambio ya no es reciente, marcar normalmente (solo si no fue eliminado)
                                if (cb.getAttribute('data-eliminado') !== 'true') {
                                    cb.checked = true;
                                    cb.removeAttribute('data-cambio-reciente');
                                }
                            }
                            // Si el cambio es reciente, mantener el estado actual del checkbox
                        }
                    }
                });
            });

            window.cargandoRequerimientosPorTelar[key] = false;
        })
        .catch(error => {
            window.cargandoRequerimientosPorTelar[key] = false;
        });
}

// Función para cargar requerimientos filtrando por fibra específica
function loadRequerimientosConFiltro(telarId: TelarId, salon: string, tipo: string, fibraFiltro: string): void {
    // Evitar múltiples llamadas simultáneas para el mismo telar
    const key = `${telarId}_${salon}_${tipo}_${fibraFiltro}`;
    if (window.cargandoRequerimientosPorTelar[key]) {
        return;
    }

    window.cargandoRequerimientosPorTelar[key] = true;

    // Preparar filtros para el GET
    const filtros: FiltrosInventario = {
        no_telar: String(telarId),
        tipo: tipoServidor(tipo)
    };

    // Agregar filtro por hilo si se proporciona
    if (fibraFiltro && fibraFiltro !== '' && fibraFiltro !== '-') {
        filtros.hilo = String(fibraFiltro).trim();
    }

    // Agregar filtro por salón si se proporciona
    if (salon && salon !== '') {
        filtros.salon = String(salon).trim();
    }

    // Usar inventario con caché y filtros (más rápido y filtrado en el servidor)
    obtenerInventarioConCache(filtros)
        .then(registros => {
            // El servidor ya filtro por telar, salon, tipo e hilo: aqui no se vuelve a filtrar.
            const registrosFiltrados = registros

            // Buscar todas las tablas que contienen checkboxes de este telar
            const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
            const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
                const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`) !== null;
                return tieneCheckboxDelTelar;
            });

            if (todasLasTablasDelTelar.length === 0) {
                window.cargandoRequerimientosPorTelar[key] = false;
                setTimeout(() => {
                    loadRequerimientosConFiltro(telarId, salon, tipo, fibraFiltro);
                }, 500);
                return;
            }

            // Limpiar TODOS los checkboxes de este telar y tipo primero
            // EXCEPTO los que fueron eliminados (mantenerlos desmarcados)
            todasLasTablasDelTelar.forEach(table => {
                table.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`).forEach(checkbox => {
                    // Si fue eliminado, mantenerlo desmarcado pero no remover el atributo
                    if (checkbox.getAttribute('data-eliminado') === 'true') {
                        checkbox.checked = false;
                    } else {
                        checkbox.checked = false;
                    }
                });
            });

            // Primera columna = hoy; si hay registros con fecha anterior, incluir esas fechas y colorearlas
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            let primerDia = new Date(hoy);
            primerDia.setHours(0, 0, 0, 0);
            const ultimoDia = new Date(hoy);
            ultimoDia.setDate(hoy.getDate() + 6);
            ultimoDia.setHours(23, 59, 59, 999);

            // Función para convertir fecha ISO (YYYY-MM-DD o YYYY-MM-DDTHH:mm:ss.fffZ) a objeto Date
            function parseFechaISO(fechaISO: Crudo): Date | null {
                if (!fechaISO) return null;
                let fechaStr = fechaISO;
                if (fechaISO.includes('T')) fechaStr = fechaISO.split('T')[0];
                if (fechaStr.includes(' ')) fechaStr = fechaStr.split(' ')[0];
                const partes = fechaStr.split('-');
                if (partes.length !== 3) return null;
                const año = parseInt(partes[0]);
                const mes = parseInt(partes[1]) - 1;
                const dia = parseInt(partes[2]);
                if (isNaN(año) || isNaN(mes) || isNaN(dia)) return null;
                try {
                    const fecha = new Date(año, mes, dia);
                    fecha.setHours(0, 0, 0, 0);
                    if (fecha.getFullYear() === año && fecha.getMonth() === mes && fecha.getDate() === dia) return fecha;
                } catch (e) { console.error('Error al parsear fecha:', fechaISO, e); }
                return null;
            }

            function formatearFechaHeader(fechaObj: Date) {
                const dia = fechaObj.getDate();
                const mes = fechaObj.getMonth() + 1;
                const año = fechaObj.getFullYear();
                const fechaFormateada = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;
                const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                const diaSemana = diasSemana[fechaObj.getDay()];
                return { fechaFormateada, diaSemana, año, mes, dia };
            }

            // Construir lista de fechas: [fechas pasadas CON registros] + [hoy, hoy+1, ...] = siempre 7 columnas
            const limiteAtras = new Date(hoy);
            limiteAtras.setDate(hoy.getDate() - 30); // 30 días de lookback para capturar registros antiguos
            limiteAtras.setHours(0, 0, 0, 0);

            const fechasPasadasSet = new Set<string>();
            registrosFiltrados.forEach(reg => {
                const f = parseFechaISO(reg.fecha);
                if (!f) return;
                if (f.getTime() < hoy.getTime() && f.getTime() >= limiteAtras.getTime()) {
                    fechasPasadasSet.add(`${f.getFullYear()}-${String(f.getMonth()+1).padStart(2,'0')}-${String(f.getDate()).padStart(2,'0')}`);
                }
            });

            const fechasPasadasOrdenadas = Array.from(fechasPasadasSet).sort().map(s => {
                const [y, m, d] = s.split('-').map(Number);
                const dt = new Date(y!, m! - 1, d); dt.setHours(0,0,0,0); return dt;
            });

            const numColumnas = todasLasTablasDelTelar.length;
            // Limitar fechas pasadas: máximo numColumnas - 2 para dejar espacio para hoy + al menos 1 futuro
            const maxPasadas = Math.max(0, numColumnas - 2);
            const fechasPasadasLimitadas = fechasPasadasOrdenadas.slice(-maxPasadas); // tomar las más recientes
            const fechasCalendario = [...fechasPasadasLimitadas];
            let _sig = new Date(hoy);
            while (fechasCalendario.length < numColumnas) {
                fechasCalendario.push(new Date(_sig));
                _sig.setDate(_sig.getDate() + 1);
            }

            // Map rápido fecha-ISO → índice de columna
            const fechaToCol = new Map();
            fechasCalendario.forEach((fc, idx) => {
                const key = `${fc.getFullYear()}-${String(fc.getMonth()+1).padStart(2,'0')}-${String(fc.getDate()).padStart(2,'0')}`;
                fechaToCol.set(key, idx);
            });

            // Actualizar headers de cada columna
            todasLasTablasDelTelar.forEach((tabla, index) => {
                tabla.style.display = '';
                const thHeader = tabla.querySelector('th');
                if (thHeader && index < fechasCalendario.length) {
                    const fechaColumna = fechasCalendario[index]!;
                    const fechaFormateada = formatearFechaHeader(fechaColumna);
                    const fechaCompletaEsperada = `${fechaFormateada.año}-${String(fechaFormateada.mes).padStart(2, '0')}-${String(fechaFormateada.dia).padStart(2, '0')}`;
                    thHeader.setAttribute('data-fecha-completa', fechaCompletaEsperada);
                    thHeader.innerHTML = `
                        <div class="text-xs leading-tight">${fechaFormateada.fechaFormateada}</div>
                        <div class="text-xs opacity-75 leading-tight">${fechaFormateada.diaSemana}</div>
                    `;
                    thHeader.classList.remove('fecha-modificada', 'calendario-fecha-anterior');
                    thHeader.style.backgroundColor = '';
                    thHeader.style.borderLeft = '';
                    if (fechaColumna.getTime() < hoy.getTime()) thHeader.classList.add('calendario-fecha-anterior');
                }
            });

            // Marcar checkboxes según fecha del registro
            registrosFiltrados.forEach(reg => {
                const fechaISO = reg.fecha;
                const fechaRegistro = parseFechaISO(fechaISO);

                if (!fechaRegistro) return;

                const fechaNorm = `${fechaRegistro.getFullYear()}-${String(fechaRegistro.getMonth() + 1).padStart(2, '0')}-${String(fechaRegistro.getDate()).padStart(2, '0')}`;
                const colIdx = fechaToCol.get(fechaNorm);
                let tablaDestino = null;

                if (colIdx !== undefined) {
                    tablaDestino = todasLasTablasDelTelar[colIdx];
                } else {
                    // Registro fuera del rango del calendario: NO amontonar en otra columna, omitir
                    return;
                }

                // Fecha del registro en YYYY-MM-DD para data-fecha-original (al desmarcar se envía esta fecha al backend)
                const y = fechaRegistro.getFullYear();
                const m = String(fechaRegistro.getMonth() + 1).padStart(2, '0');
                const d = String(fechaRegistro.getDate()).padStart(2, '0');
                const fechaRegistroStr = `${y}-${m}-${d}`;

                // Marcar checkbox
                const turnoEsperado = String(reg.turno);
                const checkboxes = tablaDestino!.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarId}"][data-tipo="${tipo}"]`);

                checkboxes.forEach(cb => {
                    if (cb.dataset.turno === turnoEsperado) {
                        // NO marcar si el checkbox fue eliminado
                        if (cb.getAttribute('data-eliminado') === 'true') {
                            cb.checked = false;
                            return;
                        }
                        cb.checked = true;
                        // Guardar la fecha real del registro para que al desmarcar se envíe la fecha correcta al backend (evita "Registro no encontrado" cuando la columna muestra otro día)
                        cb.setAttribute('data-fecha-original', fechaRegistroStr);
                    }
                });
            });


            window.cargandoRequerimientosPorTelar[key] = false;
        })
        .catch(error => {
            console.error('Error al cargar requerimientos con filtro:', error);
            window.cargandoRequerimientosPorTelar[key] = false;
        });
}

// Variables globales para el modal
if (typeof window.modalData === 'undefined') {
    window.modalData = {
        telarId: null,
        tipo: null,
        datosProceso: null,
        datosSiguiente: null,
        seleccionGuardada: {} // Guardar selecciones por telar y tipo
    };
}

// Función para abrir el modal de selección
function abrirModalSeleccion(telarId: TelarId, tipo: string, cuenta?: Crudo, calibre?: Crudo, fibra?: Crudo) {
    window.modalData.telarId = telarId;
    window.modalData.tipo = tipo;

    // Guardar el salón del telar para usarlo después
    // Buscar el salón desde el contexto del componente
    const salonTelar = document.querySelector(`input[data-telar="${telarId}"]`)?.closest<HTMLElement>('.telar-section')?.dataset?.salon ||
                       (esBarra(tipo) ? 'Karl Mayer' : (tipo === 'rizo' ? 'Jacquard' : 'Itema')); // Fallback
    window.modalData.salonTelar = salonTelar;

    // Actualizar título del modal
    document.getElementById('modalTelarNumero')!.textContent = String(telarId);

    // Verificar si hay una selección guardada previa de "Siguiente Orden" para este telar y tipo
    const seleccionPrevia = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];
    const usarFibraPrevia = seleccionPrevia && seleccionPrevia.seleccion === 'siguiente' && seleccionPrevia.datos?.fibra;

    // Obtener datos del proceso actual y siguiente orden
    // Si hay una selección previa de "Siguiente Orden", usar esa fibra para el GET
    const promesas = [
        obtenerDatosProcesoActual(telarId),
        obtenerDatosSiguienteOrden(telarId, usarFibraPrevia ? seleccionPrevia!.datos.fibra : null)
    ];

    Promise.all(promesas).then(([datosProceso, datosSiguiente]) => {
        // Configurar datos del proceso actual según el tipo (RIZO o PIE)
        window.modalData.datosProceso = datosParaModal(datosProceso, tipo);

        // Configurar datos de la siguiente orden según el tipo (RIZO o PIE)
        window.modalData.datosSiguiente = datosParaModal(datosSiguiente, tipo);

        // Actualizar tabla del modal
        document.getElementById('cuentaProceso')!.textContent = window.modalData.datosProceso!.cuenta;
        document.getElementById('calibreProceso')!.textContent = window.modalData.datosProceso!.calibre;
        document.getElementById('fibraProceso')!.textContent = window.modalData.datosProceso!.fibra;

        document.getElementById('cuentaSiguiente')!.textContent = window.modalData.datosSiguiente!.cuenta;
        document.getElementById('calibreSiguiente')!.textContent = window.modalData.datosSiguiente!.calibre;
        document.getElementById('fibraSiguiente')!.textContent = window.modalData.datosSiguiente!.fibra;

        // Mostrar modal
        const modal = document.getElementById('modalSeleccion')!;
        modal.classList.remove('hidden');
        modal.classList.add('flex', 'items-center', 'justify-center');

        // Agregar event listeners a los radio buttons para actualizar datos cuando cambien
        const radioProceso = document.getElementById('radioProceso') as HTMLInputElement;
        const radioSiguiente = document.getElementById('radioSiguiente') as HTMLInputElement;

        // Remover listeners anteriores si existen (clonar y reemplazar para limpiar listeners)
        const nuevoRadioProceso = radioProceso.cloneNode(true) as HTMLInputElement;
        radioProceso.parentNode!.replaceChild(nuevoRadioProceso, radioProceso);
        const nuevoRadioSiguiente = radioSiguiente.cloneNode(true) as HTMLInputElement;
        radioSiguiente.parentNode!.replaceChild(nuevoRadioSiguiente, radioSiguiente);

        // Limpiar selección anterior
        nuevoRadioProceso.checked = false;
        nuevoRadioSiguiente.checked = false;

        // Verificar si hay una selección guardada previa para este telar y tipo
        const seleccionPrevia = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];

        // Agregar listener para "Producción en Proceso"
        nuevoRadioProceso.addEventListener('change', function(this: HTMLInputElement) {
            if (this.checked) {
                // Hacer GET del proceso actual
                obtenerDatosProcesoActual(telarId).then(datosProceso => {
                    if (datosProceso) {
                        window.modalData.datosProceso = datosParaModal(datosProceso, tipo);

                        // Actualizar tabla del modal
                        document.getElementById('cuentaProceso')!.textContent = window.modalData.datosProceso!.cuenta;
                        document.getElementById('calibreProceso')!.textContent = window.modalData.datosProceso!.calibre;
                        document.getElementById('fibraProceso')!.textContent = window.modalData.datosProceso!.fibra;

                        // Actualizar checkboxes por la fibra del proceso actual (opcional: preview en tiempo real)
                        // Esto se puede hacer aquí o solo cuando se confirme la selección
                    }
                });
            }
        });

        // Agregar listener para "Siguiente Orden"
        nuevoRadioSiguiente.addEventListener('change', function(this: HTMLInputElement) {
            if (this.checked) {
                // Verificar si hay una selección guardada previa para obtener la fibra
                const seleccionPreviaActual = window.modalData?.seleccionGuardada?.[telarId]?.[tipo];
                const fibraPrevia = seleccionPreviaActual && seleccionPreviaActual.seleccion === 'siguiente' && seleccionPreviaActual.datos?.fibra ? seleccionPreviaActual.datos.fibra : null;

                // Hacer GET de la siguiente orden (con fibra si existe selección previa)
                obtenerDatosSiguienteOrden(telarId, fibraPrevia).then(datosSiguiente => {
                    if (datosSiguiente) {
                        window.modalData.datosSiguiente = datosParaModal(datosSiguiente, tipo);

                        // Actualizar tabla del modal
                        document.getElementById('cuentaSiguiente')!.textContent = window.modalData.datosSiguiente!.cuenta;
                        document.getElementById('calibreSiguiente')!.textContent = window.modalData.datosSiguiente!.calibre;
                        document.getElementById('fibraSiguiente')!.textContent = window.modalData.datosSiguiente!.fibra;
                    }
                });
            }
        });

        // Si hay selección previa, seleccionar esa opción; si no, seleccionar la primera fila "Siguiente Orden" por defecto
        if (seleccionPrevia) {
            if (seleccionPrevia.seleccion === 'siguiente') {
                nuevoRadioSiguiente.checked = true;
                // Disparar el evento change para cargar los datos de la siguiente orden
                nuevoRadioSiguiente.dispatchEvent(new Event('change'));
            } else if (seleccionPrevia.seleccion === 'proceso') {
                nuevoRadioProceso.checked = true;
                // Disparar el evento change para cargar los datos del proceso actual
                nuevoRadioProceso.dispatchEvent(new Event('change'));
            } else {
                // Seleccionar por defecto la primera fila "Siguiente Orden"
                nuevoRadioSiguiente.checked = true;
                nuevoRadioSiguiente.dispatchEvent(new Event('change'));
            }
        } else {
            // Seleccionar por defecto la primera fila "Siguiente Orden"
            nuevoRadioSiguiente.checked = true;
            nuevoRadioSiguiente.dispatchEvent(new Event('change'));
        }
    });
}

// Función para cerrar el modal
function cerrarModalSeleccion() {
    const modal = document.getElementById('modalSeleccion')!;
    modal.classList.add('hidden');
    modal.classList.remove('flex', 'items-center', 'justify-center');
    window.modalData = {
        telarId: null,
        tipo: null,
        datosProceso: null,
        datosSiguiente: null
    };
}

// Función para confirmar la selección
function confirmarSeleccion() {
    // Asegurar que window.modalData esté inicializado
    if (typeof window.modalData === 'undefined') {
        window.modalData = {
            telarId: null,
            tipo: null,
            datosProceso: null,
            datosSiguiente: null,
            seleccionGuardada: {}
        };
    }

    // Asegurar que seleccionGuardada esté inicializado
    if (!window.modalData.seleccionGuardada) {
        window.modalData.seleccionGuardada = {};
    }

    const seleccionado = document.querySelector<HTMLInputElement>('input[name="seleccion"]:checked');

    if (!seleccionado) {
        alert('Por favor seleccione una opción.');
        return;
    }

    // Validar que tenemos los datos necesarios
    if (!window.modalData.telarId || !window.modalData.tipo) {
        alert('Error: No se encontraron los datos del telar. Por favor, cierre y vuelva a abrir el modal.');
        return;
    }

    let datosSeleccionados;

    if (seleccionado.value === 'proceso') {
        datosSeleccionados = window.modalData.datosProceso;
    } else {
        datosSeleccionados = window.modalData.datosSiguiente;
    }

    // Validar que tenemos datos seleccionados
    if (!datosSeleccionados) {
        alert('Error: No se encontraron los datos seleccionados.');
        return;
    }

    // Actualizar visualmente la cuenta seleccionada
    const elemento = document.getElementById(`cuenta-${window.modalData.tipo}-${window.modalData.telarId}`);
    if (elemento) {
        elemento.textContent = datosSeleccionados.cuenta || '-';
    }

    // Guardar la selección Y los datos completos para este telar y tipo
    const telarId = String(window.modalData.telarId);
    const tipo = String(window.modalData.tipo);

    if (!window.modalData.seleccionGuardada[telarId]) {
        window.modalData.seleccionGuardada[telarId] = {};
    }

    window.modalData.seleccionGuardada[telarId][tipo] = {
        seleccion: seleccionado.value,
        datos: datosSeleccionados,
        ordenProd: seleccionado.value === 'proceso'
            ? (window.modalData.datosProceso?.ordenProd || '')
            : (window.modalData.datosSiguiente?.ordenProd || '')
    };

    // Cuando se selecciona una nueva fibra (proceso o siguiente), actualizar el hilo en el inventario y filtrar checkboxes
    const fibraSeleccionada = datosSeleccionados?.fibra || '';
    const salonTelar = window.modalData?.salonTelar || '';

    // Guardar valores antes del setTimeout para evitar que se pierdan
    const telarIdParaFiltro = window.modalData.telarId;
    const tipoParaFiltro = window.modalData.tipo;
    const esProceso = seleccionado.value === 'proceso';

    // Debug: mostrar la fibra seleccionada

    // Limpiar TODOS los checkboxes de este telar y tipo antes de aplicar el nuevo filtro
    const todasLasTablasDelDocumento = Array.from(document.querySelectorAll('table'));
    const todasLasTablasDelTelar = todasLasTablasDelDocumento.filter(table => {
        const tieneCheckboxDelTelar = table.querySelector(`input[data-telar="${telarIdParaFiltro}"][data-tipo="${tipoParaFiltro}"]`) !== null;
        return tieneCheckboxDelTelar;
    });

    todasLasTablasDelTelar.forEach(table => {
        table.querySelectorAll<HTMLInputElement>(`input[data-telar="${telarIdParaFiltro}"][data-tipo="${tipoParaFiltro}"]`).forEach(checkbox => {
            checkbox.checked = false;
            // Limpiar también el atributo de cambio reciente
            checkbox.removeAttribute('data-cambio-reciente');
        });
    });


    // Actualizar el hilo en el inventario cuando se confirma una selección
    if (telarIdParaFiltro && tipoParaFiltro && fibraSeleccionada && fibraSeleccionada !== '-') {
        // Actualizar el hilo en todos los registros activos del inventario para este telar y tipo
        const hiloParaActualizar = String(fibraSeleccionada).trim();

        // Llamar al endpoint para actualizar el hilo
        http.post('/programa-urd-eng/actualizar-telar', {
            no_telar: telarIdParaFiltro,
            tipo: tipoParaFiltro === 'rizo' ? 'Rizo' : 'Pie',
            hilo: hiloParaActualizar
        })
        .then(data => {

            // Invalidar TODOS los cachés para forzar una nueva consulta
            invalidarCacheInventario();

            // Esperar un poco más para asegurar que la base de datos se actualizó completamente
            // Recargar requerimientos después de actualizar el hilo
            setTimeout(() => {
                // Normalizar la fibra: remover espacios, convertir a minúsculas, y verificar que no sea '-' o vacía
                const fibraNormalizada = fibraSeleccionada ? String(fibraSeleccionada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';


                if (fibraValida) {
                    // Filtrar por la fibra específica seleccionada
                    // Invalidar caché nuevamente antes de cargar para asegurar que se use el hilo actualizado
                    invalidarCacheInventario();

                    // Esperar un poco más para asegurar que TODOS los registros se actualizaron en la BD
                    setTimeout(() => {
                        loadRequerimientosConFiltro(telarIdParaFiltro, salonTelar, tipoParaFiltro, fibraNormalizada);
                    }, 300); // Delay adicional para asegurar que el UPDATE completo terminó
                } else {
                    // Si no hay fibra válida, cargar todos los requerimientos sin filtro
                    loadRequerimientos(telarIdParaFiltro, salonTelar);
                }
            }, 500); // Aumentar el delay a 500ms para dar tiempo a que la BD se actualice
        })
        .catch(error => {
            console.error('Error al actualizar hilo en inventario:', error);
            // Aún así, intentar filtrar los checkboxes
            invalidarCacheInventario();
            setTimeout(() => {
                const fibraNormalizada = fibraSeleccionada ? String(fibraSeleccionada).trim().toLowerCase() : '';
                const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';
                if (fibraValida) {
                    loadRequerimientosConFiltro(telarIdParaFiltro, salonTelar, tipoParaFiltro, fibraNormalizada);
                } else {
                    loadRequerimientos(telarIdParaFiltro, salonTelar);
                }
            }, 200);
        });
    } else {
        // Si no hay fibra válida, solo recargar requerimientos sin actualizar hilo
        invalidarCacheInventario();
        setTimeout(() => {
            loadRequerimientos(telarIdParaFiltro, salonTelar);
        }, 100);
    }

    // Cerrar modal
    cerrarModalSeleccion();

    // Mostrar notificación de éxito (muy rápida)
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Actualizado',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: false,
            position: 'top-end',
            toast: true
        });
    }
}

// Función para obtener datos del proceso actual
async function obtenerDatosProcesoActual(telarId: TelarId): Promise<Registro | null> {
    try {
        return await http.get(`/api/telares/proceso-actual/${telarId}`)
    } catch (error) {
        return null;
    }
}

// Función para obtener datos de la siguiente orden
async function obtenerDatosSiguienteOrden(telarId: TelarId, fibra: Crudo = null): Promise<Registro | null> {
    try {
        let url = `/api/telares/siguiente-orden/${telarId}`;
        // Si se proporciona una fibra, agregarla como parámetro de consulta
        if (fibra) {
            url += `?fibra=${encodeURIComponent(fibra)}`;
        }

        return await http.get(url)
    } catch (error) {
        return null;
    }
}

// Función para obtener el inventario completo de telares (usa caché)
async function obtenerInventarioTelares() {
    return await obtenerInventarioConCache();
}

// Variable global para almacenar datos de eliminación pendiente
// Variables globales para el modal de eliminación (declaradas solo una vez)
if (typeof window.datosEliminacionPendiente === 'undefined') {
    window.datosEliminacionPendiente = null;
}
if (typeof window.checkboxEliminacionPendiente === 'undefined') {
    window.checkboxEliminacionPendiente = null;
}

// Variables para el modal de calendario semanal
if (typeof window.checkboxPendienteCalendario === 'undefined') {
    window.checkboxPendienteCalendario = null;
    window.telarIdPendienteCalendario = null;
    window.tipoPendienteCalendario = null;
    window.turnoPendienteCalendario = null;
    window.fechaOriginalPendienteCalendario = null;
    window.registroIdPendienteCalendario = null; // ID del registro que se está actualizando
    window.fechaSeleccionadaCalendario = null; // Fecha seleccionada en el calendario
    window.telarDataPendiente = null;
}

// Función para verificar estado del telar antes de eliminar
async function verificarEstadoTelarAntesDeEliminar(telarId: TelarId, tipo: string, datosEliminar: DatosEliminar, checkbox: HTMLInputElement, telarData: Registro | null = null) {
    try {
        // Validar que tenemos los datos necesarios para la verificación
        if (!datosEliminar || !datosEliminar.fecha || datosEliminar.turno === undefined) {
            console.error('Error: Faltan datos para verificar estado', datosEliminar);
            // Si faltan datos críticos, mostrar modal por seguridad
            window.datosEliminacionPendiente = datosEliminar;
            window.checkboxEliminacionPendiente = checkbox;
            // Por defecto, asumir reservado por seguridad
            window.estadoModalTela = 'reservado';
            window.statusUrdido = null;
            window.puedeEliminar = true; // Por defecto se puede eliminar si está reservado
            if (telarData) {
                window.telarDataCompleto = telarData;
            }
            const telarSection = checkbox.closest<HTMLElement>('.telar-section') || checkbox.closest<HTMLElement>('[data-telar]');
            const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
            window.telarDataPendiente = {
                salon: salon,
                telarId: telarId
            };
            window.mostrarModalTelaReservada();
            return;
        }

        // Incluir fecha y turno en la verificación para verificar el registro específico
        // Asegurar que la fecha esté en formato YYYY-MM-DD
        let fechaParaVerificar = String(datosEliminar.fecha);
        // Si la fecha viene en formato diferente, intentar convertirla
        if (fechaParaVerificar && !fechaParaVerificar.match(/^\d{4}-\d{2}-\d{2}$/)) {
            try {
                const fechaParsed = new Date(fechaParaVerificar);
                if (!isNaN(fechaParsed.getTime())) {
                    const año = fechaParsed.getFullYear();
                    const mes = String(fechaParsed.getMonth() + 1).padStart(2, '0');
                    const dia = String(fechaParsed.getDate()).padStart(2, '0');
                    fechaParaVerificar = `${año}-${mes}-${dia}`;
                }
            } catch (e) {
                // Si no se puede parsear, usar la fecha tal cual
            }
        }

        const params = new URLSearchParams({
            no_telar: String(telarId),
            tipo: String(tipo),
            fecha: fechaParaVerificar,
            turno: String(datosEliminar.turno)
        });

        let result: EstadoTelar
        try {
            result = await http.get(`/inventario-telares/verificar-estado?${params.toString()}`)
        } catch (err) {
            // 404: el registro ya no existe, o sea que ya fue eliminado.
            if ((err as HttpError).status === 404) {
                eliminarRegistro(datosEliminar, checkbox);
                return;
            }
            throw err
        }

        // Guardar datos completos del telar si están disponibles
        if (telarData) {
            window.telarDataCompleto = telarData;
        }

        // Verificar que la respuesta tenga la estructura esperada
        if (!result || typeof result !== 'object') {
            throw new Error('Respuesta inválida del servidor');
        }

        if (result.success === true) {
            // Si tiene reservado, mostrar modal
            // IMPORTANTE: Verificar explícitamente que reservado sea true (no solo truthy)
            if (result.reservado === true || result.reservado === 1 || result.reservado === '1') {
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                // Guardar el tipo de estado: reservado
                window.estadoModalTela = 'reservado';
                // Limpiar variables de urdido (no aplican para reservado)
                window.statusUrdido = null;
                window.puedeEliminar = true; // Por defecto se puede eliminar si está reservado
                // Guardar el ID del registro si está disponible en la respuesta
                if (result.registro_id) {
                    window.registroIdPendienteCalendario = result.registro_id;
                }
                // Guardar datos completos del telar para usar en actualización (si están disponibles)
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                // Guardar datos del telar para usar en actualización
                const telarSection = checkbox.closest<HTMLElement>('.telar-section') || checkbox.closest<HTMLElement>('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            } else if (result.programado === true || result.programado === 1 || result.programado === '1') {
                // Si solo está programado (sin reservado), también mostrar modal
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                // Guardar el tipo de estado: programado
                window.estadoModalTela = 'programado';
                // Guardar el Status de UrdProgramaUrdido y si se puede eliminar
                window.statusUrdido = result.status_urdido || null;
                // Convertir explícitamente a booleano: si viene false, es false; si viene true o undefined, es true
                window.puedeEliminar = result.puede_eliminar === false ? false : (result.puede_eliminar === true ? true : true);

                // Debug: mostrar los valores recibidos
                // Guardar el ID del registro si está disponible en la respuesta
                if (result.registro_id) {
                    window.registroIdPendienteCalendario = result.registro_id;
                }
                // Guardar datos completos del telar para usar en actualización (si están disponibles)
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                // Guardar datos del telar para usar en actualización
                const telarSection = checkbox.closest<HTMLElement>('.telar-section') || checkbox.closest<HTMLElement>('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            } else {
                // Si no tiene reservado ni programado, eliminar directamente
                eliminarRegistro(datosEliminar, checkbox);
            }
        } else {
            // Si hay error al verificar, verificar el tipo de error
            if (result.message && result.message.includes('no encontrado')) {
                // Si el registro no existe, eliminar directamente (ya fue eliminado)
                eliminarRegistro(datosEliminar, checkbox);
            } else {
                // Para otros errores, SIEMPRE mostrar modal por seguridad
                // NO confiar en telarData porque puede estar desactualizado después de múltiples operaciones
                // El backend es la única fuente de verdad confiable
                window.datosEliminacionPendiente = datosEliminar;
                window.checkboxEliminacionPendiente = checkbox;
                // Por defecto, asumir reservado por seguridad
                window.estadoModalTela = 'reservado';
                window.statusUrdido = null;
                window.puedeEliminar = true; // Por defecto se puede eliminar si está reservado
                if (telarData) {
                    window.telarDataCompleto = telarData;
                }
                const telarSection = checkbox.closest<HTMLElement>('.telar-section') || checkbox.closest<HTMLElement>('[data-telar]');
                const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
                window.telarDataPendiente = {
                    salon: salon,
                    telarId: telarId
                };
                window.mostrarModalTelaReservada();
            }
        }
    } catch (error) {
        console.error('Error al verificar estado:', error);
        // Si hay error de conexión o excepción, SIEMPRE mostrar modal por seguridad
        // NO confiar en telarData porque puede estar desactualizado después de múltiples operaciones
        // El backend es la única fuente de verdad confiable
        window.datosEliminacionPendiente = datosEliminar;
        window.checkboxEliminacionPendiente = checkbox;
        // Por defecto, asumir reservado por seguridad
        window.estadoModalTela = 'reservado';
        if (telarData) {
            window.telarDataCompleto = telarData;
        }
        const telarSection = checkbox.closest<HTMLElement>('.telar-section') || checkbox.closest<HTMLElement>('[data-telar]');
        const salon = telarSection?.dataset?.salon || window.salonTelar || 'Jacquard';
        window.telarDataPendiente = {
            salon: salon,
            telarId: telarId
        };
        window.mostrarModalTelaReservada();
    }
}

// Función para mostrar el modal de tela reservada (debe ser global)
window.mostrarModalTelaReservada = function() {
    // Buscar el modal en el DOM - puede estar en cualquier lugar
    let modal = document.getElementById('modalTelaReservada');

    // Si no se encuentra, puede que haya sido removido, buscar en el body o crear uno nuevo
    if (!modal) {
        // Buscar en todo el documento
        modal = document.querySelector<HTMLElement>('#modalTelaReservada');

        // Si aún no se encuentra, puede que haya sido removido del DOM
        // En este caso, necesitamos recrearlo o buscarlo de otra manera
        // Por ahora, simplemente retornar con un error silencioso
        console.warn('Modal modalTelaReservada no encontrado en el DOM');
        return;
    }

    // Asegurar que el modal esté en el body para que cubra toda la pantalla
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    // IMPORTANTE: Cambiar el texto ANTES de mostrar el modal para evitar parpadeo
    // Cambiar el texto según el estado (reservado o programado)
    // Buscar los elementos DENTRO del modal encontrado para evitar conflictos con múltiples instancias
    const estadoTela = window.estadoModalTela || 'reservado'; // Por defecto reservado
    const tituloModal = modal.querySelector('#modalTelaReservadaTitulo');
    const descripcionModal = modal.querySelector('#modalTelaReservadaDescripcion');
    const textoEliminar = modal.querySelector('#modalTelaReservadaEliminarTexto');

    // Debug: verificar si los elementos se encuentran

    // Obtener los botones de eliminar y actualizar
    const btnEliminar = modal.querySelector<HTMLButtonElement>('#btnEliminarReservado');
    const btnActualizar = modal.querySelector<HTMLButtonElement>('#btnActualizarReservado');
    // Obtener valores de las variables globales - convertir explícitamente a booleano
    const puedeEliminar = window.puedeEliminar === false ? false : (window.puedeEliminar === true ? true : true);
    const statusUrdido = window.statusUrdido || null;

    // Debug: mostrar valores antes de evaluar

    // Verificar y cambiar el texto según el estado
    if (estadoTela === 'programado') {
        // Si está solo programado (sin reservado), cambiar textos
        if (tituloModal) {
            tituloModal.textContent = 'Ya está programado';
            tituloModal.innerHTML = 'Ya está programado'; // Asegurar también innerHTML
        } else {
            console.error('Error: No se encontró el elemento modalTelaReservadaTitulo en el modal');
        }

        // Mostrar info del status de urdido si existe (solo informativo, sin bloquear)
        let infoStatus = '';
        if (statusUrdido) {
            infoStatus = ` (Status urdido: ${statusUrdido})`;
        }

        if (descripcionModal) {
            descripcionModal.textContent = 'Este telar tiene tela programada' + infoStatus + '. ¿Qué desea hacer?';
        }
        if (textoEliminar) {
            textoEliminar.textContent = 'Si elimina el registro elimina la programación';
        }

        // Botones SIEMPRE habilitados para registros programados (sin importar status de urdido)
        if (btnEliminar) {
            btnEliminar.disabled = false;
            btnEliminar.classList.remove('bg-gray-400', 'opacity-75', 'cursor-not-allowed');
            btnEliminar.classList.add('bg-red-600', 'hover:bg-red-700', 'focus:ring-red-500', 'hover:shadow-lg');
            btnEliminar.style.pointerEvents = 'auto';
            btnEliminar.style.backgroundColor = '';
            btnEliminar.onclick = confirmarEliminarConReserva;

            const btnContent = btnEliminar.querySelector('div');
            if (btnContent) {
                const spanTitulo = btnContent.querySelector('span.text-base');
                const spanTexto = btnContent.querySelector('span.text-xs');
                if (spanTitulo) spanTitulo.textContent = 'Eliminar';
                if (spanTexto) spanTexto.textContent = 'Si elimina el registro elimina la programación';
            }
        }

        if (btnActualizar) {
            btnActualizar.disabled = false;
            btnActualizar.classList.remove('bg-gray-400', 'opacity-75', 'cursor-not-allowed');
            btnActualizar.classList.add('bg-yellow-600', 'hover:bg-yellow-700', 'focus:ring-yellow-500', 'hover:shadow-lg');
            btnActualizar.style.pointerEvents = 'auto';
            btnActualizar.style.backgroundColor = '';
            btnActualizar.onclick = mostrarCalendarioParaActualizar;

            const spanActualizar = btnActualizar.querySelector('span.text-base');
            if (spanActualizar) spanActualizar.textContent = 'Actualizar';
        }
    } else {
        // Si está reservado (o por defecto), usar textos de reservado
        if (tituloModal) {
            tituloModal.textContent = 'Ya tiene tela reservada';
        }
        if (descripcionModal) {
            descripcionModal.textContent = 'Este telar tiene tela reservada. ¿Qué desea hacer?';
        }
        if (textoEliminar) {
            textoEliminar.textContent = 'Si elimina el registro elimina la reserva';
        }

        // Asegurar que los botones estén habilitados para reservado
        if (btnEliminar) {
            btnEliminar.disabled = false;
            // Restaurar clases de rojo y remover clases grises
            btnEliminar.classList.remove('bg-gray-400', 'opacity-75', 'cursor-not-allowed');
            btnEliminar.classList.add('bg-red-600', 'hover:bg-red-700', 'focus:ring-red-500', 'hover:shadow-lg');
            btnEliminar.style.pointerEvents = 'auto';
            btnEliminar.style.backgroundColor = ''; // Resetear estilo inline
            btnEliminar.onclick = confirmarEliminarConReserva; // Restaurar onclick

            // Restaurar el texto del botón
            const btnContent = btnEliminar.querySelector('div');
            if (btnContent) {
                const spanTitulo = btnContent.querySelector('span.text-base');
                const spanTexto = btnContent.querySelector('span.text-xs');
                if (spanTitulo) {
                    spanTitulo.textContent = 'Eliminar';
                }
                if (spanTexto) {
                    spanTexto.textContent = 'Si elimina el registro elimina la reserva';
                }
            }
        }

        // Asegurar que el botón de actualizar esté habilitado para reservado
        if (btnActualizar) {
            btnActualizar.disabled = false;
            // Restaurar clases de amarillo y remover clases grises
            btnActualizar.classList.remove('bg-gray-400', 'opacity-75', 'cursor-not-allowed');
            btnActualizar.classList.add('bg-yellow-600', 'hover:bg-yellow-700', 'focus:ring-yellow-500', 'hover:shadow-lg');
            btnActualizar.style.pointerEvents = 'auto';
            btnActualizar.style.backgroundColor = ''; // Resetear estilo inline
            btnActualizar.onclick = mostrarCalendarioParaActualizar; // Restaurar onclick

            // Restaurar el texto del botón
            const spanActualizar = btnActualizar.querySelector('span.text-base');
            if (spanActualizar) {
                spanActualizar.textContent = 'Actualizar';
            }
        }
    }

    // Ahora sí, mostrar el modal después de cambiar el texto
    // Asegurar que el modal cubra toda la pantalla con z-index muy alto
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '100001'; // Mayor que el modal 2 (100000)
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.display = 'flex'; // Forzar display flex
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.6)'; // Asegurar backdrop
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Asegurar que el contenido interno también tenga z-index alto
    const modalContent = modal.querySelector<HTMLElement>('div > div');
    if (modalContent) {
        modalContent.style.position = 'relative';
        modalContent.style.zIndex = '100002';
    }
};

// Función para mostrar el modal de calendario semanal (debe ser global)
window.mostrarModalCalendarioSemanal = function() {
    const modalCalendarioMostrar = document.getElementById('modalCalendarioSemanal');
    const grid = document.getElementById('calendarioSemanalGrid');
    const seccionCalendario = document.getElementById('seccionCalendario');

    if (!modalCalendarioMostrar || !grid) return;

    // Reiniciar estado del modal: mostrar calendario, ocultar todos los contenedores de turnos
    if (seccionCalendario) {
        seccionCalendario.classList.remove('hidden');
        seccionCalendario.setAttribute('style', 'display: block !important; visibility: visible !important;');
    }

    // Ocultar todos los contenedores de turnos
    const todosLosContenedores = modalCalendarioMostrar ? modalCalendarioMostrar.querySelectorAll<HTMLElement>('.turnos-container') : document.querySelectorAll<HTMLElement>('.turnos-container');
    todosLosContenedores.forEach(cont => {
        cont.style.display = 'none';
        cont.innerHTML = '';
    });

    // Obtener la fecha de hoy
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    // Nombres de los días
    const nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    // Limpiar grid
    grid.innerHTML = '';

    // Obtener la fecha original para resaltarla en azul
    const fechaOriginalISO = window.fechaOriginalPendienteCalendario;

    // Generar los próximos 7 días a partir de hoy (hoy + 6 días más)
    for (let i = 0; i < 7; i++) {
        const fecha = new Date(hoy);
        fecha.setDate(hoy.getDate() + i);

        const dia = fecha.getDate();
        const mes = fecha.getMonth() + 1;
        const año = fecha.getFullYear();
        const fechaISO = `${año}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const fechaFormato = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;

        // Obtener el día de la semana (0 = domingo, 1 = lunes, etc.)
        const diaSemanaNum = fecha.getDay();
        const nombreDia = nombresDias[diaSemanaNum];

        const esHoy = fecha.toDateString() === hoy.toDateString();
        // Verificar si es la fecha original (la que se está actualizando)
        const esFechaOriginal = fechaOriginalISO && fechaISO === fechaOriginalISO;

        // Crear contenedor para el día y los turnos
        const diaContainer = document.createElement('div');
        diaContainer.className = 'flex flex-col border-r border-gray-300 last:border-r-0';
        diaContainer.style.width = '100%';
        diaContainer.style.borderTop = '1px solid #d1d5db';
        diaContainer.style.borderBottom = '1px solid #d1d5db';
        diaContainer.setAttribute('data-fecha-container', fechaISO);

        // Crear botón del día
        const diaElement = document.createElement('button');
        diaElement.type = 'button';
        diaElement.className = `p-4 md:p-6 lg:p-8 transition-all hover:bg-gray-100 flex flex-col items-center justify-center min-h-[100px] md:min-h-[120px] w-full ${
            esFechaOriginal
                ? 'bg-blue-600 border-blue-700'
                : esHoy
                ? 'bg-blue-100 border-blue-400'
                : 'bg-white'
        }`;
        diaElement.style.width = '100%';
        diaElement.style.display = 'flex';
        diaElement.style.flexDirection = 'column';
        diaElement.innerHTML = `
            <div class="text-xs md:text-sm font-medium mb-1 ${esFechaOriginal ? 'text-white opacity-90' : 'text-gray-600'}">${nombreDia}</div>
            <div class="text-2xl md:text-3xl lg:text-4xl font-bold ${esFechaOriginal ? 'text-white' : 'text-gray-900'}">${dia}</div>
            <div class="text-xs md:text-sm font-normal mt-1 ${esFechaOriginal ? 'text-white opacity-75' : 'text-gray-500'}">${fechaFormato}</div>
        `;
        diaElement.setAttribute('data-fecha', fechaISO);
        diaElement.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.mostrarSeleccionTurnos === 'function') {
                window.mostrarSeleccionTurnos(fechaISO, diaContainer);
            } else {
                console.error('mostrarSeleccionTurnos no está definida');
                alert('Error: La función mostrarSeleccionTurnos no está disponible');
            }
        };

        // Crear contenedor de turnos (fuera del botón, debajo)
        const turnosContainer = document.createElement('div');
        turnosContainer.className = 'turnos-container';
        turnosContainer.setAttribute('data-fecha-turnos', fechaISO);
        turnosContainer.style.cssText = 'display: none; width: 100%; padding: 4px 2px; box-sizing: border-box;';
        turnosContainer.style.display = 'none';

        // Agregar botón y contenedor de turnos al contenedor del día
        diaContainer.appendChild(diaElement);
        diaContainer.appendChild(turnosContainer);

        grid.appendChild(diaContainer);
    }

    // Forzar que el grid sea horizontal
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
    grid.style.gridAutoFlow = 'row';
    grid.style.width = '100%';

    // Mover el modal al body si no está ya ahí
    if (modalCalendarioMostrar.parentElement !== document.body) {
        document.body.appendChild(modalCalendarioMostrar);
    }

    // Asegurar que el modal 1 esté completamente oculto
    const modales1 = document.querySelectorAll<HTMLElement>('#modalTelaReservada');
    modales1.forEach(m => {
        m.classList.add('hidden');
        m.classList.remove('flex');
        m.style.display = 'none';
    });

    modalCalendarioMostrar.style.position = 'fixed';
    modalCalendarioMostrar.style.top = '0';
    modalCalendarioMostrar.style.left = '0';
    modalCalendarioMostrar.style.right = '0';
    modalCalendarioMostrar.style.bottom = '0';
    modalCalendarioMostrar.style.zIndex = '100000'; // Mayor que el modal 1 para estar por encima
    modalCalendarioMostrar.style.width = '100%';
    modalCalendarioMostrar.style.height = '100%';
    modalCalendarioMostrar.style.display = 'flex'; // Forzar display flex
    modalCalendarioMostrar.style.visibility = 'visible'; // Asegurar que sea visible
    modalCalendarioMostrar.style.opacity = '1'; // Asegurar opacidad completa
    modalCalendarioMostrar.style.transform = 'none'; // Resetear transform
    modalCalendarioMostrar.classList.remove('hidden');
    modalCalendarioMostrar.classList.add('flex');
};

// Función para cerrar el modal de calendario semanal (debe ser global)
window.cerrarModalCalendarioSemanal = function() {
    const modalCalendarioCerrar = document.getElementById('modalCalendarioSemanal');
    const seccionCalendario = document.getElementById('seccionCalendario');

    // Reiniciar estado del modal: mostrar calendario, ocultar todos los contenedores de turnos
    if (seccionCalendario) {
        seccionCalendario.classList.remove('hidden');
        seccionCalendario.setAttribute('style', 'display: block !important; visibility: visible !important;');
    }

    // Ocultar todos los contenedores de turnos
    const todosLosContenedores = modalCalendarioCerrar ? modalCalendarioCerrar.querySelectorAll<HTMLElement>('.turnos-container') : document.querySelectorAll<HTMLElement>('.turnos-container');
    todosLosContenedores.forEach(cont => {
        cont.style.display = 'none';
        cont.innerHTML = '';
    });

    // Limpiar fecha seleccionada
    window.fechaSeleccionadaCalendario = null;

    // Cerrar completamente TODOS los modales 2 INMEDIATAMENTE - MÉTODO AGRESIVO
    // Buscar TODOS los modales 2 porque puede haber múltiples instancias (una por telar)
    const todosLosModales2 = document.querySelectorAll<HTMLElement>('#modalCalendarioSemanal');

    todosLosModales2.forEach((modalItem, index) => {

        // Usar setAttribute con !important para sobrescribir estilos inline
        modalItem.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modalItem.classList.add('hidden');
        modalItem.classList.remove('flex');

        // Remover del DOM completamente
        const modalParent = modalItem.parentElement;
        if (modalParent) {
            modalParent.removeChild(modalItem);
        }
    });

    // También cerrar el modal encontrado por ID (por compatibilidad) si no se encontraron otros
    if (modalCalendarioCerrar && todosLosModales2.length === 0) {
        modalCalendarioCerrar.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modalCalendarioCerrar.classList.add('hidden');
        modalCalendarioCerrar.classList.remove('flex');

        // Reiniciar estado del modal: mostrar calendario, ocultar todos los contenedores de turnos
        const seccionCalendario = document.getElementById('seccionCalendario');
        if (seccionCalendario) {
            seccionCalendario.classList.remove('hidden');
            seccionCalendario.setAttribute('style', 'display: block !important; visibility: visible !important;');
        }

        // Ocultar todos los contenedores de turnos
        const modalCalendario2 = document.getElementById('modalCalendarioSemanal');
        const todosLosContenedores = modalCalendario2 ? modalCalendario2.querySelectorAll<HTMLElement>('.turnos-container') : document.querySelectorAll<HTMLElement>('.turnos-container');
        todosLosContenedores.forEach(cont => {
            cont.style.display = 'none';
            cont.innerHTML = '';
        });

        // Limpiar fecha seleccionada
        window.fechaSeleccionadaCalendario = null;

        const modalParent = modalCalendarioCerrar.parentElement;
        if (modalParent) {
            modalParent.removeChild(modalCalendarioCerrar);
        }
    }

    // Guardar las referencias ANTES de limpiar cualquier cosa
    const datosPendientes = window.datosEliminacionPendiente;
    const checkboxPendiente = window.checkboxEliminacionPendiente;
    const telarDataPendiente = window.telarDataPendiente;
    const checkboxCalendario = window.checkboxPendienteCalendario;

    // Limpiar variables del calendario (pero mantener las del modal de confirmación)
    // Solo limpiar si NO se está seleccionando una fecha (es decir, si se cancela)
    // Si se seleccionó una fecha, las variables ya se limpiaron en seleccionarTurno
    if (checkboxCalendario && window.telarIdPendienteCalendario) {
        // Si aún existen las variables del calendario, significa que se canceló
        window.checkboxPendienteCalendario = null;
        window.telarIdPendienteCalendario = null;
        window.tipoPendienteCalendario = null;
        window.turnoPendienteCalendario = null;
        window.fechaOriginalPendienteCalendario = null;
        window.registroIdPendienteCalendario = null;
        window.fechaSeleccionadaCalendario = null;
    }
    // NO limpiar telarDataPendiente, datosEliminacionPendiente ni checkboxEliminacionPendiente
    // porque se necesitan para restaurar el modal 1 cuando se cancela

    // Si se cancela, restaurar el modal de confirmación (modal 1)
    // Las variables window.datosEliminacionPendiente y window.checkboxEliminacionPendiente
    // se mantienen porque no se limpiaron al mostrar el calendario
    if (datosPendientes && checkboxPendiente) {
        // Restaurar las variables por si se perdieron
        window.datosEliminacionPendiente = datosPendientes;
        window.checkboxEliminacionPendiente = checkboxPendiente;
        if (telarDataPendiente) {
            window.telarDataPendiente = telarDataPendiente;
        }

        // Delay más largo para asegurar que el modal 2 se cierre completamente
        setTimeout(() => {
            // Verificar y forzar cierre de TODOS los modales 2 - MÉTODO AGRESIVO
            const todosLosModales2 = document.querySelectorAll<HTMLElement>('#modalCalendarioSemanal');

            todosLosModales2.forEach((modal2, index) => {
                // Usar setAttribute con !important para sobrescribir estilos inline
                modal2.setAttribute('style', `
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    z-index: -9999 !important;
                    pointer-events: none !important;
                    position: fixed !important;
                    top: -9999px !important;
                    left: -9999px !important;
                    right: auto !important;
                    bottom: auto !important;
                    width: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
                    max-width: 0 !important;
                    max-height: 0 !important;
                `);
                modal2.classList.add('hidden');
                modal2.classList.remove('flex');

                // Remover del DOM completamente
                const modal2Parent = modal2.parentElement;
                if (modal2Parent) {
                    modal2Parent.removeChild(modal2);
                }
            });

            // Pequeño delay adicional antes de mostrar modal 1
            setTimeout(() => {
                // Mostrar el modal de tela reservada nuevamente (modal 1) con z-index alto
                if (typeof window.mostrarModalTelaReservada === 'function') {
                    window.mostrarModalTelaReservada();
                } else {
                    console.error('mostrarModalTelaReservada no está definida');
                }
            }, 50);
        }, 200);
    } else {
        // Si no hay datos pendientes del modal 1, re-marcar el checkbox
        if (checkboxCalendario) {
            checkboxCalendario.checked = true;
        }
    }
};

// Función para mostrar selección de turnos después de seleccionar fecha (debe ser global)
window.mostrarSeleccionTurnos = async function(fechaISO: string, diaElement?: HTMLElement | null) {

    if (!window.checkboxPendienteCalendario || !window.telarIdPendienteCalendario) {
        console.error('Faltan datos necesarios:', {
            checkboxPendienteCalendario: !!window.checkboxPendienteCalendario,
            telarIdPendienteCalendario: !!window.telarIdPendienteCalendario
        });
        if (typeof window.cerrarModalCalendarioSemanal === 'function') {
            window.cerrarModalCalendarioSemanal();
        }
        return;
    }

    const telarId = window.telarIdPendienteCalendario;
    const tipo = window.tipoPendienteCalendario;
    const registroId = window.registroIdPendienteCalendario; // ID del registro que se está actualizando

    // Buscar el contenedor del día si no se pasó
    if (!diaElement) {
        // Si no se pasó el elemento, buscarlo por la fecha
        const modalCalendario4 = document.getElementById('modalCalendarioSemanal');
        if (!modalCalendario4) {
            console.error('Modal modalCalendarioSemanal no encontrado');
            return;
        }
        // Buscar el contenedor del día (que tiene data-fecha-container)
        diaElement = modalCalendario4.querySelector<HTMLElement>(`[data-fecha-container="${fechaISO}"]`);
        if (!diaElement) {
            // Si no se encuentra por data-fecha-container, buscar el botón con data-fecha y obtener su padre
            const btnDia = modalCalendario4.querySelector(`[data-fecha="${fechaISO}"]`);
            if (btnDia && btnDia.parentElement) {
                diaElement = btnDia.parentElement;
            } else {
                console.error('Elemento del día no encontrado para fecha:', fechaISO);
                return;
            }
        }
    }

    // Ocultar todos los contenedores de turnos de otros días ANTES de mostrar los nuevos
    const modalCalendario3 = document.getElementById('modalCalendarioSemanal');
    if (modalCalendario3) {
        const todosLosContenedores = modalCalendario3.querySelectorAll<HTMLElement>('.turnos-container');
        todosLosContenedores.forEach(cont => {
            const contFecha = cont.getAttribute('data-fecha-turnos');
            if (contFecha && contFecha !== fechaISO) {
                // Ocultar y limpiar los turnos de otros días
                cont.style.display = 'none';
                cont.style.visibility = 'hidden';
                cont.innerHTML = '';
            }
        });
    }

    // Buscar el contenedor de turnos dentro del contenedor del día
    let turnosContainer = diaElement.querySelector<HTMLElement>('.turnos-container');
    if (!turnosContainer) {
        // Si no existe, crearlo
        turnosContainer = document.createElement('div');
        turnosContainer.className = 'turnos-container';
        turnosContainer.setAttribute('data-fecha-turnos', fechaISO);
        turnosContainer.style.cssText = 'display: none; width: 100%; padding: 4px 2px; box-sizing: border-box;';
        diaElement.appendChild(turnosContainer);
    } else {
        // Si ya existe, limpiarlo para recargar los turnos (importante: verificar que sea de la misma fecha)
        const fechaAnterior = turnosContainer.getAttribute('data-fecha-turnos');
        if (fechaAnterior !== fechaISO) {
            // Si es de otra fecha, limpiar completamente
            turnosContainer.innerHTML = '';
            turnosContainer.setAttribute('data-fecha-turnos', fechaISO);
        } else {
            // Si es la misma fecha, limpiar para recargar
            turnosContainer.innerHTML = '';
        }
    }

    // Guardar fecha seleccionada
    window.fechaSeleccionadaCalendario = fechaISO;

    // Verificar qué turnos están ocupados
    try {
        const params = new URLSearchParams({
            no_telar: String(telarId),
            tipo: tipo as string,
            fecha: fechaISO
        });

        if (registroId) {
            params.append('registro_id_excluir', String(registroId));
        }

        const result = await http.get(`/inventario-telares/verificar-turnos-ocupados?${params.toString()}`)

        if (result.success) {
            const turnosOcupados = result.turnos_ocupados || [];


            // Limpiar el contenedor y crear los botones de turno
            turnosContainer.innerHTML = '';

            // Crear botones de turno en una fila horizontal que ocupe todo el ancho
            const turnosRow = document.createElement('div');
            turnosRow.className = 'flex w-full';
            turnosRow.style.cssText = 'display: flex; width: 100%; gap: 2px;';

            [1, 2, 3].forEach(turno => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('data-turno', String(turno));

                // Verificar si el turno está ocupado (comparar como número)
                const turnoOcupado = turnosOcupados.includes(parseInt(String(turno), 10)) || turnosOcupados.includes(turno);


                if (turnoOcupado) {
                    // Turno ocupado: fondo rojo tenue, texto rojo, deshabilitado
                    btn.className = 'flex-1 py-2 text-sm font-bold rounded transition-all focus:outline-none focus:ring-2 focus:ring-red-500 bg-red-100 text-red-700 border border-red-300 cursor-not-allowed opacity-75';
                    btn.style.cssText = 'flex: 1 1 0%; min-width: 0; width: 100%;';
                    btn.disabled = true;
                    btn.title = 'Este turno ya está ocupado';
                } else {
                    // Turno disponible: fondo azul, texto blanco, habilitado
                    btn.className = 'flex-1 py-2 text-sm font-bold rounded transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 bg-blue-500 text-white border border-blue-600 hover:bg-blue-600';
                    btn.style.cssText = 'flex: 1 1 0%; min-width: 0; width: 100%;';
                    btn.disabled = false;
                    btn.title = '';
                }

                btn.textContent = String(turno);
                btn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof window.seleccionarTurno === 'function') {
                        window.seleccionarTurno(turno, btn);
                    }
                };

                turnosRow.appendChild(btn);
            });

            turnosContainer.appendChild(turnosRow);

            // Mostrar el contenedor de turnos
            turnosContainer.style.display = 'block';
            turnosContainer.style.visibility = 'visible';
            turnosContainer.style.width = '100%';
            turnosContainer.style.boxSizing = 'border-box';

        }
    } catch (error) {
        console.error('Error al verificar turnos ocupados:', error);
        // Si hay error, crear botones sin restricciones
        turnosContainer.innerHTML = '';

        // Crear botones de turno en una fila horizontal que ocupe todo el ancho
        const turnosRow = document.createElement('div');
        turnosRow.className = 'flex w-full';
        turnosRow.style.cssText = 'display: flex; width: 100%; gap: 2px;';

        [1, 2, 3].forEach(turno => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-turno', String(turno));
            btn.className = 'flex-1 py-2 text-sm font-bold rounded transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 bg-blue-500 text-white border border-blue-600 hover:bg-blue-600';
            btn.style.cssText = 'flex: 1 1 0%; min-width: 0; width: 100%;';
            btn.textContent = String(turno);
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.seleccionarTurno === 'function') {
                    window.seleccionarTurno(turno);
                }
            };
            turnosRow.appendChild(btn);
        });

        turnosContainer.appendChild(turnosRow);
        turnosContainer.style.display = 'block';
        turnosContainer.style.visibility = 'visible';
        turnosContainer.style.width = '100%';
        turnosContainer.style.boxSizing = 'border-box';
    }
};

// Función para manejar el botón cancelar del modal 2 (debe ser global)
// Cierra el modal completamente
window.manejarCancelarModal2 = function() {
    // Ocultar todos los contenedores de turnos
    const modalCalendario5 = document.getElementById('modalCalendarioSemanal');
    if (modalCalendario5) {
        const todosLosContenedores = modalCalendario5.querySelectorAll<HTMLElement>('.turnos-container');
        todosLosContenedores.forEach(cont => {
            cont.style.display = 'none';
            cont.innerHTML = '';
        });
    }

    // Cerrar el modal completamente
    if (typeof window.cerrarModalCalendarioSemanal === 'function') {
        window.cerrarModalCalendarioSemanal();
    }
}

// Función para seleccionar turno y actualizar (debe ser global)
window.seleccionarTurno = function(turno: number, btnElement?: HTMLButtonElement) {
    // Si se pasa el elemento del botón, usarlo; si no, buscarlo
    const btn = btnElement || document.querySelector<HTMLButtonElement>(`[data-turno="${turno}"]`);

    if (!btn) {
        console.error('Botón de turno no encontrado');
        return;
    }

    // Verificar si el turno está ocupado (deshabilitado)
    if (btn.disabled) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Turno Ocupado',
                text: `El turno ${turno} ya está ocupado para esta fecha. Por favor, selecciona otro turno.`,
                showConfirmButton: true,
                confirmButtonText: 'Entendido'
            });
        }
        return;
    }

    if (!window.checkboxPendienteCalendario || !window.telarIdPendienteCalendario || !window.fechaSeleccionadaCalendario) {
        console.error('Faltan datos necesarios para actualizar');
        return;
    }

    // Guardar referencias antes de limpiar
    const telarId = window.telarIdPendienteCalendario;
    const tipo = window.tipoPendienteCalendario;
    const turnoOriginal = window.turnoPendienteCalendario;
    const fechaOriginal = window.fechaOriginalPendienteCalendario;
    const fechaNueva = window.fechaSeleccionadaCalendario;

    // Limpiar TODAS las variables globales ANTES de cerrar modales
    // Esto evita que cerrarModalCalendarioSemanal restaure el modal 1
    window.datosEliminacionPendiente = null;
    window.checkboxEliminacionPendiente = null;
    window.checkboxPendienteCalendario = null;
    window.telarIdPendienteCalendario = null;
    window.tipoPendienteCalendario = null;
    window.turnoPendienteCalendario = null;
    window.fechaOriginalPendienteCalendario = null;
    window.registroIdPendienteCalendario = null;
    window.fechaSeleccionadaCalendario = null;
    window.telarDataPendiente = null;
    window.telarDataCompleto = null;

    // Ocultar AMBOS modales directamente (sin usar cerrarModalCalendarioSemanal que restaura modal 1)
    document.querySelectorAll('#modalCalendarioSemanal, #modalTelaReservada').forEach(modal => {
        modal.setAttribute('style', 'display: none !important; visibility: hidden !important;');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });

    // Actualizar la fecha y turno del registro
    const datosActualizar = {
        no_telar: String(telarId),
        tipo: tipo,
        fecha_original: fechaOriginal,
        turno: turnoOriginal,
        fecha_nueva: fechaNueva,
        turno_nuevo: turno
    };

    // Hacer la petición de actualización directamente (sin modales intermedios)
    http.post('/inventario-telares/actualizar-fecha', datosActualizar)
    .then(() => {
        // Recargar la página y hacer scroll al telar actualizado
        // Usar hash para que al recargar se posicione en el telar
        const anchorId = 'telar-' + telarId;
        // Guardar el telar en sessionStorage para hacer scroll después del reload
        sessionStorage.setItem('scrollToTelar', String(telarId));
        window.location.reload();
    })
    .catch(error => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error al actualizar',
                text: error.message || 'No se pudo actualizar la fecha y turno del registro.',
                showConfirmButton: true
            });
        }
    });
}

// Función para actualizar el registro con la nueva fecha
async function actualizarRegistroConNuevaFecha(checkbox: HTMLInputElement, telarId: TelarId, tipo: string, turno: number, fechaOriginal: string, fechaNueva: string) {
    // Obtener el salón del contexto guardado o del elemento
    const salon = window.telarDataPendiente?.salon ||
                 checkbox.closest<HTMLElement>('.telar-section')?.dataset?.salon ||
                 checkbox.closest<HTMLElement>('[data-telar]')?.dataset?.salon ||
                 window.salonTelar ||
                 'Jacquard';

    try {
        // Primero intentar usar los datos completos del telar guardados desde handleRequerimientoChange
        let telarData: Registro | null | undefined = window.telarDataCompleto;

        // Si no están disponibles, intentar obtener desde el caché
        if (!telarData) {
            try {
                // Obtener inventario completo desde el caché
                const inventario = await obtenerInventarioConCache();

                if (Array.isArray(inventario) && inventario.length > 0) {
                    telarData = inventario.find(t => String(t.no_telar) === String(telarId) || String(t.Telar) === String(telarId));
                }
            } catch (errorCache) {
                console.warn('Error al obtener desde caché, intentando obtener directamente:', errorCache);
            }

            // Si no se encontró en el caché, intentar obtener con filtro específico del telar
            if (!telarData) {
                try {
                    const inventarioFiltrado = await obtenerInventarioConCache({ no_telar: String(telarId) });
                    if (Array.isArray(inventarioFiltrado) && inventarioFiltrado.length > 0) {
                        telarData = inventarioFiltrado[0]; // El primero debería ser el telar buscado
                    }
                } catch (errorFiltrado) {
                    console.warn('Error al obtener con filtro:', errorFiltrado);
                }
            }
        }

        if (!telarData) {
            throw new Error('No se encontraron datos del telar. Por favor, recargue la página.');
        }

        // Obtener cuenta, calibre, hilo y orden según el tipo
        // Los datos pueden venir con diferentes nombres de campos según la fuente
        const cuenta = tipo === 'Rizo'
            ? (telarData.Cuenta || telarData.cuenta || '')
            : (telarData.Cuenta_Pie || telarData.CuentaPie || telarData.cuenta_pie || '');

        const calibre = tipo === 'Rizo'
            ? (telarData.CalibreRizo2 || telarData.CalibreRizo || telarData.calibre_rizo || null)
            : (telarData.CalibrePie2 || telarData.CalibrePie || telarData.calibre_pie || null);

        const hilo = tipo === 'Rizo'
            ? (telarData.Fibra_Rizo || telarData.FibraRizo || telarData.fibra_rizo || telarData.hilo || '')
            : (telarData.Fibra_Pie || telarData.FibraPie || telarData.fibra_pie || telarData.hilo || '');

        const noOrden = telarData.Orden_Prod || telarData.OrdenProd || telarData.orden_prod || telarData.no_orden || '';

        if (!cuenta || cuenta === '') {
            throw new Error('No se encontró cuenta para este telar. Verifique los datos del telar.');
        }

        // Preparar datos para crear el nuevo registro
        const datosCrear = {
            no_telar: String(telarId),
            tipo: tipo,
            fecha: fechaNueva,
            turno: turno,
            cuenta: String(cuenta),
            calibre: calibre ? parseFloat(calibre) : null,
            salon: salon,
            hilo: hilo || '',
            no_orden: String(noOrden || '')
        };

        // Actualizar la fecha del registro existente en lugar de eliminar y crear
        try {
            const datosActualizar = {
                no_telar: String(telarId),
                tipo: tipo,
                fecha_original: fechaOriginal,
                turno: turno,
                fecha_nueva: fechaNueva
            };

            const responseActualizar = await http.post('/inventario-telares/actualizar-fecha', datosActualizar)

            if (!responseActualizar || responseActualizar.success === false) {
                throw new Error(responseActualizar?.message || 'Error al actualizar la fecha del registro');
            }
        } catch (errorActualizar) {
            console.error('Error al actualizar fecha del registro:', errorActualizar);
            throw new Error((errorActualizar as { response?: { data?: { message?: string } } }).response?.data?.message || 'No se pudo actualizar la fecha del registro. El registro original se mantiene.');
        }

        // Invalidar caché para forzar recarga
        invalidarCacheInventario();

        // Desmarcar el checkbox viejo (de la fecha original)
        checkbox.checked = false;
        checkbox.setAttribute('data-eliminado', 'true');
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        // Recargar requerimientos para que aparezca en la nueva fecha y se marque el nuevo checkbox
        setTimeout(() => {
            // Obtener el tipo normalizado para la recarga
            const tipoNormalizado = tipo === 'Rizo' ? 'rizo' : 'pie';

            // Intentar obtener la fibra del telar para recargar con filtro si es necesario
            const fibraNormalizada = hilo ? String(hilo).trim().toLowerCase() : '';
            const fibraValida = fibraNormalizada && fibraNormalizada !== '' && fibraNormalizada !== '-';

            if (fibraValida && typeof loadRequerimientosConFiltro === 'function') {
                loadRequerimientosConFiltro(telarId, salon, tipoNormalizado, fibraNormalizada);
            } else if (typeof loadRequerimientos === 'function') {
                loadRequerimientos(telarId, salon);
            }
        }, 300);

        // Formatear fecha para mostrar
        const [año, mes, dia] = fechaNueva.split('-');
        const fechaFormato = `${dia}/${mes}`;

        // Mostrar notificación de éxito
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Actualizado con éxito',
                text: `Fecha actualizada a ${fechaFormato}. El registro aparecerá en la nueva fecha.`,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
    } catch (error) {
        console.error('Error al actualizar registro:', error);

        // Re-marcar el checkbox en caso de error (el registro original sigue existiendo)
        checkbox.checked = true;
        checkbox.removeAttribute('data-eliminado');
        checkbox.removeAttribute('data-cambio-reciente');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error al actualizar',
                text: (error as Error).message || 'No se pudo actualizar el registro. El registro original se mantiene.',
                showConfirmButton: false,
                timer: 3000,
                position: 'top-end',
                toast: true
            });
        }
    }
}

// Función para cerrar el modal de tela reservada
window.cerrarModalTelaReservada = function() {

    // Guardar referencia al checkbox antes de limpiar variables
    const checkbox = window.checkboxEliminacionPendiente;

    // IMPORTANTE: NO remover el modal del DOM, solo ocultarlo
    // Esto asegura que siempre esté disponible para futuras aperturas

    // FORZAR cierre completo de TODOS los modales 2 (calendario) - PRIMERO
    // Buscar TODOS los modales 2 porque puede haber múltiples instancias (una por telar)
    const todosLosModales2 = document.querySelectorAll<HTMLElement>('#modalCalendarioSemanal');

    todosLosModales2.forEach((modal2, index) => {
        // Usar setAttribute con !important para sobrescribir estilos inline
        modal2.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modal2.classList.add('hidden');
        modal2.classList.remove('flex');

        // Remover del DOM completamente
                const modal2Parent = modal2.parentElement;
                if (modal2Parent) {
                    modal2Parent.removeChild(modal2);
                }
    });

    // FORZAR cierre completo de TODOS los modales 1 - SEGUNDO
    const modales = document.querySelectorAll<HTMLElement>('#modalTelaReservada');
    modales.forEach((modal, index) => {
        // Usar setAttribute con !important para sobrescribir estilos inline
        modal.setAttribute('style', `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            z-index: -9999 !important;
            pointer-events: none !important;
            position: fixed !important;
            top: -9999px !important;
            left: -9999px !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            transform: translateX(-9999px) translateY(-9999px) scale(0) !important;
        `);
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        // IMPORTANTE: NO remover del DOM, solo ocultar
        // Esto asegura que el modal siempre esté disponible para futuras aperturas
        // El modal debe permanecer en el DOM, solo oculto
    });

    // Limpiar TODAS las variables después de cerrar ambos modales
    window.datosEliminacionPendiente = null;
    window.checkboxEliminacionPendiente = null;
    window.checkboxPendienteCalendario = null;
    window.telarIdPendienteCalendario = null;
    window.tipoPendienteCalendario = null;
    window.turnoPendienteCalendario = null;
    window.fechaOriginalPendienteCalendario = null;
    window.telarDataPendiente = null;
    window.telarDataCompleto = null;

    // Si hay un checkbox pendiente, significa que se canceló la eliminación
    // Hacer reload de la página para limpiar todo y cargar el estado correcto
    if (checkbox) {
        // Recargar la página para limpiar todo y cargar el estado correcto
        window.location.reload();
    }
};

// Función para confirmar eliminación con reserva (elimina el registro y la reserva)
function confirmarEliminarConReserva() {
    if (window.datosEliminacionPendiente && window.checkboxEliminacionPendiente) {
        // Guardar referencias antes de cerrar el modal (que limpia las variables)
        const datosEliminar = window.datosEliminacionPendiente;
        const checkbox = window.checkboxEliminacionPendiente;

        // Limpiar la referencia del checkbox antes de eliminar
        // para que cerrarModalTelaReservada no haga reload
        window.checkboxEliminacionPendiente = null;

        // Cerrar el modal inmediatamente
        window.cerrarModalTelaReservada();

        // Luego ejecutar la eliminación con las referencias guardadas
        eliminarRegistro(datosEliminar, checkbox);
    }
}

// Función para mostrar calendario cuando se presiona "Actualizar"
function mostrarCalendarioParaActualizar() {
    // Guardar datos del checkbox pendiente para usar después de seleccionar fecha
    // IMPORTANTE: NO limpiar window.datosEliminacionPendiente ni window.checkboxEliminacionPendiente
    // porque se necesitan para restaurar el modal 1 si se cancela
    if (window.checkboxEliminacionPendiente && window.datosEliminacionPendiente) {
        window.checkboxPendienteCalendario = window.checkboxEliminacionPendiente;
        window.telarIdPendienteCalendario = window.datosEliminacionPendiente.no_telar;
        window.tipoPendienteCalendario = window.datosEliminacionPendiente.tipo;
        window.turnoPendienteCalendario = window.datosEliminacionPendiente.turno;
        window.fechaOriginalPendienteCalendario = window.datosEliminacionPendiente.fecha;
        // Guardar el ID del registro si está disponible (se guarda cuando se verifica el estado)
        // El registroId se guarda en verificarEstadoTelarAntesDeEliminar cuando se recibe la respuesta
        // Mantener los datos del telar
        window.telarDataPendiente = window.telarDataPendiente || {
            salon: 'Jacquard',
            telarId: window.datosEliminacionPendiente.no_telar
        };

        // Cerrar completamente el modal de confirmación (modal 1)
        const modales = document.querySelectorAll<HTMLElement>('#modalTelaReservada');
        modales.forEach(modal => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.style.display = 'none';
            // Asegurar que el z-index no interfiera
            modal.style.zIndex = '99999';
        });

        // Pequeño delay para asegurar que el modal 1 se cierre completamente
        setTimeout(() => {
        // Mostrar modal de calendario semanal (modal 2)
        window.mostrarModalCalendarioSemanal();
        }, 100);
    }
}

// Función para eliminar el registro
function eliminarRegistro(datosEliminar: DatosEliminar, checkbox: HTMLInputElement | null) {
    // Validar que el checkbox existe
    if (!checkbox) {
        console.error('Error: checkbox es null en eliminarRegistro');
        return;
    }

    // Marcar el checkbox como eliminado permanentemente para evitar que se vuelva a marcar
    checkbox.setAttribute('data-eliminado', 'true');
    checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

    // Asegurar que el checkbox esté desmarcado
    checkbox.checked = false;

    // Para DELETE, axios envía los datos en el body pero Laravel los lee desde input()
    http.delete('/inventario-telares/eliminar', { data: datosEliminar })
    .then(response => {
        // Invalidar caché para que se actualice en la próxima carga (pero NO recargar automáticamente)
        invalidarCacheInventario();

        // El checkbox ya está desmarcado visualmente, mantenerlo así
        // Asegurar que el atributo data-eliminado esté presente para que no se vuelva a marcar nunca
        checkbox.setAttribute('data-eliminado', 'true');
        checkbox.setAttribute('data-cambio-reciente', Date.now().toString());

        // Asegurar que el checkbox esté desmarcado
        checkbox.checked = false;

        // NO recargar automáticamente los requerimientos - el checkbox permanecerá desmarcado
        // Si el usuario necesita ver los cambios, puede recargar manualmente la página

        // Mostrar notificación de éxito
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Eliminado con éxito',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
    })
    .catch(error => {
        // Si hay error, remover el atributo de eliminado para permitir reintentos
        checkbox.removeAttribute('data-eliminado');

        // Mostrar notificación de error
        if (typeof Swal !== 'undefined') {
            const errorMessage = error.message || 'Error desconocido';

            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar',
                text: errorMessage,
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
                position: 'top-end',
                toast: true
            });
        }
        // Re-marcar el checkbox si hubo error
        checkbox.checked = true;
    });
}

// El HTML llama a estas desde onclick inline, asi que siguen siendo globales.
Object.assign(window, {
  abrirModalSeleccion,
  cerrarModalSeleccion,
  confirmarSeleccion,
  confirmarEliminarConReserva,
  mostrarCalendarioParaActualizar,
  loadRequerimientos,
  invalidarCacheInventario,
})

/** Una tarjeta por cada <div data-telar-config> que haya en la pagina. */
function initTelares(): void {
  document.querySelectorAll<HTMLElement>('[data-telar-config]').forEach((nodo) => {
    if (nodo.dataset.telarIniciado) return
    nodo.dataset.telarIniciado = '1'
    initTelar(JSON.parse(nodo.dataset.telarConfig as string) as TelarConfig)
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTelares)
} else {
  initTelares()
}
