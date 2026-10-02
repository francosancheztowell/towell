/** Resultado de validar un formulario: datos listos para enviar o el campo con error. Lo usan los catálogos de Engomado. */
export type Validacion<T> = { ok: true; datos: T } | { ok: false; campo: string; mensaje: string };
