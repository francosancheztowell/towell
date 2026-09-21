@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Programar Urdido')

@section('navbar-right')
    <div id="program-board-navbar-controls" class="flex min-w-0 items-center"></div>
@endsection

@push('styles')
    @vite('resources/css/urd-eng/program-board.css')
@endpush

@section('content')
    <div class="program-board-page min-h-full w-full">
        <livewire:urd-eng.program-board module="urdido" />
    </div>
@endsection

@push('scripts')
    @vite('resources/js/urd-eng/program-board.ts')
@endpush
