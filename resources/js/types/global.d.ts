/**
 * Globals que expone resources/js/bootstrap.js y que consumen los scripts
 * inline de Blade y los módulos TS. Los tipos salen de utils/: si cambia la
 * API de http/notify, cambia aquí sola.
 *
 * Globals propias de un módulo (PT_BOOT, inventarioCache, …) se declaran en
 * ese módulo, no aquí.
 */
import type { AxiosStatic } from 'axios';
import type SwalStatic from 'sweetalert2';
import type { Http } from '../utils/http.ts';
import type { Notify } from '../utils/notifications.ts';
import type { Combobox, OpcionesCombobox } from '../utils/combobox.ts';
import type { Librerias } from '../utils/librerias.ts';

interface LivewireClient {
    dispatch(event: string, params?: Record<string, unknown>): void;
    on(event: string, callback: (...args: any[]) => void): () => void;
    hook(name: string, callback: (...args: any[]) => void): void;
}

/** Adaptador temporal window.toastr → notify (bootstrap.js); se retira en la fase 21. */
type ToastrMethod = (message: string, title?: string, options?: Record<string, unknown>) => unknown;
interface ToastrClient {
    success: ToastrMethod;
    error: ToastrMethod;
    warning: ToastrMethod;
    info: ToastrMethod;
    clear(): void;
    options: Record<string, unknown>;
}

// `var` (no `interface Window`): así sirven tanto `window.http` como `http` a secas,
// igual que en los <script> inline de Blade.
declare global {
    var axios: AxiosStatic;
    var http: Http;
    var notify: Notify;
    var showToast: (message: unknown, type?: string) => void;
    var Swal: typeof SwalStatic;
    var toastr: ToastrClient;
    var combobox: (select: HTMLSelectElement, opciones?: OpcionesCombobox) => Promise<Combobox>;
    var librerias: Librerias;
    var Livewire: LivewireClient | undefined;
}

export {};
