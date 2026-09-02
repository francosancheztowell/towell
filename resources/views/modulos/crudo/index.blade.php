@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="ANDON" />
@endsection

@section('navbar-right')
    <div id="crudo-navbar-controls" class="flex min-w-0 items-center"></div>
@endsection

@push('styles')
    @vite('resources/css/crudo/dashboard.css')
@endpush

@section('content')
    <div
        class="crudo-page min-h-full w-full px-2 py-1 sm:px-4 lg:px-5"
        data-crudo-root
        @if (app()->environment('local')) data-crudo-retry-busy @endif
    >
        <aside
            class="crudo-livewire-error"
            data-crudo-livewire-error
            role="alert"
            aria-live="assertive"
            hidden
        >
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span data-crudo-livewire-error-message>
                No fue posible actualizar el tablero.
            </span>
            <button type="button" data-crudo-livewire-error-reload>
                Recargar
            </button>
        </aside>

        <livewire:crudo.dashboard />
        <livewire:crudo.machine-detail />
    </div>

    <div
        class="crudo-detail-pending"
        data-crudo-detail-pending
        role="status"
        aria-live="polite"
        hidden
    >
        <article>
            <span class="crudo-detail-pending-icon" aria-hidden="true">
                <i class="fa-solid fa-industry"></i>
            </span>
            <div>
                <strong data-crudo-detail-pending-name>Abriendo detalle</strong>
                <span>Consultando la información del telar…</span>
            </div>
        </article>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/crudo/dashboard.ts')
@endpush
