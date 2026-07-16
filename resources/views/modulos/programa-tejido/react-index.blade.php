@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programa de Tejido')

@section('content')
    <div class="min-h-full p-3 sm:p-5">
        <div
            id="planeacion-programa-tejido-root"
            class="min-h-[calc(100vh-6rem)] rounded-xl bg-background text-foreground"
            data-index-url="{{ route('planeacion.api.v1.programa-tejido.index') }}"
            data-legacy-url="{{ route('catalogos.req-programa-tejido') }}"
        >
            <div class="flex min-h-64 items-center justify-center text-sm text-muted-foreground" role="status">
                Cargando programa de tejido...
            </div>
        </div>

        <noscript>
            <div class="rounded-lg border border-border bg-card p-4 text-card-foreground">
                Esta vista requiere JavaScript.
                <a class="font-medium text-primary underline" href="{{ route('catalogos.req-programa-tejido') }}">
                    Abrir vista anterior
                </a>
            </div>
        </noscript>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/planeacion/programa-tejido/main.tsx')
@endpush
