/**
 * Eficiencia STD y Velocidad STD: reglas puras por variante (antes duplicadas en los <script>
 * de catalagoEficiencia y catalagoVelocidad).
 */
import type { Registro } from '../../../catalogos/catalog-base.ts';

export type Variante = 'eficiencia' | 'velocidad';

/** Eficiencia se guarda 0..1 y se captura en %; Velocidad en RPM. */
export function aFormularioEstandar(variante: Variante, v: Registro): Registro {
    if (variante !== 'eficiencia') return v;

    return { ...v, Eficiencia: Math.round(Number(v.Eficiencia ?? 0) * 100) };
}

export function validarEstandar(variante: Variante, datos: Registro): string | null {
    if (variante === 'eficiencia') {
        const pct = Number(datos.Eficiencia);

        return Number.isFinite(pct) && pct >= 0 && pct <= 100 ? null : 'La eficiencia debe estar entre 0% y 100%';
    }
    const rpm = Number(String(datos.Velocidad ?? '').trim());

    return String(datos.Velocidad ?? '').trim() !== '' && Number.isFinite(rpm) && rpm >= 0
        ? null
        : 'La velocidad debe ser un número válido';
}

export function procesarEstandar(variante: Variante, datos: Registro): Registro {
    const base: Registro = {
        SalonTejidoId: String(datos.SalonTejidoId ?? '').trim(),
        NoTelarId: String(datos.NoTelarId ?? '').trim(),
        FibraId: String(datos.FibraId ?? '').trim(),
        Densidad: String(datos.Densidad ?? '').trim() || 'Normal',
    };

    return variante === 'eficiencia'
        ? { ...base, Eficiencia: Number(datos.Eficiencia) / 100 }
        : { ...base, Velocidad: Number(datos.Velocidad) };
}

export function resumenEstandar(variante: Variante, v: Registro): string {
    const valor =
        variante === 'eficiencia' ? `${Math.round(Number(v.Eficiencia ?? 0) * 100)}%` : `${String(v.Velocidad ?? '')} RPM`;

    return `${String(v.SalonTejidoId ?? '')} · Telar ${String(v.NoTelarId ?? '')} · ${String(v.FibraId ?? '')} · ${valor}`;
}
