/**
 * Edición/Reimpresión de órdenes (Urdido y Engomado): calificar julios de Engomado y
 * descarga del PDF / Excel simplificado de Engomado. El tablero es Livewire.
 * Vistas: resources/views/modulos/{urdido/reimpresion-urdido,engomado/reimpresion-engomado}.blade.php
 */
import { http, HttpError } from '../utils/http.ts'
import { notify } from '../utils/notifications.ts'
import { delegate } from '../utils/dom.ts'
import { abrirCalificarJulios } from '../modulos/urdido/comun/calificar-julios/index.ts'
import { nombreDeDisposicion } from './logica.ts'
import { initializeResponsiveBoard } from './responsive.ts'

// U1 (19-05): Livewire (EdicionOrdenes::calificar) manda el folio; el modal de la variante
// engomado lo incluye reimpresion-engomado.blade.php. Sin puente window.
window.addEventListener('engomado-calificar-julios', ((event: CustomEvent<{ folio: string }>) => {
  abrirCalificarJulios('engomado', event.detail?.folio ?? '')
}) as EventListener)

/** Mensaje del JSON de error aunque la respuesta se haya pedido como blob. */
const mensajeDeBlob = async (err: unknown, porDefecto: string): Promise<string> => {
  if (!(err instanceof HttpError)) return porDefecto
  let cuerpo: unknown = err.data
  if (cuerpo instanceof Blob) {
    try {
      cuerpo = JSON.parse(await cuerpo.text())
    } catch {
      cuerpo = null
    }
  }
  const datos = (cuerpo ?? {}) as { message?: string; error?: string }
  return datos.message || datos.error || porDefecto
}

const descargar = async (boton: HTMLButtonElement, url: string): Promise<void> => {
  // El simplificado es un Excel: solo se descarga, sin ventana de vista previa.
  const esExcel = url.includes('simplificado=1')
  const tipo = esExcel ? 'spreadsheetml' : 'application/pdf'
  // La ventana se abre en el clic original para que la tableta no la bloquee tras la petición.
  const popup = esExcel ? null : window.open('', 'imprimir-engomado', 'width=720,height=560,scrollbars=yes,resizable=yes')
  boton.disabled = true
  let disposicion: string | undefined
  try {
    // http devuelve solo el cuerpo: el nombre del archivo (Content-Disposition) se toma
    // en transformResponse, que recibe las cabeceras.
    const archivo = await http.get<Blob>(url, {
      responseType: 'blob',
      headers: { Accept: `${tipo}, application/json` },
      transformResponse: [(data: Blob, headers) => {
        disposicion = headers?.['content-disposition'] as string | undefined
        return data
      }],
    })
    if (!(archivo instanceof Blob) || !archivo.type.includes(tipo)) {
      throw new Error('No se recibió el archivo. Comprueba que tu sesión siga activa.')
    }
    const objectUrl = URL.createObjectURL(archivo)
    if (popup && !popup.closed) popup.location.href = objectUrl
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = nombreDeDisposicion(disposicion) || (esExcel ? 'ORDEN_ENGOMADO_SIMPLE.xlsx' : 'ORDEN_ENGOMADO.pdf')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000)
  } catch (error) {
    popup?.close()
    const porDefecto = error instanceof Error && !(error instanceof HttpError) ? error.message : 'No se pudo generar el archivo.'
    notify.error(await mensajeDeBlob(error, porDefecto))
  } finally {
    boton.disabled = false
  }
}

delegate<HTMLButtonElement, MouseEvent>(document, 'click', '[data-engomado-pdf]', (_event, boton) => {
  const url = boton.dataset.engomadoPdf
  if (!url || boton.disabled) return
  void descargar(boton, url)
})

initializeResponsiveBoard()
document.addEventListener('livewire:navigated', initializeResponsiveBoard)
