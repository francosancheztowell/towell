@extends('layouts.app')

@section('page-title', 'Checklist BPM Urdido - ' . $header->Folio)

@section('navbar-right')
    <div class="flex items-center gap-2">
        {{-- <a href="{{ route('urd-bpm.index') }}" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            ← Volver
        </a> --}}
        
        @if($header->Status === 'Creado')
            <form action="{{ route('urd-bpm-line.terminar', $header->Folio) }}" method="POST" class="inline" id="form-terminar">
                @csrf
                @method('PATCH')
                <button type="button" onclick="validarYTerminar()" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Terminado
                </button>
            </form>
        @elseif($header->Status === 'Terminado')
            <form action="{{ route('urd-bpm-line.autorizar', $header->Folio) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Autorizar
                </button>
            </form>
            <form action="{{ route('urd-bpm-line.rechazar', $header->Folio) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    Rechazar
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 1000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '{{ session('error') }}',
                    confirmButtonColor: '#3b82f6'
                });
            });
        </script>
    @endif

    <!-- Información del Header -->
    <div class="bg-white rounded-lg shadow-sm border p-2 mb-2 mt-2 mx-4">
        <div class="grid grid-cols-3 md:grid-cols-6 gap-2 text-xs">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Folio</p>
                <p class="text-lg font-bold text-blue-600">{{ $header->Folio }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Fecha</p>
                <p class="text-xs font-semibold">{{ $header->Fecha ? $header->Fecha->format('d/m/Y H:i') : '' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Entrega</p>
                <p class="text-xs font-semibold">{{ $header->NombreEmplEnt }}</p>
                <p class="text-xs text-gray-600">T{{ $header->TurnoEntrega }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Recibe</p>
                <p class="text-xs font-semibold">{{ $header->NombreEmplRec }}</p>
                <p class="text-xs text-gray-600">T{{ $header->TurnoRecibe }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Autoriza</p>
                <p class="text-xs font-semibold">{{ $header->NombreEmplAutoriza }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase mb-0.5">Status</p>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
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
    <div class="bg-white rounded-lg shadow-sm border p-2 mx-60 mb-32">
        <h2 class="text-base font-bold text-gray-800 mb-2 border-b pb-1.5 px-2">Actividades</h2>
        
        <div class="overflow-y-auto" style="max-height: calc(100vh - 280px);">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-gray-100 border-b">
                    <tr>
                        <th class="text-left px-2 py-2 font-semibold text-gray-700 w-12">Orden</th>
                        <th class="text-left px-2 py-2 font-semibold text-gray-700">Actividad</th>
                        <th class="text-center px-2 py-2 font-semibold text-gray-700 w-32">{{ $nombreMaquina }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actividades as $actividad)
                        @php
                            $valor = (int)$lineas->get($actividad->Actividad, 0);
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-2 py-1.5 text-center text-gray-600 font-medium">{{ $actividad->Orden }}</td>
                            <td class="px-2 py-1.5 text-base font-medium">{{ $actividad->Actividad }}</td>
                            <td class="px-2 py-1.5 text-center">
                                <button type="button"
                                    class="cell-btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-300
                                        {{ $valor == 1 ? 'bg-green-100 border-green-400 text-green-700 hover:bg-green-200' : 
                                           ($valor == 2 ? 'bg-red-100 border-red-400 text-red-700 hover:bg-red-200' : 
                                           'bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100 hover:border-gray-400') }}"
                                    data-actividad="{{ $actividad->Actividad }}"
                                    data-valor="{{ $valor }}"
                                    {{ $header->Status !== 'Creado' ? 'disabled' : '' }}
                                    onclick="toggleActividad(this)">
                                   <span class="cell-icon text-lg font-bold">
                                        {!! $valor == 1 ? '✓' : ($valor == 2 ? '✗' : '○') !!}
                                   </span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // Función global para volver al índice sin apilar páginas y refrescar
    window.volverAlIndice = function() {
        window.location.replace("{{ route('urd-bpm.index') }}");
    };

    // Interceptar el botón "atrás" del navegador
    window.addEventListener('popstate', function(event) {
        window.volverAlIndice();
    });

    // Interceptar navegación desde caché (back/forward) para refrescar
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // Validar que todas las actividades estén marcadas antes de terminar
    function validarYTerminar() {
        const totalActividades = {{ $actividades->count() }};
        const actividadesMarcadas = document.querySelectorAll('.cell-btn[data-valor="1"], .cell-btn[data-valor="2"]').length;
        
        if (actividadesMarcadas < totalActividades) {
            const faltantes = totalActividades - actividadesMarcadas;
            Swal.fire({
                icon: 'warning',
                title: 'Actividades pendientes',
                text: `Faltan ${faltantes} actividad(es) por marcar. Todas las actividades deben estar marcadas (✓ o ✗) antes de terminar.`,
                confirmButtonColor: '#3b82f6'
            });
            return;
        }
        
        // Si todas están marcadas, enviar el formulario
        document.getElementById('form-terminar').submit();
    }

    // Toggle individual activity: 0 (vacío) → 1 (palomita) → 2 (tache) → 0
    function toggleActividad(btn) {
        const actividad = btn.dataset.actividad;
        const valorActual = parseInt(btn.dataset.valor) || 0;
        
        // Ciclo: 0 → 1 → 2 → 0
        const valorNuevo = (valorActual + 1) % 3;
        
        console.log('toggleActividad llamado:', { actividad, valorActual, valorNuevo });
        
        fetch("{{ route('urd-bpm-line.toggle', $header->Folio) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                actividad: actividad,
                valor: valorNuevo
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Actualizar UI
                btn.dataset.valor = valorNuevo;
                btn.classList.remove('bg-green-100','border-green-400','text-green-700','bg-red-100','border-red-400','text-red-700','bg-gray-50','border-gray-300','text-gray-400');
                
                if (valorNuevo === 1) {
                    btn.classList.add('bg-green-100','border-green-400','text-green-700');
                    btn.querySelector('.cell-icon').innerHTML = '✓';
                } else if (valorNuevo === 2) {
                    btn.classList.add('bg-red-100','border-red-400','text-red-700');
                    btn.querySelector('.cell-icon').innerHTML = '✗';
                } else {
                    btn.classList.add('bg-gray-50','border-gray-300','text-gray-400');
                    btn.querySelector('.cell-icon').innerHTML = '○';
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al actualizar la actividad',
                    confirmButtonColor: '#3b82f6'
                });
            }
        })
        .catch(error => {
            console.error('Error completo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al comunicarse con el servidor: ' + error.message,
                confirmButtonColor: '#3b82f6'
            });
        });
    }
    </script>
@endsection
