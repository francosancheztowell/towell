@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title')
    <x-layout.page-title title="Plan de Ventas vs OC vs Real" />
@endsection

@section('content')
    <livewire:ventas.dashboard-pv-vs-oc />
@endsection
