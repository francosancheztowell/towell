/**
 * Fila seleccionada de la grilla.
 *
 * window.selectedRowIndex era un número congelado en el momento del clic: al insertar una
 * fila antes, borrar otra fila o reordenar con arrastrar, window.allRows cambiaba y ese
 * índice pasaba a señalar OTRA fila, sobre la que después actuaban Editar, Eliminar o
 * Ver líneas. Ahora lo que se guarda es la fila, y el índice se calcula al leerlo.
 *
 * Los ~20 lugares que leen o escriben window.selectedRowIndex no cambian: siguen viendo
 * un número (-1 = nada seleccionado) y pueden asignarlo igual que antes.
 */
type Filas = () => ArrayLike<Element>;

export function instalarIndiceSeleccion(destino: object, filas: Filas): void {
    let fila: Element | null = null;

    Object.defineProperty(destino, 'selectedRowIndex', {
        configurable: true,
        enumerable: true,
        get(): number {
            // Borrada del DOM: ya no hay selección que señalar.
            if (!fila || !fila.isConnected) return -1;

            return Array.prototype.indexOf.call(filas(), fila);
        },
        set(indice: number | null | undefined) {
            fila = typeof indice === 'number' && indice >= 0 ? (filas()[indice] ?? null) : null;
        },
    });
}
