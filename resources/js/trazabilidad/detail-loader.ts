import { errorMessage, eventElement, queryElement } from './dom';
import type {
    DetailResponse,
    DetailType,
    TrazabilidadFilters,
} from './types';

interface DetailHooks {
    flogs(): void;
    produccion(): void;
    trazabilidad(response: DetailResponse): void;
}

interface CachedDetail {
    createdAt: number;
    data: DetailResponse;
}

interface PendingDetail {
    controller: AbortController;
    promise: Promise<DetailResponse>;
}

const CACHE_TTL_MS = 30_000;
const TITLES: Record<DetailType, string> = {
    flogs: 'Flog',
    trazabilidad: 'Trazabilidad',
    produccion: 'Programa tejido',
};

export class DetailLoader {
    private readonly cache = new Map<string, CachedDetail>();
    private readonly pending = new Map<string, PendingDetail>();
    private activeController: AbortController | null = null;
    private sequence = 0;
    private activeType: DetailType | null = null;
    /** El botón "Ver detalle" que abrió el panel: el foco vuelve ahí al cerrar. */
    private opener: HTMLElement | null = null;

    private readonly page: HTMLElement;
    private readonly result: HTMLElement;
    private readonly routes: Partial<Record<DetailType, string>>;
    private readonly hooks: DetailHooks;

    public constructor(
        page: HTMLElement,
        result: HTMLElement,
        routes: Partial<Record<DetailType, string>>,
        hooks: DetailHooks,
    ) {
        this.page = page;
        this.result = result;
        this.routes = routes;
        this.hooks = hooks;
        this.bind();

        // El detalle abierto vive en la URL (?detalle=…): recargar o compartir el enlace lo reabre.
        const inicial = new URL(window.location.href).searchParams.get('detalle') as DetailType | null;
        if (inicial && inicial in TITLES) void this.open(inicial);
    }

    public invalidateAndClose(): void {
        this.cache.clear();
        if (!this.result.classList.contains('hidden')) this.close();
    }

    private bind(): void {
        this.page.addEventListener('click', (event) => {
            const trigger = eventElement(event)?.closest<HTMLElement>('[data-resumen-detalle]');
            if (!trigger) return;

            const type = trigger.dataset.resumenDetalle as DetailType | undefined;
            if (type && type in TITLES) {
                this.opener = trigger;
                void this.open(type);
            }
        });

        this.result.addEventListener('click', (event) => {
            const target = eventElement(event);
            if (target?.closest('[data-volver-resumen]')) this.close();
            if (target?.closest('[data-detalle-reintentar]') && this.activeType) void this.open(this.activeType);
        });
    }

    private async open(type: DetailType): Promise<void> {
        const requestSequence = ++this.sequence;
        this.activeType = type;
        this.recordarEnUrl(type);
        this.showShell(TITLES[type]);

        try {
            const data = await this.request(type, this.currentFilters());
            if (requestSequence !== this.sequence) return;

            const content = this.content();
            if (content) content.innerHTML = data.html || '';

            if (type === 'flogs') this.hooks.flogs();
            if (type === 'produccion') this.hooks.produccion();
            if (type === 'trazabilidad') this.hooks.trazabilidad(data);
        } catch (error) {
            if (requestSequence !== this.sequence || this.wasCancelled(error)) return;

            this.showError(errorMessage(error, 'No se pudo cargar el detalle.'));
        } finally {
            if (requestSequence === this.sequence) this.loading(false);
        }
    }

    private request(
        type: DetailType,
        filters: TrazabilidadFilters,
    ): Promise<DetailResponse> {
        const route = this.routes[type];
        if (!route) return Promise.reject(new Error('El detalle solicitado no está disponible.'));

        const key = `${type}:${JSON.stringify(filters)}`;
        const inProgress = this.pending.get(key);
        if (inProgress) {
            this.activeController = inProgress.controller;
            return inProgress.promise;
        }

        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.createdAt < CACHE_TTL_MS) {
            this.cancelActive();
            return Promise.resolve(cached.data);
        }
        this.cache.delete(key);

        this.cancelActive();
        const controller = new AbortController();
        this.activeController = controller;
        const promise = window.http.get<DetailResponse>(route, {
            params: filters,
            signal: controller.signal,
        }).then((data) => {
            this.cache.set(key, { data, createdAt: Date.now() });
            return data;
        }).finally(() => {
            if (this.pending.get(key)?.controller === controller) this.pending.delete(key);
            if (this.activeController === controller) this.activeController = null;
        });

        this.pending.set(key, { controller, promise });

        return promise;
    }

    private showShell(title: string): void {
        queryElement<HTMLElement>('#resultado-resumen-livewire')?.classList.add('hidden');
        this.result.classList.remove('hidden');

        const heading = queryElement<HTMLElement>('[data-detalle-titulo]', this.result);
        if (heading) {
            heading.textContent = title;
            // Quien navega con teclado o lector de pantalla llega al título del detalle.
            heading.focus({ preventScroll: true });
        }

        this.content()?.replaceChildren();
        this.hideError();
        this.loading(true);
    }

    private close(): void {
        this.cancelActive();
        this.sequence++;
        this.activeType = null;
        this.recordarEnUrl(null);
        this.content()?.replaceChildren();
        this.loading(false);
        this.hideError();
        this.result.classList.add('hidden');
        queryElement<HTMLElement>('#resultado-resumen-livewire')?.classList.remove('hidden');

        if (this.opener?.isConnected) this.opener.focus();
        this.opener = null;
    }

    /** replaceState y no pushState: abrir/cerrar el detalle no llena el historial del botón Atrás. */
    private recordarEnUrl(type: DetailType | null): void {
        const url = new URL(window.location.href);
        if (type) {
            url.searchParams.set('detalle', type);
        } else {
            url.searchParams.delete('detalle');
        }
        window.history.replaceState(window.history.state, '', url);
    }

    private currentFilters(): TrazabilidadFilters {
        const value = (selector: string): string =>
            queryElement<HTMLSelectElement>(selector)?.value?.trim() || '';

        return {
            flog: value('#filtro-flog'),
            articulo: value('#filtro-articulo'),
            tamano: value('#filtro-tamano'),
        };
    }

    private cancelActive(): void {
        this.activeController?.abort();
        this.activeController = null;
    }

    private wasCancelled(error: unknown): boolean {
        if (error instanceof DOMException && error.name === 'AbortError') return true;
        if (typeof error !== 'object' || error === null) return false;

        const candidate = error as {
            name?: string;
            code?: string;
            original?: { code?: string };
        };

        return candidate.name === 'AbortError'
            || candidate.code === 'ERR_CANCELED'
            || candidate.original?.code === 'ERR_CANCELED';
    }

    private showError(message: string): void {
        const text = queryElement<HTMLElement>('[data-detalle-error-texto]', this.result);
        if (text) text.textContent = message;
        this.errorBox()?.classList.remove('hidden');
    }

    private hideError(): void {
        this.errorBox()?.classList.add('hidden');
    }

    private loading(show: boolean): void {
        queryElement<HTMLElement>('[data-detalle-cargando]', this.result)
            ?.classList.toggle('hidden', !show);
    }

    private content(): HTMLElement | null {
        return queryElement<HTMLElement>('[data-detalle-contenido]', this.result);
    }

    private errorBox(): HTMLElement | null {
        return queryElement<HTMLElement>('[data-detalle-error]', this.result);
    }
}
