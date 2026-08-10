{{--
    Paginador de <x-tabla>. Reemplaza al de Livewire, que en escritorio repite el
    conteo que ya va a la izquierda del pie y usa botones oscuros que no combinan
    con la tabla blanca.

    Recibe $paginator y $elements de $paginator->links('components.tabla-paginacion').
--}}
@if ($paginator->hasPages())
    @php
        $base = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-bold transition';
        $inactivo = $base.' border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700';
        $activo = $base.' border-blue-500 bg-blue-500 text-white';
        $deshabilitado = $base.' cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300';
    @endphp

    <nav class="flex items-center gap-1" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="{{ $deshabilitado }}" aria-disabled="true"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <button type="button" wire:click="previousPage" wire:loading.attr="disabled"
                    class="{{ $inactivo }}" rel="prev" aria-label="Página anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-xs font-bold text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $activo }}" aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" wire:key="pagina-{{ $page }}" wire:click="gotoPage({{ $page }})"
                                wire:loading.attr="disabled" class="{{ $inactivo }}">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" wire:loading.attr="disabled"
                    class="{{ $inactivo }}" rel="next" aria-label="Página siguiente">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        @else
            <span class="{{ $deshabilitado }}" aria-disabled="true"><i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
