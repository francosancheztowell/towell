@extends('layouts.app')

@section('page-title', 'Editar Usuario')

@section('content')
    {{-- Cancelar / Guardar viven en el componente (así Guardar tiene su spinner de wire:submit). --}}
    <div class="pantalla-completa bg-white px-4 py-4 md:px-6">
        <livewire:configuracion.editar-usuario :usuario-id="$usuarioId" />
    </div>
@endsection
