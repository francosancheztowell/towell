@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores Muestras')

@section('content')
    <livewire:desarrolladores.captura modo="muestras" />
@endsection
