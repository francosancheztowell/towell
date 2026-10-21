/**
 * Lista de usuarios: filtros en cliente, modal "Nuevo Usuario" y confirmación al eliminar.
 * Vista: resources/views/modulos/usuarios/select.blade.php.
 * El alta es un POST normal del form (configuracion.usuarios.store sigue en la edición con flash).
 */
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { escapeHtml } from '../../../utils/format.ts';
import { notify } from '../../../utils/notifications.ts';

interface Filtros {
    numero_empleado: string;
    area: string;
    turno: string;
}

const SIN_FILTROS: Filtros = { numero_empleado: '', area: '', turno: '' };
const TURNOS = ['1', '2', '3', '4'];

let activos: Filtros = { ...SIN_FILTROS };

function opcion(valor: string, texto: string, actual: string): string {
    return `<option value="${escapeHtml(valor)}"${valor === actual ? ' selected' : ''}>${escapeHtml(texto)}</option>`;
}

function aplicar(filtros: Filtros, contenedor: HTMLElement, boton: HTMLElement | null): void {
    activos = filtros;
    let visibles = 0;
    qsa('.usuario-item', contenedor).forEach((item) => {
        const d = item.dataset;
        const mostrar = (d.numeroEmpleado ?? '').includes(filtros.numero_empleado)
            && (!filtros.area || d.area === filtros.area)
            && (!filtros.turno || d.turno === filtros.turno);
        item.style.display = mostrar ? '' : 'none';
        if (mostrar) visibles++;
    });

    if (boton) {
        const conFiltros = Boolean(filtros.numero_empleado || filtros.area || filtros.turno);
        let badge = qs('.badge-filtros', boton);
        if (conFiltros && !badge) {
            badge = document.createElement('span');
            badge.className = 'badge-filtros ml-2 px-2 py-0.5 text-xs font-bold bg-red-500 rounded-full text-white';
            badge.textContent = '!';
            boton.appendChild(badge);
        } else if (!conFiltros) {
            badge?.remove();
        }
    }

    let vacio = qs('.mensaje-no-resultados', contenedor);
    if (visibles > 0) {
        vacio?.remove();
    } else if (!vacio) {
        vacio = document.createElement('div');
        vacio.className = 'mensaje-no-resultados px-4 py-12 text-center';
        vacio.innerHTML = `
            <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron usuarios</h3>
            <p class="mt-1 text-sm text-gray-500">Intenta con otros filtros.</p>`;
        contenedor.appendChild(vacio);
    }
}

async function abrirFiltros(contenedor: HTMLElement, boton: HTMLElement): Promise<void> {
    const areas = [...new Set(qsa('.usuario-item', contenedor).map((i) => i.dataset.area ?? '').filter((a) => a.trim() !== ''))].sort();
    let filtros: Filtros = { ...SIN_FILTROS };
    const valor = await notify.dialog({
        titulo: 'Filtrar Usuarios',
        html: `
            <div class="text-left">
                <div class="mb-4">
                    <label for="filtro_numero_empleado" class="block text-sm font-medium text-gray-700 mb-1">Número de Empleado</label>
                    <input type="text" id="filtro_numero_empleado" placeholder="Ej: 2045"
                           value="${escapeHtml(activos.numero_empleado)}">
                </div>
                <div class="mb-4">
                    <label for="filtro_area" class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                    <select id="filtro_area" style="display: block; width: 100%; padding: 0.5rem;">
                        ${opcion('', 'Todas las áreas', activos.area)}${areas.map((a) => opcion(a, a, activos.area)).join('')}
                    </select>
                </div>
                <div class="mb-4">
                    <label for="filtro_turno" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                    <select id="filtro_turno" style="display: block; width: 100%; padding: 0.5rem;">
                        ${opcion('', 'Todos los turnos', activos.turno)}${TURNOS.map((t) => opcion(t, `Turno ${t}`, activos.turno)).join('')}
                    </select>
                </div>
            </div>`,
        tono: null,
        formulario: true,
        ancho: 'md',
        botones: [
            { texto: 'Limpiar', valor: 'limpiar', variante: 'secundario' },
            { texto: 'Aplicar Filtros', valor: 'aplicar', variante: 'primario', valida: true },
        ],
        validar: ({ raiz }) => ({
            numero_empleado: qs<HTMLInputElement>('#filtro_numero_empleado', raiz)?.value.trim() ?? '',
            area: qs<HTMLSelectElement>('#filtro_area', raiz)?.value ?? '',
            turno: qs<HTMLSelectElement>('#filtro_turno', raiz)?.value ?? '',
        }),
        alValidar: (datos) => {
            filtros = datos as Filtros;
        },
    });
    if (valor === 'aplicar') aplicar(filtros, contenedor, boton);
    else if (valor === 'limpiar') aplicar({ ...SIN_FILTROS }, contenedor, boton);
}

onReady(() => {
    const contenedor = qs('#usuarios-container');
    const modal = qs('#modalCrearUsuario');
    const form = qs<HTMLFormElement>('#formCrearUsuario');
    if (!contenedor) return;

    delegate(document, 'click', '[data-usuarios-filtrar]', (_e, boton) => void abrirFiltros(contenedor, boton));

    delegate(document, 'click', '[data-usuarios-crear]', () => modal?.classList.remove('hidden'));

    delegate(document, 'click', '[data-usuarios-cerrar-crear]', () => {
        modal?.classList.add('hidden');
        form?.reset();
    });

    delegate<HTMLFormElement, SubmitEvent>(contenedor, 'submit', 'form[data-confirmar-eliminar]', async (e, borrar) => {
        e.preventDefault();
        const ok = await notify.confirm({
            title: '¿Eliminar usuario?',
            icon: 'warning',
            confirmColor: '#dc2626',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar',
        });
        if (ok) borrar.submit();
    });
});
