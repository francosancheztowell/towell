/**
 * Reporte OEE Atadores: modal de fechas y "Exportar a OEE" (verifica semanas, confirma, despacha el job
 * que reescribe OEE_ATADORES.xlsx y sondea su estado cada 3 s hasta 10 min).
 */
import { delegate } from '../../../../utils/dom.ts';
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { mensajeError } from '../../comun/pagina.ts';
import { iniciarReporte } from '../comun.ts';
import { SONDEO_MS, estadoDelSondeo, htmlConfirmacion } from './logica.ts';

interface ConfigOee {
    indice: string;
    fechaIni: string;
    fechaFin: string;
    rutas: { verificar: string; despachar: string; estado: string };
}

const raiz = document.getElementById('reporte-atadores');
const cfg = raiz?.dataset.oee ? (JSON.parse(raiz.dataset.oee) as ConfigOee) : null;
iniciarReporte(raiz?.dataset.indice);

async function exportar(c: ConfigOee): Promise<void> {
    if (!c.fechaIni || !c.fechaFin) return;

    void notify.loading('Verificando...');
    let verificacion: { semanas_rango?: unknown[]; semanas_con_datos?: unknown[] };
    try {
        verificacion = await http.get(`${c.rutas.verificar}?${new URLSearchParams({ fecha_ini: c.fechaIni, fecha_fin: c.fechaFin })}`);
    } catch (err) {
        void notify.alert(mensajeError(err, 'No se pudo verificar el archivo OEE.'), 'Error', 'error');
        return;
    }
    notify.close();

    const conDatos = verificacion.semanas_con_datos ?? [];
    const confirmado = await notify.confirm({
        title: 'Exportar a OEE',
        html: htmlConfirmacion(verificacion.semanas_rango ?? [], conDatos),
        icon: conDatos.length > 0 ? 'warning' : 'question',
        confirmText: 'Exportar',
        confirmColor: '#7c3aed',
    });
    if (!confirmado) return;

    void notify.loading('Procesando... el archivo OEE se está actualizando (puede tomar varios minutos)');
    let token: string;
    try {
        const res = await http.post<{ token?: string }>(c.rutas.despachar, { fecha_ini: c.fechaIni, fecha_fin: c.fechaFin });
        if (!res.token) throw new Error('sin token');
        token = res.token;
    } catch (err) {
        void notify.alert(mensajeError(err, 'No se pudo iniciar la exportación.'), 'Error', 'error');
        return;
    }

    const inicio = Date.now();
    const sondeo = setInterval(async () => {
        let datos: { estado?: unknown; mensaje?: unknown } = {};
        try {
            datos = await http.get(c.rutas.estado.replace('__TOKEN__', encodeURIComponent(token)));
        } catch {
            // Error de red: se sigue intentando hasta el límite.
        }
        switch (estadoDelSondeo(datos.estado, Date.now() - inicio)) {
            case 'listo':
                clearInterval(sondeo);
                notify.close();
                notify.success('Archivo OEE actualizado correctamente.');
                break;
            case 'error':
                clearInterval(sondeo);
                void notify.alert(typeof datos.mensaje === 'string' && datos.mensaje ? datos.mensaje : 'No se pudo actualizar el archivo OEE.', 'Error', 'error');
                break;
            case 'agotado':
                clearInterval(sondeo);
                void notify.alert('La exportación tardó demasiado. Verifique el archivo manualmente.', 'Tiempo agotado', 'warning');
                break;
        }
    }, SONDEO_MS);
}

if (cfg) {
    delegate<HTMLElement>(document, 'click', '[data-accion="exportar-oee"]', () => void exportar(cfg));
}
