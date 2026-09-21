@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Nuevo Requerimiento')

@section('content')
    <livewire:inventario-trama.nuevo-requerimiento :folio="request()->query('folio')" />
@endsection
