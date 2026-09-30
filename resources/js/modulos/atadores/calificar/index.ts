/**
 * Calificar atadores (/atadores/calificar): resumen del atado, checklist Jacquard/SMIT, Montado/Enhebrado
 * de Karl Mayer, devolución y las tres acciones del navbar (Terminar, Califica Tejedor, Autoriza Supervisor).
 * Antes eran 1 620 líneas inline en la vista (19-03). Todo guarda en POST /atadores/save con `action`.
 */
import { delegate } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { pedirFormulario } from '../comun/modal-formulario.ts';
import { exigirOk, leerPagina, mensajeError, mostrarUnRato, valorDe, type RespuestaAtadores } from '../comun/pagina.ts';
import { iniciarProcesoKm } from '../comun/proceso-km.ts';
import { iniciarDevolucion } from './devolucion.ts';
import { evaluarMermaTecleada, MERMA_MAX, AVISO_MERMA_RANGO, mermaParaGuardar, puedeDesmarcar, revisarParaTerminar, textoMerma, type Aviso } from './logica.ts';
import type { ConfigCalificar } from './tipos.ts';

const pagina = leerPagina<ConfigCalificar>('calificar-atado');

if (pagina) iniciar(pagina.raiz, pagina.datos);

function iniciar(raiz: HTMLElement, cfg: ConfigCalificar): void {
    if (cfg.km) iniciarProcesoKm(raiz, cfg.km);
    const atado = cfg.atado;
    if (!atado) return;
    iniciarDevolucion(cfg);

    const $ = <T extends HTMLElement = HTMLElement>(id: string): T | null => document.getElementById(id) as T | null;
    const avisar = (a: Aviso): void => {
        void notify.alert(a.texto, a.titulo, 'warning');
        if (a.foco) $(a.foco)?.focus();
    };
    const error = (err: unknown, porDefecto: string, titulo = 'Error'): void => {
        void notify.alert(mensajeError(err, porDefecto), titulo, 'error');
    };
    const guardar = async (accion: string, datos: Record<string, unknown> = {}, porDefecto = 'No se pudo guardar'): Promise<RespuestaAtadores> =>
        exigirOk(
            await http.post<RespuestaAtadores>(cfg.rutas.guardar, { action: accion, no_julio: atado.noJulio, no_orden: atado.noOrden, ...datos }),
            porDefecto,
        );
    const habilitar = (id: string, si: boolean): void => {
        const b = $<HTMLButtonElement>(id);
        if (!b) return;
        b.disabled = !si;
        b.classList.toggle('opacity-50', !si);
        b.classList.toggle('cursor-not-allowed', !si);
    };

    // ----- Observaciones: autoguardado a los 2 s -----
    const observaciones = $<HTMLTextAreaElement>('observaciones');
    let tObs: ReturnType<typeof setTimeout> | undefined;
    const guardarObservaciones = async (): Promise<void> => {
        const guardando = $('autoSaveIndicator');
        try {
            await guardar('observaciones', { observaciones: observaciones?.value ?? '' });
            guardando?.classList.add('hidden');
            mostrarUnRato($('savedIndicator'));
        } catch {
            guardando?.classList.add('hidden');
        }
    };
    observaciones?.addEventListener('input', () => {
        $('autoSaveIndicator')?.classList.remove('hidden');
        $('savedIndicator')?.classList.add('hidden');
        clearTimeout(tObs);
        tObs = setTimeout(() => void guardarObservaciones(), 2000);
    });
    $<HTMLFormElement>('formObservaciones')?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (atado.soloLectura) {
            void notify.alert('Este registro está autorizado y no se pueden realizar modificaciones', 'Solo Lectura', 'info');
            return;
        }
        guardar('observaciones', { observaciones: observaciones?.value ?? '' }, 'No se pudieron guardar las observaciones')
            .then(() => notify.success('Observaciones guardadas'))
            .catch((err) => error(err, 'No se pudieron guardar las observaciones'));
    });

    // ----- Merma Kg: autoguardado a los 1.5 s, tope de 5 kg -----
    const merma = $<HTMLInputElement>('mergaKg');
    let tMerma: ReturnType<typeof setTimeout> | undefined;
    const guardarMerma = async (textoValor: string): Promise<void> => {
        if (atado.soloLectura) return;
        const r = mermaParaGuardar(textoValor);
        if ('aviso' in r) {
            avisar(r.aviso);
            return;
        }
        try {
            await guardar('merga', { mergaKg: r.valor }, 'No se pudo guardar la merma');
            if (merma) {
                merma.value = textoMerma(r.valor);
                merma.classList.add('border-green-500', 'bg-green-50');
                setTimeout(() => merma.classList.remove('border-green-500', 'bg-green-50'), 2000);
            }
            mostrarUnRato($('mergaSavedIndicator'));
        } catch (err) {
            error(err, 'No se pudo conectar con el servidor. Verifica tu conexión.', 'Error al guardar');
        }
    };
    merma?.addEventListener('input', () => {
        $('mergaSavedIndicator')?.classList.add('hidden');
        clearTimeout(tMerma);
        const accion = evaluarMermaTecleada(merma.value);
        if (accion === 'excede') {
            merma.value = String(MERMA_MAX);
            avisar(AVISO_MERMA_RANGO);
            void guardarMerma(String(MERMA_MAX));
        } else if (accion === 'programar') {
            tMerma = setTimeout(() => void guardarMerma(merma.value), 1500);
        }
    });
    merma?.addEventListener('blur', () => {
        const n = parseFloat(merma.value.trim());
        if (merma.value.trim() !== '' && !Number.isNaN(n)) merma.value = textoMerma(n);
    });

    // ----- Folio Paro: autoguardado a los 1.2 s -----
    const folio = $<HTMLInputElement>('folioParo');
    let tFolio: ReturnType<typeof setTimeout> | undefined;
    folio?.addEventListener('input', () => {
        $('folioParoSavedIndicator')?.classList.add('hidden');
        clearTimeout(tFolio);
        tFolio = setTimeout(async () => {
            if (atado.soloLectura) return;
            const valor = folio.value.trim();
            try {
                const res = await guardar('folio_paro', { folio_paro: valor }, 'No se pudo guardar Folio Paro');
                folio.value = typeof res.folio_paro === 'string' ? res.folio_paro : valor;
                mostrarUnRato($('folioParoSavedIndicator'));
            } catch (err) {
                error(err, 'No se pudo guardar Folio Paro');
            }
        }, 1200);
    });

    // ----- Checklist (Jacquard / SMIT) -----
    delegate<HTMLInputElement>(raiz, 'change', 'input[data-maquina]', async (_ev, check) => {
        try {
            await guardar('maquina_estado', { maquinaId: check.dataset.maquina, estado: check.checked }, 'No se pudo actualizar máquina');
        } catch (err) {
            check.checked = !check.checked;
            error(err, 'No se pudo actualizar máquina');
        }
    });

    delegate<HTMLInputElement>(raiz, 'change', 'input[data-actividad]', async (_ev, check) => {
        const marcado = check.checked;
        if (atado.soloLectura) {
            check.checked = !marcado;
            return;
        }
        const celda = check.closest('tr')?.querySelector('.operador-cell');
        if (!marcado && !puedeDesmarcar(celda?.textContent ?? '', cfg.usuario?.cve)) {
            check.checked = true;
            avisar({ titulo: 'No permitido', texto: 'No puedes desmarcar una actividad realizada por otro usuario. Por favor, consúltalo con tu supervisor.' });
            return;
        }
        try {
            const res = await guardar('actividad_estado', { actividadId: check.dataset.actividad, estado: marcado }, 'No se pudo actualizar actividad');
            if (celda) {
                const yo = cfg.usuario ? `${cfg.usuario.cve} - ${cfg.usuario.nombre}` : '-';
                celda.textContent = marcado ? String(res.operador || yo).trim() : '-';
            }
        } catch (err) {
            check.checked = !marcado;
            error(err, 'No se pudo actualizar actividad');
        }
    });

    // ----- Acciones del navbar -----
    const bloquearCaptura = (): void => {
        document.querySelectorAll<HTMLInputElement>('input[type="checkbox"]').forEach((cb) => (cb.disabled = true));
        if (observaciones) observaciones.disabled = true;
        if (merma) merma.disabled = true;
    };

    const terminar = async (): Promise<void> => {
        const checks = (sel: string): boolean[] => [...raiz.querySelectorAll<HTMLInputElement>(sel)].map((c) => c.checked);
        const aviso = revisarParaTerminar({
            esKm: cfg.esKm,
            maquinas: checks('input[data-maquina]'),
            actividades: checks('input[data-actividad]'),
            devolucion: Boolean($<HTMLInputElement>('chkDevolucion')?.checked),
            filasKm: [...document.querySelectorAll<HTMLTableRowElement>('#devKmBody tr[data-julio]')].map((tr) => ({
                julio: tr.dataset.julio ?? '',
                metros: tr.querySelector<HTMLInputElement>('[data-campo="metros"]')?.value ?? '',
                kilos: tr.querySelector<HTMLInputElement>('[data-campo="kilos"]')?.value ?? '',
            })),
            dev: { julio: valorDe('dev_no_julio'), ubicacion: valorDe('dev_ubicacion'), metros: valorDe('dev_metros'), kilos: valorDe('dev_kilos') },
            merma: merma?.value ?? '',
        });
        if (aviso) {
            avisar(aviso);
            return;
        }
        const form = await pedirFormulario('modalTerminarAtado', 'formTerminarAtado');
        if (!form) return;
        try {
            await guardar('terminar', { comments_ata: String(form.get('comentarios') ?? '').trim() }, 'No se pudo terminar');
            notify.success('Atado terminado: el estatus cambió a "Terminado"');
            habilitar('btnTerminar', false);
            habilitar('btnCalificar', true);
            bloquearCaptura();
        } catch (err) {
            error(err, 'No se pudo terminar');
        }
    };

    const calificar = async (): Promise<void> => {
        const form = await pedirFormulario('modalCalificarTejedor', 'formCalificarTejedor');
        if (!form) return;
        const calidad = String(form.get('calidad') ?? '');
        const limpieza = String(form.get('limpieza') ?? '');
        try {
            const res = await guardar('calificacion', {
                calidad: Number(calidad),
                limpieza: Number(limpieza),
                comments_tej: String(form.get('comentarios') ?? '').trim(),
            });
            const pintar = (id: string, valor: string, clase: string): void => {
                const el = $(id);
                if (!el) return;
                el.textContent = valor;
                el.className = clase;
            };
            pintar('valCalidad', calidad, 'px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold text-sm');
            pintar('valLimpieza', limpieza, 'px-2 py-1 bg-green-100 text-green-800 rounded font-semibold text-sm');
            const tejedor = res.tejedor as { cve?: string; nombre?: string } | undefined;
            if (tejedor && cfg.usuario) {
                const cve = $('valCveTejedor');
                const nom = $('valNomTejedor');
                if (cve) cve.textContent = tejedor.cve || cfg.usuario.cve || '-';
                if (nom) nom.textContent = tejedor.nombre || cfg.usuario.nombre || '';
                $('tejedorDash')?.classList.remove('hidden');
            }
            habilitar('btnTerminar', false);
            habilitar('btnCalificar', false);
            habilitar('btnAutorizar', true);
            notify.success('Calificación guardada');
        } catch (err) {
            error(err, 'No se pudo guardar');
        }
    };

    const autorizar = async (): Promise<void> => {
        const form = await pedirFormulario('modalAutorizarSupervisor', 'formAutorizarSupervisor');
        if (!form) return;
        try {
            const res = await guardar('supervisor', { comments_sup: String(form.get('comentarios') ?? '').trim() }, 'No se pudo autorizar el proceso');
            const sup = res.supervisor as { cve?: string; nombre?: string } | undefined;
            if (sup) {
                const cve = $('valCveSupervisor');
                const nom = $('valNomSupervisor');
                if (cve) cve.textContent = sup.cve || '-';
                if (nom) nom.textContent = sup.nombre || '-';
            }
            notify.success('Proceso completado: el atado quedó autorizado y en el historial');
            const destino = typeof res.redirect === 'string' ? res.redirect : cfg.rutas.programa;
            setTimeout(() => window.location.assign(destino), 2100);
        } catch (err) {
            error(err, 'No se pudo autorizar el proceso');
        }
    };

    const acciones: Record<string, () => Promise<void>> = { terminar, calificar, autorizar };
    delegate<HTMLElement>(document, 'click', '[data-accion]', (_ev, boton) => {
        const accion = acciones[boton.dataset.accion ?? ''];
        if (accion && !(boton as HTMLButtonElement).disabled) void accion();
    });
}
