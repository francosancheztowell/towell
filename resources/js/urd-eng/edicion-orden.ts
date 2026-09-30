/**
 * Edicion de orden (Urdido/Engomado). Livewire guarda los campos de la orden;
 * aqui solo queda lo que no vale la pena hacer por servidor:
 * autocompletar catalogos de AX y la captura de la tabla de produccion (que es
 * del modulo de produccion), con su modal de empleados.
 * Vistas: resources/views/modulos/{urdido/editar-orden-programada,engomado/editar-orden-engomado}.blade.php
 *         + resources/views/livewire/urd-eng/edicion-orden.blade.php
 */
import { http } from '../utils/http.ts'
import { notify } from '../utils/notifications.ts'
import { debounce } from '../utils/format.ts'
import { delegate } from '../utils/dom.ts'
import { abrir, cerrarPorId } from '../componentes/dialog.ts'
import { exigirExito, mensajeError, type RespuestaApi } from '../modulos/urdido/comun/pagina.ts'
import {
  opcionAutocompletado,
  peticionCeldaProduccion,
  peticionesOficiales,
  validarOficiales,
  type FilaCapturada,
  type Oficial,
  type RutaProduccion,
} from './logica.ts'

type Usuario = { numero_empleado?: string; nombre?: string; turno?: number | string }

type Ventana = { Livewire?: { find: (id: string) => { $refresh: () => void } | undefined } }

const MODAL_EMPLEADOS = 'modal-empleados-produccion'

const raiz = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-edicion-orden]')

const post = async (url: string, datos: unknown): Promise<RespuestaApi> =>
  exigirExito(await http.post<RespuestaApi>(url, datos), 'No se pudo actualizar')

const refrescarComponente = (): void => {
  const id = raiz()?.getAttribute('wire:id')
  if (id) (window as unknown as Ventana).Livewire?.find(id)?.$refresh()
}

// ─────────────────────────────────────────────────────────── tabla de produccion

const RUTAS: Record<RutaProduccion, string> = { horas: 'rutaHoras', fecha: 'rutaFecha', campos: 'rutaCampos' }

const guardarCeldaProduccion = async (input: HTMLInputElement | HTMLSelectElement): Promise<void> => {
  const contenedor = raiz()
  const registroId = Number(input.dataset.registroId)
  const campo = input.dataset.field
  if (!contenedor || !campo || !Number.isInteger(registroId)) return

  const valor = input.value === '' ? null : input.value
  // Valor al que volver si falla el guardado. La primera vez no hay ultimoGuardado,
  // asi que se lee el que pinto el servidor: defaultValue en un input, y en un
  // <select> la opcion con el atributo selected (defaultValue no existe ahi).
  const anterior = input.dataset.ultimoGuardado ?? (
    input instanceof HTMLSelectElement
      ? input.querySelector<HTMLOptionElement>('option[selected]')?.value ?? input.value
      : input.defaultValue
  )
  if (valor === input.dataset.ultimoGuardado) return

  const peticion = peticionCeldaProduccion(campo, registroId, valor)
  if (!peticion) return
  input.dataset.ultimoGuardado = input.value

  try {
    await post(contenedor.dataset[RUTAS[peticion.ruta]] ?? '', peticion.datos)
    notify.success('Actualizado')
  } catch (error) {
    input.value = anterior
    input.dataset.ultimoGuardado = anterior
    notify.error(mensajeError(error, 'No se pudo actualizar'))
  }
}

// ──────────────────────────────────────────────────────────── modal de empleados

let usuarios: Usuario[] | null = null
let registroEnEdicion: { id: number; actuales: Oficial[] } | null = null

const cargarUsuarios = async (): Promise<Usuario[]> => {
  if (usuarios) return usuarios
  try {
    const respuesta = await http.get<{ data?: Usuario[] }>(raiz()?.dataset.rutaUsuarios ?? '')
    usuarios = Array.isArray(respuesta?.data) ? respuesta.data : []
  } catch {
    // Sin catálogo el modal sigue abriendo con los empleados que ya tiene la fila.
    usuarios = []
  }
  return usuarios
}

const filasModal = (): HTMLElement[] =>
  Array.from(document.querySelectorAll<HTMLElement>(`#${MODAL_EMPLEADOS} [data-oficial-fila]`))

const campo = <T extends HTMLElement>(fila: HTMLElement, nombre: string): T | null =>
  fila.querySelector<T>(`[data-oficial="${nombre}"]`)

const opcionEmpleado = (clave: string, nombre: string): HTMLOptionElement => {
  const opcion = new Option(clave, clave)
  opcion.dataset.nombre = nombre
  return opcion
}

const mostrarErrorModal = (texto: string): void => {
  const nodo = document.querySelector<HTMLElement>(`#${MODAL_EMPLEADOS} [data-oficiales-error]`)
  if (!nodo) return
  nodo.textContent = texto
  nodo.classList.toggle('hidden', texto === '')
}

const llenarModal = (lista: Usuario[], actuales: Oficial[]): void => {
  const porNumero = new Map(actuales.map((o) => [o.numero, o]))
  filasModal().forEach((fila, indice) => {
    const actual = porNumero.get(indice + 1)
    const cve = String(actual?.cve ?? '').trim()
    const select = campo<HTMLSelectElement>(fila, 'cve')
    if (select) {
      const opciones = lista
        .map((u) => [String(u.numero_empleado ?? '').trim(), u.nombre ?? ''] as const)
        .filter(([clave]) => clave !== '')
        .map(([clave, nombre]) => opcionEmpleado(clave, nombre))
      if (cve && !opciones.some((o) => o.value === cve)) opciones.push(opcionEmpleado(cve, actual?.nombre ?? ''))
      select.replaceChildren(new Option('No. Empleado', ''), ...opciones)
      select.value = cve
    }
    const nombre = campo<HTMLInputElement>(fila, 'nombre')
    if (nombre) nombre.value = actual?.nombre ?? ''
    const metros = campo<HTMLInputElement>(fila, 'metros')
    if (metros) metros.value = actual?.metros == null ? '' : String(actual.metros)
    const turno = campo<HTMLSelectElement>(fila, 'turno')
    if (turno) turno.value = actual?.turno == null ? '' : String(actual.turno)
  })
  mostrarErrorModal('')
}

const leerModal = (): FilaCapturada[] =>
  filasModal().map((fila) => ({
    cve: campo<HTMLSelectElement>(fila, 'cve')?.value ?? '',
    nombre: campo<HTMLInputElement>(fila, 'nombre')?.value ?? '',
    metros: campo<HTMLInputElement>(fila, 'metros')?.value ?? '',
    turno: campo<HTMLSelectElement>(fila, 'turno')?.value ?? '',
  }))

const abrirModalEmpleados = async (boton: HTMLElement): Promise<void> => {
  const registroId = Number(boton.dataset.registroId)
  if (!raiz() || !Number.isInteger(registroId)) return

  let actuales: Oficial[] = []
  try {
    actuales = JSON.parse(boton.dataset.oficiales || '[]') as Oficial[]
  } catch {
    actuales = []
  }
  llenarModal(await cargarUsuarios(), actuales)
  registroEnEdicion = { id: registroId, actuales }
  abrir(MODAL_EMPLEADOS)
}

const guardarEmpleados = async (boton: HTMLButtonElement): Promise<void> => {
  const contenedor = raiz()
  if (!contenedor || !registroEnEdicion) return

  const resultado = validarOficiales(leerModal())
  if (!resultado.ok) {
    mostrarErrorModal(resultado.error)
    return
  }
  mostrarErrorModal('')

  const rutas = { eliminar: contenedor.dataset.rutaEliminarOficial ?? '', guardar: contenedor.dataset.rutaGuardarOficial ?? '' }
  boton.disabled = true
  try {
    for (const peticion of peticionesOficiales(registroEnEdicion.id, resultado.oficiales, registroEnEdicion.actuales)) {
      await post(rutas[peticion.tipo], peticion.datos)
    }
    cerrarPorId(MODAL_EMPLEADOS)
    registroEnEdicion = null
    notify.success('Empleados actualizados')
    refrescarComponente()
  } catch (error) {
    notify.error(mensajeError(error, 'No se pudieron guardar'))
  } finally {
    boton.disabled = false
  }
}

// ───────────────────────────────────────────────────────────────── autocomplete

const RUTA_POR_TIPO: Record<string, string> = {
  bom: 'rutaBom',
  'bom-formula': 'rutaBomFormula',
  lote: 'rutaLote',
}

const montarAutocomplete = (): void => {
  const contenedor = raiz()
  if (!contenedor) return

  const caja = document.createElement('div')
  caja.className = 'fixed z-[99999] hidden max-h-60 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg'
  caja.setAttribute('role', 'listbox')
  document.body.appendChild(caja)

  let activo: HTMLInputElement | null = null

  const ocultar = (): void => {
    caja.classList.add('hidden')
    activo = null
  }

  const colocar = (input: HTMLInputElement): void => {
    const rect = input.getBoundingClientRect()
    caja.style.top = `${rect.bottom + 4}px`
    caja.style.left = `${rect.left}px`
    caja.style.width = `${rect.width}px`
  }

  const opcion = (input: HTMLInputElement, tipo: string, fila: Record<string, string | undefined>): HTMLElement => {
    const { etiqueta, valor } = opcionAutocompletado(tipo, fila)
    const nodo = document.createElement('div')
    nodo.className = 'cursor-pointer px-3 py-2 text-sm hover:bg-blue-50'
    nodo.setAttribute('role', 'option')
    nodo.textContent = etiqueta
    nodo.addEventListener('mousedown', (evento) => {
      evento.preventDefault()
      input.value = valor
      // Livewire escucha input y blur: asi el campo se guarda solo.
      input.dispatchEvent(new Event('input', { bubbles: true }))
      input.dispatchEvent(new Event('blur', { bubbles: true }))
      ocultar()
    })
    return nodo
  }

  const buscar = async (input: HTMLInputElement): Promise<void> => {
    const tipo = input.dataset.autocomplete ?? ''
    const clave = RUTA_POR_TIPO[tipo]
    const ruta = clave ? contenedor.dataset[clave] : undefined
    const texto = input.value.trim()
    if (!ruta || texto === '') return ocultar()

    try {
      const datos = await http.get<Record<string, string>[] | { data?: Record<string, string>[] }>(ruta, { params: { q: texto } })
      const filas = Array.isArray(datos) ? datos : (datos?.data ?? [])
      if (!filas.length) return ocultar()

      caja.replaceChildren(...filas.map((fila) => opcion(input, tipo, fila)))
      activo = input
      colocar(input)
      caja.classList.remove('hidden')
    } catch {
      // Sin sugerencias: el campo se sigue pudiendo escribir a mano.
      ocultar()
    }
  }

  const buscarPronto = debounce((input: HTMLInputElement) => void buscar(input), 300)

  delegate<HTMLInputElement>(document, 'input', '[data-autocomplete]', (_evento, input) => buscarPronto(input))

  document.addEventListener('click', (evento) => {
    if (activo && !caja.contains(evento.target as Node) && evento.target !== activo) ocultar()
  })

  // La caja es position:fixed: se coloca con coordenadas del viewport, sin scrollX/scrollY.
  window.addEventListener('scroll', () => activo && colocar(activo), true)
  window.addEventListener('resize', () => activo && colocar(activo))
}

// ───────────────────────────────────────────────────────────────────── arranque

delegate<HTMLInputElement | HTMLSelectElement>(document, 'change', '.produccion-input', (_evento, celda) => {
  void guardarCeldaProduccion(celda)
})

// Elegir un No. Empleado en el modal pone su nombre.
delegate<HTMLSelectElement>(document, 'change', `#${MODAL_EMPLEADOS} [data-oficial="cve"]`, (_evento, select) => {
  const nombre = select.closest<HTMLElement>('[data-oficial-fila]')?.querySelector<HTMLInputElement>('[data-oficial="nombre"]')
  if (nombre) nombre.value = select.options[select.selectedIndex]?.dataset.nombre ?? ''
})

const acciones: Record<string, (el: HTMLElement) => void> = {
  'editar-empleados': (el) => void abrirModalEmpleados(el),
  'guardar-empleados': (el) => void guardarEmpleados(el as HTMLButtonElement),
}

delegate(document, 'click', '[data-accion]', (evento, el) => {
  const accion = acciones[el.dataset.accion ?? '']
  if (!accion) return
  evento.preventDefault()
  accion(el)
})

// Livewire (EdicionOrden::notificar). Los errores ya los muestra el propio componente en
// su diálogo "No se guardó"; aquí solo el aviso breve de éxito.
window.addEventListener('program-board-notify', ((evento: CustomEvent<{ type?: string; message?: string }>) => {
  const tipo = evento.detail?.type ?? 'success'
  const mensaje = evento.detail?.message ?? 'Listo'
  if (tipo === 'warning') notify.warning(mensaje)
  else if (tipo !== 'error') notify.success(mensaje)
}) as EventListener)

montarAutocomplete()
