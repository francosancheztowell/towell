declare module 'sortablejs' {
    export interface SortableEvent {
        item: HTMLElement;
        from: HTMLElement;
        to: HTMLElement;
        oldIndex?: number;
        newIndex?: number;
    }

    export interface SortableOptions {
        animation?: number;
        chosenClass?: string;
        dataIdAttr?: string;
        direction?: 'horizontal' | 'vertical';
        dragClass?: string;
        fallbackOnBody?: boolean;
        ghostClass?: string;
        handle?: string;
        onEnd?: (event: SortableEvent) => void;
        onStart?: (event: SortableEvent) => void;
        swapThreshold?: number;
    }

    export default class Sortable {
        constructor(element: HTMLElement, options?: SortableOptions);
        destroy(): void;
        sort(order: string[], useAnimation?: boolean): void;
    }
}
