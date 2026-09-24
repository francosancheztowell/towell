import {
  type ColumnFilter,
  type ColumnOption,
  type FilterMap,
  type FilterStore,
  type PuApi,
  type PuCan,
  type PuConfig,
  type RawRow,
  type SelectedInventario,
  type SelectedTelar,
  type SortRule,
  type TableKey,
  type SwalClient,
  puWindow,
} from './types'

/* ---------- Config (isla JSON del Blade) ---------- */
const readConfig = (): PuConfig => {
  const el = document.getElementById('pu-config')
  const raw = (el?.textContent ?? '').trim()
  const parsed = (raw ? (JSON.parse(raw) as Partial<PuConfig>) : {}) as Partial<PuConfig>
  const api = (parsed.api ?? {}) as Partial<PuApi>
  const columns = (parsed.columns ?? {}) as Partial<Record<TableKey, ColumnOption[]>>
  const can = (parsed.can ?? {}) as Partial<PuCan>
  return {
    api: {
      inventarioTelares: api.inventarioTelares ?? '',
      inventarioDisponible: api.inventarioDisponible ?? '',
      inventarioDisponibleGet: api.inventarioDisponibleGet ?? '',
      programarTelar: api.programarTelar ?? '',
      programarRequerimientos: api.programarRequerimientos ?? '',
      actualizarTelar: api.actualizarTelar ?? '',
      reservarInventario: api.reservarInventario ?? '',
      liberarTelar: api.liberarTelar ?? '',
    },
    columns: {
      telares: columns.telares ?? [],
      inventario: columns.inventario ?? [],
    },
    can: {
      modificar: can.modificar ?? false,
      crear: can.crear ?? false,
      eliminar: can.eliminar ?? false,
    },
    telares: Array.isArray(parsed.telares) ? (parsed.telares as RawRow[]) : [],
  }
}

const CONFIG = readConfig()
const API: PuApi = CONFIG.api
const COLUMN_OPTIONS: Record<TableKey, ColumnOption[]> = CONFIG.columns
const CAN_MODIFICAR: boolean = CONFIG.can.modificar
const CAN_CREAR: boolean = CONFIG.can.crear
const CAN_ELIMINAR: boolean = CONFIG.can.eliminar

const getCsrfToken = (): string =>
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

/* ---------- Helpers DOM ---------- */
const $ = (s: string, c: ParentNode = document): HTMLElement | null =>
  c.querySelector(s) as HTMLElement | null
const $$ = (s: string, c: ParentNode = document): HTMLElement[] =>
  Array.from(c.querySelectorAll(s)) as HTMLElement[]
const show = (el: HTMLElement | null): void => {
  el?.classList.remove('hidden')
}
const hide = (el: HTMLElement | null): void => {
  el?.classList.add('hidden')
}
const disable = (el: HTMLElement | null, v = true): HTMLElement | null => {
  if (el) (el as HTMLButtonElement).disabled = !!v
  return el
}

/* ---------- Loader único ---------- */
const setLoading = (v: boolean): void => {
  const el = $('#puLoader')
  if (!el) return
  el.classList.toggle('opacity-0', !v)
  el.classList.toggle('pointer-events-none', !v)
  el.setAttribute('aria-hidden', String(!v))
}

/* ---------- Lecturas seguras sobre filas crudas ---------- */
const s = (v: unknown, d = ''): string =>
  v === null || v === undefined ? d : String(v)
/**
 * Texto que va a parar dentro de una plantilla HTML. 'cuenta' y 'calibre' se
 * editan en esta misma pantalla y se guardan tal cual, asi que sin esto un
 * `<img src=x onerror=...>` en cuenta se ejecutaba al repintar la tabla.
 */
const esc = (v: unknown): string =>
  s(v).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`)
const num = (v: unknown): number => {
  const x = Number(v)
  return Number.isNaN(x) ? 0 : x
}

/* ---------- Toast ---------- */
const toast = (icon: string, title: string, text = '', timer = 2000): void => {
  const Swal = puWindow.Swal as SwalClient | undefined
  if (!Swal) return
  void Swal.fire({ toast: true, position: 'top-end', icon, title, text, showConfirmButton: false, timer })
}

/* ---------- HTTP ---------- */
interface ApiResponse {
  success?: boolean
  message?: string
  error?: string
  data?: RawRow[] | RawRow
}

/** Normaliza `data` (objeto único u arreglo) a lista de filas. */
const asRows = (d: unknown): RawRow[] => {
  if (Array.isArray(d)) return d as RawRow[]
  if (typeof d === 'object' && d !== null) return [d as RawRow]
  return []
}

const http = {
  async request(url: string, options: RequestInit = {}): Promise<ApiResponse> {
    const token = getCsrfToken()
    const res = await fetch(url, {
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      ...options,
    })

    const json = (await res.json().catch(() => ({ success: false, message: res.statusText }))) as ApiResponse

    if (res.status === 419) {
      throw new Error('La sesión expiró o el token de seguridad no es válido. Por favor recarga la página (F5) e intenta de nuevo.')
    }

    if (!res.ok || json.success === false) {
      throw new Error(json.message ?? json.error ?? res.statusText)
    }

    return json
  },
  get: (url: string): Promise<ApiResponse> => http.request(url, { method: 'GET' }),
  post: (url: string, b: Record<string, unknown>): Promise<ApiResponse> =>
    http.request(url, { method: 'POST', body: JSON.stringify(b ?? {}) }),
}

/* ---------- Formato ---------- */
const fmt = {
  salonBadge(salon: unknown): string {
    const map: Record<string, string> = {
      Jacquard: 'bg-pink-100 text-pink-700',
      JACQUARD: 'bg-pink-100 text-pink-700',
      Itema: 'bg-purple-100 text-purple-700',
      ITEMA: 'bg-purple-100 text-purple-700',
      Smith: 'bg-cyan-100 text-cyan-700',
      SMIT: 'bg-cyan-100 text-cyan-700',
      'Karl Mayer': 'bg-amber-100 text-amber-700',
      'KARL MAYER': 'bg-amber-100 text-amber-700',
      Sulzer: 'bg-lime-100 text-lime-700',
      SULZER: 'bg-lime-100 text-lime-700',
    }
    return map[s(salon, 'Jacquard').trim()] ?? 'bg-indigo-100 text-indigo-700'
  },
  tipoBadge(t: unknown): string {
    const u = s(t, '-').toUpperCase().trim()
    if (u === 'RIZO') return 'bg-rose-100 text-rose-700'
    if (u === 'PIE') return 'bg-teal-100 text-teal-700'
    return 'bg-gray-100 text-gray-700'
  },
  num(n: unknown, d = 2): string {
    if (n === null || n === undefined || n === '') return ''
    const val = Number(n)
    return Number.isNaN(val) ? '' : val.toFixed(d)
  },
  date(iso: unknown): string {
    if (!iso) return ''
    const str = String(iso).trim()
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (m) {
      const y = parseInt(m[1] ?? '', 10)
      const mon = parseInt(m[2] ?? '', 10) - 1
      const day = parseInt(m[3] ?? '', 10)
      const d = new Date(y, mon, day)
      if (!Number.isNaN(d.getTime()) && d.getFullYear() === y && d.getMonth() === mon && d.getDate() === day) {
        return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
      }
    }
    const d = new Date(str)
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
  },
}

/** InventBatchId = prefijo de InventSerialId (ej. 00061-744 → 00061). Para comparar lote. */
const deriveInventBatchFromSerial = (serialId: unknown, batchId: unknown): string => {
  const serial = s(serialId).trim()
  if (!serial || serial.indexOf('-') === -1) return s(batchId)
  const prefijo = (serial.split('-')[0] ?? '').trim()
  return prefijo || s(batchId)
}

/** Coincidencia de lote: inventBatchId o prefijo de InventSerialId debe coincidir con telNoOrden */
const matchLote = (telNoOrden: string, invBatchId: string, invSerialId: string): boolean => {
  if (!telNoOrden) return true
  const batch = invBatchId.trim()
  const derived = deriveInventBatchFromSerial(invSerialId, batch)
  return batch === telNoOrden || derived === telNoOrden
}

/** Coincidencia de cuenta: telar "3156" => InventSizeId que empiece por "3156" */
const matchCuenta = (cuentaTelar: unknown, inventSizeId: unknown): boolean => {
  const a = s(cuentaTelar).replace(/\s+/g, '').toUpperCase()
  const b = s(inventSizeId).replace(/\s+/g, '').toUpperCase()
  if (!a) return false
  return b.startsWith(a)
}

const eq = {
  str: (a: unknown, b: unknown): boolean =>
    s(a).trim().toUpperCase() === s(b).trim().toUpperCase(),
  num: (a: unknown, b: unknown): boolean => {
    const x = Number(a)
    const y = Number(b)
    if (Number.isNaN(x) || Number.isNaN(y)) return String(a) === String(b)
    return Math.abs(x - y) < 1e-6
  },
}

interface TelarGroup {
  tipo: unknown
  calibre: unknown
  salon: unknown
}

const sameGroup = (a: TelarGroup, b: TelarGroup): boolean =>
  eq.str(a.tipo, b.tipo) && eq.num(a.calibre, b.calibre) && eq.str(a.salon, b.salon)

const normalizeTipo = (t: unknown): string => {
  const u = s(t).toUpperCase().trim()
  if (u === 'RIZO') return 'Rizo'
  if (u === 'PIE') return 'Pie'
  return u || '-'
}

interface Reservable {
  no_orden?: unknown
  reservado?: unknown
  is_reservado?: unknown
  programado?: unknown
  is_programado?: unknown
}

const hasNoOrden = (telar: Reservable | null | undefined): boolean =>
  s(telar?.no_orden).trim() !== ''

/** Estado canónico de una fila cruda: misma regla para badge, sort y filtros.
 * Ojo: el backend manda `Reservado`/`Programado` capitalizados y no siempre
 * vienen los flags en minúscula; julio+orden también implican reservado. */
const rowNoJulio = (r: RawRow): string => s(r.no_julio).trim()
/** Una barra de Karl Mayer se alimenta de hasta cuatro julios; rizo y pie, de uno. */
const rowJulios = (r: RawRow): string[] =>
  Array.isArray(r.julios)
    ? (r.julios as unknown[]).map((j) => s(j).trim()).filter(Boolean)
    : [rowNoJulio(r)].filter(Boolean)
const rowOrdenes = (r: RawRow): string[] => {
  const julios = rowJulios(r)
  const crudas = Array.isArray(r.ordenes)
    ? (r.ordenes as unknown[]).map((o) => s(o).trim())
    : [rowNoOrden(r)]
  return julios.map((_, i) => crudas[i] ?? '')
}
const rowMaxJulios = (r: RawRow): number => Number(r.max_julios) || 1
/** En Karl Mayer el tipo es 1..4: sin el prefijo "Barra" se lee como un numero suelto. */
const etiquetaTipo = (tel: SelectedTelar): string => {
  const tipo = s(tel.tipo).trim()
  if (!tipo) return 'N/A'
  return (tel.max_julios || 1) > 1 ? `Barra ${tipo}` : tipo
}
const etiquetasOrdenes = (r: RawRow): string[] => {
  const ordenes = rowMaxJulios(r) > 1 ? rowOrdenes(r) : [rowNoOrden(r)]
  return ordenes.map((o) => s(o).trim()).filter(Boolean)
}
/**
 * Celda de una barra: solo el primer valor a la vista; los demas quedan apilados
 * y ocultos hasta que la fila se expande (`data-expandido="1"`).
 */
const celdaApilada = (valores: string[]): string => {
  const items = valores.map((v) => s(v).trim()).filter(Boolean)
  if (!items.length) return '-'
  if (items.length === 1) return esc(items[0])
  return (
    '<span class="pu-stack">' +
    items.map((v, i) => `<span class="${i === 0 ? '' : 'pu-extra'}">${esc(v)}</span>`).join('') +
    '</span>'
  )
}
const rowNoOrden = (r: RawRow): string => s(r.no_orden).trim()
const rowReservado = (r: RawRow): boolean =>
  r.reservado === true ||
  r.is_reservado === true ||
  r.Reservado === true ||
  (rowNoJulio(r) !== '' && rowNoOrden(r) !== '')
const rowProgramado = (r: RawRow): boolean =>
  r.programado === true || r.is_programado === true || r.Programado === true
const estadoDe = (r: RawRow): 'reservado' | 'programado' | 'libre' =>
  rowReservado(r) ? 'reservado' : rowProgramado(r) ? 'programado' : 'libre'
const isReservado = (telar: Reservable | null | undefined): boolean => {
  if (typeof telar?.reservado === 'boolean') return telar.reservado
  if (typeof telar?.is_reservado === 'boolean') return telar.is_reservado
  return false
}
const isProgramado = (telar: Reservable | null | undefined): boolean => {
  if (typeof telar?.programado === 'boolean') return telar.programado
  if (typeof telar?.is_programado === 'boolean') return telar.is_programado
  return false
}

/* ---------- Estado ---------- */
interface PuState {
  filters: FilterStore
  selectedTelar: SelectedTelar | null
  selectedTelares: SelectedTelar[]
  selectedInventario: SelectedInventario | null
  /** Piezas elegidas para la reserva: una en rizo/pie, hasta cuatro en una barra de KM. */
  selectedInventarios: SelectedInventario[]
  columns: Record<TableKey, ColumnOption[]>
  sort: Record<TableKey, SortRule[]>
  telaresData: RawRow[]
  telaresDataOriginal: RawRow[]
  inventarioData: RawRow[]
  inventarioDataOriginal: RawRow[]
  mostrarTodoInventario: boolean
}

const state: PuState = {
  filters: { telares: {}, inventario: {} },
  selectedTelar: null,
  selectedTelares: [],
  selectedInventario: null,
  selectedInventarios: [],
  columns: { telares: COLUMN_OPTIONS.telares ?? [], inventario: COLUMN_OPTIONS.inventario ?? [] },
  sort: {
    telares: [{ column: 'no_telar', direction: 'asc' }],
    inventario: [],
  },
  telaresData: CONFIG.telares,
  telaresDataOriginal: structuredClone(CONFIG.telares) as RawRow[],
  inventarioData: [],
  inventarioDataOriginal: [],
  mostrarTodoInventario: false,
}

/** Filtros activos de una tabla, unificando formato lista (modal) y mapa (menú contextual). */
const activeFilters = (table: TableKey): Array<{ column: string; value: string }> => {
  const store = state.filters[table]
  const out: Array<{ column: string; value: string }> = []
  if (Array.isArray(store)) {
    for (const f of store as ColumnFilter[]) out.push({ column: f.column, value: f.value })
  } else if (typeof store === 'object' && store !== null) {
    for (const [column, value] of Object.entries(store as FilterMap)) out.push({ column, value })
  }
  return out.filter((f) => s(f.value).trim() !== '')
}

const mapFilters = (table: TableKey): FilterMap => {
  const store = state.filters[table]
  if (!Array.isArray(store) && typeof store === 'object' && store !== null) return store as FilterMap
  const fresh: FilterMap = {}
  state.filters[table] = fresh
  return fresh
}

/* ---------- Filtros rápidos (chips en la cabecera) ---------- */
const quickFilters = {
  salons(): string[] {
    const seen = new Set<string>()
    for (const r of state.telaresDataOriginal) {
      const v = s(r.salon).trim()
      if (v) seen.add(v)
    }
    return [...seen].sort((a, b) => a.localeCompare(b, 'es'))
  },
  short(salon: string): string {
    const key = salon.trim().toUpperCase()
    if (key === 'ITEMA' || key === 'ITE') return 'SMI'
    const parts = salon.trim().split(/\s+/)
    if (parts.length > 1) return parts.map((p) => (p[0] ?? '').toUpperCase()).join('').slice(0, 3)
    return salon.trim().slice(0, 3).toUpperCase()
  },
  paint(): void {
    const box = $('#puChips')
    if (!box) return
    const map = mapFilters('telares')
    const activeSalon = s(map.salon)
    const activeEstado = s(map.estado)
    const estados: Array<[string, string]> = [
      ['', 'Todos'],
      ['libre', 'Libre'],
      ['reservado', 'Reservado'],
      ['programado', 'Programado'],
    ]
    box.innerHTML =
      this.salons()
        .map(
          (sal) =>
            `<button type="button" class="pu-chip" data-qf-salon="${sal}" aria-pressed="${activeSalon === sal}" title="Filtrar salón ${sal}">${this.short(sal)}</button>`,
        )
        .join('') +
      '<span class="pu-chip-sep"></span>' +
      estados
        .map(
          ([v, label]) =>
            `<button type="button" class="pu-chip" data-qf-estado="${v}" aria-pressed="${v === '' ? activeEstado === '' : activeEstado === v}" title="Estado: ${label}">${label}</button>`,
        )
        .join('')
    box.querySelectorAll<HTMLButtonElement>('[data-qf-salon]').forEach((btn) => {
      btn.onclick = () => this.setSalon(btn.dataset.qfSalon ?? '')
    })
    box.querySelectorAll<HTMLButtonElement>('[data-qf-estado]').forEach((btn) => {
      btn.onclick = () => this.setEstado(btn.dataset.qfEstado ?? '')
    })
  },
  setSalon(v: string): void {
    const map = mapFilters('telares')
    if (s(map.salon) === v) delete map.salon
    else map.salon = v
    render.telares(state.telaresDataOriginal)
  },
  setEstado(v: string): void {
    const map = mapFilters('telares')
    if (v === '' || s(map.estado) === v) delete map.estado
    else map.estado = v
    render.telares(state.telaresDataOriginal)
  },
}

/* ---------- Render ---------- */
const render = {
  sorted(data: RawRow[], sorts: SortRule[] = []): RawRow[] {
    if (!Array.isArray(sorts) || !sorts.length) return [...data]

    return [...data].sort((a, b) => {
      for (const rule of sorts) {
        const col = rule.column
        const dir = rule.direction
        const av: unknown = a[col]
        const bv: unknown = b[col]
        const emptyA = av === null || av === undefined || av === ''
        const emptyB = bv === null || bv === undefined || bv === ''

        if (emptyA && emptyB) continue
        if (emptyA) return 1
        if (emptyB) return -1

        let cmp = 0

        if (['fecha', 'ProdDate'].includes(col)) {
          cmp = new Date(s(av)).getTime() - new Date(s(bv)).getTime()
        } else if (['no_telar', 'no_julio', 'no_orden', 'calibre', 'metros', 'Metros', 'InventQty'].includes(col)) {
          cmp = (parseFloat(s(av)) || 0) - (parseFloat(s(bv)) || 0)
        } else if (['reservado', 'programado'].includes(col)) {
          cmp = (av ? 1 : 0) - (bv ? 1 : 0)
        } else if (col === 'estado') {
          const statusVal = (row: RawRow): number => {
            const e = estadoDe(row)
            if (e === 'reservado') return 2
            if (e === 'programado') return 1
            return 0
          }
          cmp = statusVal(a) - statusVal(b)
        } else {
          const as = s(av).toLowerCase()
          const bs = s(bv).toLowerCase()
          cmp = as < bs ? -1 : as > bs ? 1 : 0
        }

        if (cmp !== 0) {
          return dir === 'asc' ? cmp : -cmp
        }
      }
      return 0
    })
  },

  updateSortIcons(): void {
    $$('#telaresTable .sortable .sort-icon').forEach((i) => {
      i.className = 'fa-solid fa-sort text-gray-400 sort-icon'
    })
    $$('#inventarioTable .sortable-inventario .sort-icon-inventario').forEach((i) => {
      i.className = 'fa-solid fa-sort text-gray-400 sort-icon-inventario'
    })
    $$('.sort-priority').forEach((el) => el.remove())

    const applyIcon = (selector: string, sorts: SortRule[], iconClass: string): void => {
      ;(sorts ?? []).forEach((rule, idx) => {
        const th = $(`${selector}[data-column="${rule.column}"]`)
        if (!th) return

        const icon = th.querySelector(`.${iconClass}`)
        if (icon) {
          icon.className =
            rule.direction === 'asc'
              ? `fa-solid fa-sort-up text-blue-600 ${iconClass}`
              : `fa-solid fa-sort-down text-blue-600 ${iconClass}`
        }

        const btn = th.querySelector('button')
        if (btn) {
          const marker = document.createElement('span')
          marker.className = 'sort-priority'
          marker.textContent = String(idx + 1)
          btn.appendChild(marker)
        }
      })
    }

    applyIcon('#telaresTable .sortable', state.sort.telares, 'sort-icon')
    applyIcon('#inventarioTable .sortable-inventario', state.sort.inventario, 'sort-icon-inventario')
  },

  telares(rows: RawRow[]): void {
    const tbody = $('#telaresTable tbody') as HTMLTableSectionElement | null
    if (!tbody) return

    tbody.innerHTML = ''

    if (!rows || !rows.length) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="14"
                        class="px-4 py-8 text-center text-sm text-gray-500">
                        No hay datos disponibles
                    </td>
                </tr>`
      state.selectedTelar = null
      disable($('#btnProgramar'))
      this.updateSortIcons()
      quickFilters.paint()
      return
    }

    state.telaresData = rows.slice()

    const data = this.sorted(
      activeFilters('telares').length
        ? filters.filterLocal(rows, activeFilters('telares').map((f) => ({ columna: f.column, valor: f.value })))
        : rows,
      state.sort.telares,
    )

    const frag = document.createDocumentFragment()

    data.forEach((r, idx) => {
      const metrosF = parseFloat(s(r.metros, '0')) || 0
      const noJulio = rowNoJulio(r)
      const noOrden = rowNoOrden(r)
      const hasBoth = metrosF > 0 && noJulio !== ''
      const reservado = rowReservado(r)
      const programado = rowProgramado(r)
      const tieneNoOrd = noOrden !== ''

      const telarNo = s(r.no_telar)
      const tipoUpper = s(r.tipo).toUpperCase().trim()

      const rowId = s(r.id)
      const isInMultiple =
        Array.isArray(state.selectedTelares) &&
        state.selectedTelares.some((t) =>
          rowId && t.id ? String(t.id) === String(rowId) : t.no_telar === telarNo && s(t.tipo).toUpperCase().trim() === tipoUpper,
        )

      let baseBg = hasBoth ? 'bg-blue-100' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'
      let border = hasBoth ? 'border-l-4 border-blue-400' : ''

      if (isInMultiple) {
        baseBg = 'bg-yellow-50'
        border = 'border-l-[3px] border-yellow-500'
      }

      const tr = document.createElement('tr')
      tr.className = `selectable-row hover:bg-blue-50 cursor-pointer ${baseBg} ${border}`
      // Seleccionar es toda la funcion de la pantalla: tiene que poder hacerse
      // con el teclado, no solo con el raton.
      tr.tabIndex = 0
      tr.setAttribute('aria-selected', 'false')
      tr.dataset.id = s(r.id)
      tr.dataset.baseBg = baseBg
      tr.dataset.telar = telarNo
      tr.dataset.tipo = tipoUpper
      tr.dataset.cuenta = s(r.cuenta)
      tr.dataset.calibre = s(r.calibre)
      tr.dataset.hilo = s(r.hilo).trim()
      tr.dataset.salon = s(r.salon)
      tr.dataset.noJulio = noJulio
      tr.dataset.julios = rowJulios(r).join(',')
      tr.dataset.ordenes = rowOrdenes(r).join(',')
      tr.dataset.maxJulios = String(rowMaxJulios(r))
      tr.dataset.noOrden = noOrden
      tr.dataset.metros = s(r.metros)
      tr.dataset.hasBoth = hasBoth ? 'true' : 'false'
      tr.dataset.isReservado = reservado ? 'true' : 'false'
      tr.dataset.isProgramado = programado ? 'true' : 'false'
      tr.dataset.tipoAtado = s(r.tipo_atado, 'Normal')
      if (r.fecha) {
        const f = s(r.fecha).trim()
        const match = f.match(/^(\d{4}-\d{2}-\d{2})/)
        tr.dataset.fecha = match?.[1] ?? f
      }
      if (r.turno) {
        tr.dataset.turno = s(r.turno)
      }

      const tipoAtado = s(r.tipo_atado, 'Normal')
      const tipoAtadoCell = CAN_MODIFICAR
        ? `<select
                        class="tipo-atado-select w-full bg-white px-2 py-1 text-xs border border-gray-300 rounded-md text-gray-900 focus:ring-2 focus:ring-blue-500"
                        data-telar="${esc(telarNo)}"
                        data-tipo="${esc(tipoUpper)}"
                    >
                        <option value="Normal" ${tipoAtado === 'Normal' ? 'selected' : ''}>Normal</option>
                        <option value="Especial" ${tipoAtado === 'Especial' ? 'selected' : ''}>Especial</option>
                   </select>`
        : `<span class="text-gray-800 text-xs font-medium">${esc(tipoAtado)}</span>`

      const checkboxChecked = isInMultiple ? ' checked' : ''
      const checkboxDisabled = reservado || programado || tieneNoOrd ? ' disabled' : ''
      const checkboxCursor =
        reservado || programado || tieneNoOrd ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'

      const checkboxCell = CAN_CREAR
        ? `<td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <input type="checkbox"
                           class="telar-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 ${checkboxCursor}"
                           data-telar="${esc(telarNo)}"
                           data-tipo="${esc(tipoUpper)}"
                           ${checkboxDisabled}${checkboxChecked}>
                </td>`
        : ''

      tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-bold">
                    ${esc(telarNo)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.tipo)}">
                        ${esc(s(r.tipo, '-'))}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center editable-cell cursor-context-menu"
                    data-editable-field="cuenta"
                    data-id="${esc(r.id)}"
                    data-telar="${esc(telarNo)}"
                    data-tipo="${esc(tipoUpper)}"
                    title="Clic derecho para editar">${esc(r.cuenta)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center editable-cell cursor-context-menu"
                    data-editable-field="calibre"
                    data-id="${esc(r.id)}"
                    data-telar="${esc(telarNo)}"
                    data-tipo="${esc(tipoUpper)}"
                    title="Clic derecho para editar">${fmt.num(r.calibre)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${fmt.date(r.fecha)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${esc(r.turno)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${esc(r.hilo)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${fmt.num(r.metros, 0)}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${celdaApilada(rowJulios(r))}
                    ${
                      rowMaxJulios(r) > 1
                        ? `<button type="button" class="pu-toggle" aria-expanded="false" title="Ver todos los julios">
                             <span>${rowJulios(r).length}/${rowMaxJulios(r)}</span>
                             <i class="fa-solid fa-chevron-down"></i>
                           </button>`
                        : ''
                    }
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${celdaApilada(etiquetasOrdenes(r))}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${
                      reservado
                        ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Reservado</span>'
                        : programado
                          ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700">Programado</span>'
                          : '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">Libre</span>'
                    }
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    ${tipoAtadoCell}
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.salonBadge(r.salon)}">
                        ${esc(s(r.salon, 'Jacquard'))}
                    </span>
                </td>
                ${checkboxCell}
            `

      frag.appendChild(tr)
    })

    tbody.appendChild(frag)
    this.updateSortIcons()
    quickFilters.paint()
  },

  inventario(rows: RawRow[]): void {
    const tbody = $('#inventarioTable tbody') as HTMLTableSectionElement | null
    if (!tbody) return

    tbody.innerHTML = ''

    if (!state.inventarioDataOriginal.length && rows?.length) {
      state.inventarioDataOriginal = JSON.parse(JSON.stringify(rows)) as RawRow[]
    }

    const tel = state.selectedTelar
    let data = rows ?? []

    if (state.mostrarTodoInventario) {
      data = state.inventarioDataOriginal.length ? state.inventarioDataOriginal : data
    } else if (tel) {
      const telCuenta = s(tel.cuenta).trim()
      const telTipo = s(tel.tipo).toUpperCase().trim()
      const telNo = tel.no_telar ?? ''
      const telJulio = tel.no_julio
      const telNoOrden = s(tel.no_orden).trim()
      const telJulios = tel.julios ?? []
      const esBarra = (tel.max_julios || 1) > 1
      const quedanHuecos = telJulios.length < (tel.max_julios || 1)

      data = data.filter((r) => {
        const noTelarAsignado = s(r.NoTelarId)
        const hasTelar = noTelarAsignado !== ''
        const invTipo = s(r.Tipo).toUpperCase().trim()
        const inventBatchId = s(r.InventBatchId).trim()
        const inventSerialId = s(r.InventSerialId).trim()
        const asignadoAMiTelar = hasTelar && noTelarAsignado === telNo

        // Vincular julio ↔ no_julio: si la pieza está reservada para nuestro telar, mostrarla siempre
        if (asignadoAMiTelar) return true

        // Una barra con hueco admite julios de otra orden. Rizo y Pie, y una
        // barra ya llena, siguen amarrados al lote que ya tienen.
        if (!(esBarra && quedanHuecos) && telNoOrden && !matchLote(telNoOrden, inventBatchId, inventSerialId)) {
          return false
        }

        // Misma cuenta (InventSizeId inicia con cuenta del telar)
        if (telCuenta && !matchCuenta(telCuenta, r.InventSizeId)) return false

        // Si el telar ya tiene No. Julio, mostrar esa pieza concreta.
        // En una barra llena, las cuatro; si aún cabe otra, no ocultar el resto.
        if (esBarra && !quedanHuecos) return telJulios.includes(inventSerialId)
        if (!esBarra && telJulio) return inventSerialId === telJulio

        // Ocultar piezas asignadas a otro telar
        if (hasTelar && noTelarAsignado !== telNo) return false

        // Coincidencia por Tipo (Rizo/Pie o la barra 1..4)
        if (telTipo && invTipo && invTipo !== telTipo) return false

        return true
      })
    }

    const invFilters = activeFilters('inventario')
    if (invFilters.length) {
      data = filters.filterLocal(
        data,
        invFilters.map((f) => ({ columna: f.column, valor: f.value })),
      )
    }

    data = this.sorted(data, state.sort.inventario)

    state.inventarioData = data

    if (!data.length) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="12"
                        class="px-4 py-8 text-center text-sm text-gray-500">
                        <i class="fa-solid fa-box-open w-12 h-12 text-gray-400 mb-2"></i>
                        No hay datos de inventario disponible por el momento
                    </td>
                </tr>`
      return
    }

    const frag = document.createDocumentFragment()

    data.forEach((r) => {
      const noTelarAsignado = s(r.NoTelarId)
      const hasTelar = noTelarAsignado !== ''
      const tr = document.createElement('tr')

      tr.className = hasTelar
        ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
        : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer'

      tr.dataset.disabled = hasTelar ? 'true' : 'false'
      if (!hasTelar) {
        tr.tabIndex = 0
        tr.setAttribute('aria-selected', 'false')
      }
      tr.dataset.tipo = s(r.Tipo)
      tr.dataset.itemId = s(r.ItemId)
      tr.dataset.configId = s(r.ConfigId)
      tr.dataset.inventSizeId = s(r.InventSizeId)
      tr.dataset.inventColorId = s(r.InventColorId)
      tr.dataset.inventLocationId = s(r.InventLocationId)
      tr.dataset.inventBatchId = s(r.InventBatchId)
      tr.dataset.wmsLocationId = s(r.WMSLocationId)
      tr.dataset.inventSerialId = s(r.InventSerialId)
      tr.dataset.noTelarId = noTelarAsignado
      tr.dataset.metros = s(r.Metros)
      tr.dataset.numJulio = s(r.InventSerialId)

      const kilos = fmt.num(r.InventQty, 0)
      const metros = fmt.num(r.Metros, 0)

      tr.innerHTML = `
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.ItemId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-medium ${fmt.tipoBadge(r.Tipo)}">
                        ${esc(r.Tipo)}
                    </span>
                </td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.ConfigId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.InventSizeId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.InventColorId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.InventBatchId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.WMSLocationId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${esc(r.InventSerialId)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${fmt.date(r.ProdDate)}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${metros}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center">${kilos}</td>
                <td class="px-3 py-1.5 text-sm text-gray-700 whitespace-nowrap text-center font-medium">${esc(noTelarAsignado)}</td>
            `

      frag.appendChild(tr)
    })

    tbody.appendChild(frag)
    selection.updateFiltroButton()
    // El tbody se reconstruye entero: hay que volver a pintar las piezas elegidas.
    selection.repaintInventario()
  },
}

/* ---------- Selección ---------- */
const selection = {
  clearVisualRow(row: HTMLTableRowElement | null): void {
    if (!row) return

    row.classList.remove('is-selected', 'bg-blue-600', 'text-white', 'bg-green-700', 'bg-yellow-50')

    row.style.removeProperty('background-color')
    row.style.removeProperty('color')
    row.style.removeProperty('border-left')

    row.setAttribute('aria-selected', 'false')
    row.querySelectorAll('td').forEach((td) => {
      td.classList.remove('text-white')
      td.style.removeProperty('color')
    })

    const hasBoth = row.dataset.hasBoth === 'true'
    const base = row.dataset.baseBg ?? (row.className.includes('bg-gray-50') ? 'bg-gray-50' : 'bg-white')

    row.className = `selectable-row hover:bg-blue-50 cursor-pointer ${hasBoth ? 'bg-blue-100 border-l-4 border-blue-400' : base}`
  },

  clearVisualInventario(row: HTMLTableRowElement | null): void {
    if (!row) return

    row.querySelector('.pu-slot')?.remove()
    row.setAttribute('aria-selected', 'false')
    row.classList.remove('is-selected', 'bg-green-700', 'text-white')
    row.style.removeProperty('background-color')
    row.style.removeProperty('color')

    row.querySelectorAll('td').forEach((td) => {
      td.classList.remove('text-white')
      td.style.removeProperty('color')
    })

    row.className =
      row.dataset.disabled === 'true'
        ? 'bg-green-100 selectable-row-inventario cursor-not-allowed opacity-75'
        : 'hover:bg-orange-50 selectable-row-inventario cursor-pointer'
  },

  clear(rerenderInventario = true): void {
    this.clearVisualRow($('#telaresTable .selectable-row.is-selected') as HTMLTableRowElement | null)
    $$('#inventarioTable .selectable-row-inventario.is-selected').forEach((r) =>
      this.clearVisualInventario(r as HTMLTableRowElement),
    )

    state.selectedTelar = null
    state.selectedInventario = null
    state.selectedInventarios = []
    state.selectedTelares = []
    this.updateContadorJulios()
    state.mostrarTodoInventario = false

    disable($('#btnProgramar'))
    disable($('#btnReservar'))
    disable($('#btnLiberarTelar'))

    this.updateFiltroButton()

    if (rerenderInventario && state.inventarioDataOriginal.length) {
      render.inventario(state.inventarioDataOriginal)
    }
  },

  updateFiltroButton(): void {
    const btn = $('#btnQuitarFiltroInventario')
    if (!btn) return

    if (state.selectedTelar) {
      btn.classList.remove('hidden')

      const icon = btn.querySelector('i')
      const text = btn.querySelector('span')

      if (state.mostrarTodoInventario) {
        if (icon) icon.className = 'fa-solid fa-filter'
        if (text) text.textContent = 'Aplicar Filtro'
        btn.title = 'Aplicar filtro y mostrar solo registros del telar seleccionado'
      } else {
        if (icon) icon.className = 'fa-solid fa-filter-circle-xmark'
        if (text) text.textContent = 'Quitar Filtro'
        btn.title = 'Quitar filtro y mostrar todos los registros'
      }
    } else {
      btn.classList.add('hidden')
    }
  },

  toggleTelarCheckbox(row: HTMLTableRowElement, checked: boolean): void {
    const cb = row.querySelector('.telar-checkbox') as HTMLInputElement | null
    const tipoUpper = s(row.dataset.tipo).toUpperCase().trim()

    const item: SelectedTelar = {
      id: s(row.dataset.id) || null,
      no_telar: s(row.dataset.telar) || null,
      tipo: normalizeTipo(tipoUpper),
      calibre: s(row.dataset.calibre),
      hilo: s(row.dataset.hilo),
      salon: s(row.dataset.salon),
      cuenta: s(row.dataset.cuenta),
      fecha: s(row.dataset.fecha),
      turno: s(row.dataset.turno),
      no_julio: s(row.dataset.noJulio),
      julios: s(row.dataset.julios).split(',').filter(Boolean),
      ordenes: s(row.dataset.ordenes).split(','),
      max_julios: Number(row.dataset.maxJulios) || 1,
      no_orden: s(row.dataset.noOrden),
      reservado: row.dataset.isReservado === 'true',
      programado: row.dataset.isProgramado === 'true',
      is_reservado: row.dataset.isReservado === 'true',
      is_programado: row.dataset.isProgramado === 'true',
      tipo_atado: 'Normal',
    }

    if ((item.is_reservado || item.is_programado) && checked) {
      toast('info', 'Telar no disponible', 'No se puede usar en selección múltiple')
      if (cb) cb.checked = false
      return
    }

    if (hasNoOrden(item) && checked) {
      toast('info', 'Telar con orden', 'No se puede usar en selección múltiple')
      if (cb) cb.checked = false
      return
    }

    if (!Array.isArray(state.selectedTelares)) {
      state.selectedTelares = []
    }

    if (checked) {
      if (state.selectedTelares.length) {
        const ref = state.selectedTelares[0] as SelectedTelar

        if (isReservado(ref) || isProgramado(ref)) {
          toast('info', 'Telar no disponible en seleccion', 'No se puede agregar mas telares a una seleccion con telares reservados o programados', 3000)
          if (cb) cb.checked = false
          return
        }

        if (hasNoOrden(ref)) {
          toast('info', 'Telar con orden en seleccion', 'No se puede agregar mas telares a una seleccion que contiene telares con orden', 3000)
          if (cb) cb.checked = false
          return
        }

        if (!sameGroup(item, ref)) {
          toast('warning', 'Seleccion incompatible', 'Solo puedes seleccionar telares con el mismo Tipo, Calibre y Salon. La cuenta puede variar.', 3000)
          if (cb) cb.checked = false
          return
        }
      }

      const exists = item.id
        ? state.selectedTelares.some((t) => String(t.id) === String(item.id))
        : state.selectedTelares.some((t) => t.no_telar === item.no_telar && eq.str(t.tipo, item.tipo))

      if (!exists) state.selectedTelares.push(item)

      row.classList.add('bg-yellow-50')
      row.style.setProperty('border-left', '3px solid #eab308', 'important')
    } else {
      state.selectedTelares = state.selectedTelares.filter((t) => {
        if (item.id) return String(t.id) !== String(item.id)
        return !(t.no_telar === item.no_telar && eq.str(t.tipo, item.tipo))
      })
      row.classList.remove('bg-yellow-50')
      row.style.removeProperty('border-left')
    }

    this.validateButtons()
  },

  applyTelar(row: HTMLTableRowElement): void {
    const prev = $('#telaresTable .selectable-row.is-selected') as HTMLTableRowElement | null
    if (prev && prev !== row) this.clearVisualRow(prev)

    const hasBoth = row.dataset.hasBoth === 'true'

    row.className = `selectable-row is-selected cursor-pointer ${hasBoth ? 'border-l-4 border-blue-300' : ''}`

    row.setAttribute('aria-selected', 'true')
    // blue-600: con blanco encima da 5.1:1. El blue-500 anterior se quedaba en 3.7:1.
    row.classList.add('bg-blue-600', 'text-white')
    row.style.setProperty('background-color', '#2563eb', 'important')
    row.style.setProperty('color', '#fff', 'important')

    row.querySelectorAll('td').forEach((td) => {
      td.classList.add('text-white')
      td.style.setProperty('color', '#fff', 'important')
    })

    const tipoOk = normalizeTipo(row.dataset.tipo)

    state.selectedTelar = {
      id: s(row.dataset.id) || null,
      no_telar: s(row.dataset.telar) || null,
      tipo: tipoOk,
      cuenta: s(row.dataset.cuenta),
      salon: s(row.dataset.salon),
      calibre: s(row.dataset.calibre),
      hilo: s(row.dataset.hilo),
      no_julio: s(row.dataset.noJulio),
      julios: s(row.dataset.julios).split(',').filter(Boolean),
      ordenes: s(row.dataset.ordenes).split(','),
      max_julios: Number(row.dataset.maxJulios) || 1,
      no_orden: s(row.dataset.noOrden),
      fecha: s(row.dataset.fecha),
      turno: s(row.dataset.turno),
      tipo_atado: (row.querySelector('.tipo-atado-select') as HTMLSelectElement | null)?.value ?? 'Normal',
      reservado: row.dataset.isReservado === 'true',
      programado: row.dataset.isProgramado === 'true',
      is_reservado: row.dataset.isReservado === 'true',
      is_programado: row.dataset.isProgramado === 'true',
    }

    // Cambiar de telar descarta las piezas elegidas para el anterior.
    state.selectedInventario = null
    state.selectedInventarios = []
    this.updateContadorJulios()

    this.validateButtons()
    this.updateFiltroButton()

    if (state.inventarioDataOriginal.length) {
      state.mostrarTodoInventario = false
      render.inventario(state.inventarioDataOriginal)

      // Solo rizo/pie preselecciona su pieza. En una barra, marcarla sola estorbaria
      // al elegir los julios que faltan.
      if (state.selectedTelar.no_julio && (state.selectedTelar.max_julios || 1) <= 1) {
        const noJulio = state.selectedTelar.no_julio
        setTimeout(() => {
          const match = $$('#inventarioTable .selectable-row-inventario').find(
            (r) => (r as HTMLTableRowElement).dataset.inventSerialId === noJulio,
          ) as HTMLTableRowElement | undefined
          if (match) this.applyInventario(match)
        }, 100)
      }
    }
  },

  applyInventario(row: HTMLTableRowElement): void {
    const tel = state.selectedTelar

    if (tel?.tipo) {
      const telTipo = s(tel.tipo).toUpperCase().trim()
      const invTipo = s(row.dataset.tipo).toUpperCase().trim()
      if (telTipo && invTipo && telTipo !== invTipo) {
        toast('warning', 'Tipo distinto', 'El tipo de la pieza no coincide con el telar', 1800)
        return
      }
    }

    const item = this.itemDeFila(row)

    const max = tel?.max_julios ?? 1
    const huecos = max - (tel?.julios.length ?? 0)

    if (max <= 1) {
      // Rizo y pie toman un solo julio: la seleccion se reemplaza, como siempre.
      state.selectedInventarios = [item]
    } else {
      // Una barra de Karl Mayer admite hasta cuatro: se acumulan y cada una se pinta distinto.
      const ix = state.selectedInventarios.findIndex((i) => i.inventSerialId === item.inventSerialId)
      if (ix > -1) {
        state.selectedInventarios.splice(ix, 1)
      } else if (huecos <= 0) {
        toast('info', 'Barra llena', `La barra ${tel?.tipo} ya tiene sus ${max} julios`, 2500)
        return
      } else if (state.selectedInventarios.length >= huecos) {
        toast('info', 'Limite de julios', `Solo quedan ${huecos} julio(s) libres en esta barra`, 2500)
        return
      } else {
        state.selectedInventarios.push(item)
      }
    }

    // El resto del flujo sigue leyendo una sola pieza: se deja la ultima elegida.
    state.selectedInventario = state.selectedInventarios.at(-1) ?? null

    this.repaintInventario()
    this.validateButtons()
  },

  /** Pieza de inventario a partir del dataset de su fila. */
  itemDeFila(row: HTMLTableRowElement): SelectedInventario {
    return {
      itemId: s(row.dataset.itemId),
      configId: s(row.dataset.configId),
      inventSizeId: s(row.dataset.inventSizeId),
      inventColorId: s(row.dataset.inventColorId),
      inventLocationId: s(row.dataset.inventLocationId),
      inventBatchId: s(row.dataset.inventBatchId),
      wmsLocationId: s(row.dataset.wmsLocationId),
      inventSerialId: s(row.dataset.inventSerialId),
      metros: parseFloat(s(row.dataset.metros, '0')) || 0,
      numJulio: s(row.dataset.numJulio),
      tipo: s(row.dataset.tipo),
      data: state.inventarioData.find(
        (i) => s(i.ItemId) === s(row.dataset.itemId) && s(i.InventSerialId) === s(row.dataset.inventSerialId),
      ),
    }
  },

  /**
   * Marca de golpe los julios libres del mismo lote, hasta llenar la barra.
   * El lote sale de la primera pieza elegida y, si no hay, del No. Orden del telar.
   */
  seleccionarLote(): void {
    const tel = state.selectedTelar
    if (!tel || (tel.max_julios || 1) <= 1) return

    const lote = s(state.selectedInventarios[0]?.inventBatchId || tel.no_orden).trim()
    if (!lote) {
      toast('info', 'Sin lote', 'Selecciona primero un julio para saber de que lote', 2500)
      return
    }

    const huecos = (tel.max_julios || 1) - tel.julios.length
    if (huecos <= 0) {
      toast('info', 'Barra llena', `La barra ${tel.tipo} ya tiene sus ${tel.max_julios} julios`, 2500)
      return
    }

    // El tipo se valida aqui tambien: con "Quitar Filtro" activo la tabla muestra
    // piezas de otras barras y el lote se las llevaria de corbata.
    // Un julio KM sin tipo entra en cualquier barra.
    const telTipo = s(tel.tipo).toUpperCase().trim()
    const candidatas = ($$('#inventarioTable .selectable-row-inventario') as HTMLTableRowElement[])
      .filter(
        (r) =>
          r.dataset.disabled !== 'true' &&
          s(r.dataset.inventBatchId).trim() === lote &&
          (!telTipo || s(r.dataset.tipo).trim() === '' || s(r.dataset.tipo).toUpperCase().trim() === telTipo),
      )
      .slice(0, huecos)

    if (!candidatas.length) {
      toast('info', 'Sin julios', `No hay julios libres del lote ${lote}`, 2500)
      return
    }

    state.selectedInventarios = candidatas.map((r) => this.itemDeFila(r))
    state.selectedInventario = state.selectedInventarios.at(-1) ?? null

    this.repaintInventario()
    this.validateButtons()
    toast('success', `${candidatas.length} julio(s) del lote ${lote}`, '', 2000)
  },

  /** Repinta la tabla de inventario segun `state.selectedInventarios`. */
  repaintInventario(): void {
    const rows = $$('#inventarioTable .selectable-row-inventario') as HTMLTableRowElement[]
    rows.forEach((r) => this.clearVisualInventario(r))

    const sel = state.selectedInventarios
    sel.forEach((item, i) => {
      const row = rows.find((r) => r.dataset.inventSerialId === item.inventSerialId)
      if (!row) return

      row.setAttribute('aria-selected', 'true')
      // green-700: 5.6:1 con texto blanco. El emerald-500 anterior daba 2.6:1.
      row.classList.add('is-selected', 'bg-green-700', 'text-white')
      row.style.setProperty('background-color', '#047857', 'important')
      row.style.setProperty('color', '#fff', 'important')
      row.querySelectorAll('td').forEach((td) => {
        td.classList.add('text-white')
        td.style.setProperty('color', '#fff', 'important')
      })

      if (sel.length > 1) {
        const first = row.querySelector('td')
        if (first) {
          const badge = document.createElement('span')
          badge.className =
            'pu-slot mr-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-black/25 text-[10px] font-bold align-middle'
          badge.textContent = String(i + 1)
          first.prepend(badge)
        }
      }
    })

    this.updateContadorJulios()
  },

  /** Contador de julios de la barra: los ya reservados y los que se van a reservar. */
  updateContadorJulios(): void {
    const box = $('#puJuliosContador')
    const btnLote = $('#btnSeleccionarLote')

    const tel = state.selectedTelar
    const max = tel?.max_julios ?? 1
    const esBarra = !!tel && max > 1

    btnLote?.classList.toggle('hidden', !esBarra)

    if (!box) return
    if (!esBarra || !tel) {
      box.classList.add('hidden')
      return
    }

    const pendientes = state.selectedInventarios.length
    box.classList.remove('hidden')
    box.textContent = `Julios ${tel.julios.length}/${max}${pendientes ? ` (+${pendientes} por reservar)` : ''}`
  },

  validateButtons(): void {
    const btnProgramar = $('#btnProgramar')
    const btnReservar = $('#btnReservar')
    const btnLiberarTelar = $('#btnLiberarTelar')

    // Reservar: telar + inventario del mismo tipo (Rizo/Pie, o la barra 1..4 en KM).
    // Un julio KM sin tipo se puede reservar en cualquier barra.
    const telSel = state.selectedTelar
    const invSel = state.selectedInventarios
    const tiposMatch =
      telSel && invSel.length > 0
        ? invSel.every((i) => {
            const invTipo = s(i.tipo || i.data?.Tipo).trim()
            if (!invTipo) return true
            return eq.str(telSel.tipo, invTipo)
          })
        : false

    // Liberar telar: sólo telar individual y reservado
    if (state.selectedTelar && isReservado(state.selectedTelar)) {
      // Una barra de KM con columnas libres sigue admitiendo julio; rizo y pie no.
      const quedanHuecos = state.selectedTelar.julios.length < (state.selectedTelar.max_julios || 1)
      disable(btnReservar, !(quedanHuecos && invSel.length > 0 && tiposMatch))
      disable(btnProgramar, true)
      disable(btnLiberarTelar, false)
      return
    }

    disable(btnLiberarTelar, true)

    const canReservar = !!(state.selectedTelar && invSel.length > 0 && tiposMatch)
    disable(btnReservar, !canReservar)

    const hasMultiple = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0
    const hasIndividual = !!(state.selectedTelar && state.selectedTelar.no_telar)

    // Programar (prioridad selección múltiple)
    if (hasMultiple) {
      const hasReserved = state.selectedTelares.some(isReservado)
      const hasProgramado = state.selectedTelares.some(isProgramado)
      const hasOrden = state.selectedTelares.some(hasNoOrden)
      disable(btnProgramar, hasReserved || hasProgramado || hasOrden)
      return
    }

    if (hasIndividual && state.selectedTelar) {
      const tel = state.selectedTelar
      disable(btnProgramar, isReservado(tel) || isProgramado(tel) || hasNoOrden(tel))
      return
    }

    disable(btnProgramar, true)
  },
}

/* ---------- Filtros ---------- */
const filters = {
  updateBadge(): void {
    const total = Object.keys(state.filters.telares ?? {}).length + Object.keys(state.filters.inventario ?? {}).length
    const badge = $('#filterCount')
    if (!badge) return
    badge.textContent = String(total)
    badge.classList.toggle('hidden', total === 0)
  },

  filterLocal(data: RawRow[], list: Array<{ columna?: string; column?: string; valor?: unknown; value?: unknown }>): RawRow[] {
    if (!list?.length || !data?.length) return data

    return data.filter((item) =>
      list.every((f) => {
        const col = f.columna ?? f.column
        const val = s(f.valor ?? f.value).toLowerCase().trim()

        if (!col || !val) return true

        const itemVal: unknown = item[col]

        if (col === 'estado') {
          return estadoDe(item).includes(val)
        }

        if (['reservado', 'programado'].includes(col)) {
          const normalized = ['1', 'true', 'si', 'sí', 'yes', 'activo', 'reservado', 'programado'].includes(val)
          return Boolean(itemVal) === normalized
        }

        if (col === 'NoTelarId') {
          if (['null', 'vacío', 'vacio', 'disponible', ''].includes(val)) {
            return !itemVal
          }
          return s(itemVal).toLowerCase().includes(val)
        }

        if (itemVal == null || itemVal === '') return false

        if (col === 'InventSizeId') {
          return s(itemVal).toLowerCase().startsWith(val)
        }

        if (['fecha', 'ProdDate'].includes(col)) {
          try {
            const dItem = new Date(s(itemVal))
            const dFil = new Date(val)
            if (!Number.isNaN(dItem.getTime()) && !Number.isNaN(dFil.getTime())) {
              return dItem.toDateString() === dFil.toDateString()
            }
          } catch {
            /* cae al includes genérico */
          }
        }

        if (['calibre', 'metros', 'InventQty', 'Metros'].includes(col)) {
          const a = parseFloat(s(itemVal))
          const b = parseFloat(val)
          if (!Number.isNaN(a) && !Number.isNaN(b)) {
            return Math.abs(a - b) < 0.001 || s(itemVal).toLowerCase().includes(val)
          }
        }

        return s(itemVal).toLowerCase().includes(val)
      }),
    )
  },

  async openModal(): Promise<void> {
    const Swal = puWindow.Swal as SwalClient | undefined
    if (!Swal) return
    const swalFire = Swal.fire
    const options = (type: TableKey): string =>
      (state.columns[type] ?? []).map((c) => `<option value="${c.field}">${c.label}</option>`).join('')

    const row = (type: TableKey, idx: number | string, col = '', val = ''): string => `
            <div class="filter-row" data-idx="${idx}">
                <div class="grid grid-cols-2 gap-3 p-3 rounded-md bg-gray-50 border border-gray-200 mb-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Columna</label>
                        <select class="filter-col w-full px-2 py-2 border border-gray-300 rounded-md">
                            ${options(type)}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Valor</label>
                        <input type="text"
                               class="filter-val w-full px-2 py-2 border border-gray-300 rounded-md"
                               value="${val}">
                    </div>
                    <div class="col-span-2 flex justify-end">
                        <button type="button"
                                class="btn-rm px-3 py-1.5 bg-red-500 text-white rounded-md">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>`

    const renderRows = (type: TableKey, list: ColumnFilter[]): string => list.map((f) => row(type, f.idx, f.column, f.value)).join('')

    const initialTelares: ColumnFilter[] = activeFilters('telares').map((f, i) => ({
      table: 'telares' as TableKey,
      column: f.column,
      value: f.value,
      idx: Date.now() + i,
    }))

    const html = `
            <div id="swalFilterContainer" class="text-left">
                <div class="mb-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tabla</label>
                    <select id="swalTableSel"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="telares" selected>Programación de Requerimientos</option>
                        <option value="inventario">Inventario Disponible</option>
                    </select>
                </div>
                <div id="swalFilters" class="max-h-[420px] overflow-y-auto">
                    ${renderRows('telares', initialTelares.length ? initialTelares : [{ table: 'telares', column: '', value: '', idx: Date.now() }])}
                </div>
                <button type="button" id="swalAdd"
                        class="w-full mt-3 px-3 py-2 border-2 border-dashed border-gray-300 rounded-md text-gray-500">
                    <i class="fa-solid fa-plus mr-1"></i> Agregar filtro
                </button>
            </div>`

    const result = await swalFire({
      title: 'Filtrar Tablas',
      html,
      width: '700px',
      showCancelButton: true,
      confirmButtonText: 'Aplicar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#9333ea',
      cancelButtonColor: '#6b7280',
      didOpen: () => {
        const container = $('#swalFilterContainer')
        const addBtn = $('#swalAdd') as HTMLButtonElement | null
        const selectTable = $('#swalTableSel') as HTMLSelectElement | null

        const bindRemove = (): void => {
          container?.querySelectorAll<HTMLElement>('.btn-rm').forEach((btn) => {
            btn.onclick = (e) => (e.currentTarget as HTMLElement | null)?.closest('.filter-row')?.remove()
          })
        }

        bindRemove()

        if (addBtn) {
          addBtn.onclick = () => {
            $('#swalFilters')?.insertAdjacentHTML('beforeend', row((selectTable?.value ?? 'telares') as TableKey, Date.now()))
            bindRemove()
          }
        }

        if (selectTable) {
          selectTable.onchange = (e) => {
            const type = ((e.currentTarget as HTMLSelectElement | null)?.value ?? 'telares') as TableKey
            const list = activeFilters(type)
            const mapped: ColumnFilter[] = list.length
              ? list.map((f, i) => ({ table: type, column: f.column, value: f.value, idx: Date.now() + i }))
              : [{ table: type, column: '', value: '', idx: Date.now() }]
            const box = $('#swalFilters')
            if (box) box.innerHTML = renderRows(type, mapped)
            bindRemove()
          }
        }
      },
      preConfirm: () => {
        const type = (($('#swalTableSel') as HTMLSelectElement | null)?.value ?? 'telares') as TableKey
        const list = $$('#swalFilters .filter-row')
          .map((div) => {
            const col = (div.querySelector('.filter-col') as HTMLSelectElement | null)?.value?.trim() ?? ''
            const val = (div.querySelector('.filter-val') as HTMLInputElement | null)?.value?.trim() ?? ''
            return col && val ? { columna: col, valor: val } : null
          })
          .filter((x): x is { columna: string; valor: string } => x !== null)

        if (!list.length) {
          Swal.showValidationMessage('Agrega al menos un filtro válido')
          return false
        }

        return { type, filters: list }
      },
    })

    if (!result.isConfirmed) return

    const payload = result.value as { type?: unknown; filters?: unknown } | undefined
    const type: TableKey = payload?.type === 'inventario' ? 'inventario' : 'telares'
    const list = (Array.isArray(payload?.filters) ? payload.filters : []) as Array<{ columna?: unknown; valor?: unknown }>

    const original = (
      type === 'telares'
        ? state.telaresDataOriginal.length
          ? state.telaresDataOriginal
          : state.telaresData
        : state.inventarioDataOriginal.length
          ? state.inventarioDataOriginal
          : state.inventarioData
    ) as RawRow[]

    const normalized = list
      .filter((f) => s(f.columna).trim() && s(f.valor).trim())
      .map((f) => ({ columna: s(f.columna).trim(), valor: s(f.valor).trim() }))
    const filtered = filters.filterLocal(original, normalized)
    if (type === 'telares') render.telares(filtered)
    else render.inventario(filtered)

    state.filters[type] = normalized.map((f, i) => ({ table: type, column: f.columna, value: f.valor, idx: Date.now() + i }))

    filters.updateBadge()
    toast('success', `${normalized.length} filtro(s) aplicados`, '', 2000)
  },

  applyColumnFilter(table: TableKey, column: string, value: string): void {
    const next = s(value).trim()
    const map = mapFilters(table)
    if (!next) {
      delete map[column]
    } else {
      map[column] = next
    }

    if (table === 'telares') render.telares(state.telaresDataOriginal)
    if (table === 'inventario') render.inventario(state.inventarioDataOriginal)
  },

  clearColumnFilter(table: TableKey, column: string): void {
    delete mapFilters(table)[column]
    if (table === 'telares') render.telares(state.telaresDataOriginal)
    if (table === 'inventario') render.inventario(state.inventarioDataOriginal)
  },

  clearTable(table: TableKey): void {
    state.filters[table] = {}
    if (table === 'telares') render.telares(state.telaresDataOriginal)
    if (table === 'inventario') render.inventario(state.inventarioDataOriginal)
  },

  reset(): void {
    if (state.telaresDataOriginal.length) {
      render.telares(state.telaresDataOriginal)
    }

    if (state.inventarioDataOriginal.length) {
      render.inventario(state.inventarioDataOriginal)
    }

    state.filters = { telares: {}, inventario: {} }
    filters.updateBadge()

    toast('success', 'Filtros restablecidos', '', 1500)
  },
}

/* ---------- Sorting ---------- */
const sorting = {
  toggle(table: TableKey, col: string, additive = false): void {
    const current = Array.isArray(state.sort[table]) ? [...state.sort[table]] : []
    const idx = current.findIndex((sr) => sr.column === col)

    if (!additive) {
      if (idx === 0) {
        const first = current[0] as SortRule
        if (first.direction === 'asc') {
          state.sort[table] = [{ column: col, direction: 'desc' }]
        } else {
          state.sort[table] = []
        }
      } else {
        state.sort[table] = [{ column: col, direction: 'asc' }]
      }
    } else if (idx === -1) {
      current.push({ column: col, direction: 'asc' })
      state.sort[table] = current
    } else if ((current[idx] as SortRule).direction === 'asc') {
      ;(current[idx] as SortRule).direction = 'desc'
      state.sort[table] = current
    } else {
      current.splice(idx, 1)
      state.sort[table] = current
    }

    if (table === 'telares') render.telares(state.telaresDataOriginal)
    if (table === 'inventario') render.inventario(state.inventarioDataOriginal)
  },
  bind(): void {
    $('#telaresTable thead')?.addEventListener('click', (e: MouseEvent) => {
      const th = (e.target as Element | null)?.closest('.sortable') as HTMLElement | null
      if (!th) return

      const col = th.dataset.column
      if (!col) return

      sorting.toggle('telares', col, e.shiftKey || e.ctrlKey || e.metaKey)
    })

    $('#inventarioTable thead')?.addEventListener('click', (e: MouseEvent) => {
      const th = (e.target as Element | null)?.closest('.sortable-inventario') as HTMLElement | null
      if (!th) return

      const col = th.dataset.column
      if (!col) return

      sorting.toggle('inventario', col, e.shiftKey || e.ctrlKey || e.metaKey)
    })
  },
}

/* ---------- Acciones ---------- */
const actions = {
  programar(): void {
    if (!CAN_CREAR) {
      toast('warning', 'No tiene permiso para programar')
      return
    }
    const multiple = Array.isArray(state.selectedTelares) && state.selectedTelares.length > 0
    const tel = state.selectedTelar

    // Selección múltiple => programar requerimientos
    if (multiple) {
      const hasProgramado = state.selectedTelares.some(isProgramado)
      const hasOrden = state.selectedTelares.some(hasNoOrden)
      if (hasProgramado) {
        toast('info', 'Telar programado', 'No se puede programar un telar que ya está programado')
        return
      }
      if (hasOrden) {
        toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden')
        return
      }

      const telaresJson = encodeURIComponent(JSON.stringify(state.selectedTelares))
      const url = `${API.programarRequerimientos}?telares=${telaresJson}`

      sessionStorage.setItem('selectedTelares', JSON.stringify(state.selectedTelares))
      window.location.href = url
      return
    }

    // Selección individual
    if (tel && tel.no_telar && !isReservado(tel) && !isProgramado(tel)) {
      if (hasNoOrden(tel)) {
        toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden')
        return
      }

      const base = state.telaresDataOriginal.length ? state.telaresDataOriginal : state.telaresData

      const telarCompleto = tel.id
        ? base.find((t) => s(t.id) === String(tel.id))
        : base.find(
            (t) =>
              s(t.no_telar) === s(tel.no_telar) &&
              s(t.tipo).toUpperCase().trim() === s(tel.tipo).toUpperCase().trim(),
          )

      const telarArray = [
        {
          id: tel.id ?? (telarCompleto ? s(telarCompleto.id) || null : null),
          no_telar: tel.no_telar,
          tipo: tel.tipo,
          cuenta: tel.cuenta || (telarCompleto ? s(telarCompleto.cuenta) : ''),
          salon: tel.salon || (telarCompleto ? s(telarCompleto.salon) : ''),
          calibre: tel.calibre || (telarCompleto ? s(telarCompleto.calibre) : ''),
          hilo: tel.hilo || (telarCompleto ? s(telarCompleto.hilo) : ''),
          fecha: tel.fecha || (telarCompleto ? s(telarCompleto.fecha) : ''),
          turno: tel.turno || (telarCompleto ? s(telarCompleto.turno) : ''),
          tipo_atado: tel.tipo_atado || (telarCompleto ? s(telarCompleto.tipo_atado, 'Normal') : 'Normal'),
        },
      ]

      const telaresJson = encodeURIComponent(JSON.stringify(telarArray))
      const url = `${API.programarRequerimientos}?telares=${telaresJson}`

      sessionStorage.setItem('selectedTelares', JSON.stringify(telarArray))
      window.location.href = url
      return
    }

    if (!tel?.no_telar) {
      toast('warning', 'Selecciona un telar')
      return
    }

    if (isReservado(tel)) {
      toast('info', 'Telar reservado', 'No se puede programar un telar reservado')
      return
    }

    if (isProgramado(tel)) {
      toast('info', 'Telar programado', 'No se puede volver a programar un telar ya programado')
      return
    }

    if (hasNoOrden(tel)) {
      toast('info', 'Telar con orden', 'No se puede programar un telar que ya tiene No. Orden')
    }
  },

  async liberarTelar(): Promise<void> {
    if (!CAN_ELIMINAR) {
      toast('warning', 'No tiene permiso para liberar')
      return
    }
    const Swal = puWindow.Swal as SwalClient | undefined
    if (!Swal) return
    const tel = state.selectedTelar

    if (!tel?.no_telar) {
      void Swal.fire('Aviso', 'Selecciona un telar primero', 'warning')
      return
    }

    if (!isReservado(tel)) {
      void Swal.fire('Aviso', 'Este telar no está reservado', 'warning')
      return
    }

    const ok = await Swal.fire({
      title: '¿Liberar telar?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Si, liberar',
      confirmButtonColor: '#dc2626',
    }).then((r) => r.isConfirmed)

    if (!ok) return

    // Sin loader ni refetch: el endpoint devuelve el telar ya liberado en `data`.
    // Se aplica optimista y solo se refresca el inventario en silencio.
    const btn = $('#btnLiberarTelar')
    disable(btn, true)

    const telNo = tel.no_telar
    const telId = tel.id
    const telTipo = s(tel.tipo).toUpperCase().trim()
    const matchRow = (r: RawRow): boolean =>
      telId ? s(r.id) === telId : s(r.no_telar) === telNo && s(r.tipo).toUpperCase().trim() === telTipo

    const prevData = structuredClone(state.telaresData) as RawRow[]
    const prevOriginal = structuredClone(state.telaresDataOriginal) as RawRow[]

    const applyLiberado = (d: RawRow | undefined): void => {
      for (const base of [state.telaresData, state.telaresDataOriginal]) {
        const ix = base.findIndex(matchRow)
        if (ix === -1) continue
        const row = base[ix] as RawRow
        row.metros = d?.metros ?? 0
        row.no_julio = s(d?.no_julio)
        row.no_julio2 = ''
        row.no_julio3 = ''
        row.no_julio4 = ''
        row.julios = []
        row.no_orden = s(d?.no_orden)
        row.no_orden2 = ''
        row.no_orden3 = ''
        row.no_orden4 = ''
        row.ordenes = []
        // La fibra no se pierde al liberar: solo se refresca con lo que devuelve el servidor.
        if (d) row.hilo = s(d.hilo)
        row.reservado = false
        row.is_reservado = false
        row.programado = false
        row.is_programado = false
      }
      render.telares(state.telaresData)
    }

    applyLiberado(undefined)
    selection.clear(false)
    toast('success', `Telar ${telNo} liberado`, '', 2500)

    try {
      const resp = await http.post(API.liberarTelar, {
        id: telId ?? null,
        no_telar: telNo,
        tipo: tel.tipo,
      })

      if (resp.success) {
        const updated = asRows(resp.data)[0]
        if (updated) applyLiberado(updated)
        if (resp.message) toast('success', resp.message, '', 3000)
      } else {
        throw new Error(resp.message ?? 'No se pudo liberar')
      }

      // La pieza liberada vuelve al inventario: refresh silencioso, sin loader.
      http
        .get(API.inventarioDisponibleGet)
        .then((inv) => {
          const rows = asRows(inv?.data)
          if (rows.length) {
            state.inventarioDataOriginal = JSON.parse(JSON.stringify(rows)) as RawRow[]
            render.inventario(rows)
            selection.updateFiltroButton()
          }
        })
        .catch(() => {
          /* se reconcilia en la próxima selección */
        })
    } catch (e) {
      state.telaresData = prevData
      state.telaresDataOriginal = prevOriginal
      render.telares(state.telaresData)
      void Swal.fire('Error', e instanceof Error ? e.message : 'Error al liberar', 'error')
    } finally {
      selection.validateButtons()
    }
  },

  async reservar(): Promise<void> {
    if (!CAN_MODIFICAR) {
      toast('warning', 'No tiene permiso para reservar')
      return
    }
    const Swal = puWindow.Swal as SwalClient | undefined
    if (!Swal) return
    const tel = state.selectedTelar

    if (!tel?.no_telar) {
      void Swal.fire('Aviso', 'Selecciona un telar', 'warning')
      return
    }

    // Validar que el telar tenga ID (requerido para identificar el registro específico)
    if (!tel?.id) {
      void Swal.fire('Error', 'No se pudo identificar el registro del telar. Por favor, selecciona el telar nuevamente.', 'error')
      return
    }

    const quedanHuecos = tel.julios.length < (tel.max_julios || 1)
    if (isReservado(tel) && !quedanHuecos) {
      void Swal.fire('Aviso', 'Este telar ya está reservado', 'warning')
      return
    }

    const piezas = state.selectedInventarios.filter((i) => i.data)
    if (!piezas.length) {
      void Swal.fire('Aviso', 'Selecciona una fila de inventario', 'warning')
      return
    }

    if (tel.julios.some((j) => piezas.some((i) => i.inventSerialId === j))) {
      void Swal.fire('Aviso', 'Ese julio ya está en esta barra', 'warning')
      return
    }

    // Una barra no admite mas julios de los que le quedan libres.
    const huecos = (tel.max_julios || 1) - tel.julios.length
    if (piezas.length > huecos) {
      void Swal.fire('Aviso', `Solo quedan ${huecos} julio(s) libres en esta barra`, 'warning')
      return
    }

    // Validar que el tipo coincida (Rizo/Pie o la barra 1..4)
    const telTipo = s(tel.tipo).trim()
    const distinta = piezas.find((i) => {
      const invTipo = s(i.data?.Tipo ?? i.tipo).trim()
      return invTipo && telTipo && !eq.str(invTipo, telTipo)
    })
    if (distinta) {
      void Swal.fire('Advertencia', 'El tipo de la pieza no coincide con el telar.', 'warning')
      return
    }

    if (piezas.some((i) => i.data?.NoTelarId)) {
      void Swal.fire('Aviso', 'Esa pieza ya tiene telar asignado', 'warning')
      return
    }

    const ok = await Swal.fire({
      title: piezas.length > 1 ? `¿Reservar ${piezas.length} julios?` : '¿Reservar pieza?',
      text: `Reservar para telar ${tel.no_telar} (${etiquetaTipo(tel)})`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Si, reservar',
    }).then((r) => r.isConfirmed)

    if (!ok) return

    setLoading(true)

    // Snapshot para revertir la edición optimista si el POST falla.
    const prevData = structuredClone(state.telaresData) as RawRow[]
    const prevOriginal = structuredClone(state.telaresDataOriginal) as RawRow[]
    // Julios ya escritos: si el lote falla a medias, el snapshot los borraria.
    let confirmadas = 0

    try {
      // El telar se marca dentro de la misma transaccion que crea la reserva
      const tTipo = normalizeTipo(tel.tipo).toUpperCase()
      const mismoRegistro = (x: RawRow): boolean =>
        tel.id
          ? s(x.id) === tel.id
          : s(x.no_telar) === s(tel.no_telar) && s(x.tipo).toUpperCase().trim() === tTipo

      const aplicarReservaLocal = (row: RawRow, pieza: SelectedInventario, lote: string): void => {
        const serial = pieza.numJulio || ''
        const esBarra = (Number(row.max_julios) || tel.max_julios || 1) > 1
        row.metros = pieza.metros || 0
        if (!esBarra) {
          row.no_julio = serial
          row.no_orden = lote
          row.julios = serial ? [serial] : []
          row.ordenes = lote ? [lote] : []
          return
        }
        const julios = rowJulios(row)
        const ordenes = rowOrdenes(row)
        if (serial && !julios.includes(serial)) {
          julios.push(serial)
          ordenes.push(lote)
        }
        row.julios = julios
        row.ordenes = ordenes
        row.no_julio = julios[0] ?? ''
        row.no_orden = ordenes[0] ?? ''
        row.reservado = true
      }

      // Un POST por julio: el backend (acomodarJulio) coloca cada uno en la
      // primera columna libre de la barra, asi que van en serie, no en paralelo.
      for (const pieza of piezas) {
        const it = pieza.data as RawRow
        const lote = pieza.inventBatchId || s(it.InventBatchId)
        const localidad = pieza.wmsLocationId || s(it.WMSLocationId)

        const ix = state.telaresData.findIndex(mismoRegistro)
        if (ix > -1) {
          aplicarReservaLocal(state.telaresData[ix] as RawRow, pieza, lote)

          const jx = state.telaresDataOriginal.findIndex(mismoRegistro)
          if (jx > -1) aplicarReservaLocal(state.telaresDataOriginal[jx] as RawRow, pieza, lote)

          render.telares(state.telaresData)
        }

        const payload: Record<string, unknown> = {
          NoTelarId: tel.no_telar,
          SalonTejidoId: tel.salon || null,
          ItemId: it.ItemId,
          ConfigId: it.ConfigId ?? null,
          InventSizeId: it.InventSizeId ?? null,
          InventColorId: it.InventColorId ?? null,
          InventLocationId: it.InventLocationId ?? null,
          InventBatchId: it.InventBatchId ?? null,
          WMSLocationId: it.WMSLocationId ?? null,
          InventSerialId: it.InventSerialId ?? null,
          Tipo: normalizeTipo(tel.tipo),
          Metros: it.Metros ?? null,
          InventQty: it.InventQty ?? null,
          ProdDate: it.ProdDate ?? null,
          fecha: tel.fecha || null,
          turno: tel.turno || null,
          tej_inventario_telares_id: parseInt(tel.id, 10),
          telar: {
            metros: pieza.metros || 0,
            no_julio: pieza.numJulio || '',
            no_orden: lote,
            localidad,
          },
        }

        await http.post(API.reservarInventario, payload)
        confirmadas += 1
      }

      state.selectedInventario = null
      state.selectedInventarios = []

      const [inv, telrs] = await Promise.all([http.get(API.inventarioDisponibleGet), http.get(API.inventarioTelares)])

      const invRows = asRows(inv?.data)
      if (invRows.length) {
        state.inventarioDataOriginal = JSON.parse(JSON.stringify(invRows)) as RawRow[]
        render.inventario(invRows)
        selection.updateFiltroButton()
      }

      const telRows = asRows(telrs?.data)
      if (telRows.length) {
        state.telaresDataOriginal = JSON.parse(JSON.stringify(telRows)) as RawRow[]
        state.telaresData = telRows
        render.telares(telRows)

        setTimeout(() => {
          // Por id: un mismo telar puede tener la misma barra dos veces, con fechas
          // distintas (son dos requerimientos), y telar+tipo agarraba la otra fila.
          const found = $$('#telaresTable .selectable-row').find((r) =>
            tel.id
              ? (r as HTMLTableRowElement).dataset.id === tel.id
              : (r as HTMLTableRowElement).dataset.telar === tel.no_telar &&
                s((r as HTMLTableRowElement).dataset.tipo).toUpperCase().trim() === tTipo,
          ) as HTMLTableRowElement | undefined
          if (found) selection.applyTelar(found)
        }, 100)
      }

      toast('success', piezas.length > 1 ? `${piezas.length} julios reservados` : 'Pieza reservada', '', 2500)
    } catch (e) {
      if (confirmadas > 0) {
        // El lote fallo a medias: esos julios ya estan en la base, asi que volver
        // al snapshot los borraria de la pantalla. Se recarga lo que haya guardado.
        const telrs = await http.get(API.inventarioTelares).catch(() => null)
        const rows = asRows(telrs?.data)
        if (rows.length) {
          state.telaresDataOriginal = JSON.parse(JSON.stringify(rows)) as RawRow[]
          state.telaresData = rows
        }
        state.selectedInventario = null
        state.selectedInventarios = []
      } else {
        state.telaresData = prevData
        state.telaresDataOriginal = prevOriginal
      }
      render.telares(state.telaresData)
      void Swal.fire(
        'Error',
        `${e instanceof Error ? e.message : 'Error al reservar'}${confirmadas ? ` (se guardaron ${confirmadas} de ${piezas.length} julios)` : ''}`,
        'error',
      )
    } finally {
      setLoading(false)
    }
  },
}

/* ---------- Edición inline cuenta/calibre (clic derecho → input, Enter para guardar) ---------- */
const editableCell = {
  activeTd: null as HTMLTableCellElement | null,
  activeInput: null as HTMLInputElement | null,
  originalContent: '',
  async startEdit(td: HTMLTableCellElement): Promise<void> {
    if (this.activeTd) this.cancelEdit()
    const field = td.dataset.editableField
    if (!field || !['cuenta', 'calibre'].includes(field)) return

    this.activeTd = td
    this.originalContent = td.textContent?.trim() ?? ''
    const isCalibre = field === 'calibre'
    const value = isCalibre ? String(parseFloat(this.originalContent) || '') : this.originalContent

    const input = document.createElement('input')
    input.type = isCalibre ? 'number' : 'text'
    input.step = '0.01'
    input.className =
      'w-full px-2 py-1 text-sm text-center bg-white text-gray-900 border border-gray-400 rounded focus:ring-2 focus:ring-gray-300 focus:border-gray-500 focus:outline-none selection:bg-gray-200 selection:text-gray-900'
    input.value = value
    input.dataset.field = field
    this.activeInput = input

    td.textContent = ''
    td.appendChild(input)
    input.focus()
    input.select()

    const save = async (): Promise<void> => {
      const newVal = input.value.trim()
      const id = td.dataset.id ? parseInt(td.dataset.id, 10) : null
      const noTelar = td.dataset.telar ?? ''
      const tipo = td.dataset.tipo ?? ''

      if (!noTelar) {
        toast('warning', 'No se puede actualizar', 'Falta identificar el telar')
        this.cancelEdit()
        return
      }

      const payload: Record<string, unknown> = { no_telar: noTelar, tipo: normalizeTipo(tipo), id }
      if (field === 'cuenta') payload.cuenta = newVal
      if (field === 'calibre') payload.calibre = newVal !== '' ? parseFloat(newVal) : null

      try {
        await http.post(API.actualizarTelar, payload)
        td.textContent = field === 'calibre' ? (newVal !== '' ? fmt.num(parseFloat(newVal)) : '') : newVal
        const base = state.telaresDataOriginal.length ? state.telaresDataOriginal : state.telaresData
        // Por id cuando lo hay: la misma barra puede repetirse en el telar con otra fecha.
        const idx = base.findIndex((t) =>
          id ? Number(t.id) === id : s(t.no_telar) === noTelar && s(t.tipo).toUpperCase().trim() === tipo,
        )
        if (idx >= 0) {
          const target = base[idx] as RawRow
          if (field === 'cuenta') target.cuenta = newVal
          if (field === 'calibre') target.calibre = newVal !== '' ? parseFloat(newVal) : null
        }
        // Actualizar dataset del row para que applyTelar/toggleTelarCheckbox lean el valor correcto
        const row = td.closest('tr')
        if (row) {
          if (field === 'cuenta') (row as HTMLTableRowElement).dataset.cuenta = newVal
          if (field === 'calibre') (row as HTMLTableRowElement).dataset.calibre = newVal !== '' ? String(parseFloat(newVal)) : ''
        }
        // Actualizar state.selectedTelares para que Programación de Requerimientos reciba el valor correcto
        if (Array.isArray(state.selectedTelares)) {
          const tTipo = tipo.toUpperCase().trim()
          state.selectedTelares.forEach((t) => {
            const esEste = id
              ? Number(t.id) === id
              : s(t.no_telar) === noTelar && s(t.tipo).toUpperCase().trim() === tTipo
            if (esEste) {
              if (field === 'cuenta') t.cuenta = newVal
              if (field === 'calibre') t.calibre = newVal !== '' ? String(parseFloat(newVal)) : ''
            }
          })
        }
        // Actualizar state.selectedTelar (selección individual) si es el telar editado
        const tel = state.selectedTelar
        if (tel && s(tel.no_telar) === noTelar && s(tel.tipo).toUpperCase().trim() === tipo.toUpperCase().trim()) {
          if (field === 'cuenta') tel.cuenta = newVal
          if (field === 'calibre') tel.calibre = newVal !== '' ? String(parseFloat(newVal)) : ''
        }
        toast('success', 'Actualizado', '', 1500)
      } catch (err) {
        toast('error', 'Error', err instanceof Error ? err.message : 'No se pudo actualizar')
        td.textContent = this.originalContent
      }
      this.activeTd = null
      this.activeInput = null
    }

    const cancel = (): void => {
      td.textContent = this.originalContent
      this.activeTd = null
      this.activeInput = null
    }

    input.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        void save()
      } else if (e.key === 'Escape') {
        e.preventDefault()
        cancel()
      }
    })

    input.addEventListener(
      'blur',
      () => {
        if (this.activeInput === input) cancel()
      },
      { once: true },
    )
  },
  cancelEdit(): void {
    if (this.activeTd && this.activeInput) {
      this.activeTd.textContent = this.originalContent
      this.activeTd = null
      this.activeInput = null
    }
  },
}

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', () => {
  void (async (): Promise<void> => {
    sorting.bind()
    render.telares(state.telaresData)

    let contextTarget: { table: TableKey | null; column: string | null } = { table: null, column: null }
    const contextMenu = $('#tableContextMenu')
    const closeContextMenu = (): void => contextMenu?.classList.add('hidden')

    const openContextMenu = (e: MouseEvent, table: TableKey, column: string): void => {
      if (!contextMenu || !column) return
      e.preventDefault()
      contextTarget = { table, column }
      contextMenu.classList.remove('hidden')
      contextMenu.style.left = `${e.pageX}px`
      contextMenu.style.top = `${e.pageY}px`
    }

    $('#telaresTable thead')?.addEventListener('contextmenu', (e: MouseEvent) => {
      const th = (e.target as Element | null)?.closest('.sortable') as HTMLElement | null
      if (!th) return
      const column = th.dataset.column
      if (!column) return
      openContextMenu(e, 'telares', column)
    })

    $('#telaresTable tbody')?.addEventListener('contextmenu', (e: MouseEvent) => {
      const td = (e.target as Element | null)?.closest('.editable-cell') as HTMLTableCellElement | null
      if (td) {
        e.preventDefault()
        e.stopPropagation()
        closeContextMenu()
        void editableCell.startEdit(td)
      }
    })

    $('#inventarioTable thead')?.addEventListener('contextmenu', (e: MouseEvent) => {
      const th = (e.target as Element | null)?.closest('.sortable-inventario') as HTMLElement | null
      if (!th) return
      const column = th.dataset.column
      if (!column) return
      openContextMenu(e, 'inventario', column)
    })

    document.addEventListener('click', (e: Event) => {
      if (!(e.target as Element | null)?.closest('#tableContextMenu')) closeContextMenu()
    })

    contextMenu?.addEventListener('click', (e: Event) => {
      const Swal = puWindow.Swal as SwalClient | undefined
      if (!Swal) return
      const action = ((e.target as Element | null)?.closest('[data-action') as HTMLElement | null)?.dataset.action
      if (!action) return

      const { table, column } = contextTarget
      if (!table || !column) return

      if (action === 'filter-column') {
        const current = mapFilters(table)[column] ?? ''
        const isBooleanFilter = ['reservado', 'programado'].includes(column)

        if (isBooleanFilter) {
          void Swal.fire({
            title: 'Filtrar columna',
            input: 'select',
            inputOptions: { 1: 'Sí', 0: 'No' },
            inputValue: current || '1',
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
          }).then((result) => {
            if (result.isConfirmed) {
              filters.applyColumnFilter(table, column, s(result.value))
            }
          })
        } else {
          void Swal.fire({
            title: 'Filtrar columna',
            input: 'text',
            inputValue: current,
            inputPlaceholder: 'Valor de filtro',
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
          }).then((result) => {
            if (result.isConfirmed) {
              filters.applyColumnFilter(table, column, s(result.value))
            }
          })
        }
      }

      if (action === 'clear-column-filter') {
        filters.clearColumnFilter(table, column)
      }

      if (action === 'clear-table-filters') {
        filters.clearTable(table)
      }

      closeContextMenu()
    })

    // Cargar inventario inicial
    setLoading(true)
    try {
      const { data } = await http.get(API.inventarioDisponibleGet)
      const rows = asRows(data)
      state.inventarioDataOriginal = JSON.parse(JSON.stringify(rows)) as RawRow[]
      render.inventario(rows)
      selection.validateButtons()
      selection.updateFiltroButton()
    } catch {
      render.inventario([])
      disable($('#btnReservar'))
    } finally {
      setLoading(false)
    }

    // Checkboxes: selección múltiple
    $('#telaresTable tbody')?.addEventListener('change', (e: Event) => {
      const target = e.target as HTMLElement | null
      if (!target) return
      if (target.classList.contains('tipo-atado-select')) {
        if (!CAN_MODIFICAR) return
        const row = target.closest('.selectable-row') as HTMLTableRowElement | null
        const select = target as HTMLSelectElement
        const telar = row?.dataset.telar ?? ''
        const tipo = row?.dataset.tipo ?? ''
        const nuevo = select.value || 'Normal'

        if (!telar) return

        // Actualizar visualmente datos locales
        if (row && row.classList.contains('is-selected')) {
          state.selectedTelar = { ...(state.selectedTelar as SelectedTelar), tipo_atado: nuevo }
        }

        // Enviar al backend (id permite resolver Folio para actualizar programas)
        const payload: Record<string, unknown> = { no_telar: telar, tipo: normalizeTipo(tipo), tipo_atado: nuevo }
        if (row?.dataset?.id) payload.id = parseInt(row.dataset.id, 10)
        let failed = false
        http
          .post(API.actualizarTelar, payload)
          .catch((err: unknown) => {
            failed = true
            toast('error', 'No se pudo actualizar tipo de atado', err instanceof Error ? err.message : '')
            // Revertir select si falla
            select.value = row?.dataset.tipoAtadoPrev ?? 'Normal'
          })
          .then(() => {
            if (!failed) toast('success', 'Tipo de atado actualizado')
          })

        if (row) row.dataset.tipoAtadoPrev = nuevo
        return
      }

      if (!target.classList.contains('telar-checkbox')) return

      e.stopPropagation()

      const checkbox = target as HTMLInputElement
      if (checkbox.disabled) {
        checkbox.checked = false
        return
      }

      const row = target.closest('.selectable-row') as HTMLTableRowElement | null
      if (!row) return

      selection.toggleTelarCheckbox(row, checkbox.checked)
    })

    // Click filas telares: selección individual
    $('#telaresTable tbody')?.addEventListener('click', (e: MouseEvent) => {
      const target = e.target as HTMLElement | null
      if (!target) return

      const toggle = target.closest('.pu-toggle') as HTMLElement | null
      if (toggle) {
        e.preventDefault()
        e.stopPropagation()
        // En el dataset y no en una clase: seleccionar la fila reescribe su className.
        const tr = toggle.closest('tr') as HTMLTableRowElement | null
        if (tr) {
          tr.dataset.expandido = tr.dataset.expandido === '1' ? '' : '1'
          toggle.setAttribute('aria-expanded', String(tr.dataset.expandido === '1'))
        }
        return
      }

      if (
        target.closest('button,a') ||
        (target instanceof HTMLInputElement && target.type === 'checkbox') ||
        target.closest('.telar-checkbox')
      ) {
        return
      }

      const row = target.closest('.selectable-row') as HTMLTableRowElement | null
      if (!row) return

      e.preventDefault()
      e.stopPropagation()

      if (row.classList.contains('is-selected')) {
        selection.clear()
      } else {
        selection.applyTelar(row)
      }
    })

    // Click filas inventario
    $('#inventarioTable tbody')?.addEventListener('click', (e: MouseEvent) => {
      const target = e.target as HTMLElement | null
      if (!target) return
      if (target.closest('button,a')) return

      const row = target.closest('.selectable-row-inventario') as HTMLTableRowElement | null
      if (!row) return

      if (row.dataset.disabled === 'true') {
        toast('info', 'Pieza ya reservada')
        return
      }

      e.preventDefault()
      e.stopPropagation()

      selection.applyInventario(row)
    })

    // Enter o Espacio sobre la fila enfocada equivale al clic. Sin esto la pantalla
    // no se puede operar sin raton, y seleccionar es toda su funcion.
    const teclaSelecciona = (
      e: KeyboardEvent,
      selector: string,
      accion: (row: HTMLTableRowElement) => void,
    ): void => {
      if (e.key !== 'Enter' && e.key !== ' ') return
      const target = e.target as HTMLElement | null
      // Un checkbox o el select de atado se quedan con su propia tecla.
      if (!target || target.closest('input,select,button,a')) return
      const row = target.closest(selector) as HTMLTableRowElement | null
      if (!row) return
      e.preventDefault()
      accion(row)
    }

    $('#telaresTable tbody')?.addEventListener('keydown', (e: KeyboardEvent) =>
      teclaSelecciona(e, '.selectable-row', (row) => {
        if (row.classList.contains('is-selected')) selection.clear()
        else selection.applyTelar(row)
      }),
    )

    $('#inventarioTable tbody')?.addEventListener('keydown', (e: KeyboardEvent) =>
      teclaSelecciona(e, '.selectable-row-inventario', (row) => {
        if (row.dataset.disabled === 'true') {
          toast('info', 'Pieza ya reservada')
          return
        }
        selection.applyInventario(row)
      }),
    )

    $('#btnSeleccionarLote')?.addEventListener('click', () => selection.seleccionarLote())

    $('#btnReloadTelares')?.addEventListener('click', () => filters.reset())

    // Botón quitar/aplicar filtro inventario (segunda tabla)
    $('#btnQuitarFiltroInventario')?.addEventListener('click', () => {
      if (!state.selectedTelar) return
      state.mostrarTodoInventario = !state.mostrarTodoInventario
      render.inventario(state.inventarioDataOriginal)
      selection.updateFiltroButton()
    })

    // Acciones
    $('#btnProgramar')?.addEventListener('click', () => actions.programar())
    $('#btnReservar')?.addEventListener('click', () => {
      void actions.reservar()
    })
    $('#btnLiberarTelar')?.addEventListener('click', () => {
      void actions.liberarTelar()
    })
  })()
})
