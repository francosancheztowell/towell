@extends('layouts.app')

@section('title', 'Catálogo de Fallas')

@section('page-title')
    Catálogo de Fallas
@endsection

@section('content')
    <div class="w-full px-2 py-4 md:px-4">
        <livewire:mantenimiento.catalogo-fallas />
    </div>
@endsection
