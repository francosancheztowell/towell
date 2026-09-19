/**
 * Tipos de la pantalla de creacion de ordenes.
 *
 * Solo se declara lo que cruza una frontera real (servidor -> bundle, o entre
 * modulos). Lo interno de un modulo se infiere.
 */

/** Un telar tal como llega del servidor en data-config. */
export interface Telar {
    no_telar: string
    tipo?: string | null
    cuenta?: string | null
    calibre?: number | string | null
    hilo?: string | null
    tamano?: string | null
    urdido?: string | null
    tipo_atado?: string | null
    destino?: string | null
    fecha_req?: string | null
    metros?: number | string | null
    kilos?: number | string | null
    agrupar?: boolean
    maquina_urd?: string | null
    maquinaId?: string | null
}

/** Telar ya normalizado: tipos resueltos y numeros convertidos. */
export interface TelarNormalizado extends Telar {
    tipo: string
    hilo: string | null
    tamano: string | null
    calibre: number | null
    metros: number
    kilos: number
    agrupar: boolean
}

/** Telares que van juntos a una misma orden. */
export interface Grupo {
    telares: TelarNormalizado[]
    telaresStr: string
    cuenta: string
    calibre: number | string | null
    hilo: string
    tamano: string
    tipo: string
    urdido: string
    tipoAtado: string
    destino: string
    fechaReq: string
    metros: number
    kilos: number
    maquinaId: string
}

export interface MaterialesGuardados {
    materialesUrdido: unknown[]
    materialesEngomado: unknown[]
    timestamp: number
}

/** Rutas y datos que el blade inyecta por data-config. */
export interface ConfigCreacionOrdenes {
    telaresData?: Telar[]
    destinoOptions?: string[]
    routes: Record<string, string>
}
