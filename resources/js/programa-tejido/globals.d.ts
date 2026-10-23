/**
 * Superficie global de Programa Tejido.
 *
 * Solo lo que de verdad cruza una frontera: lo que la pagina (otros scripts,
 * otros Blade) consume del bundle, y lo que el servidor le pasa al bundle.
 *
 * Deliberadamente NO estan las ~95 funciones que el bundle se llama a si mismo
 * por window.: eso es herencia de cuando el codigo eran 8 Blade dentro de un
 * mismo <script>, no un contrato. Se declaran junto al codigo, en index.ts.
 * Se pasan aqui cuando algun consumidor externo real las necesite.
 */
declare global {
    interface Window {
        /** index.ts: refresca en su lugar todas las filas de los telares de esos registros. */
        ptRefrescarTelares?: (ids: string[]) => Promise<void>;
        /**
         * Valores que solo conoce el servidor. Los imprime
         * resources/views/modulos/programa-tejido/scripts/main.blade.php
         * como <script type="application/json" id="pt-boot">; lo lee boot.ts.
         */
        PT_BOOT?: {
            basePath: string;
            apiPath: string;
            linePath: string;
            /** Definicion de las 92 columnas, de UtilityHelpers::getTableColumns(). */
            columns: Array<{ field: string; label: string; dateType?: 'date' | 'datetime' | null }>;
            /** Campos que el usuario tiene ocultos (OrdColProgramaTejido). */
            hiddenFields: string[];
            /** Capacidades de la superficie (config planeacion.superficies): false = acción B oculta. */
            capacidades?: Record<string, boolean>;
            routes: {
                codificacion: string;
                codificacionModelos: string;
                vincularRegistros: string;
                marbetes: string;
                marbetesGuardar: string;
                recalcularFechas: string;
            };
        };

        /** Namespace del modulo. Lo consume act-calendarios.blade.php via PT.loader. */
        PT?: {
            loader?: { show(): void; hide(): void };
            presets?: { save(): void; load(): void };
            rowCache?: WeakMap<HTMLElement, unknown>;
            clearRowCache?(): void;
            filterIndex?: {
                rebuild(): void;
                updateRow(row: HTMLElement): void;
                removeRow(row: HTMLElement): void;
            };
        };

        // Puentes de los modulos del bundle (PT-TS 1): los llaman index.ts o el onclose de
        // x-ui.modal-base en los Blade de modal/*.
        abrirModalActCalendarios?: () => Promise<void>;
        cerrarModalActCalendarios?: () => void;
        abrirModalRepaso?: (row: Element | null | undefined) => void;
        cerrarModalRepaso?: () => void;
        abrirModalMarbetes?: (row: Element | null | undefined) => void;
        cerrarModalMarbetes?: () => void;
        loadReqProgramaTejidoLines?: (params?: Record<string, string>) => Promise<void>;
        openLinesModal?: (programaId: string | number) => void;
        verDetallesGrupoBalanceo?: (ordCompartida: number | string) => Promise<void>;
        abrirBalancearDesdeSeleccion?: () => void;
        aplicarBalanceoAutomatico?: (ordCompartida: number | string) => Promise<void>;
        recargarGanttOrdCompartida?: (ordCompartida: number | string) => Promise<void>;
        /** Columnas de la grilla si otro script las publica; balancear cae al <thead> si no. */
        columns?: Array<{ field: string; label?: string; dateType?: 'date' | 'datetime' | null }>;

        /** Vive en el scope de index.ts y hoy no se publica (HANDOFF PT-TS 2): repaso lo busca aqui. */
        agregarRegistroSinRecargar?: (data: Record<string, unknown>, opciones?: { preventReload?: boolean }) => Promise<unknown>;

    }
}

// Hace de este archivo un módulo: sin esto el `declare global` no aplica (y skipLibCheck lo calla).
export {};
