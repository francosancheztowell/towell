@extends('layouts.app')

@section('page-title', 'Edición Engomado')

@section('navbar-right')
    <div id="tabla-navbar-acciones" data-edicion-ordenes-actions class="flex min-w-0 items-center gap-2"></div>
@endsection

@push('styles')
    @vite('resources/css/urd-eng/edicion-ordenes.css')
@endpush

@section('content')
    <livewire:engomado.edicion-ordenes />
@endsection

@push('scripts')
    @include('modulos.engomado.partials.modal-calificar-julios-eng')
    @vite('resources/js/urd-eng/edicion-ordenes.ts')
@endpush
