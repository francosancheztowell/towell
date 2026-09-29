/**
 * Tabla de captura: inputs de RPM / % EF por horario, estándares y lectura de las filas.
 */
import { qs, qsa } from '../../../../utils/dom.ts';
import {
    HORARIOS,
    construirLinea,
    estandarDe,
    horaDeTexto,
    limitarValor,
    pctGuardado,
    pctStd,
    sugerirValor,
    tituloObservacion,
    valorGuardado,
    type Horario,
    type LineaCorte,
    type TipoValor,
} from '../comun/logica.ts';
import { cuerpoTabla, estado, horaHorario } from './estado.ts';

const esc = (v: string | number): string => CSS.escape(String(v));

export function inputValor(telar: string | number, h: number, tipo: TipoValor, raiz: ParentNode = document): HTMLInputElement | null {
    return qs<HTMLInputElement>(`input.valor-input[data-telar="${esc(telar)}"][data-horario="${h}"][data-type="${tipo}"]`, raiz);
}

export function inputStd(telar: string | number, campo: 'rpm_std' | 'eficiencia_std', raiz: ParentNode = document): HTMLInputElement | null {
    return qs<HTMLInputElement>(`input[data-telar="${esc(telar)}"][data-field="${campo}"]`, raiz);
}

export function checkObs(telar: string | number, h: number, raiz: ParentNode = document): HTMLInputElement | null {
    return qs<HTMLInputElement>(`input.obs-checkbox[data-telar="${esc(telar)}"][data-horario="${h}"]`, raiz);
}

export function horarioTomado(h: number): boolean {
    return horaDeTexto(horaHorario(h)?.textContent) !== null;
}

export function leerHorarios(): Record<Horario, string | null> {
    const val = (h: Horario): string | null => horaDeTexto(horaHorario(h)?.textContent);
    return { 1: val(1), 2: val(2), 3: val(3) };
}

export function inputsStd(): HTMLInputElement[] {
    return qsa<HTMLInputElement>('input[data-field="rpm_std"], input[data-field="eficiencia_std"]');
}

export function valorEntero(input: HTMLInputElement | null): number {
    return input ? parseInt(input.value, 10) || 0 : 0;
}

/** Valor sugerido al enfocar un input en 0: horario anterior o estándar. */
export function valorSugerido(telar: string, horario: number, tipo: TipoValor): number {
    const std = inputStd(telar, tipo === 'rpm' ? 'rpm_std' : 'eficiencia_std');
    const estandar = estandarDe(tipo, std?.value);
    return sugerirValor(horario, estandar, {
        1: valorEntero(inputValor(telar, 1, tipo)),
        2: valorEntero(inputValor(telar, 2, tipo)),
    });
}

export function limitarInput(input: HTMLInputElement): void {
    const tipo = input.dataset.type === 'rpm' ? 'rpm' : 'eficiencia';
    input.value = String(limitarValor(input.value, tipo, parseInt(input.dataset.telar ?? '', 10)));
}

/** Enter: RPM → % EF de la misma fila; % EF → RPM de la siguiente fila (mismo horario). */
export function siguienteInput(input: HTMLInputElement): HTMLInputElement | null {
    const horario = input.dataset.horario ?? '';
    const activos = (tipo: TipoValor): HTMLInputElement[] =>
        qsa<HTMLInputElement>(`input.valor-input[data-horario="${esc(horario)}"][data-type="${tipo}"]`).filter((el) => !el.disabled && !el.readOnly);

    if (input.dataset.type === 'rpm') {
        const ef = inputValor(input.dataset.telar ?? '', Number(horario), 'eficiencia');
        return ef && !ef.disabled && !ef.readOnly ? ef : null;
    }
    const idx = activos('eficiencia').indexOf(input);
    return idx === -1 ? null : activos('rpm')[idx + 1] ?? null;
}

/* ---------- Estándares ---------- */

interface TelarPrograma {
    NoTelar?: number | string;
    noTelar?: number | string;
    telar?: number | string;
    VelocidadSTD?: number | string | null;
    VelocidadStd?: number | string | null;
    RPM?: number | string | null;
    rpm?: number | string | null;
    EficienciaSTD?: number | string | null;
    EficienciaStd?: number | string | null;
    Eficiencia?: number | string | null;
    eficiencia?: number | string | null;
}

/** Estándares del programa de tejido; pone en 0 los horarios de esos telares. */
export function pintarProgramaStd(telares: TelarPrograma[]): void {
    for (const t of telares) {
        const n = t.NoTelar ?? t.noTelar ?? t.telar;
        if (!n) continue;
        const rpm = t.VelocidadSTD ?? t.VelocidadStd ?? t.RPM ?? t.rpm ?? 0;
        const ef = t.EficienciaSTD ?? t.EficienciaStd ?? t.Eficiencia ?? t.eficiencia ?? 0;
        const rpmInput = inputStd(n, 'rpm_std');
        const efInput = inputStd(n, 'eficiencia_std');
        if (rpmInput) {
            rpmInput.value = String(rpm);
            rpmInput.placeholder = '';
        }
        if (efInput) {
            efInput.value = pctStd(ef);
            efInput.placeholder = '';
        }
        for (const h of HORARIOS) {
            const r = inputValor(n, h, 'rpm');
            const e = inputValor(n, h, 'eficiencia');
            if (r) r.value = '0';
            if (e) e.value = '0';
        }
    }
}

interface TelarHistorial {
    NoTelarId?: number | string;
    NoTelar?: number | string;
    telar?: number | string;
    VelocidadStd?: number | string | null;
    RpmStd?: number | string | null;
    EficienciaStd?: number | string | null;
    Eficiencia?: number | string | null;
}

/** Completa los estándares vacíos con la última RPM/eficiencia real del telar. */
export function completarStd(telares: TelarHistorial[]): void {
    for (const t of telares) {
        const n = t.NoTelarId ?? t.NoTelar ?? t.telar;
        if (!n) continue;
        const rpmInput = inputStd(n, 'rpm_std');
        const efInput = inputStd(n, 'eficiencia_std');
        if (rpmInput && !rpmInput.value) rpmInput.value = String(t.VelocidadStd ?? t.RpmStd ?? 0);
        if (efInput && !efInput.value) efInput.value = pctStd(t.EficienciaStd ?? t.Eficiencia ?? 0);
    }
}

export function marcarErrorStd(): void {
    for (const i of inputsStd()) {
        if (!i.value) i.placeholder = 'Error al cargar';
    }
}

/* ---------- Corte guardado ---------- */

export function sincronizarTituloObs(telar: string | number, h: number, texto: string): void {
    const cb = checkObs(telar, h);
    if (cb) cb.title = tituloObservacion(texto);
}

function pintarValor(telar: number, h: Horario, tipo: TipoValor, valor: unknown): void {
    const input = inputValor(telar, h, tipo);
    const texto = valorGuardado(tipo, valor);
    if (input && texto !== null) input.value = texto;
}

function pintarObs(telar: number, h: Horario, marcado: unknown, texto: string | null): void {
    const cb = checkObs(telar, h);
    if (cb) cb.checked = !!marcado;
    if (texto) {
        estado.observaciones[`${telar}-${h}`] = texto;
        sincronizarTituloObs(telar, h, texto);
    }
}

export function pintarLineas(lineas: LineaCorte[]): void {
    for (const t of lineas) {
        const id = t.NoTelar;
        const rpmStd = inputStd(id, 'rpm_std');
        const efStd = inputStd(id, 'eficiencia_std');
        if (rpmStd) rpmStd.value = t.RpmStd == null ? '' : String(t.RpmStd);
        if (efStd) efStd.value = pctGuardado(t.EficienciaStd);
        pintarValor(id, 1, 'rpm', t.RpmR1);
        pintarValor(id, 1, 'eficiencia', t.EficienciaR1);
        pintarValor(id, 2, 'rpm', t.RpmR2);
        pintarValor(id, 2, 'eficiencia', t.EficienciaR2);
        pintarValor(id, 3, 'rpm', t.RpmR3);
        pintarValor(id, 3, 'eficiencia', t.EficienciaR3);
        pintarObs(id, 1, t.StatusOB1, t.ObsR1);
        pintarObs(id, 2, t.StatusOB2, t.ObsR2);
        pintarObs(id, 3, t.StatusOB3, t.ObsR3);
    }
}

/** Una línea por fila de la tabla, en el orden de la secuencia. */
export function recopilarDatosTelares(): LineaCorte[] {
    const out: LineaCorte[] = [];
    for (const fila of qsa<HTMLTableRowElement>('tr', cuerpoTabla())) {
        const telar = fila.querySelector('td:first-child')?.textContent?.trim();
        if (!telar) continue;
        const rpmStd = inputStd(telar, 'rpm_std', fila);
        const efStd = inputStd(telar, 'eficiencia_std', fila);
        const lee = (h: Horario, tipo: TipoValor): number => valorEntero(inputValor(telar, h, tipo, fila));
        const marcado = (h: Horario): boolean => !!checkObs(telar, h, fila)?.checked;
        const obs = (h: Horario): string => estado.observaciones[`${telar}-${h}`] ?? '';
        out.push(
            construirLinea({
                telar: parseInt(telar, 10),
                rpmStd: rpmStd ? rpmStd.value : null,
                efStd: efStd ? efStd.value : null,
                rpm: { 1: lee(1, 'rpm'), 2: lee(2, 'rpm'), 3: lee(3, 'rpm') },
                eficiencia: { 1: lee(1, 'eficiencia'), 2: lee(2, 'eficiencia'), 3: lee(3, 'eficiencia') },
                marcado: { 1: marcado(1), 2: marcado(2), 3: marcado(3) },
                obs: { 1: obs(1), 2: obs(2), 3: obs(3) },
            }),
        );
    }
    return out;
}

export function aplicarModoSoloLectura(): void {
    for (const btn of qsa<HTMLButtonElement>('[data-accion="tomar-hora"]')) {
        btn.disabled = true;
        btn.classList.add('opacity-60', 'cursor-not-allowed');
    }
    for (const input of [...inputsStd(), ...qsa<HTMLInputElement>('.valor-input')]) {
        input.readOnly = true;
        input.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
    }
    for (const cb of qsa<HTMLInputElement>('.obs-checkbox')) {
        cb.dataset.readonly = '1';
        cb.classList.add('cursor-pointer', 'opacity-70');
    }
}
