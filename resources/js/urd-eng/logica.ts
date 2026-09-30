/**
 * Lógica pura (sin DOM) de la edición de órdenes Urdido/Engomado.
 * Tests: tests/Js/programa-urd-eng-edicion-orden.test.mjs
 */

/** Un oficial (1-3) de una fila de producción. */
export interface Oficial {
  numero: number
  cve: string | null
  nombre: string | null
  turno: number | null
  metros: number | null
}

/** Lo que el usuario escribió en una fila del modal de empleados (todo texto). */
export interface FilaCapturada {
  cve: string
  nombre: string
  metros: string
  turno: string
}

export type ResultadoOficiales = { ok: true; oficiales: Oficial[] } | { ok: false; error: string }

export const TURNOS = [1, 2, 3, 4] as const

/**
 * Valida las 3 filas del modal "Editar Empleados" (mismas reglas que el preConfirm de
 * SweetAlert que reemplaza): sin claves repetidas, turno 1-4 obligatorio y un oficial por turno.
 */
export function validarOficiales(filas: FilaCapturada[]): ResultadoOficiales {
  const oficiales: Oficial[] = []
  const claves = new Set<string>()
  const turnos = new Set<number>()

  for (const [indice, fila] of filas.entries()) {
    const numero = indice + 1
    const cve = fila.cve.trim()
    if (!cve) {
      oficiales.push({ numero, cve: null, nombre: null, metros: null, turno: null })
      continue
    }
    const turno = Number.parseInt(fila.turno, 10)
    if (claves.has(cve)) return { ok: false, error: `El No. Empleado ${cve} está repetido.` }
    if (!(TURNOS as readonly number[]).includes(turno)) {
      return { ok: false, error: `Selecciona un turno válido (1-4) para el empleado ${numero}.` }
    }
    if (turnos.has(turno)) return { ok: false, error: `No puede haber dos oficiales en el turno ${turno}.` }
    claves.add(cve)
    turnos.add(turno)
    const metros = fila.metros.trim()
    oficiales.push({ numero, cve, nombre: fila.nombre.trim() || null, metros: metros === '' ? null : Number(metros), turno })
  }

  return { ok: true, oficiales }
}

export type PeticionOficial =
  | { tipo: 'eliminar'; datos: { registro_id: number; numero_oficial: number } }
  | {
      tipo: 'guardar'
      datos: {
        registro_id: number
        numero_oficial: number
        cve_empl: string | null
        nom_empl: string | null
        metros: number | null
        turno: number | null
      }
    }

/**
 * Peticiones a mandar, en orden: una fila vacía que antes tenía oficial se elimina; una fila
 * con oficial se guarda; una fila vacía que ya estaba vacía no se toca.
 */
export function peticionesOficiales(registroId: number, nuevos: Oficial[], actuales: Oficial[]): PeticionOficial[] {
  const porNumero = new Map(actuales.map((o) => [o.numero, o]))
  const peticiones: PeticionOficial[] = []
  for (const nuevo of nuevos) {
    const actual = porNumero.get(nuevo.numero)
    if (!nuevo.cve && !nuevo.nombre) {
      if (actual?.cve || actual?.nombre) {
        peticiones.push({ tipo: 'eliminar', datos: { registro_id: registroId, numero_oficial: nuevo.numero } })
      }
      continue
    }
    peticiones.push({
      tipo: 'guardar',
      datos: {
        registro_id: registroId,
        numero_oficial: nuevo.numero,
        cve_empl: nuevo.cve,
        nom_empl: nuevo.nombre,
        metros: nuevo.metros,
        turno: nuevo.turno,
      },
    })
  }
  return peticiones
}

const CAMPOS_PRODUCCION: Readonly<Record<string, string>> = {
  hilos: 'Hilos',
  hilatura: 'Hilatura',
  maquina: 'Maquina',
  operac: 'Operac',
  transf: 'Transf',
  canoa1: 'Canoa1',
  canoa2: 'Canoa2',
  solidos: 'Solidos',
  roturas: 'Roturas',
  ubicacion: 'Ubicacion',
  metros: 'Metros',
}

export type RutaProduccion = 'horas' | 'fecha' | 'campos'

/**
 * Ruta y payload para guardar una celda de la tabla de producción; null si el campo no
 * se guarda desde aquí.
 */
export function peticionCeldaProduccion(
  campo: string,
  registroId: number,
  valor: string | null,
): { ruta: RutaProduccion; datos: Record<string, unknown> } | null {
  if (campo === 'h_inicio' || campo === 'h_fin') {
    return { ruta: 'horas', datos: { registro_id: registroId, campo: campo === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', valor } }
  }
  if (campo === 'fecha') return { ruta: 'fecha', datos: { registro_id: registroId, fecha: valor } }
  const nombre = CAMPOS_PRODUCCION[campo]
  if (!nombre) return null
  const numerico = nombre !== 'Ubicacion' && valor !== null
  return { ruta: 'campos', datos: { registro_id: registroId, campo: nombre, valor: numerico ? Number(valor) : valor } }
}

/** Nombre de archivo de una cabecera Content-Disposition (o '' si no trae). */
export function nombreDeDisposicion(disposicion: string | null | undefined): string {
  return disposicion?.match(/filename="?([^";]+)"?/i)?.[1] ?? ''
}

/** Texto de la opción de autocompletado y valor que se escribe en el campo. */
export function opcionAutocompletado(tipo: string, fila: Record<string, string | undefined>): { etiqueta: string; valor: string } {
  if (tipo === 'bom') return { etiqueta: `${fila.BOMID ?? ''} - ${fila.NAME ?? ''}`, valor: fila.BOMID ?? '' }
  const valor = fila.BomFormula ?? fila.LoteProveedor ?? ''
  return { etiqueta: valor, valor }
}
