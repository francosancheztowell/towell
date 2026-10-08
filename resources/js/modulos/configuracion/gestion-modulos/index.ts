/**
 * Gestión de módulos (resources/views/modulos/gestion-modulos/index.blade.php).
 * Árbol de módulos a la izquierda; a la derecha el módulo elegido y los permisos de todos
 * los usuarios en él, editables uno por uno o en grupo (ModuloPermisosController).
 */
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { debounce } from '../../../utils/format.ts';
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import {
    ancestros,
    calcularOrden,
    CAMPOS,
    contarPermisos,
    filtrarUsuarios,
    normalizar,
    opcionesDependencia,
    type Campo,
    type Filtro,
    type FiltroAcceso,
    type ModuloDato,
    type UsuarioPermisos,
} from './logica.ts';

const ETIQUETAS: Record<Campo, string> = {
    acceso: 'Acceso', crear: 'Crear', modificar: 'Modificar', eliminar: 'Eliminar', registrar: 'Registrar',
};
// SYSRoles guarda "registrar" con el typo de la tabla: reigstrar.
const FLAG_MODULO: Record<Campo, string> = {
    acceso: 'acceso', crear: 'crear', modificar: 'modificar', eliminar: 'eliminar', registrar: 'reigstrar',
};
const NIVEL_CLASES: Record<string, string> = {
    '1': 'bg-blue-100 text-blue-800',
    '2': 'bg-green-100 text-green-800',
    '3': 'bg-amber-100 text-amber-800',
};

onReady(() => {
    const encontrado = document.getElementById('gestion-modulos');
    if (!encontrado) return;
    const raiz: HTMLElement = encontrado;

    const url = (clave: string, id: string): string => (raiz.dataset[clave] ?? '').replace('__ID__', encodeURIComponent(id));
    const puedeEditar = raiz.dataset.puedeEditarPermisos === '1';
    const totalUsuarios = Number(raiz.dataset.totalUsuarios ?? 0);

    const nodos = qsa<HTMLButtonElement>('[data-modulo-nodo]', raiz);
    const modulos: ModuloDato[] = nodos.map((n) => ({
        key: n.dataset.key ?? '',
        orden: n.dataset.orden ?? '',
        modulo: n.dataset.modulo ?? '',
        nivel: n.dataset.nivel ?? '',
        dependencia: n.dataset.dependencia ?? '',
    }));

    const panelVacio = qs('[data-panel-vacio]', raiz)!;
    const panelDetalle = qs('[data-panel-detalle]', raiz)!;
    const cuerpo = qs<HTMLTableSectionElement>('[data-usuarios-cuerpo]', raiz)!;
    const scroll = qs('[data-usuarios-scroll]', raiz)!;
    const selTodos = qs<HTMLInputElement>('[data-sel-todos]', raiz)!;
    const lote = qs('[data-lote]', raiz)!;
    const filtroTexto = qs<HTMLInputElement>('[data-filtro-texto]', raiz)!;
    const filtroArea = qs<HTMLSelectElement>('[data-filtro-area]', raiz)!;

    let nodoActual: HTMLButtonElement | null = null;
    let usuarios: UsuarioPermisos[] = [];
    let visibles: UsuarioPermisos[] = [];
    const seleccion = new Set<number>();
    // Arranca en "Con acceso": lo habitual es revisar o quitar a quienes ya entran.
    const filtro: Filtro = { texto: '', area: '', acceso: 'con' };
    let peticion = 0;
    let ocupado = false;

    // ── Árbol ──────────────────────────────────────────────────────────────

    const hijosDe = (item: Element): HTMLElement | null =>
        item.querySelector<HTMLElement>(':scope > [data-modulo-hijos]');

    function plegar(item: Element, abierto: boolean): void {
        const ul = hijosDe(item);
        if (!ul) return;
        ul.classList.toggle('hidden', !abierto);
        item.querySelector(':scope > div > [data-modulo-plegar]')?.setAttribute('aria-expanded', String(abierto));
    }

    delegate(raiz, 'click', '[data-modulo-plegar]', (_e, boton) => {
        const item = boton.closest('[data-modulo-item]')!;
        plegar(item, boton.getAttribute('aria-expanded') !== 'true');
    });

    const botonTodo = qs<HTMLButtonElement>('[data-arbol-plegar-todo]', raiz);
    botonTodo?.addEventListener('click', () => {
        const abrir = botonTodo.getAttribute('aria-pressed') !== 'true';
        botonTodo.setAttribute('aria-pressed', String(abrir));
        botonTodo.title = abrir ? 'Contraer todo' : 'Expandir todo';
        qsa('[data-modulo-item]', raiz).forEach((item) => {
            // Contraer deja abiertos los de nivel 1, que es como carga la página.
            const nivel = item.querySelector<HTMLElement>(':scope > div > [data-modulo-nodo]')?.dataset.nivel;
            plegar(item, abrir || nivel === '1');
        });
    });

    const buscador = qs<HTMLInputElement>('[data-arbol-buscar]', raiz);
    const sinResultados = qs('[data-arbol-sin-resultados]', raiz);
    buscador?.addEventListener('input', debounce(() => {
        const q = normalizar(buscador.value);
        const arbol = qs('[data-arbol]', raiz);
        if (!arbol) return;

        // Un módulo se ve si coincide él o algún descendiente; con búsqueda, se abre el camino.
        const visita = (item: HTMLElement): boolean => {
            const nodo = item.querySelector<HTMLElement>(':scope > div > [data-modulo-nodo]');
            const propio = !q || normalizar(`${nodo?.dataset.modulo ?? ''} ${nodo?.dataset.orden ?? ''}`).includes(q);
            const ul = hijosDe(item);
            let hijo = false;
            ul?.querySelectorAll<HTMLElement>(':scope > [data-modulo-item]').forEach((h) => { hijo = visita(h) || hijo; });
            item.hidden = !(propio || hijo);
            if (q && ul) plegar(item, hijo);
            return propio || hijo;
        };
        let alguno = false;
        arbol.querySelectorAll<HTMLElement>(':scope > [data-modulo-item]').forEach((i) => { alguno = visita(i) || alguno; });
        sinResultados?.classList.toggle('hidden', alguno);
    }, 150));

    function abrirCamino(nodo: HTMLElement): void {
        let item = nodo.closest('[data-modulo-item]')?.parentElement?.closest('[data-modulo-item]');
        while (item) {
            plegar(item, true);
            item = item.parentElement?.closest('[data-modulo-item]');
        }
    }

    function actualizarConteoArbol(nodo: HTMLElement, n: number): void {
        nodo.dataset.conAcceso = String(n);
        const el = nodo.querySelector<HTMLElement>('[data-modulo-conteo]');
        if (!el) return;
        el.textContent = String(n);
        el.title = `${n} ${n === 1 ? 'usuario' : 'usuarios'} con acceso`;
        el.classList.toggle('bg-slate-200/70', n > 0);
        el.classList.toggle('text-slate-700', n > 0);
        el.classList.toggle('text-slate-400', n === 0);
    }

    delegate<HTMLButtonElement>(raiz, 'click', '[data-modulo-nodo]', (_e, nodo) => seleccionar(nodo));

    // ── Panel del módulo ───────────────────────────────────────────────────

    function datoDe(nodo: HTMLElement): ModuloDato {
        return modulos.find((m) => m.key === nodo.dataset.key)!;
    }

    function seleccionar(nodo: HTMLButtonElement, opciones: { historial?: boolean } = {}): void {
        nodoActual?.setAttribute('aria-current', 'false');
        nodoActual = nodo;
        nodo.setAttribute('aria-current', 'true');
        panelVacio.classList.add('hidden');
        panelDetalle.classList.remove('hidden');
        panelDetalle.classList.add('flex');

        const dato = datoDe(nodo);
        qs('[data-det-padres]', raiz)!.textContent = ancestros(modulos, dato).join(' › ') || 'Módulo principal';
        qs('[data-det-nombre]', raiz)!.textContent = dato.modulo;
        qs('[data-det-orden]', raiz)!.textContent = dato.orden;
        const nivel = qs('[data-det-nivel]', raiz)!;
        nivel.textContent = `Nivel ${dato.nivel}`;
        nivel.className = `rounded-full px-2 py-0.5 text-caption font-medium ${NIVEL_CLASES[dato.nivel] ?? NIVEL_CLASES['3']}`;
        const ruta = qs('[data-det-ruta]', raiz)!;
        const r = nodo.dataset.ruta ?? '';
        ruta.textContent = r || 'Sin ruta: los permisos se buscan por nombre';
        ruta.classList.toggle('text-amber-700', !r);

        // Columnas de permisos que el módulo no declara en SYSRoles: se atenúan, no se ocultan.
        for (const c of CAMPOS) {
            const th = qs<HTMLElement>(`[data-col="${c}"]`, raiz)!;
            const usa = nodo.dataset[FLAG_MODULO[c]] === '1';
            th.classList.toggle('text-slate-400', !usa);
            th.title = usa ? '' : `Este módulo no usa el permiso ${ETIQUETAS[c]}`;
        }

        if (opciones.historial !== false) {
            const u = new URL(window.location.href);
            u.searchParams.set('modulo', dato.key);
            window.history.replaceState(null, '', u);
        }

        seleccion.clear();
        void cargarUsuarios();
    }

    async function cargarUsuarios(): Promise<void> {
        if (!nodoActual) return;
        const id = nodoActual.dataset.key ?? '';
        const mia = ++peticion;
        pintarEsqueleto();
        try {
            const res = await http.get<{ usuarios: UsuarioPermisos[] }>(url('urlPermisos', id));
            if (mia !== peticion) return; // llegó tarde: el usuario ya eligió otro módulo
            usuarios = res.usuarios;
            llenarAreas();
            pintar();
        } catch {
            if (mia !== peticion) return;
            pintarEstado('No se pudieron cargar los permisos de este módulo.', 'Reintentar', () => void cargarUsuarios());
        }
    }

    const molde = (sel: string): HTMLTemplateElement => qs<HTMLTemplateElement>(sel, raiz)!;
    const tplFila = molde('[data-tpl-fila]');
    const tplEsqueleto = molde('[data-tpl-esqueleto]');
    const tplEstado = molde('[data-tpl-estado]');
    const clonar = (tpl: HTMLTemplateElement): HTMLTableRowElement =>
        tpl.content.firstElementChild!.cloneNode(true) as HTMLTableRowElement;

    /** Una fila con un mensaje (error, vacío) y, si se da, un botón de acción. */
    function pintarEstado(texto: string, boton?: string, alHacerClic?: () => void): void {
        const fila = clonar(tplEstado);
        fila.querySelector('[data-estado-texto]')!.textContent = texto;
        const b = fila.querySelector<HTMLButtonElement>('[data-estado-boton]')!;
        if (boton && alHacerClic) {
            b.textContent = boton;
            b.addEventListener('click', alHacerClic);
        } else {
            b.remove();
        }
        cuerpo.replaceChildren(fila);
    }

    function pintarEsqueleto(): void {
        cuerpo.replaceChildren(...Array.from({ length: 8 }, () => clonar(tplEsqueleto)));
        qsa('[data-col-conteo]', raiz).forEach((el) => { el.textContent = '—'; });
        actualizarLote();
    }

    function llenarAreas(): void {
        const previa = filtroArea.value;
        const areas = [...new Set(usuarios.map((u) => u.area).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
        filtroArea.replaceChildren(new Option('Todas las áreas', ''), ...areas.map((a) => new Option(a, a)));
        filtroArea.value = areas.includes(previa) ? previa : '';
        filtro.area = filtroArea.value;
    }

    function filaUsuario(u: UsuarioPermisos): HTMLTableRowElement {
        const fila = clonar(tplFila);
        const marcado = seleccion.has(u.id);
        fila.dataset.id = String(u.id);
        fila.dataset.marcado = String(marcado);
        fila.querySelector('[data-f-sel-texto]')!.textContent = `Seleccionar a ${u.nombre}`;
        const caja = fila.querySelector<HTMLInputElement>('[data-sel]')!;
        caja.value = String(u.id);
        caja.checked = marcado;

        const nombre = fila.querySelector<HTMLElement>('[data-f-nombre]')!;
        nombre.textContent = u.nombre;
        nombre.toggleAttribute('data-sin-acceso', !u.acceso);
        fila.querySelector('[data-f-detalle]')!.textContent = [u.numero ? `#${u.numero}` : '', u.puesto].filter(Boolean).join(' · ');
        fila.querySelector('[data-f-area]')!.textContent = u.area || '—';

        for (const c of CAMPOS) {
            const celda = fila.querySelector<HTMLElement>(`[data-permiso="${c}"], [data-permiso-ver="${c}"]`)!;
            const activo = u[c];
            celda.dataset.activo = String(activo);
            if (celda.tagName === 'BUTTON') {
                celda.setAttribute('aria-pressed', String(activo));
                celda.setAttribute('aria-label', `${ETIQUETAS[c]} · ${u.nombre}`);
            } else {
                celda.setAttribute('aria-label', `${ETIQUETAS[c]} · ${u.nombre}: ${activo ? 'sí' : 'no'}`);
            }
        }
        return fila;
    }

    function pintar(): void {
        visibles = filtrarUsuarios(usuarios, filtro);

        const conteo = contarPermisos(usuarios);
        for (const c of CAMPOS) {
            const el = qs(`[data-col-conteo="${c}"]`, raiz);
            if (el) el.textContent = String(conteo[c]);
        }
        const conteos: Record<FiltroAcceso, number> = { todos: usuarios.length, con: conteo.acceso, sin: usuarios.length - conteo.acceso };
        qsa<HTMLElement>('[data-filtro-conteo]', raiz).forEach((el) => {
            el.textContent = String(conteos[el.dataset.filtroConteo as FiltroAcceso] ?? '');
        });
        if (nodoActual) actualizarConteoArbol(nodoActual, conteo.acceso);

        if (visibles.length === 0) {
            const hayFiltros = Boolean(filtro.texto || filtro.area || filtro.acceso !== 'todos');
            if (filtro.acceso === 'con' && !filtro.texto && !filtro.area) pintarEstado('Nadie tiene acceso a este módulo todavía.', 'Ver todos los usuarios', limpiarFiltros);
            else if (hayFiltros) pintarEstado('Ningún usuario coincide con los filtros.', 'Limpiar filtros', limpiarFiltros);
            else pintarEstado('No hay usuarios registrados.');
            actualizarLote();
            return;
        }

        cuerpo.replaceChildren(...visibles.map(filaUsuario));
        actualizarLote();
    }

    // ── Filtros ────────────────────────────────────────────────────────────

    filtroTexto.addEventListener('input', debounce(() => { filtro.texto = filtroTexto.value; pintar(); }, 150));
    filtroArea.addEventListener('change', () => { filtro.area = filtroArea.value; pintar(); });
    delegate<HTMLInputElement>(raiz, 'change', 'input[name="filtro-acceso"]', (_e, radio) => {
        filtro.acceso = radio.value as FiltroAcceso;
        pintar();
    });
    function limpiarFiltros(): void {
        filtro.texto = ''; filtro.area = ''; filtro.acceso = 'todos';
        filtroTexto.value = ''; filtroArea.value = '';
        qs<HTMLInputElement>('input[name="filtro-acceso"][value="todos"]', raiz)!.checked = true;
        pintar();
    }

    // ── Selección y acciones en grupo ──────────────────────────────────────

    function actualizarLote(): void {
        const n = seleccion.size;
        lote.hidden = n === 0 || !puedeEditar;
        scroll.classList.toggle('pb-28', !lote.hidden);
        const texto = qs('[data-lote-conteo]', raiz);
        if (texto) texto.textContent = n === 1 ? '1 seleccionado' : `${n} seleccionados`;

        const marcadosVisibles = visibles.filter((u) => seleccion.has(u.id)).length;
        selTodos.checked = visibles.length > 0 && marcadosVisibles === visibles.length;
        selTodos.indeterminate = marcadosVisibles > 0 && marcadosVisibles < visibles.length;
    }

    delegate<HTMLInputElement>(raiz, 'change', '[data-sel]', (_e, caja) => {
        const id = Number(caja.value);
        if (caja.checked) seleccion.add(id); else seleccion.delete(id);
        const fila = caja.closest('tr');
        if (fila) fila.dataset.marcado = String(caja.checked);
        actualizarLote();
    });

    selTodos.addEventListener('change', () => {
        for (const u of visibles) {
            if (selTodos.checked) seleccion.add(u.id); else seleccion.delete(u.id);
        }
        pintar();
    });

    async function aplicar(ids: number[], permisos: Partial<Record<Campo, boolean>>): Promise<boolean> {
        if (!nodoActual || ids.length === 0) return false;
        const res = await http.put<{ message: string }>(url('urlPermisosUpdate', nodoActual.dataset.key ?? ''), { usuarios: ids, permisos });
        const afectados = new Set(ids);
        for (const u of usuarios) if (afectados.has(u.id)) Object.assign(u, permisos);
        if (ids.length > 1) notify.success(res.message);
        return true;
    }

    function avisarError(err: unknown): void {
        if (err instanceof HttpError && (err.status === 419 || err.status === 401)) return; // sesion.ts ya avisa
        if (err instanceof HttpError && err.status === 403) {
            notify.error('No tienes permiso para cambiar permisos de usuarios.');
            return;
        }
        notify.error('No se pudo guardar el cambio. Intenta de nuevo.');
    }

    // Un permiso de un usuario: se pinta al momento y se revierte si el servidor falla.
    delegate<HTMLButtonElement>(raiz, 'click', '[data-permiso]', async (_e, boton) => {
        const fila = boton.closest<HTMLElement>('tr[data-id]');
        const u = usuarios.find((x) => x.id === Number(fila?.dataset.id));
        const campo = boton.dataset.permiso as Campo;
        if (!u || !campo) return;

        const valor = !u[campo];
        const enfocar = () => raiz.querySelector<HTMLButtonElement>(`tr[data-id="${u.id}"] [data-permiso="${campo}"]`)?.focus();
        u[campo] = valor;
        pintar();
        enfocar();
        try {
            await aplicar([u.id], { [campo]: valor });
        } catch (err) {
            u[campo] = !valor;
            avisarError(err);
            pintar();
            enfocar();
        }
    });

    delegate<HTMLButtonElement>(raiz, 'click', '[data-lote-accion]', async (_e, boton) => {
        const accion = boton.dataset.loteAccion;
        if (accion === 'limpiar') { seleccion.clear(); pintar(); return; }
        if (ocupado || seleccion.size === 0) return;

        const ids = [...seleccion];
        const quienes = ids.length === 1 ? '1 usuario' : `${ids.length} usuarios`;
        const campo = qs<HTMLSelectElement>('[data-lote-permiso]', raiz)!.value as Campo;
        let permisos: Partial<Record<Campo, boolean>>;

        if (accion === 'quitar-todo') {
            const ok = await notify.confirm({
                title: '¿Quitar del módulo?',
                text: `${quienes} perderán todos sus permisos en "${nodoActual?.dataset.modulo ?? ''}". Puedes volver a dárselos después.`,
                icon: 'warning',
                confirmText: 'Sí, quitar',
                cancelText: 'Cancelar',
                confirmColor: '#dc2626',
            });
            if (!ok) return;
            permisos = Object.fromEntries(CAMPOS.map((c) => [c, false]));
        } else if (accion === 'dar-acceso') {
            permisos = { acceso: true };
        } else {
            permisos = { [campo]: accion === 'activar' };
        }

        ocupado = true;
        qsa<HTMLButtonElement>('[data-lote-accion]', raiz).forEach((b) => { b.disabled = true; });
        try {
            if (await aplicar(ids, permisos)) seleccion.clear();
        } catch (err) {
            avisarError(err);
        } finally {
            ocupado = false;
            qsa<HTMLButtonElement>('[data-lote-accion]', raiz).forEach((b) => { b.disabled = false; });
            pintar();
        }
    });

    // ── Acciones del módulo: editar, sincronizar, eliminar ─────────────────

    const dialogo = (id: string) => document.getElementById(id) as HTMLDialogElement | null;
    delegate(document, 'click', '[data-abrir-dialogo]', (_e, b) => dialogo(b.dataset.abrirDialogo ?? '')?.showModal());

    function poblarDependencias(prefijo: 'create' | 'edit', nivel: string): void {
        const select = document.getElementById(`${prefijo}Dependencia`) as HTMLSelectElement;
        const ayuda = document.getElementById(`${prefijo}DependenciaHelp`)!;
        const opciones = opcionesDependencia(modulos, nivel);
        select.replaceChildren(new Option('Seleccionar dependencia', ''), ...opciones.map((o) => new Option(o.label, o.value)));
        select.disabled = opciones.length === 0;
        ayuda.textContent = nivel === '1'
            ? 'Los módulos de Nivel 1 no tienen dependencia'
            : nivel === '2' ? 'Selecciona el módulo principal (Nivel 1)'
            : nivel === '3' ? 'Selecciona el submódulo (Nivel 2) donde agregar este elemento'
            : 'Selecciona primero el nivel';
    }

    for (const prefijo of ['create', 'edit'] as const) {
        const nivel = document.getElementById(`${prefijo}Nivel`) as HTMLSelectElement | null;
        const dep = document.getElementById(`${prefijo}Dependencia`) as HTMLSelectElement | null;
        const orden = document.getElementById(`${prefijo}Orden`) as HTMLInputElement | null;
        if (!nivel || !dep || !orden) continue;
        nivel.addEventListener('change', () => {
            poblarDependencias(prefijo, nivel.value);
            orden.value = nivel.value === '1' ? calcularOrden(modulos, '1', '') : '';
        });
        dep.addEventListener('change', () => { orden.value = calcularOrden(modulos, nivel.value, dep.value); });
    }

    delegate(raiz, 'click', '[data-accion="editar"]', () => {
        if (!nodoActual) return;
        const d = nodoActual.dataset;
        (document.getElementById('editForm') as HTMLFormElement).action = url('urlUpdate', d.key ?? '');
        (document.getElementById('editOrden') as HTMLInputElement).value = d.orden ?? '';
        (document.getElementById('editModulo') as HTMLInputElement).value = d.modulo ?? '';
        (document.getElementById('editNivel') as HTMLSelectElement).value = d.nivel ?? '1';
        poblarDependencias('edit', d.nivel ?? '1');
        (document.getElementById('editDependencia') as HTMLSelectElement).value = d.dependencia ?? '';
        (document.getElementById('editRuta') as HTMLInputElement).value = d.ruta ?? '';
        for (const c of CAMPOS) {
            const flag = FLAG_MODULO[c];
            const caja = document.getElementById(`edit_${flag}`) as HTMLInputElement | null;
            if (caja) caja.checked = d[flag] === '1';
        }
        dialogo('editModal')?.showModal();
    });

    delegate(raiz, 'click', '[data-accion="sincronizar"]', async () => {
        if (!nodoActual) return;
        const ok = await notify.confirm({
            title: '¿Sincronizar permisos?',
            text: 'Se crea la fila de este módulo a los usuarios que todavía no la tienen.',
            icon: 'question',
            confirmText: 'Sí, sincronizar',
            cancelText: 'Cancelar',
        });
        if (!ok) return;
        try {
            const res = await http.post<{ success: boolean; message: string }>(url('urlSync', nodoActual.dataset.key ?? ''));
            notify.success(res.message);
            void cargarUsuarios();
        } catch (err) {
            if (err instanceof HttpError && err.status !== 419 && err.status !== 401) {
                notify.error((err.data as { message?: string } | null)?.message ?? 'No se pudieron sincronizar los permisos');
            }
        }
    });

    delegate(raiz, 'click', '[data-accion="eliminar"]', async () => {
        if (!nodoActual) return;
        const ok = await notify.confirm({
            title: '¿Eliminar módulo?',
            text: `Se elimina "${nodoActual.dataset.modulo ?? ''}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar',
            confirmColor: '#dc2626',
        });
        if (!ok) return;
        const form = document.getElementById('globalDeleteForm') as HTMLFormElement;
        form.action = url('urlDestroy', nodoActual.dataset.key ?? '');
        form.submit();
    });

    // ── Estado inicial: ?modulo=<idrol> vuelve a abrir el módulo ───────────

    const inicial = new URL(window.location.href).searchParams.get('modulo');
    const nodoInicial = nodos.find((n) => n.dataset.key === inicial);
    if (nodoInicial) {
        abrirCamino(nodoInicial);
        seleccionar(nodoInicial, { historial: false });
        nodoInicial.scrollIntoView({ block: 'center' });
    }
    if (totalUsuarios === 0) selTodos.disabled = true;
});
