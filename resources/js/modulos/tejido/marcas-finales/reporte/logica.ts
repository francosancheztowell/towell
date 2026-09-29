/** Reporte de Marcas Finales por fecha: reglas puras. */

/** Nombre del PDF descargado (el mismo que armaba el script anterior). */
export function nombrePdf(fecha: string): string {
    return `marcas_finales_${String(fecha).replaceAll('/', '-')}.pdf`;
}
