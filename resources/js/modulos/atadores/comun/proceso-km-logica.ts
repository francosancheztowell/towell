/** Lógica pura de Montado/Enhebrado de Karl Mayer (sin DOM): tests en tests/Js/atadores-proceso-km.test.mjs. */

export function textoEmpleado(cve: string, nombre: string): string {
    return nombre ? `${cve} - ${nombre}` : cve;
}

/** ¿La clave ya está en otra fila (1..3) de la misma tarjeta? `valores[i]` es la fila i+1. */
export function empleadoRepetido(valores: readonly string[], fila: number, cve: string): boolean {
    const clave = cve.trim();
    if (!clave) return false;

    return valores.some((valor, i) => i + 1 !== fila && valor.trim() === clave);
}

/** Valor de <input type="datetime-local"> para `fecha` en hora local. */
export function fechaHoraLocal(fecha: Date): string {
    const dos = (n: number): string => String(n).padStart(2, '0');

    return `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}T${dos(fecha.getHours())}:${dos(fecha.getMinutes())}`;
}

export function nombreTarjeta(prefijo: string): string {
    return prefijo === 'enhebrado' ? 'Enhebrado' : 'Montado';
}
