/**
 * Agrupacion de telares en ordenes.
 *
 * Dos telares van a la misma orden si comparten cuenta, tipo, maquina de
 * urdido y tipo de atado. El hilo NO entra en la clave: telares con hilos
 * distintos pueden urdirse juntos.
 */

import { aNumero, estaVacio, normalizarTipo } from './formato.ts'
import type { Grupo, Telar, TelarNormalizado } from './types.ts'

export const normalizarTelares = (telares: Telar[] | null | undefined): TelarNormalizado[] =>
    (telares ?? []).map((t) => ({
        ...t,
        tipo: normalizarTipo(t.tipo) || 'Rizo',
        hilo: !estaVacio(t.hilo) ? String(t.hilo).trim() : null,
        tamano: !estaVacio(t.tamano) ? String(t.tamano).trim() : null,
        calibre: !estaVacio(t.calibre) ? parseFloat(String(t.calibre)) : null,
        metros: aNumero(t.metros, 0),
        kilos: aNumero(t.kilos, 0),
        agrupar: !!t.agrupar,
    }))

export const agruparTelares = (telares: TelarNormalizado[] | null | undefined): Grupo[] => {
    const grupos: Record<string, Grupo> = Object.create(null)
    const sueltos: TelarNormalizado[] = []

    for (const telar of telares ?? []) {
        if (!telar.agrupar) {
            sueltos.push(telar)
            continue
        }

        const tipo = normalizarTipo(telar.tipo) || 'Rizo'
        const cuenta = String(telar.cuenta ?? '').trim()
        const urdido = String(telar.urdido ?? '').trim()
        const tipoAtado = String(telar.tipo_atado ?? 'Normal').trim()
        const clave = `${cuenta}|${tipo.toUpperCase()}|${urdido}|${tipoAtado}`

        let grupo = grupos[clave]

        if (!grupo) {
            grupo = {
                telares: [],
                telaresStr: '',
                cuenta,
                calibre: !estaVacio(telar.calibre) ? parseFloat(String(telar.calibre)) : null,
                hilo: !estaVacio(telar.hilo) ? String(telar.hilo).trim() : '',
                tamano: !estaVacio(telar.tamano) ? String(telar.tamano).trim() : '',
                tipo,
                urdido,
                tipoAtado,
                destino: String(telar.destino ?? '').trim(),
                fechaReq: telar.fecha_req ?? '',
                metros: 0,
                kilos: 0,
                maquinaId: telar.urdido || telar.maquina_urd || telar.maquinaId || '',
            }
            grupos[clave] = grupo
        }

        grupo.telares.push(telar)
        grupo.metros += telar.metros || 0
        grupo.kilos += telar.kilos || 0
    }

    const salida: Grupo[] = Object.values(grupos).map((g) => ({
        ...g,
        // Con mas de un telar el destino se elige a mano.
        destino: g.telares.length > 1 ? '' : g.destino,
        telaresStr: g.telares.map((t) => t.no_telar).join(','),
    }))

    for (const t of sueltos) {
        const calibre = !estaVacio(t.calibre) ? parseFloat(String(t.calibre)) : null

        salida.push({
            telares: [t],
            telaresStr: t.no_telar,
            cuenta: t.cuenta ?? '',
            calibre: calibre !== null ? calibre : '',
            hilo: t.hilo ?? '',
            tamano: t.tamano ?? '',
            tipo: normalizarTipo(t.tipo) || 'Rizo',
            urdido: t.urdido ?? '',
            tipoAtado: t.tipo_atado ?? 'Normal',
            destino: t.destino ?? '',
            fechaReq: t.fecha_req ?? '',
            metros: t.metros || 0,
            kilos: t.kilos || 0,
            maquinaId: t.urdido || t.maquina_urd || t.maquinaId || '',
        })
    }

    return salida
}
