@extends('layouts.app')

@section('title', 'Tel · Telares por Operador · Crear')
@section('page-title')
Nuevo Operador
@endsection

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
    @if($errors->any())
        <div class="rounded bg-red-100 text-red-800 px-3 py-2 mb-3">
            <ul class="mb-0 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tel-telares-operador.store') }}" class="bg-white rounded shadow p-4 max-w-xl">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Número de Empleado</label>
                <input type="text" name="numero_empleado" value="{{ old('numero_empleado') }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre</label>
                <input type="text" name="nombreEmpl" value="{{ old('nombreEmpl') }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. Telar</label>
                <input type="text" name="NoTelarId" value="{{ old('NoTelarId') }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <a href="{{ route('tel-telares-operador.index') }}" class="px-4 py-2 rounded bg-gray-200">Cancelar</a>
            <button class="px-4 py-2 rounded bg-green-600 text-white">Guardar</button>
        </div>
    </form>
</div>
@endsection

