<div id="reqpt-line-wrapper" class="mt-4 hidden">
    <div class="shadow rounded-md overflow-hidden">
        <!-- Tabla con altura máxima fija y scroll interno -->
        <div class="overflow-x-auto max-h-48" style="max-height: 250px; overflow-y: auto;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-500 text-white sticky top-0">
                    <tr>
                        <th class="px-2 py-1 text-left text-xs font-semibold">Fecha</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Piezas</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Total Kilos</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Aplicación</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Trama</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 1</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 2</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 3</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 4</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Combinación 5</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Rizo</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Pie</th>
                        <th class="px-2 py-1 text-right text-xs font-semibold">Mts/Rizo</th>

                    </tr>
                </thead>
                <tbody id="reqpt-line-body" class=" divide-y divide-gray-100 bg-white">
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Controlador para cancelar peticiones anteriores
let currentAbortController = null;
let currentRequestId = 0;

async function loadReqProgramaTejidoLines(params = {}) {
    const wrap = document.getElementById('reqpt-line-wrapper');
    const body = document.getElementById('reqpt-line-body');
    const meta = document.getElementById('reqpt-line-meta');
    if (!wrap || !body) return;

    // Cancelar petición anterior si existe
    if (currentAbortController) {
        currentAbortController.abort();
    }

    // Crear nuevo controlador de aborto
    currentAbortController = new AbortController();
    const requestId = ++currentRequestId;

    const qs = new URLSearchParams(params).toString();
    const url = '/planeacion/req-programa-tejido-line' + (qs ? ('?' + qs) : '');

    // Mostrar estado de carga
    body.innerHTML = `<tr><td colspan="14" class="px-3 py-4 text-center text-sm text-gray-500">
        <div class="flex items-center justify-center gap-2">
            <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <span>Cargando...</span>
        </div>
    </td></tr>`;
    wrap.classList.remove('hidden');

    try {
        const r = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: currentAbortController.signal
        });

        // Verificar si esta petición sigue siendo la más reciente
        if (requestId !== currentRequestId) {
            return; // Ignorar respuesta si hay una petición más reciente
        }

        if (!r.ok) {
            body.innerHTML = `<tr><td colspan="14" class="px-3 py-6">
                <div class="max-w-xl mx-auto  text-blue-800 rounded-md p-4 text-sm text-center">
                    <div class="font-semibold mb-1">No se pudo cargar el detalle</div>
                    <div>Intenta nuevamente más tarde.</div>
                </div>
            </td></tr>`;
            meta && (meta.textContent = '');
            return;
        }

        const data = await r.json();

        // Verificar nuevamente si esta petición sigue siendo la más reciente
        if (requestId !== currentRequestId) {
            return; // Ignorar respuesta si hay una petición más reciente
        }

        const page = data?.data ?? data; // soporta paginate o arreglo simple
        const items = page?.data ?? page; // paginate.data o arreglo

        if (!Array.isArray(items) || items.length === 0) {
            body.innerHTML = `<tr><td colspan="14" class="px-3 py-6 text-center text-sm text-gray-500">Sin líneas registradas</td></tr>`;
            meta && (meta.textContent = '0 registros');
            return;
        }

        const rows = items.map(it => {
            const f = (v) => {
                if (v === null || v === undefined || v === '') return '';
                if (isNaN(v)) return String(v);
                const num = Number(v);
                return num.toLocaleString('en-US', {
                    minimumFractionDigits: num % 1 === 0 ? 0 : 2,
                    maximumFractionDigits: 2
                });
            };
            const fecha = it.Fecha ? new Date(it.Fecha).toLocaleDateString() : '';
            return `
                <tr class="hover:bg-blue-50">
                    <td class="px-2 py-1 text-xs">${fecha}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Cantidad)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Kilos)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Aplicacion)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Trama)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina1)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina2)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina3)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina4)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Combina5)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Rizo)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.Pie)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.MtsPie)}</td>
                    <td class="px-2 py-1 text-xs text-right">${f(it.MtsRizo)}</td>
                </tr>`;
        }).join('');

        // Verificar una última vez antes de renderizar
        if (requestId === currentRequestId) {
            body.innerHTML = rows;
            meta && (meta.textContent = `${items.length} registro(s)`);
        }
    } catch(e) {
        // Ignorar errores de aborto
        if (e.name === 'AbortError') {
            return;
        }

        // Verificar si esta petición sigue siendo la más reciente antes de mostrar error
        if (requestId !== currentRequestId) {
            return;
        }

        body.innerHTML = `<tr><td colspan="14" class="px-3 py-6">
            <div class="max-w-xl mx-auto  text-red-700 rounded-md p-4 text-sm text-center">
                <div class="font-semibold mb-1">No se pudo cargar el detalle</div>
                <div>Por favor verifica tu conexión e inténtalo de nuevo.</div>
            </div>
        </td></tr>`;
        meta && (meta.textContent = '');
    }
}

// Exponer para que la vista principal lo invoque al seleccionar una fila
window.loadReqProgramaTejidoLines = loadReqProgramaTejidoLines;
</script>

