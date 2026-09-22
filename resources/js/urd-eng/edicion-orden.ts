/**
 * Edicion de orden (Urdido/Engomado). Livewire guarda los campos de la orden;
 * aqui solo queda lo que no vale la pena hacer por servidor:
 * autocompletar catalogos de AX, la captura de la tabla de produccion (que es
 * del modulo de produccion) y los dialogos de SweetAlert.
 */

type Oficial = {
  numero: number
  cve: string | null
  nombre: string | null
  turno: number | null
  metros: number | null
}

type Usuario = { numero_empleado?: string; nombre?: string; turno?: number | string }

type Ventana = {
  Swal?: any
  http?: { get: (url: string) => Promise<any>; post: (url: string, data?: unknown) => Promise<any> }
  Livewire?: { find: (id: string) => any }
}

const ventana = window as unknown as Ventana

const raiz = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-edicion-orden]')

const aviso = (icon: 'success' | 'error', title: string): void => {
  void ventana.Swal?.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2200 })
}

const escapar = (valor: unknown): string =>
  String(valor ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')

const pedir = async (url: string, datos: unknown): Promise<any> => {
  if (ventana.http) return ventana.http.post(url, datos)
  const respuesta = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
    },
    body: JSON.stringify(datos),
  })
  return respuesta.json()
}

const refrescarComponente = (): void => {
  const id = document.querySelector('[wire\\:id]')?.getAttribute('wire:id')
  if (id) ventana.Livewire?.find(id)?.$refresh()
}

// ─────────────────────────────────────────────────────────── tabla de produccion

const CAMPOS_PRODUCCION: Record<string, string> = {
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

const guardarCeldaProduccion = async (input: HTMLInputElement | HTMLSelectElement): Promise<void> => {
  const contenedor = raiz()
  const registroId = Number(input.dataset.registroId)
  const campo = input.dataset.field
  if (!contenedor || !campo || !Number.isInteger(registroId)) return

  const valor = input.value === '' ? null : input.value
  const anterior = input.dataset.ultimoGuardado ?? input.defaultValue
  if (valor === input.dataset.ultimoGuardado) return
  input.dataset.ultimoGuardado = input.value

  try {
    if (campo === 'h_inicio' || campo === 'h_fin') {
      await pedir(contenedor.dataset.rutaHoras ?? '', {
        registro_id: registroId,
        campo: campo === 'h_inicio' ? 'HoraInicial' : 'HoraFinal',
        valor,
      })
    } else if (campo === 'fecha') {
      await pedir(contenedor.dataset.rutaFecha ?? '', { registro_id: registroId, fecha: valor })
    } else {
      const nombre = CAMPOS_PRODUCCION[campo]
      if (!nombre) return
      const numerico = nombre !== 'Ubicacion' && valor !== null
      await pedir(contenedor.dataset.rutaCampos ?? '', {
        registro_id: registroId,
        campo: nombre,
        valor: numerico ? Number(valor) : valor,
      })
    }
    input.defaultValue = input.value
    aviso('success', 'Actualizado')
  } catch (error) {
    input.value = anterior
    input.dataset.ultimoGuardado = anterior
    const data = (error as { data?: { error?: string; message?: string } }).data
    const texto = data?.error || data?.message || (error instanceof Error ? error.message : 'No se pudo actualizar')
    window.alert(texto)
  }
}

// ──────────────────────────────────────────────────────────── modal de empleados

let usuarios: Usuario[] | null = null

const cargarUsuarios = async (): Promise<Usuario[]> => {
  if (usuarios) return usuarios
  const contenedor = raiz()
  try {
    const respuesta = ventana.http
      ? await ventana.http.get(contenedor?.dataset.rutaUsuarios ?? '')
      : await (await fetch(contenedor?.dataset.rutaUsuarios ?? '', { headers: { Accept: 'application/json' } })).json()
    usuarios = Array.isArray(respuesta?.data) ? respuesta.data : []
  } catch {
    usuarios = []
  }
  return usuarios ?? []
}

const filaEmpleado = (i: number, actual: Partial<Oficial>, lista: Usuario[]): string => {
  const cve = String(actual.cve ?? '').trim()
  const opciones = lista
    .map((u) => {
      const clave = String(u.numero_empleado ?? '').trim()
      if (!clave) return ''
      return `<option value="${escapar(clave)}" data-nombre="${escapar(u.nombre ?? '')}" ${clave === cve ? 'selected' : ''}>${escapar(clave)}</option>`
    })
    .join('')
  const suelto = cve && !lista.some((u) => String(u.numero_empleado ?? '').trim() === cve)
    ? `<option value="${escapar(cve)}" data-nombre="${escapar(actual.nombre ?? '')}" selected>${escapar(cve)}</option>`
    : ''
  const turno = actual.turno ?? ''

  return `<div style="display:grid;grid-template-columns:1.1fr 1.2fr .7fr .5fr;column-gap:8px;margin-bottom:8px;">
    <select id="emp_${i}" class="px-2 py-1 border rounded text-sm"><option value="">No. Empleado</option>${opciones}${suelto}</select>
    <input id="nom_${i}" class="px-2 py-1 border rounded text-sm bg-gray-100" value="${escapar(actual.nombre ?? '')}" readonly placeholder="Nombre">
    <input id="met_${i}" type="number" min="0" step="0.01" class="px-2 py-1 border rounded text-sm" value="${escapar(actual.metros ?? '')}" placeholder="Metros">
    <select id="tur_${i}" class="px-2 py-1 border rounded text-sm">
      <option value="">Turno</option>
      ${[1, 2, 3, 4].map((t) => `<option value="${t}" ${String(turno) === String(t) ? 'selected' : ''}>${t}</option>`).join('')}
    </select>
  </div>`
}

const abrirModalEmpleados = async (boton: HTMLElement): Promise<void> => {
  const contenedor = raiz()
  const registroId = Number(boton.dataset.registroId)
  if (!contenedor || !ventana.Swal || !Number.isInteger(registroId)) return

  const lista = await cargarUsuarios()
  const actuales: Oficial[] = JSON.parse(boton.dataset.oficiales || '[]')
  const porNumero = new Map(actuales.map((o) => [o.numero, o]))

  const resultado = await ventana.Swal.fire({
    title: 'Editar Empleados',
    width: 920,
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    html: `<div class="text-left"><p class="text-sm text-gray-600 mb-2">Hasta 3 empleados.</p>
      ${[1, 2, 3].map((i) => filaEmpleado(i, porNumero.get(i) ?? {}, lista)).join('')}</div>`,
    didOpen: () => {
      for (let i = 1; i <= 3; i++) {
        const select = document.getElementById(`emp_${i}`) as HTMLSelectElement | null
        const nombre = document.getElementById(`nom_${i}`) as HTMLInputElement | null
        select?.addEventListener('change', () => {
          if (nombre) nombre.value = select.options[select.selectedIndex]?.dataset.nombre ?? ''
        })
      }
    },
    preConfirm: (): Oficial[] | false => {
      const nuevos: Oficial[] = []
      const claves = new Set<string>()
      const turnos = new Set<number>()

      for (let i = 1; i <= 3; i++) {
        const cve = (document.getElementById(`emp_${i}`) as HTMLSelectElement | null)?.value.trim() ?? ''
        const nombre = (document.getElementById(`nom_${i}`) as HTMLInputElement | null)?.value.trim() ?? ''
        const metros = (document.getElementById(`met_${i}`) as HTMLInputElement | null)?.value.trim() ?? ''
        const turno = Number.parseInt((document.getElementById(`tur_${i}`) as HTMLSelectElement | null)?.value ?? '', 10)

        if (!cve) {
          nuevos.push({ numero: i, cve: null, nombre: null, metros: null, turno: null })
          continue
        }
        if (claves.has(cve)) {
          ventana.Swal.showValidationMessage(`El No. Empleado ${cve} está repetido.`)
          return false
        }
        if (![1, 2, 3, 4].includes(turno)) {
          ventana.Swal.showValidationMessage(`Selecciona un turno válido (1-4) para el empleado ${i}.`)
          return false
        }
        if (turnos.has(turno)) {
          ventana.Swal.showValidationMessage(`No puede haber dos oficiales en el turno ${turno}.`)
          return false
        }
        claves.add(cve)
        turnos.add(turno)
        nuevos.push({ numero: i, cve, nombre: nombre || null, metros: metros === '' ? null : Number(metros), turno })
      }

      return nuevos
    },
  })

  if (!resultado.isConfirmed || !Array.isArray(resultado.value)) return

  try {
    for (const nuevo of resultado.value as Oficial[]) {
      const actual = porNumero.get(nuevo.numero)
      if (!nuevo.cve && !nuevo.nombre) {
        if (actual?.cve || actual?.nombre) {
          await pedir(contenedor.dataset.rutaEliminarOficial ?? '', { registro_id: registroId, numero_oficial: nuevo.numero })
        }
        continue
      }
      await pedir(contenedor.dataset.rutaGuardarOficial ?? '', {
        registro_id: registroId,
        numero_oficial: nuevo.numero,
        cve_empl: nuevo.cve,
        nom_empl: nuevo.nombre,
        metros: nuevo.metros,
        turno: nuevo.turno,
      })
    }
    aviso('success', 'Empleados actualizados')
    refrescarComponente()
  } catch (error) {
    aviso('error', error instanceof Error ? error.message : 'No se pudieron guardar')
  }
}

// ───────────────────────────────────────────────────────────────── autocomplete

const RUTA_POR_TIPO: Record<string, string> = {
  bom: 'rutaBom',
  'bom-formula': 'rutaBomFormula',
  lote: 'rutaLote',
}

const etiqueta = (tipo: string, fila: Record<string, string>): string =>
  tipo === 'bom' ? `${fila.BOMID} - ${fila.NAME ?? ''}` : (fila.BomFormula ?? fila.LoteProveedor ?? '')

const valorDe = (tipo: string, fila: Record<string, string>): string =>
  (tipo === 'bom' ? fila.BOMID : (fila.BomFormula ?? fila.LoteProveedor)) ?? ''

const montarAutocomplete = (): void => {
  const contenedor = raiz()
  if (!contenedor) return

  const caja = document.createElement('div')
  caja.className = 'fixed z-[99999] hidden max-h-60 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg'
  document.body.appendChild(caja)

  let activo: HTMLInputElement | null = null
  let temporizador: number | undefined

  const ocultar = (): void => {
    caja.classList.add('hidden')
    activo = null
  }

  const colocar = (input: HTMLInputElement): void => {
    const rect = input.getBoundingClientRect()
    caja.style.top = `${rect.bottom + window.scrollY + 4}px`
    caja.style.left = `${rect.left + window.scrollX}px`
    caja.style.width = `${rect.width}px`
  }

  const buscar = async (input: HTMLInputElement): Promise<void> => {
    const tipo = input.dataset.autocomplete ?? ''
    const clave = RUTA_POR_TIPO[tipo]
    const ruta = clave ? contenedor.dataset[clave] : undefined
    const texto = input.value.trim()
    if (!ruta || texto === '') return ocultar()

    try {
      const url = new URL(ruta, window.location.origin)
      url.searchParams.set('q', texto)
      const datos = ventana.http ? await ventana.http.get(url.toString()) : await (await fetch(url.toString(), { headers: { Accept: 'application/json' } })).json()
      const filas: Record<string, string>[] = Array.isArray(datos) ? datos : (datos?.data ?? [])
      if (!filas.length) return ocultar()

      caja.innerHTML = ''
      filas.forEach((fila) => {
        const opcion = document.createElement('div')
        opcion.className = 'cursor-pointer px-3 py-2 text-sm hover:bg-blue-50'
        opcion.textContent = etiqueta(tipo, fila)
        opcion.addEventListener('mousedown', (evento) => {
          evento.preventDefault()
          input.value = valorDe(tipo, fila)
          // Livewire escucha input y blur: asi el campo se guarda solo.
          input.dispatchEvent(new Event('input', { bubbles: true }))
          input.dispatchEvent(new Event('blur', { bubbles: true }))
          ocultar()
        })
        caja.appendChild(opcion)
      })

      activo = input
      colocar(input)
      caja.classList.remove('hidden')
    } catch {
      ocultar()
    }
  }

  document.addEventListener('input', (evento) => {
    const input = (evento.target as Element | null)?.closest<HTMLInputElement>('[data-autocomplete]')
    if (!input) return
    window.clearTimeout(temporizador)
    temporizador = window.setTimeout(() => void buscar(input), 300)
  })

  document.addEventListener('click', (evento) => {
    if (activo && !caja.contains(evento.target as Node) && evento.target !== activo) ocultar()
  })

  window.addEventListener('scroll', () => activo && colocar(activo), true)
  window.addEventListener('resize', () => activo && colocar(activo))
}

// ──────────────────────────────────────────────────────────── dialogos Livewire

const ETIQUETA_ACCION: Record<string, string> = {
  solo_campo: 'Solo el campo Metros de la orden',
  actualizar_produccion_toda: 'Actualizar toda la producción',
  actualizar_produccion_sin_hora_inicio: 'Solo los registros sin hora de inicio',
}

const componente = (): { confirmarMetros: (a: string) => void; confirmarNoTelas: () => void; descartarPendiente: () => void } | undefined => {
  const id = document.querySelector('[data-edicion-orden]')?.getAttribute('wire:id')
  return id ? (ventana.Livewire?.find(id) as any) : undefined
}

const escucharDialogos = (): void => {
  window.addEventListener('edicion-orden-metros', (async (evento: CustomEvent<{ acciones: string[] }>) => {
    const acciones: Record<string, string> = {}
    for (const accion of evento.detail?.acciones ?? []) acciones[accion] = ETIQUETA_ACCION[accion] ?? accion

    const { value } = await ventana.Swal.fire({
      icon: 'question',
      title: 'Actualizar metros',
      text: '¿Cómo quieres aplicar el cambio en la producción?',
      input: 'radio',
      inputOptions: acciones,
      inputValue: 'solo_campo',
      showCancelButton: true,
      confirmButtonText: 'Continuar',
      cancelButtonText: 'Cancelar',
      inputValidator: (v: string) => (v ? null : 'Selecciona una opción'),
    })

    if (value) componente()?.confirmarMetros(value)
    else componente()?.descartarPendiente()
  }) as unknown as EventListener)

  window.addEventListener('edicion-orden-confirmar', (async (evento: CustomEvent<{ mensaje: string }>) => {
    const { isConfirmed } = await ventana.Swal.fire({
      icon: 'warning',
      title: 'Confirmar cambio',
      text: evento.detail?.mensaje ?? '',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#2563eb',
    })

    if (isConfirmed) componente()?.confirmarNoTelas()
    else componente()?.descartarPendiente()
  }) as unknown as EventListener)
}

// ───────────────────────────────────────────────────────────────────── arranque

document.addEventListener('change', (evento) => {
  const celda = (evento.target as Element | null)?.closest<HTMLInputElement>('.produccion-input')
  if (celda) void guardarCeldaProduccion(celda)
})

document.addEventListener('click', (evento) => {
  const boton = (evento.target as Element | null)?.closest<HTMLElement>('.btn-editar-empleados')
  if (boton) {
    evento.preventDefault()
    void abrirModalEmpleados(boton)
  }
})

window.addEventListener('program-board-notify', ((evento: CustomEvent<{ type: 'success' | 'error'; message: string }>) => {
  aviso(evento.detail?.type ?? 'success', evento.detail?.message ?? 'Listo')
}) as unknown as EventListener)

montarAutocomplete()
escucharDialogos()
