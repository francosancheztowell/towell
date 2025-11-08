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
                <select id="empleadoSelectEdit" name="numero_empleado" class="w-full border rounded px-3 py-2" required>
                    @foreach(($usuarios ?? []) as $u)
                        @php($selected = old('numero_empleado', $item->numero_empleado) == $u->numero_empleado)
                        <option value="{{ $u->numero_empleado }}" data-nombre="{{ $u->nombre }}" data-turno="{{ $u->turno }}" {{ $selected ? 'selected' : '' }}>
                            {{ $u->numero_empleado }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nombre</label>
                <input type="text" id="nombreEdit" name="nombreEmpl" value="{{ old('nombreEmpl', $item->nombreEmpl) }}" class="w-full border rounded px-3 py-2 bg-gray-50" readonly>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">No. Telar</label>
                <select id="editTelarPage" name="NoTelarId" class="w-full border rounded px-3 py-2" required>
                    @foreach(($telares ?? []) as $tel)
                        @php($selected = old('NoTelarId', $item->NoTelarId) == $tel->NoTelarId)
                        <option value="{{ $tel->NoTelarId }}" data-salon="{{ $tel->SalonTejidoId }}" {{ $selected ? 'selected' : '' }}>
                            {{ $tel->NoTelarId }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Turno</label>
                <input type="text" id="turnoEdit" name="Turno" value="{{ old('Turno', $item->Turno) }}" class="w-full border rounded px-3 py-2 bg-gray-50" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Salón Tejido Id</label>
                <input type="text" id="editSalonPage" name="SalonTejidoId" value="{{ old('SalonTejidoId', $item->SalonTejidoId) }}" class="w-full border rounded px-3 py-2" required>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <a href="{{ route('tel-telares-operador.index') }}" class="px-4 py-2 rounded bg-gray-200">Cancelar</a>
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Actualizar</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function(){
        const telarSel = document.getElementById('editTelarPage');
        const salonInp = document.getElementById('editSalonPage');
        if (telarSel && salonInp) {
            telarSel.addEventListener('change', () => {
                const opt = telarSel.options[telarSel.selectedIndex];
                salonInp.value = opt ? (opt.getAttribute('data-salon') || '') : '';
            });
            // Inicializa según selección actual
            const opt = telarSel.options[telarSel.selectedIndex];
            if (opt && !salonInp.value) salonInp.value = opt.getAttribute('data-salon') || '';
        }

        // Vincular empleado -> nombre y turno
        const empSel = document.getElementById('empleadoSelectEdit');
        const nombre = document.getElementById('nombreEdit');
        const turno = document.getElementById('turnoEdit');
        if (empSel && nombre && turno) {
            const sync = () => {
                const op = empSel.options[empSel.selectedIndex];
                nombre.value = op ? (op.getAttribute('data-nombre') || '') : '';
                turno.value = op ? (op.getAttribute('data-turno') || '') : '';
            };
            empSel.addEventListener('change', sync);
            sync();
        }
    })();
</script>
@endpush
