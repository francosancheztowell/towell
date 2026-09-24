/*
 * Entrada del monitoreo cliente (fase 12). Se importa con una línea desde app.js.
 *
 * El transporte es axios (ya en el bundle y ya con el token CSRF que le pone bootstrap.js),
 * no utils/http: así un fallo de telemetría no dispara towell:http-error ni la UI de
 * sesión vencida. TODO(ADOP): pasar a utils/http cuando permita silenciar ambos.
 */
import axios from 'axios'
import { iniciar, type Post } from './cliente'
import { iniciarDispositivo } from './dispositivo'

const post: Post = (url, body) => axios.post(url, body, { timeout: 10_000 }).then((r) => r.data)

try {
  const nombre = iniciar({ post }) !== null ? post : null
  const modal = (): void => {
    try {
      iniciarDispositivo(nombre)
    } catch {
      // El modal nunca rompe la página.
    }
  }
  modal()
  document.addEventListener('livewire:navigated', modal)
} catch {
  // La telemetría nunca rompe la página.
}
