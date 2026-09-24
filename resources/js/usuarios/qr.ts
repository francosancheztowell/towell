import QRCode from 'qrcode'

// Pagina "Mi QR" (modulos/usuarios/qr.blade.php). Codifica el numero de empleado,
// que es lo que lee el login por QR. Antes dependia de qrcodejs por CDN, que estaba
// comentado: la pagina tronaba con "QRCode is not defined".

const QR_SIZE = 256
const DOWNLOAD_SIZE = 300
const LOGO_SIZE = 100

const renderQr = async (container: HTMLElement): Promise<HTMLCanvasElement | null> => {
  const text = container.dataset.qrText ?? ''
  if (text === '') {
    return null
  }

  const canvas = document.createElement('canvas')
  await QRCode.toCanvas(canvas, text, {
    width: QR_SIZE,
    margin: 0,
    errorCorrectionLevel: 'H',
    color: { dark: '#1e40af', light: '#ffffff' },
  })
  container.replaceChildren(canvas)
  return canvas
}

// PNG de 300px con el logo al centro (la correccion H tolera el hueco).
const download = (qr: HTMLCanvasElement, logoUrl: string, filename: string): void => {
  const out = document.createElement('canvas')
  out.width = DOWNLOAD_SIZE
  out.height = DOWNLOAD_SIZE
  const ctx = out.getContext('2d')
  if (!ctx) {
    return
  }
  ctx.drawImage(qr, 0, 0, DOWNLOAD_SIZE, DOWNLOAD_SIZE)

  const img = new Image()
  img.onload = () => {
    const pos = (DOWNLOAD_SIZE - LOGO_SIZE) / 2
    ctx.fillStyle = 'white'
    ctx.fillRect(pos - 5, pos - 5, LOGO_SIZE + 10, LOGO_SIZE + 10)
    ctx.drawImage(img, pos, pos, LOGO_SIZE, LOGO_SIZE)

    const link = document.createElement('a')
    link.download = filename
    link.href = out.toDataURL('image/png')
    link.click()
  }
  img.src = logoUrl
}

const init = async (): Promise<void> => {
  const container = document.querySelector<HTMLElement>('#qrcode')
  if (!container) {
    return
  }
  const canvas = await renderQr(container)

  document.querySelector<HTMLButtonElement>('[data-qr-download]')?.addEventListener('click', () => {
    if (canvas) {
      download(canvas, container.dataset.qrLogo ?? '', container.dataset.qrFilename ?? 'QR.png')
    }
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => void init(), { once: true })
} else {
  void init()
}
