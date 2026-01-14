@extends('layouts.app')

@section('page-title', 'Base de Datos')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">
            <i class="fas fa-database mr-2 text-blue-600"></i>
            Base de Datos
        </h1>
        
        <div class="mt-6">
            <p class="text-gray-600 mb-4">
                Aquí puedes gestionar la configuración de la base de datos.
            </p>
            
            <!-- Contenido de la vista -->
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">
                    Contenido de la vista de Base de Datos. Personaliza este contenido según tus necesidades.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
