@extends('layouts.app')

@section('page-title', 'Pesos por Rollos')

@section('content')
    <div class="min-h-full p-3 sm:p-5">
        <div
            id="planeacion-pesos-rollos-root"
            class="min-h-[calc(100vh-6rem)] rounded-xl bg-background text-foreground"
            data-index-url="{{ route('planeacion.api.v1.pesos-rollos.index') }}"
            data-legacy-url="{{ route('planeacion.catalogos.pesos-rollos', ['legacy' => 1]) }}"
        >
            <div class="flex min-h-64 items-center justify-center text-sm text-muted-foreground" role="status">
                Cargando catalogo...
            </div>
        </div>

        <noscript>
            <div class="rounded-lg border border-border bg-card p-4 text-card-foreground">
                Esta pantalla requiere JavaScript.
                <a class="font-medium text-primary underline" href="{{ route('planeacion.catalogos.pesos-rollos', ['legacy' => 1]) }}">
                    Abrir vista anterior
                </a>
            </div>
        </noscript>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/planeacion/pesos-rollos/main.tsx')
@endpush
