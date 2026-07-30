@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Programación Urdido" />
@endsection

@section('navbar-right')
    <div id="program-board-navbar-controls" class="flex min-w-0 items-center"></div>
@endsection

@push('styles')
    @vite('resources/css/urd-eng/program-board.css')
@endpush

@section('content')
    <div class="program-board-page min-h-full w-full px-2 py-3 sm:px-4">
        <livewire:urd-eng.program-board module="urdido" />
    </div>
@endsection

@push('scripts')
    @vite('resources/js/urd-eng/program-board.ts')
@endpush
