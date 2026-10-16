@extends('layouts.app')

@section('page-title', 'Editar Usuario')

@section('navbar-right')
    {{-- form="usuario-form": el botón vive en el navbar y envía el form del componente. --}}
    <a href="{{ route('configuracion.usuarios.select') }}"
       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" form="usuario-form"
            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        <i class="fa-solid fa-save" aria-hidden="true"></i> Guardar
    </button>
@endsection

@section('content')
    <div class="pantalla-completa p-2 md:px-4">
        <livewire:configuracion.editar-usuario :usuario-id="$usuarioId" />
    </div>
@endsection
