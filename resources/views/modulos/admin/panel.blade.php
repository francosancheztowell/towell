{{--
    Vista host del panel /admin (fase 13). Cada ruta de routes/modules/admin.php
    pasa el componente Livewire a montar y el título; /admin/errores/{id} además el id.
--}}
@extends('layouts.app')

@section('title', 'Administración · '.$titulo)

@section('page-title')
    Administración · {{ $titulo }}
@endsection

@section('navbar-right')
    {{-- Los componentes teletransportan aquí sus botones (ver x-tabla). --}}
    <div id="tabla-navbar-acciones" class="flex items-center gap-2"></div>
@endsection

@section('content')
    <div class="w-full space-y-3 px-2 py-3 md:px-4">
        @include('modulos.admin._nav')

        @livewire($componente, isset($id) ? ['errorId' => (int) $id] : [])
    </div>
@endsection
