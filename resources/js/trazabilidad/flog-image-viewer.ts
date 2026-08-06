import { errorMessage, eventElement, queryElement } from './dom';
import { ScrollManager } from './scroll-manager';

interface ViewerState {
    src: string;
    title: string;
    scale: number;
    baseScale: number;
    panX: number;
    panY: number;
    dragging: boolean;
    dragStartX: number;
    dragStartY: number;
    panStartX: number;
    panStartY: number;
}

export class FlogImageViewer {
    private readonly state: ViewerState = {
        src: '',
        title: '',
        scale: 1,
        baseScale: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        panStartX: 0,
        panStartY: 0,
    };

    public constructor(
        private readonly result: HTMLElement,
        private readonly modal: HTMLElement,
        private readonly scroll: ScrollManager,
    ) {
        this.bind();
    }

    public initImages(root: ParentNode): void {
        root.querySelectorAll<HTMLImageElement>('[data-flog-img]').forEach((image) => {
            if (image.complete && image.naturalWidth === 0) {
                this.markBroken(image);
            }
        });
    }

    public close(): void {
        if (this.modal.classList.contains('hidden')) return;

        this.modal.classList.add('hidden');
        const image = this.image();
        if (image) image.src = '';

        const viewport = this.viewport();
        if (viewport) viewport.style.transform = '';

        this.state.src = '';
        this.state.dragging = false;
        this.stage()?.classList.remove('is-dragging');
        this.scroll.sync();
    }

    private bind(): void {
        this.result.addEventListener('error', (event) => {
            if (event.target instanceof HTMLImageElement && event.target.matches('[data-flog-img]')) {
                this.markBroken(event.target);
            }
        }, true);

        this.result.addEventListener('click', (event) => {
            const trigger = eventElement(event)?.closest<HTMLElement>('[data-flog-zoom]');
            if (!trigger) return;

            const image = queryElement<HTMLImageElement>('img', trigger);
            const src = trigger.dataset.flogZoom || image?.src || '';
            const title = trigger.dataset.flogZoomTitle
                || trigger.getAttribute('aria-label')
                || 'Imagen';

            this.open(src, title);
        });

        this.result.addEventListener('keydown', (event) => {
            if (!(event instanceof KeyboardEvent) || !['Enter', ' '].includes(event.key)) return;
            const trigger = eventElement(event)?.closest<HTMLElement>('[data-flog-zoom]');
            if (!trigger) return;

            event.preventDefault();
            trigger.click();
        });

        this.modal.addEventListener('click', (event) => this.handleModalClick(event));
        this.modal.addEventListener('wheel', (event) => this.handleWheel(event), { passive: false });
        this.modal.addEventListener('mousedown', (event) => this.startDrag(event));

        document.addEventListener('mousemove', (event) => this.drag(event));
        document.addEventListener('mouseup', () => this.stopDrag());
        document.addEventListener('keydown', (event) => this.handleKeydown(event));
        window.addEventListener('resize', () => {
            if (!this.modal.classList.contains('hidden')) this.fit();
        });
    }

    private handleModalClick(event: MouseEvent): void {
        const target = eventElement(event);
        if (!target) return;

        if (target.closest('[data-modal-flog-close]')) {
            this.close();
            return;
        }
        if (target.closest('[data-flog-zoom-in]')) {
            this.zoom(1.2);
            return;
        }
        if (target.closest('[data-flog-zoom-out]')) {
            this.zoom(1 / 1.2);
            return;
        }
        if (target.closest('[data-flog-zoom-reset]')) {
            this.fit();
            return;
        }
        if (target.closest('[data-flog-download]')) {
            void this.download();
            return;
        }
        if (
            target.classList.contains('modal-flog-imagen__backdrop')
            || (target.closest('[data-flog-stage]') && target.tagName !== 'IMG')
        ) {
            this.close();
        }
    }

    private handleWheel(event: WheelEvent): void {
        if (
            this.modal.classList.contains('hidden')
            || !eventElement(event)?.closest('[data-flog-stage]')
        ) {
            return;
        }

        event.preventDefault();
        this.zoom(event.deltaY < 0 ? 1.12 : 1 / 1.12);
    }

    private startDrag(event: MouseEvent): void {
        if (
            event.button !== 0
            || !(event.target instanceof HTMLImageElement)
            || !event.target.closest('[data-flog-stage]')
        ) {
            return;
        }

        this.state.dragging = true;
        this.state.dragStartX = event.clientX;
        this.state.dragStartY = event.clientY;
        this.state.panStartX = this.state.panX;
        this.state.panStartY = this.state.panY;
        this.stage()?.classList.add('is-dragging');
    }

    private drag(event: MouseEvent): void {
        if (this.modal.classList.contains('hidden') || !this.state.dragging) return;

        this.state.panX = this.state.panStartX + event.clientX - this.state.dragStartX;
        this.state.panY = this.state.panStartY + event.clientY - this.state.dragStartY;
        this.applyTransform();
    }

    private stopDrag(): void {
        if (!this.state.dragging) return;

        this.state.dragging = false;
        this.stage()?.classList.remove('is-dragging');
    }

    private handleKeydown(event: KeyboardEvent): void {
        if (this.modal.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
            this.close();
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            this.zoom(1.2);
        } else if (event.key === '-') {
            event.preventDefault();
            this.zoom(1 / 1.2);
        } else if (event.key === '0') {
            event.preventDefault();
            this.fit();
        }
    }

    private open(src: string, title: string): void {
        const image = this.image();
        if (!src || !image) return;

        this.state.src = src;
        this.state.title = title;
        this.state.scale = 1;
        this.state.baseScale = 1;
        this.state.panX = 0;
        this.state.panY = 0;

        image.onload = () => this.fit();
        image.src = src;
        image.alt = title;
        if (image.complete && image.naturalWidth > 0) this.fit();

        const heading = queryElement<HTMLElement>('#modal-flog-imagen-titulo');
        if (heading) heading.textContent = title;

        this.modal.classList.remove('hidden');
        this.scroll.sync();
    }

    private fit(): void {
        const image = this.image();
        const stage = this.stage();
        if (!image || !stage || image.naturalWidth === 0 || image.naturalHeight === 0) return;

        const availableWidth = Math.max(stage.clientWidth - 48, 200);
        const availableHeight = Math.max(stage.clientHeight - 48, 200);
        this.state.baseScale = Math.min(
            availableWidth / image.naturalWidth,
            availableHeight / image.naturalHeight,
        );
        this.state.scale = this.state.baseScale;
        this.state.panX = 0;
        this.state.panY = 0;
        image.style.width = `${image.naturalWidth}px`;
        image.style.height = `${image.naturalHeight}px`;
        this.applyTransform();
    }

    private zoom(factor: number): void {
        const min = this.state.baseScale * 0.35;
        const max = this.state.baseScale * 12;
        this.state.scale = Math.min(max, Math.max(min, this.state.scale * factor));
        this.applyTransform();
    }

    private applyTransform(): void {
        const viewport = this.viewport();
        if (viewport) {
            viewport.style.transform = `translate(${this.state.panX}px, ${this.state.panY}px) scale(${this.state.scale})`;
        }

        const percentage = this.state.baseScale > 0
            ? Math.round((this.state.scale / this.state.baseScale) * 100)
            : 100;
        const label = queryElement<HTMLElement>('[data-flog-zoom-label]', this.modal);
        if (label) label.textContent = `${percentage}%`;
    }

    private async download(): Promise<void> {
        if (!this.state.src) return;

        try {
            const response = await fetch(this.state.src, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('No se pudo obtener la imagen.');

            const url = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = this.fileName();
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(url);
            window.notify?.success('Descarga iniciada');
        } catch (error) {
            window.notify?.error(errorMessage(error, 'No se pudo descargar la imagen.'));
        }
    }

    private fileName(): string {
        try {
            const file = new URL(this.state.src, window.location.origin).searchParams.get('file');
            if (file) return file;
        } catch {
            // Usa el título como respaldo.
        }

        const base = (this.state.title || 'imagen-flog')
            .replace(/[^\w\s\-.áéíóúñÁÉÍÓÚÑ]/g, '')
            .trim()
            .replace(/\s+/g, '_')
            .slice(0, 80);

        return `${base || 'imagen-flog'}.jpg`;
    }

    private markBroken(image: HTMLImageElement): void {
        if (image.classList.contains('is-broken')) return;

        image.classList.add('is-broken');
        const thumb = image.closest<HTMLElement>('.flog-lineas-thumb');
        const cell = thumb?.closest<HTMLTableCellElement>('td');
        if (thumb) thumb.remove();
        if (cell && !cell.textContent?.trim()) cell.textContent = '—';
    }

    private stage(): HTMLElement | null {
        return queryElement<HTMLElement>('[data-flog-stage]', this.modal);
    }

    private viewport(): HTMLElement | null {
        return queryElement<HTMLElement>('[data-flog-viewport]', this.modal);
    }

    private image(): HTMLImageElement | null {
        return queryElement<HTMLImageElement>('[data-modal-flog-img]', this.modal);
    }
}
