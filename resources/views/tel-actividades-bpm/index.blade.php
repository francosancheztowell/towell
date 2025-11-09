@extends('layouts.app')

@section('title', 'Tel · Actividades BPM')
@section('page-title')
Actividades BPM
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
    @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: @json(session('success')),
                confirmButtonText: 'Aceptar'
            });
        </script>
    @endif

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
        <form method="GET" action="{{ route('tel-actividades-bpm.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar actividad..." class="border rounded px-3 py-2 w-64">
            <button class="px-3 py-2 rounded bg-blue-600 text-white">Buscar</button>
            @if(($q ?? '') !== '')
                <a href="{{ route('tel-actividades-bpm.index') }}" class="px-3 py-2 rounded bg-gray-200">Limpiar</a>
            @endif
        </form>
        <a href="{{ route('tel-actividades-bpm.create') }}" class="px-3 py-2 rounded bg-green-600 text-white">
            <i class="fa-solid fa-plus mr-1.5"></i> Nueva Actividad
        </a>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-3 py-2 text-left w-28">Orden</th>
                    <th class="px-3 py-2 text-left">Actividad</th>
                    <th class="px-3 py-2 text-left w-40">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-white even:bg-gray-50">
                        <td class="px-3 py-2 align-middle">{{ $it->Orden }}</td>
                        <td class="px-3 py-2 align-middle">{{ $it->Actividad }}</td>
                        <td class="px-3 py-2 align-middle">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('tel-actividades-bpm.edit', $it) }}" class="px-2 py-1 rounded bg-amber-600 text-white">Editar</a>
                                <form method="POST" action="{{ route('tel-actividades-bpm.destroy', $it) }}" onsubmit="return confirm('¿Eliminar actividad?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-2 py-1 rounded bg-red-600 text-white">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-3 text-center text-gray-500">Sin registros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

