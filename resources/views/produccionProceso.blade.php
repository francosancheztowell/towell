@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title
        title="Producción en Proceso"
    />
@endsection

@section('content')
    <x-layout.module-grid :modulos="$modulos" columns="xl:grid-cols-4" :filterConfig="true" />
@endsection
