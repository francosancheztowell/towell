/**
 * Producción Engomado — validación de filas antes de "Listo" y de Finalizar.
 * Las reglas viven en logica.ts (camposFaltantes); aquí solo se lee y marca el DOM.
 */
import { cfg, cuerpoTabla, marcarCampoError, valorDe } from './contexto.ts';
import { SELECTOR_CAMPO, camposFaltantes, parsearOficiales, renglonesFaltantes, type DatosFila } from './logica.ts';

function datosDeFila(fila: HTMLElement): DatosFila {
    const cantidad = (nombre: string): string =>
        fila.querySelector(`button[data-campo="${nombre}"] .quantity-display`)?.textContent?.trim() ?? '';
    return {
        fecha: valorDe(fila, 'input.input-fecha'),
        tieneOficial: parsearOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson).length > 0,
        turno: valorDe(fila, 'select[data-field="turno"]'),
        hInicio: valorDe(fila, 'input[data-field="h_inicio"]'),
        hFin: valorDe(fila, 'input[data-field="h_fin"]'),
        julio: valorDe(fila, 'select[data-field="no_julio"]'),
        kgBruto: valorDe(fila, 'input[data-field="kg_bruto"]'),
        tara: valorDe(fila, 'input[data-field="tara"]'),
        kgNeto: valorDe(fila, 'input[data-field="kg_neto"]'),
        metros: valorDe(fila, 'input[data-field="metros"]'),
        solidos: valorDe(fila, 'input[data-field="solidos"]'),
        canoa1: cantidad('temp_canoa1'),
        canoa2: cantidad('temp_canoa2'),
        ubicacion: valorDe(fila, 'select[data-field="ubicacion"]'),
    };
}

/** Campos que le faltan a la fila (vacío = completa). */
export function validarFila(fila: HTMLElement): string[] {
    return camposFaltantes(datosDeFila(fila), cfg().maxKgBruto);
}

const mermas = (): HTMLInputElement[] =>
    Array.from(document.querySelectorAll<HTMLInputElement>('input[data-field="merma_con_goma"], input[data-field="merma_sin_goma"]'));

function limpiarErroresVisuales(): void {
    cuerpoTabla()?.querySelectorAll('input, select').forEach((c) => marcarCampoError(c, false));
    mermas().forEach((c) => marcarCampoError(c, false));
}

/** Valida mermas y todas las filas, marca en rojo lo que falta y devuelve los renglones del aviso. */
export function validarRegistrosCompletos(): string[] {
    limpiarErroresVisuales();

    const [conGoma, sinGoma] = [
        document.querySelector<HTMLInputElement>('input[data-field="merma_con_goma"]'),
        document.querySelector<HTMLInputElement>('input[data-field="merma_sin_goma"]'),
    ];
    let faltaMerma = false;
    for (const m of [conGoma, sinGoma]) {
        if (!m || m.value.trim() === '') {
            marcarCampoError(m, true);
            faltaMerma = true;
        }
    }

    const incompletas: { fila: number; campos: string[] }[] = [];
    cuerpoTabla()
        ?.querySelectorAll<HTMLTableRowElement>('tr[data-registro-id]')
        .forEach((fila, i) => {
            const faltan = validarFila(fila);
            if (!faltan.length) return;
            for (const c of faltan) {
                const control = fila.querySelector<HTMLElement>(SELECTOR_CAMPO[c] ?? ':not(*)');
                if (c.startsWith('Temp Canoa')) {
                    if (control) {
                        control.classList.add('border-red-500', 'border-2', 'ring-2', 'ring-red-300');
                        control.style.border = '2px solid #ef4444';
                    }
                } else {
                    marcarCampoError(control, true);
                }
            }
            incompletas.push({ fila: i + 1, campos: faltan });
        });

    return faltaMerma || incompletas.length ? renglonesFaltantes(faltaMerma, incompletas) : [];
}
