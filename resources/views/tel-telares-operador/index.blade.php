@extends('layouts.app')

@section('title', 'Tel · Telares por Operador')
@section('page-title')
Telares por Operador
@endsection

@section('content')
<div class="container mx-auto px-3 md:px-6 py-4">
    @if(session('success'))
        <div class="rounded-md bg-green-100 text-green-800 px-3 py-2 mb-3">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-3">
        <a href="{{ route('tel-telares-operador.create') }}" class="px-3 py-2 rounded bg-green-600 text-white">Nuevo Operador</a>
        <a href="{{ route('tel-telares-operador.create') }}" class="px-3 py-2 rounded bg-yellow-600 text-white">Editar</a>
        <a href="{{ route('tel-telares-operador.create') }}" class="px-3 py-2 rounded bg-red-600 text-white">Eliminar</a>
    </div>

    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-3 py-2 text-left">Número</th>
                    <th class="px-3 py-2 text-left">Nombre</th>
                    <th class="px-3 py-2 text-left">No. Telar</th>
                    <th class="px-3 py-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="odd:bg-white even:bg-gray-50">
                        <td class="px-3 py-2">{{ $it->numero_empleado }}</td>
                        <td class="px-3 py-2">{{ $it->nombreEmpl }}</td>
                        <td class="px-3 py-2">{{ $it->NoTelarId }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('tel-telares-operador.edit', $it) }}" class="px-2 py-1 rounded bg-amber-500 text-white">Editar</a>
                            <form action="{{ route('tel-telares-operador.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar operador?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2 py-1 rounded bg-red-600 text-white">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-3 text-center text-gray-500">Sin registros</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection

