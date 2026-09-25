/*
 * Nombre del dispositivo e IP del modal de usuario (MON-19).
 *
 * Antes el nombre vivía en localStorage bajo `device_name_<hash>` (clave inestable);
 * ahora se guarda en SYSMonDispositivo vía POST /telemetria/dispositivo/nombre y el
 * Blade lo pinta desde el servidor. El valor viejo se migra una vez y se borra.
 * Con el monitoreo apagado (`post` null) el nombre solo se muestra.
 *
 * Solo importa tipos: los tests node importan este .ts directo.
 */

import type { Post } from './cliente.ts'

const URL_NOMBRE = '/telemetria/dispositivo/nombre'
const NOMBRE_MAX = 80

type Almacen = Pick<Storage, 'getItem' | 'removeItem'>

const almacenLocal = (): Almacen | null => {
  try {
    return globalThis.localStorage
  } catch {
    return null
  }
}

export function iniciarDispositivo(post: Post | null, doc: Document = document, almacen: Almacen | null = almacenLocal()): void {
  mostrarIpLocal(doc)

  const etiqueta = doc.getElementById('device-name')
  // wire:navigate cambia el navbar: se llama de nuevo en cada livewire:navigated.
  if (!etiqueta || !post || etiqueta.dataset.monListo) return
  etiqueta.dataset.monListo = '1'

  const porDefecto = etiqueta.dataset.default ?? ''
  const llaveVieja = etiqueta.dataset.llaveLocal ?? ''
  let nombre = etiqueta.dataset.nombre ?? ''
  const pintar = (): void => {
    etiqueta.textContent = nombre || porDefecto
  }

  const guardar = (nuevo: string): Promise<void> => {
    const anterior = nombre
    nombre = nuevo.trim().slice(0, NOMBRE_MAX)
    pintar()

    return post(URL_NOMBRE, { nombre }).then(() => undefined, (e: unknown) => {
      nombre = anterior
      pintar()
      throw e
    })
  }

  // Migración del nombre que antes vivía solo en este navegador. La llave vieja se
  // borra hasta que el servidor ya devuelve un nombre en el render: el endpoint
  // responde 204 aunque no haya podido guardar, así que un 204 no lo prueba.
  const viejo = llaveVieja ? leer(almacen, llaveVieja) : ''
  if (viejo && !nombre) {
    guardar(viejo).catch(() => {})
  } else if (viejo) {
    borrar(almacen, llaveVieja)
  }

  const editor = doc.getElementById('device-name-editor')
  const input = doc.getElementById('device-name-input') as HTMLInputElement | null
  if (!editor || !input) return

  const abrir = (): void => {
    input.value = nombre
    editor.classList.remove('hidden')
    input.focus()
    input.select()
  }
  const cerrar = (): void => editor.classList.add('hidden')
  const confirmar = (): void => {
    cerrar()
    guardar(input.value).catch(() => {
      (globalThis as { notify?: { error?: (m: string) => void } }).notify?.error?.('No se pudo guardar el nombre del dispositivo.')
    })
  }

  etiqueta.addEventListener('click', abrir)
  doc.getElementById('edit-device-name')?.addEventListener('click', abrir)
  doc.getElementById('save-device-name')?.addEventListener('click', confirmar)
  doc.getElementById('cancel-device-name')?.addEventListener('click', cerrar)
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') confirmar()
    if (e.key === 'Escape') cerrar()
  })
  editor.addEventListener('click', (e) => {
    if (e.target === editor) cerrar()
  })
}

const leer = (almacen: Almacen | null, llave: string): string => {
  try {
    return almacen?.getItem(llave)?.trim() ?? ''
  } catch {
    return ''
  }
}

const borrar = (almacen: Almacen | null, llave: string): void => {
  try {
    almacen?.removeItem(llave)
  } catch {
    // Almacenamiento bloqueado: la llave vieja se queda, no pasa nada.
  }
}

/**
 * Si el servidor solo ve localhost (se entra desde el mismo equipo), intenta la IP de
 * la red local por WebRTC. Movido tal cual desde el <script> inline del modal.
 */
function mostrarIpLocal(doc: Document): void {
  const el = doc.getElementById('device-ip-display')
  const ipServidor = (el?.getAttribute('data-server-ip') ?? '').trim()
  if (!el || !['', '127.0.0.1', '::1'].includes(ipServidor) || typeof RTCPeerConnection !== 'function') return

  try {
    let pc: RTCPeerConnection | null = new RTCPeerConnection({ iceServers: [] })
    pc.createDataChannel('')
    pc.createOffer().then((oferta) => pc?.setLocalDescription(oferta)).catch(() => {})
    pc.onicecandidate = (ice) => {
      const ip = ice.candidate?.candidate.match(/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/)?.[1]
      if (ip && /^(192\.168\.|10\.|172\.)/.test(ip)) {
        el.textContent = ip
        el.title = `IPv4 (red local): ${ip}`
        pc?.close()
        pc = null
      }
    }
    setTimeout(() => pc?.close(), 3000)
  } catch {
    // Sin WebRTC: se queda la IP del servidor.
  }
}
