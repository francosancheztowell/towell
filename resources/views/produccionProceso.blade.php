@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title
        title="Producción en Proceso"
    />
@endsection

@section('content')
    <div id="produccion-proceso-root"></div>
    
    @push('scripts')
        @php
            $produccionProcesoData = [
                'modulos' => $modulos,
                'columns' => 'xl:grid-cols-4',
                'filterConfig' => true,
            ];
        @endphp
        <script>
            window.produccionProcesoData = @json($produccionProcesoData);
        </script>
        @vite(['resources/js/produccion-proceso.tsx'])
    @endpush
@endsection
