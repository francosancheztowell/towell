@extends('layouts.app')

@section('page-title', 'Estado de máquina')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <x-navbar.button-report
            id="btn-filtrar-verifica-maquina"
            title="Filtrar por estatus"
            text="Filtrar"
            icon="fa-filter"
            bg="bg-green-600"
            iconColor="text-white"
            :checkPermission="false"
            onclick="window.dispatchEvent(new CustomEvent('verifica-maquina-abrir-filtros'))"
        />
        <x-navbar.button-create
            module="Estado Maquina"
            id="btn-nueva-verificacion"
            title="Nueva verificación"
            text="Nuevo"
            onclick="window.dispatchEvent(new CustomEvent('verifica-maquina-abrir-modal'))"
        />
    </div>
@endsection

@section('content')
<div class="flex h-[calc(100vh-64px)] w-full flex-col overflow-hidden p-3 sm:p-4 md:p-5">
    <div class="mx-auto flex min-h-0 w-full max-w-[96rem] flex-1 flex-col">
        <livewire:mecanicos.verifica-maquina.index />
    </div>
</div>
@endsection
