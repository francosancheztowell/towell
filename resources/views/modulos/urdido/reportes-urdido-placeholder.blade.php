@extends('layouts.app')

@section('page-title', $titulo ?? 'Reporte en desarrollo')

@section('content')
    <div class="w-full max-w-xl mx-auto p-6">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center">
            <i class="fas fa-tools text-amber-500 text-5xl mb-4"></i>
            <h2 class="text-xl font-bold text-gray-800 mb-2">{{ $titulo ?? 'Reporte' }}</h2>
            <p class="text-gray-600 mb-6">{{ $mensaje ?? 'Este reporte está en desarrollo.' }}</p>
            <a href="{{ route('urdido.reportes.urdido') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                <i class="fas fa-arrow-left"></i> Volver a Reportes
            </a>
        </div>
    </div>
@endsection
