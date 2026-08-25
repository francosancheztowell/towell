@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('content')
    <livewire:desarrolladores.captura modo="programa" />
@endsection
