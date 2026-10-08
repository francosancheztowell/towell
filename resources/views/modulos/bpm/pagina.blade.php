{{--
    Vista host de los BPM (Urdido, Engomado, Tejedores). Cada ruta pasa el componente
    Livewire (bpm.folios, bpm.checklist, bpm.actividades), el área y el título; el checklist además el folio.
--}}
@extends('layouts.app')

@section('page-title', $titulo.(isset($folio) ? ' · '.$folio : ''))

@section('navbar-right')
    {{-- Los componentes teletransportan aquí sus botones (ver x-tabla y bpm/checklist). --}}
    <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@section('content')
    <div class="pantalla-completa bg-white p-2 md:px-4">
        @livewire($componente, array_filter(['area' => \App\Support\Bpm\AreaBpm::from($area), 'folio' => $folio ?? null]))
    </div>
@endsection
