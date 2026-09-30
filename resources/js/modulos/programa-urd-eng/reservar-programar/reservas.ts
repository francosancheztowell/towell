/**
 * Acciones de la barra: Programar (navega a Programación de requerimientos), Reservar piezas de
 * inventario para el telar y Liberar el telar. Mismos endpoints y payloads que antes de 19-05.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { loader } from '../../../componentes/loader.ts';
import { CLAVE_SESION_TELARES, urlConTelares } from '../comun/contrato-flujo.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { $, alertar, avisar, cfg, disable, state } from './estado.ts';
import {
    aplicarLiberado,
    aplicarReservaLocal,
    asRows,
    clonar,
    esMismoTelar,
    etiquetaTipo,
    isReservado,
    loteDePieza,
    normalizeTipo,
    payloadReserva,
    piezasReservables,
    telaresParaProgramar,
    validarReserva,
} from './logica.ts';
import { actualizarBotones, limpiarSeleccion, reseleccionarTelar } from './seleccion.ts';
import { reemplazarInventario } from './tabla-inventario.ts';
import { pintarTelares } from './tabla-telares.ts';
import type { RawRow } from './types.ts';

interface RespuestaFilas extends RespuestaApi {
    data?: RawRow[] | RawRow;
}

const getFilas = async (url: string): Promise<RawRow[]> =>
    asRows(exigirExito(await http.get<RespuestaFilas>(url), 'No se pudieron cargar los datos').data);

/** Inventario disponible (GET). */
export const cargarInventario = (): Promise<RawRow[]> => getFilas(cfg.api.inventarioDisponibleGet);

/* ---------- Programar ---------- */

export function programar(): void {
    if (!cfg.can.crear) {
        notify.warning('No tiene permiso para programar');
        return;
    }
    const base = state.telaresDataOriginal.length ? state.telaresDataOriginal : state.telaresData;
    const r = telaresParaProgramar(state.selectedTelar, state.selectedTelares, base);
    if (!r.ok) {
        avisar(r.aviso);
        return;
    }
    // Programación de requerimientos lee ?telares= y, si no viene, sessionStorage (contrato-flujo.ts).
    sessionStorage.setItem(CLAVE_SESION_TELARES, JSON.stringify(r.telares));
    window.location.href = urlConTelares(cfg.api.programarRequerimientos, r.telares);
}

/* ---------- Liberar ---------- */

export async function liberarTelar(): Promise<void> {
    if (!cfg.can.eliminar) {
        notify.warning('No tiene permiso para liberar');
        return;
    }
    const tel = state.selectedTelar;
    if (!tel?.no_telar) {
        alertar({ tipo: 'warning', titulo: 'Aviso', texto: 'Selecciona un telar primero' });
        return;
    }
    if (!isReservado(tel)) {
        alertar({ tipo: 'warning', titulo: 'Aviso', texto: 'Este telar no está reservado' });
        return;
    }

    const ok = await notify.confirm({ title: '¿Liberar telar?', icon: 'warning', confirmText: 'Sí, liberar', confirmColor: '#dc2626' });
    if (!ok) return;

    // Sin loader ni refetch: el endpoint devuelve el telar ya liberado en `data`.
    // Se aplica optimista y solo se refresca el inventario en silencio.
    disable($('#btnLiberarTelar'), true);

    const ref = { id: tel.id, no_telar: tel.no_telar, tipo: tel.tipo };
    const prevData = clonar(state.telaresData);
    const prevOriginal = clonar(state.telaresDataOriginal);

    const aplicar = (d: RawRow | undefined): void => {
        for (const base of [state.telaresData, state.telaresDataOriginal]) {
            const row = base.find((r) => esMismoTelar(r, ref));
            if (row) aplicarLiberado(row, d);
        }
        pintarTelares(state.telaresData);
    };

    aplicar(undefined);
    limpiarSeleccion(false);
    notify.success(`Telar ${ref.no_telar} liberado`);

    try {
        const resp = exigirExito(
            await http.post<RespuestaFilas>(cfg.api.liberarTelar, { id: ref.id ?? null, no_telar: ref.no_telar, tipo: tel.tipo }),
            'No se pudo liberar',
        );
        const actualizado = asRows(resp.data)[0];
        if (actualizado) aplicar(actualizado);
        if (resp.message) notify.success(resp.message);

        // La pieza liberada vuelve al inventario: refresco silencioso.
        cargarInventario()
            .then(reemplazarInventario)
            .catch(() => {
                /* se reconcilia en la próxima selección */
            });
    } catch (err) {
        state.telaresData = prevData;
        state.telaresDataOriginal = prevOriginal;
        pintarTelares(state.telaresData);
        notify.error(mensajeError(err, 'Error al liberar'));
    } finally {
        actualizarBotones();
    }
}

/* ---------- Reservar ---------- */

export async function reservar(): Promise<void> {
    if (!cfg.can.modificar) {
        notify.warning('No tiene permiso para reservar');
        return;
    }
    const tel = state.selectedTelar;
    const piezas = piezasReservables(state.selectedInventarios);
    const aviso = validarReserva(tel, piezas);
    if (aviso || !tel) {
        if (aviso) alertar(aviso);
        return;
    }

    const ok = await notify.confirm({
        title: piezas.length > 1 ? `¿Reservar ${piezas.length} julios?` : '¿Reservar pieza?',
        text: `Reservar para telar ${tel.no_telar} (${etiquetaTipo(tel)})`,
        icon: 'question',
        confirmText: 'Sí, reservar',
    });
    if (!ok) return;

    loader.show();

    // Snapshot para revertir la edición optimista si el POST falla.
    const prevData = clonar(state.telaresData);
    const prevOriginal = clonar(state.telaresDataOriginal);
    // Julios ya escritos: si el lote falla a medias, el snapshot los borraría.
    let confirmadas = 0;
    const ref = { id: tel.id, no_telar: tel.no_telar, tipo: tel.tipo };
    const tTipo = normalizeTipo(tel.tipo).toUpperCase();

    try {
        // Un POST por julio: el backend (acomodarJulio) coloca cada uno en la primera columna
        // libre de la barra, así que van en serie, no en paralelo.
        for (const pieza of piezas) {
            const lote = loteDePieza(pieza);
            const fila = state.telaresData.find((r) => esMismoTelar(r, ref));
            if (fila) {
                aplicarReservaLocal(fila, pieza, lote, tel.max_julios);
                const original = state.telaresDataOriginal.find((r) => esMismoTelar(r, ref));
                if (original) aplicarReservaLocal(original, pieza, lote, tel.max_julios);
                pintarTelares(state.telaresData);
            }

            exigirExito(await http.post<RespuestaApi>(cfg.api.reservarInventario, payloadReserva(tel, pieza)), 'Error al reservar');
            confirmadas += 1;
        }

        state.selectedInventarios = [];
        notify.success(piezas.length > 1 ? `${piezas.length} julios reservados` : 'Pieza reservada');

        // La recarga va aparte: si falla, la reserva ya quedó guardada y no debe reportarse como error.
        try {
            const [inv, telares] = await Promise.all([cargarInventario(), getFilas(cfg.api.inventarioTelares)]);
            reemplazarInventario(inv);

            if (telares.length) {
                state.telaresDataOriginal = clonar(telares);
                state.telaresData = telares;
                pintarTelares(telares);
                // Por id: un mismo telar puede tener la misma barra dos veces con fechas distintas.
                reseleccionarTelar(ref, tTipo);
            }
        } catch {
            notify.warning('Reserva guardada, pero no se pudo actualizar la tabla. Recarga la página.');
        }
    } catch (err) {
        if (confirmadas > 0) {
            // El lote falló a medias: esos julios ya están en la base, así que volver al
            // snapshot los borraría de la pantalla. Se recarga lo que haya guardado.
            const rows = await getFilas(cfg.api.inventarioTelares).catch((): RawRow[] => []);
            if (rows.length) {
                state.telaresDataOriginal = clonar(rows);
                state.telaresData = rows;
            }
            state.selectedInventarios = [];
        } else {
            state.telaresData = prevData;
            state.telaresDataOriginal = prevOriginal;
        }
        pintarTelares(state.telaresData);
        const sufijo = confirmadas ? ` (se guardaron ${confirmadas} de ${piezas.length} julios)` : '';
        notify.error(`${mensajeError(err, 'Error al reservar')}${sufijo}`);
    } finally {
        loader.hide();
    }
}
