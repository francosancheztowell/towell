/*
 * El servidor embebido de PHP (`artisan serve`) atiende una petición a la vez:
 * si un clic cae mientras el poll reconstruye el tablero, responde 503 sin haber
 * ejecutado nada. Como la petición no llegó a correr, repetirla es seguro. Por
 * eso dashboard.ts solo instala este reintento cuando el servidor es ese
 * (ver data-crudo-retry-busy en index.blade.php); en producción un 503 no da
 * esa garantía y reintentar podría reenviar una acción que sí corrió.
 *
 * ponytail: cuatro reintentos con espera corta. Si hiciera falta backoff real, el
 * problema sería el dimensionamiento del servidor, no la petición.
 */
export const BUSY_STATUS = 503
export const BUSY_RETRIES = 4
export const BUSY_RETRY_DELAY_MS = 220

type FetchLike = (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>

export const withBusyRetry = (
  originalFetch: FetchLike,
  matches: (url: string) => boolean,
  wait: (ms: number) => Promise<void> = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
): FetchLike => async (input, init) => {
  // Solo con url + init se puede repetir: el body de un Request ya construido
  // se consume en el primer intento.
  const reusable = typeof input === 'string' || input instanceof URL
  const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url

  if (!reusable || !matches(url)) {
    return originalFetch(input, init)
  }

  let response = await originalFetch(input, init)

  for (let attempt = 0; attempt < BUSY_RETRIES && response.status === BUSY_STATUS; attempt++) {
    await wait(BUSY_RETRY_DELAY_MS * (attempt + 1))
    response = await originalFetch(input, init)
  }

  return response
}
