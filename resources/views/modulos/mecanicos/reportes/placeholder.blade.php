@extends('layouts.app')

@section('page-title', $titulo)

@section('content')
    <div class="w-full p-4">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-blue-600 px-6 py-4">
                <h1 class="text-xl font-bold text-white">{{ $titulo }}</h1>
            </div>
            <div class="px-6 py-10 text-center">
                <i class="fa-solid fa-chart-column text-gray-300 text-3xl mb-3"></i>
                <p class="text-gray-600 font-medium">Este reporte se implementará a continuación</p>
            </div>
        </div>
    </div>
@endsection
