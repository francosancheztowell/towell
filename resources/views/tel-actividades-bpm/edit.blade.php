@extends('layouts.app')

@section('title', 'Tel · Actividades BPM · Editar')
@section('page-title')
Editar Actividad BPM
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

    <form method="POST" action="{{ route('tel-actividades-bpm.update', $item) }}" class="bg-white rounded shadow p-4 max-w-xl">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="block text-sm font-medium mb-1">Actividad</label>
            <input type="text" name="Actividad" value="{{ old('Actividad', $item->Actividad) }}" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">Orden: {{ $item->Orden }}</div>
            <div class="flex gap-2">
                <a href="{{ route('tel-actividades-bpm.index') }}" class="px-4 py-2 rounded bg-gray-200">Cancelar</a>
                <button class="px-4 py-2 rounded bg-blue-600 text-white">Actualizar</button>
            </div>
        </div>
    </form>
</div>
@endsection

