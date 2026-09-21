@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar requerimientos')

@section('navbar-right')
    <a href="{{ route('tejido.inventario.trama.nuevo.requerimiento') }}"
        title="Nuevo Requerimiento"
        class="flex items-center gap-2 px-4 py-2 rounded-md bg-blue-500 text-white hover:bg-blue-600 transition-colors">
        <i class="fas fa-plus"></i>
        <span>Nuevo</span>
    </a>
@endsection

@section('content')
    <livewire:inventario-trama.consultar-requerimiento />
@endsection
