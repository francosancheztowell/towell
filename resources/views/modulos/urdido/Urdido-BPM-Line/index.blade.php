@extends('layouts.app')

@section('page-title', 'Checklist BPM Urdido - ' . $header->Folio)

@section('navbar-right')
    <div class="flex items-center gap-2">
        <a href="{{ route('urd-bpm.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            ← Volver
        </a>
        
        @if($header->Status === 'Creado')
            <form action="{{ route('urd-bpm-line.terminar', $header->Folio) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Terminado
                </button>
            </form>
        @elseif($header->Status === 'Terminado')
            <form action="{{ route('urd-bpm-line.autorizar', $header->Folio) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Autorizar
                </button>
            </form>
            <form action="{{ route('urd-bpm-line.rechazar', $header->Folio) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    Rechazar
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-3 rounded-lg bg-green-600/10 border border-green-600/30 text-green-800 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-3 rounded-lg bg-red-600/10 border border-red-600/30 text-red-800 px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    <!-- Información del Header -->
    <div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Folio</p>
                <p class="text-2xl font-bold text-blue-600">{{ $header->Folio }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Fecha y Hora</p>
                <p class="text-base font-semibold">{{ $header->Fecha ? $header->Fecha->format('d/m/Y H:i') : '' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Quien Entrega</p>
                <p class="text-base font-semibold">{{ $header->NombreEmplEnt }}</p>
                <p class="text-xs text-gray-600">{{ $header->CveEmplEnt }} - Turno {{ $header->TurnoEntrega }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Quien Recibe</p>
                <p class="text-base font-semibold">{{ $header->NombreEmplRec }}</p>
                <p class="text-xs text-gray-600">{{ $header->CveEmplRec }} - Turno {{ $header->TurnoRecibe }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Autoriza</p>
                <p class="text-base font-semibold">{{ $header->NombreEmplAutoriza }}</p>
                <p class="text-xs text-gray-600">{{ $header->CveEmplAutoriza }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Status</p>
                <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold
                    @if($header->Status === 'Creado') bg-yellow-100 text-yellow-800
                    @elseif($header->Status === 'Terminado') bg-blue-100 text-blue-800
                    @elseif($header->Status === 'Autorizado') bg-green-100 text-green-800
                    @endif">
                    {{ $header->Status }}
                </span>
            </div>
        </div>
    </div>

    <!-- Checklist de Actividades -->
    <div class="bg-white rounded-lg shadow-sm border p-4">
        <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Actividades</h2>
        
        <div class="overflow-y-auto" style="max-height: calc(100vh - 400px);">
            <table class="min-w-full">
                <thead class="sticky top-0 bg-gray-100 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700 w-16">Orden</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Actividad</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-700 w-24">
                            <input type="checkbox" 
                                   id="checkAll" 
                                   onchange="toggleAll(this)"
                                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   {{ $header->Status !== 'Creado' ? 'disabled' : '' }}>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actividades as $actividad)
                        @php
                            $isChecked = $lineas->has($actividad->Actividad);
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 text-center text-gray-600">{{ $actividad->Orden }}</td>
                            <td class="px-4 py-3">{{ $actividad->Actividad }}</td>
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox" 
                                       name="actividad[]" 
                                       value="{{ $actividad->Actividad }}"
                                       {{ $isChecked ? 'checked' : '' }}
                                       {{ $header->Status !== 'Creado' ? 'disabled' : '' }}
                                       onchange="toggleActividad('{{ $actividad->Actividad }}', this.checked)"
                                       class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Toggle individual activity
    function toggleActividad(actividad, checked) {
        fetch("{{ route('urd-bpm-line.toggle', $header->Folio) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                actividad: actividad,
                checked: checked
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error al actualizar la actividad');
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al comunicarse con el servidor');
            location.reload();
        });
    }

    // Toggle all checkboxes
    function toggleAll(checkbox) {
        const checkboxes = document.querySelectorAll('input[name="actividad[]"]:not(:disabled)');
        checkboxes.forEach(cb => {
            if (cb.checked !== checkbox.checked) {
                cb.checked = checkbox.checked;
                toggleActividad(cb.value, cb.checked);
            }
        });
    }

    // Update "check all" state when individual checkboxes change
    document.addEventListener('DOMContentLoaded', function() {
        updateCheckAllState();
    });

    function updateCheckAllState() {
        const checkAll = document.getElementById('checkAll');
        const checkboxes = document.querySelectorAll('input[name="actividad[]"]:not(:disabled)');
        const checkedCount = document.querySelectorAll('input[name="actividad[]"]:checked:not(:disabled)').length;
        
        if (checkboxes.length > 0) {
            checkAll.checked = checkedCount === checkboxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
    }
</script>
@endsection
