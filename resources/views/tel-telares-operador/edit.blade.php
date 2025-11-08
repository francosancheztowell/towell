@extends('layouts.app')

@section('title', 'Tel · Telares por Operador · Editar')
@section('page-title')
Editar Operador
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

    <form method="POST" action="{{ route('tel-telares-operador.update', $item) }}" class="bg-white rounded shadow p-4 max-w-xl">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Número de Empleado</label>
                <input type="text" name="numero_empleado" value="{{ old('numero_empleado', $item->numero_empleado) }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre</label>
                <input type="text" name="nombreEmpl" value="{{ old('nombreEmpl', $item->nombreEmpl) }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. Telar</label>
                <input type="text" name="NoTelarId" value="{{ old('NoTelarId', $item->NoTelarId) }}" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno</label>
                <select name="Turno" class="w-full border rounded px-3 py-2" required>
                    @php($turnoVal = old('Turno', (string)($item->Turno)))
                    <option value="1" {{ $turnoVal == '1' ? 'selected' : '' }}>1</option>
                    <option value="2" {{ $turnoVal == '2' ? 'selected' : '' }}>2</option>
                    <option value="3" {{ $turnoVal == '3' ? 'selected' : '' }}>3</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Salón Tejido Id</label>
                <input type="text" name="SalonTejidoId" value="{{ old('SalonTejidoId', $item->SalonTejidoId) }}" class="w-full border rounded px-3 py-2" required>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <a href="{{ route('tel-telares-operador.index') }}" class="px-4 py-2 rounded bg-gray-200">Cancelar</a>
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Actualizar</button>
        </div>
    </form>
</div>
@endsection
