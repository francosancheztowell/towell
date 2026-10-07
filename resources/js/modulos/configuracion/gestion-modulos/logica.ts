/**
 * Gestión de módulos: lógica sin DOM (orden sugerido, dependencias, filtros de usuarios).
 * La usa ./index.ts; la prueba tests/Js/gestion-modulos.test.ts.
 */

export const CAMPOS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as const;
export type Campo = (typeof CAMPOS)[number];

export interface ModuloDato {
    key: string;
    orden: string;
    modulo: string;
    nivel: string;
    dependencia: string;
}

export type UsuarioPermisos = {
    id: number;
    numero: string;
    nombre: string;
    area: string;
    puesto: string;
} & Record<Campo, boolean>;

export type FiltroAcceso = 'todos' | 'con' | 'sin';

export interface Filtro {
    texto: string;
    area: string;
    acceso: FiltroAcceso;
}

/** Minúsculas y sin acentos: "Planeación" se encuentra escribiendo "planeacion". */
export function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}

/**
 * Orden que le toca a un módulo nuevo:
 * nivel 1 → siguiente centena; nivel 2 → siguiente número tras el último hijo del padre;
 * nivel 3 → "<padre>-N" con N secuencial.
 */
export function calcularOrden(modulos: ModuloDato[], nivel: string, dependencia: string): string {
    if (nivel === '1') {
        const max = Math.max(0, ...modulos.filter((m) => m.nivel === '1').map((m) => parseInt(m.orden, 10) || 0));
        return String(max === 0 ? 100 : max + 100);
    }
    if (!dependencia) return '';

    if (nivel === '2') {
        const base = parseInt(dependencia, 10);
        if (Number.isNaN(base)) return '';
        const hermanos = modulos
            .filter((m) => m.nivel === '2' && m.dependencia === dependencia)
            .map((m) => parseInt(m.orden, 10) || 0);
        return String(Math.max(base, ...hermanos) + 1);
    }

    if (nivel === '3') {
        const sufijos = modulos
            .filter((m) => m.nivel === '3' && m.dependencia === dependencia)
            .map((m) => parseInt(/-(\d+)$/.exec(m.orden)?.[1] ?? '0', 10));
        return `${dependencia}-${Math.max(0, ...sufijos) + 1}`;
    }

    return '';
}

/** Padres posibles para un nivel: nivel 2 cuelga de un nivel 1; nivel 3 de un nivel 2. */
export function opcionesDependencia(modulos: ModuloDato[], nivel: string): { value: string; label: string }[] {
    const nivelPadre = nivel === '2' ? '1' : nivel === '3' ? '2' : '';
    if (!nivelPadre) return [];

    return modulos
        .filter((m) => m.nivel === nivelPadre)
        .map((m) => {
            const abuelo = nivel === '3' ? modulos.find((p) => p.orden === m.dependencia) : undefined;
            return { value: m.orden, label: `${m.modulo} (${m.orden})${abuelo ? ` · ${abuelo.modulo}` : ''}` };
        });
}

/** Nombres de los ancestros, del más alto al padre directo. */
export function ancestros(modulos: ModuloDato[], modulo: ModuloDato): string[] {
    const porOrden = new Map(modulos.map((m) => [m.orden, m]));
    const nombres: string[] = [];
    let actual = porOrden.get(modulo.dependencia);
    while (actual && nombres.length < 5) {
        nombres.unshift(actual.modulo);
        actual = porOrden.get(actual.dependencia);
    }
    return nombres;
}

export function filtrarUsuarios(usuarios: UsuarioPermisos[], filtro: Filtro): UsuarioPermisos[] {
    const texto = normalizar(filtro.texto);
    return usuarios.filter((u) => {
        if (filtro.area && u.area !== filtro.area) return false;
        if (filtro.acceso === 'con' && !u.acceso) return false;
        if (filtro.acceso === 'sin' && u.acceso) return false;
        return !texto || normalizar(`${u.nombre} ${u.numero} ${u.puesto}`).includes(texto);
    });
}

/** Cuántos usuarios tienen cada permiso. */
export function contarPermisos(usuarios: UsuarioPermisos[]): Record<Campo, number> {
    const conteo = Object.fromEntries(CAMPOS.map((c) => [c, 0])) as Record<Campo, number>;
    for (const u of usuarios) for (const c of CAMPOS) if (u[c]) conteo[c]++;
    return conteo;
}
