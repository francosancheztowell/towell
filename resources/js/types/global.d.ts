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

/** Lo mínimo de jQuery + Select2 que usa el código TS (trazabilidad). Se va con 15-02. */
interface Select2Bridge {
    data(key: string): unknown;
    select2(command: 'close' | 'destroy' | Record<string, unknown>): void;
    off(events: string): Select2Bridge;
    on(events: string, handler: (event: Event) => void): Select2Bridge;
}

interface JQueryBridge {
    (element: Element): Select2Bridge;
}

interface LivewireClient {
    dispatch(event: string, params?: Record<string, unknown>): void;
    on(event: string, callback: (...args: any[]) => void): () => void;
    hook(name: string, callback: (...args: any[]) => void): void;
}

/** Métodos de toastr que existen en vistas; lo reemplaza notify en 15-02. */
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
    var $: JQueryBridge | undefined;
    var jQuery: JQueryBridge | undefined;
    var Livewire: LivewireClient | undefined;
}

export {};
