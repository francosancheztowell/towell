/**
 * URL de la superficie actual (Programa o Muestras) a partir de la URL de Programa.
 *
 * Es la misma regla que el parche de window.fetch de index.js: el código de la grilla escribe
 * las rutas de Programa y el parche las reescribe a la superficie. window.http (axios) no pasa
 * por ese parche, así que lo que se migra a http reescribe la URL aquí.
 * tests/Js/programa-tejido-rutas.test.ts compara las dos implementaciones.
 */
export interface RutasSuperficie {
    basePath?: string | undefined;
    apiPath?: string | undefined;
    linePath?: string | undefined;
}

export function rutaSuperficie(url: string, boot: RutasSuperficie | null | undefined = window.PT_BOOT): string {
    const base = boot?.basePath || '/planeacion/programa-tejido';
    const api = boot?.apiPath || '/programa-tejido';
    const lineas = boot?.linePath || '/req-programa-tejido-line';
    let next = url;

    if (next.includes('/planeacion/programa-tejido')) {
        next = next.replace('/planeacion/programa-tejido', base);
    }
    if (next.includes('/programa-tejido')) {
        next = next.replace('/programa-tejido', api);
    }
    if (next.includes('/planeacion/req-programa-tejido-line')) {
        next = next.replace('/planeacion/req-programa-tejido-line', lineas);
    }

    return next;
}
