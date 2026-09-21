export type TableKey = 'telares' | 'inventario'

export type SortDirection = 'asc' | 'desc'

export interface SortRule {
  column: string
  direction: SortDirection
}

/** Filtro aplicado desde el modal (formato lista). */
export interface ColumnFilter {
  table: TableKey
  column: string
  value: string
  idx: number
}

/** Filtro de columna del menú contextual (formato mapa). */
export type FilterMap = Record<string, string>

export type FilterStore = Record<TableKey, ColumnFilter[] | FilterMap>

export interface ColumnOption {
  field: string
  label: string
}

/** Fila cruda tal como llega del backend (claves en snake_case / PascalCase). */
export type RawRow = Record<string, unknown>

export interface PuApi {
  inventarioTelares: string
  inventarioDisponible: string
  inventarioDisponibleGet: string
  programarTelar: string
  programarRequerimientos: string
  actualizarTelar: string
  reservarInventario: string
  liberarTelar: string
}

export interface PuCan {
  modificar: boolean
  crear: boolean
  eliminar: boolean
}

export interface PuConfig {
  api: PuApi
  columns: Record<TableKey, ColumnOption[]>
  can: PuCan
  telares: RawRow[]
}

/** Selección individual de telar normalizada para el resto del flujo. */
export interface SelectedTelar {
  id: string | null
  no_telar: string | null
  tipo: string
  cuenta: string
  salon: string
  calibre: string
  hilo: string
  no_julio: string
  /** Julios asignados a la fila: hasta cuatro en una barra de Karl Mayer, uno en rizo/pie. */
  julios: string[]
  max_julios: number
  no_orden: string
  fecha: string
  turno: string
  tipo_atado: string
  reservado: boolean
  programado: boolean
  is_reservado: boolean
  is_programado: boolean
}

/** Pieza de inventario seleccionada. */
export interface SelectedInventario {
  itemId: string
  configId: string
  inventSizeId: string
  inventColorId: string
  inventLocationId: string
  inventBatchId: string
  wmsLocationId: string
  inventSerialId: string
  metros: number
  numJulio: string
  tipo: string
  data: RawRow | undefined
}

export interface PuSwalResult {
  isConfirmed: boolean
  value?: unknown
}

export type PuSwalFire = (
  titleOrOptions: string | Record<string, unknown>,
  text?: string,
  icon?: string,
) => Promise<PuSwalResult>

export type PuWindow = Window & {
  Swal?: unknown
}

export interface SwalClient {
  fire: PuSwalFire
  showValidationMessage: (message: string) => void
}

export const puWindow = window as unknown as PuWindow
