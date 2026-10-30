/**
 * Título del navbar según la subsección de la sidebar (sin DOM, para probarlo con node --test).
 * Resumen General conserva el título del tablero; las demás muestran su propio nombre.
 */
export const pageTitle = (sub: string, label: string, base: string): string => {
    const name = label.replace(/\s+/g, ' ').trim();
    return sub === 'resumen' || name === '' ? base : name;
};
