/** Botón "Crear Órdenes": validar, pedir la fecha de requerimiento y POST crear-ordenes. */
import { qsa } from '../../../utils/dom.ts';
import { HttpError, http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirExito, icono, mensajeError } from '../../urdido/comun/pagina.ts';
import type { RespuestaApi } from '../../urdido/comun/pagina.ts';
import { pedirFechaRequerimiento } from '../comun/modal-fecha-requerimiento.ts';
import { cache, filaActual, estado, rutas } from './estado.ts';
import { enfocarCampo, leerDatosEngomado } from './engomado.ts';
import { marcarDestinoPendiente } from './grupos.ts';
import { armarPayload, validarCreacion } from './logica.ts';
import type { Aviso, FilaConstruccion } from './logica.ts';
import { actualizarBotonCrear, marcados } from './materiales.ts';

interface RespuestaCrear extends RespuestaApi {
    data?: { folio?: string; folioConsumo?: string };
}

const selectDestinoActual = (): HTMLSelectElement | null =>
    estado.filaSeleccionadaId
        ? (document.getElementById(estado.filaSeleccionadaId)?.querySelector<HTMLSelectElement>('[data-destino-select="true"]') ?? null)
        : null;

/** Filas de la Tabla 4 (No. Julios, Hilos, Observaciones). */
function leerConstruccionDom(): Partial<FilaConstruccion>[] {
    return qsa<HTMLTableRowElement>('#tbodyConstruccionUrdido tr').map((tr) => {
        const [julios, hilos, observaciones] = qsa<HTMLInputElement>('input', tr).map((i) => i.value);
        return { julios: julios ?? '', hilos: hilos ?? '', observaciones: observaciones ?? '' };
    });
}

function enfocar(foco: Aviso['foco']): void {
    if (!foco) return;
    if (foco.tipo === 'destino') {
        const select = selectDestinoActual();
        select?.focus();
        marcarDestinoPendiente(select);
    } else if (foco.tipo === 'julios') {
        const tr = qsa<HTMLTableRowElement>('#tbodyConstruccionUrdido tr')[foco.fila];
        tr?.querySelector<HTMLInputElement>('input')?.focus();
    } else {
        enfocarCampo(foco.campo);
    }
}

export async function crearOrdenes(boton: HTMLElement | null): Promise<void> {
    const actual = filaActual();
    const resultado = validarCreacion({
        estado: actual
            ? {
                  grupo: actual.grupo,
                  bomId: actual.bomId,
                  destinoSeleccionado: actual.destinoSeleccionado,
                  requiereDestinoManual: actual.requiereDestinoManual,
                  materialesEngomado: actual.materialesEngomado || [],
              }
            : null,
        destinoSelect: selectDestinoActual()?.value || '',
        marcados: marcados(),
        construccion: leerConstruccionDom(),
        engomado: leerDatosEngomado(),
    });

    if (!resultado.ok) {
        await notify.alert(resultado.aviso.texto, resultado.aviso.titulo, 'warning');
        enfocar(resultado.aviso.foco);
        return;
    }
    if (actual) actual.destinoSeleccionado = resultado.destino;

    const fecha = await pedirFechaRequerimiento();
    if (!fecha) return;

    const contenido = boton ? [...boton.childNodes] : [];
    if (boton instanceof HTMLButtonElement) boton.disabled = true;
    boton?.setAttribute('aria-busy', 'true');
    boton?.replaceChildren(icono('fa fa-spinner fa-spin'));

    try {
        const r = exigirExito(
            await http.post<RespuestaCrear>(rutas().crearOrdenes, armarPayload(resultado.datos, fecha)),
            'Error desconocido',
        );
        cache.limpiar();
        await notify.alert(
            `Folio: ${r.data?.folio ?? ''} · Folio Consumo: ${r.data?.folioConsumo ?? ''}`,
            '¡Órdenes creadas exitosamente!',
            'success',
        );
        window.location.href = rutas().despues;
    } catch (err) {
        if (err instanceof HttpError && err.status === 422 && err.errors) {
            void notify.validation(err.errors, 'Error al crear órdenes');
        } else {
            void notify.alert(mensajeError(err, 'Ocurrió un error inesperado'), 'Error al crear órdenes', 'error');
        }
    } finally {
        boton?.replaceChildren(...contenido);
        boton?.removeAttribute('aria-busy');
        actualizarBotonCrear();
    }
}
