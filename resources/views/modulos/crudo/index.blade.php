@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Crudo" />
@endsection

@section('navbar-right')
    <div id="crudo-navbar-controls" class="flex min-w-0 items-center"></div>
@endsection

@push('styles')
    @vite('resources/css/crudo/dashboard.css')
@endpush

@section('content')
    <div class="crudo-page min-h-full w-full px-2 py-3 sm:px-4 lg:px-5">
        <livewire:crudo.dashboard />
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
