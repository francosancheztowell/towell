import { errorMessage, eventElement, queryElement } from './dom';
import { ScrollManager } from './scroll-manager';
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
    produccion: 'Producción',
    ventas: 'Ventas',
};

export class DetailLoader {
    private readonly cache = new Map<string, CachedDetail>();
    private readonly pending = new Map<string, PendingDetail>();
    private activeController: AbortController | null = null;
    private sequence = 0;

    public constructor(
        private readonly page: HTMLElement,
        private readonly result: HTMLElement,
        private readonly routes: Partial<Record<Exclude<DetailType, 'ventas'>, string>>,
        private readonly hooks: DetailHooks,
        private readonly scroll: ScrollManager,
    ) {
        this.bind();
    }

    public invalidateAndClose(): void {
        this.cache.clear();
        this.close();
    }

    private bind(): void {
        this.page.addEventListener('click', (event) => {
            const trigger = eventElement(event)?.closest<HTMLElement>('[data-resumen-detalle]');
            if (!trigger) return;

            const type = trigger.dataset.resumenDetalle as DetailType | undefined;
            if (type && type in TITLES) void this.open(type);
        });

        this.result.addEventListener('click', (event) => {
            if (eventElement(event)?.closest('[data-volver-resumen]')) this.close();
        });
    }

    private async open(type: DetailType): Promise<void> {
        const requestSequence = ++this.sequence;
        this.showShell(TITLES[type]);

        if (type === 'ventas') {
            this.cancelActive();
            this.loading(false);
            const content = this.content();
            if (content) content.appendChild(this.salesPlaceholder());
            return;
        }

        try {
            const data = await this.request(type, this.currentFilters());
            if (requestSequence !== this.sequence) return;

            const content = this.content();
            if (content) content.innerHTML = data.html || '';

            if (type === 'flogs') this.hooks.flogs();
            if (type === 'produccion') this.hooks.produccion();
            if (type === 'trazabilidad') this.hooks.trazabilidad(data);
            this.scroll.restoreInteraction();
        } catch (error) {
            if (requestSequence !== this.sequence || this.wasCancelled(error)) return;

            const errorBox = this.errorBox();
            if (errorBox) {
                errorBox.textContent = errorMessage(error, 'No se pudo cargar el detalle.');
                errorBox.classList.remove('hidden');
            }
        } finally {
            if (requestSequence === this.sequence) this.loading(false);
        }
    }

    private request(
        type: Exclude<DetailType, 'ventas'>,
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
        if (heading) heading.textContent = title;

        this.content()?.replaceChildren();
        const errorBox = this.errorBox();
        if (errorBox) {
            errorBox.replaceChildren();
            errorBox.classList.add('hidden');
        }
        this.loading(true);
    }

    private close(): void {
        this.cancelActive();
        this.sequence++;
        this.content()?.replaceChildren();
        this.loading(false);
        const errorBox = this.errorBox();
        if (errorBox) {
            errorBox.replaceChildren();
            errorBox.classList.add('hidden');
        }
        this.result.classList.add('hidden');
        queryElement<HTMLElement>('#resultado-resumen-livewire')?.classList.remove('hidden');
        this.scroll.restoreInteraction();
    }

    private currentFilters(): TrazabilidadFilters {
        const value = (selector: string): string => {
            const input = queryElement<HTMLInputElement | HTMLSelectElement>(selector);
            return input?.value?.trim() || '';
        };

        return {
            flog: value('#filtro-flog'),
            articulo: value('#filtro-articulo'),
            tamano: value('#filtro-tamano'),
            color: value('#filtro-color'),
            mes: value('#filtro-mes'),
            metrica: value('#filtro-metrica') === 'peso' ? 'peso' : 'cantidad',
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

    private salesPlaceholder(): HTMLElement {
        const box = document.createElement('div');
        box.className = 'rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm';

        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-receipt text-3xl text-slate-300';
        const title = document.createElement('p');
        title.className = 'mt-4 font-bold text-slate-600';
        title.textContent = 'Detalle de ventas pendiente de conexión';
        const copy = document.createElement('p');
        copy.className = 'mt-1 text-sm text-slate-400';
        copy.textContent = 'Esta pantalla es únicamente frontend por el momento.';
        box.append(icon, title, copy);

        return box;
    }
}
