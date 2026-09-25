import { initializeResponsiveBoard } from './responsive'

type EngomadoWindow = Window & { abrirModalCalificarJuliosEng?: (folio: string) => void }

window.addEventListener('engomado-calificar-julios', ((event: CustomEvent<{ folio: string }>) => {
  (window as EngomadoWindow).abrirModalCalificarJuliosEng?.(event.detail.folio)
}) as EventListener)

// Preserve opening and downloading both existing PDF formats. Open the window
// on the original click so tablet browsers do not block it after the request.
document.addEventListener('click', async (event: MouseEvent) => {
  const button = (event.target as Element | null)?.closest<HTMLButtonElement>('[data-engomado-pdf]')
  const url = button?.dataset.engomadoPdf
  if (!button || !url || button.disabled) return
  // El simplificado es un Excel: solo se descarga, sin ventana de vista previa.
  const esExcel = url.includes('simplificado=1')
  const tipo = esExcel ? 'spreadsheetml' : 'application/pdf'
  const popup = esExcel ? null : window.open('', 'imprimir-engomado', 'width=720,height=560,scrollbars=yes,resizable=yes')
  button.disabled = true
  try {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: `${tipo}, application/json` } })
    if (!response.ok) {
      const payload = await response.json().catch(() => ({})) as { error?: string }
      throw new Error(payload.error || 'No se pudo generar el archivo.')
    }
    if (!response.headers.get('Content-Type')?.includes(tipo)) {
      throw new Error('No se recibió el archivo. Comprueba que tu sesión siga activa.')
    }
    const objectUrl = URL.createObjectURL(await response.blob())
    if (popup && !popup.closed) popup.location.href = objectUrl
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i)?.[1] || (esExcel ? 'ORDEN_ENGOMADO_SIMPLE.xlsx' : 'ORDEN_ENGOMADO.pdf')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000)
  } catch (error) {
    popup?.close()
    window.alert(error instanceof Error ? error.message : 'No se pudo generar el archivo.')
  } finally {
    button.disabled = false
  }
})

initializeResponsiveBoard()
document.addEventListener('livewire:navigated', initializeResponsiveBoard)
