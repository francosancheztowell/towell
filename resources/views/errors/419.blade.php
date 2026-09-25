@extends('errors.layout')

{{-- UX-11: mismo mensaje que el aviso de window.http y de Livewire (utils/sesion.ts). Los
     formularios normales no llegan aquí: bootstrap/app.php los manda al login con flash. --}}
@section('color', 'amber')
@section('codigo', '419')
@section('titulo', 'Tu sesión expiró')
@section('mensaje', 'Por seguridad, vuelve a iniciar sesión para continuar.')
@section('acciones')
    <a href="{{ route('login') }}"
       class="inline-flex items-center justify-center min-h-touch bg-amber-600 hover:bg-amber-700 text-white px-8 py-3 rounded-md font-medium text-sm transition-colors duration-200">
        Iniciar sesión
    </a>
@endsection
