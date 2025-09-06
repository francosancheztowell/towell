<!-- resources/views/ingresar_folio.blade.php -->
@extends('layouts.app')

@section('content')
    @if ($errors->any())
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '{{ $errors->first() }}',
                    timer: 3000,
                    showConfirmButton: false,
                    timerProgressBar: true,
                });
            });
        </script>
    @endif

    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.18);
            animation: fadeInUp 0.6s ease-out both;
            position: relative;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
        }

        .loader div {
            width: 32px;
            height: 32px;
            border: 4px solid #fff;
            border-top: 4px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .glass-card.loading form {
            opacity: 0.4;
            pointer-events: none;
        }

        .glass-card.loading .loader {
            display: block;
        }
    </style>
    <div class="flex min-h-screen h-screen from-blue-100 to-blue-300">
        <!-- Panel izquierdo: Captura de OT -->
        <div class="flex-1 flex items-center justify-center">
            <div class="glass-card w-full max-w-md p-8" id="card">
                <h1 class="text-2xl font-bold text-center mb-6 tracking-wide text-gray-800">ORDEN DE TRABAJO</h1>
                <form id="folioForm" action="{{ route('produccion.ordenTrabajo') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="folio" class="block mb-1 text-sm font-medium text-center text-gray-700">
                            Por favor ingrese su orden de trabajo
                        </label>
                        <input type="text" name="folio" id="folio"
                            class="w-full px-4 py-2 rounded-md text-black border border-gray-300 focus:ring-2 focus:ring-blue-500 text-sm"
                            required>
                    </div>
                    <button type="submit"
                        class="w-full px-4 py-2 bg-blue-600 rounded-md text-white text-sm font-semibold shadow hover:shadow-lg transition-all duration-300">
                        Cargar Información
                    </button>
                </form>
            </div>
        </div>
        <!-- Panel derecho: Lista de órdenes -->
        <div class="w-full max-w-[600px] flex flex-col p-2 h-[420px]">
            <div class="flex flex-col bg-white bg-opacity-90 rounded-2xl shadow-lg p-2 h-full">
                <h2 class="text-xl font-bold text-gray-800 text-center">ÓRDENES PENDIENTES URDIDO</h2>
                <div class="flex-1 flex flex-col bg-white rounded-2xl shadow-lg p-1 min-h-0">
                    <div class="overflow-x-auto flex-1 flex flex-col min-h-0">
                        <div class="min-w-full flex-1 flex flex-col min-h-0">
                            <!-- Encabezados tipo tabla -->
                            <div class="grid grid-cols-8 bg-gray-100 rounded-t-2xl">
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Prioridad</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Folio</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Tipo</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Metros</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm col-span-2">L. Mat. Urdido</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Cuenta</div>
                                <div class="py-0.5 px-1 text-gray-700 font-bold text-sm">Calibre</div>
                            </div>
                            <!-- ahora pintamos 3 listas para los 3 mc coy -->
                            @php
                                use Illuminate\Support\Str;
                                $secciones = ['Mc Coy 1', 'Mc Coy 2', 'Mc Coy 3'];
                                $fmtMetros = function ($v) {
                                    return fmod($v, 1) == 0
                                        ? intval($v)
                                        : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
                                };
                            @endphp

                            <div class="space-y-1">
                                @foreach ($secciones as $mc)
                                    <div class="rounded-xl border border-blue-200 bg-white p-1">
                                        <h3 class="text-blue-800 font-bold">{{ $mc }}</h3>

                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs">

                                                <tbody class="divide-y tabla-urdido" data-grupo="{{ $mc }}">
                                                    @forelse (($porUrdido[$mc] ?? collect()) as $ordP)
                                                        <tr class="order-item hover:bg-yellow-100"
                                                            data-id="{{ $ordP->folio }}" data-orden="{{ $ordP->folio }}">
                                                            {{-- data-orden="{{ $ordP->folio }}" -> ESTO FUNCIONA PARA SELECCIONAR UN REGISTRO Y COLOCAR EL FOLIO EN EL PRIMER CONTENEDOR --}}
                                                            {{-- Handle + Prioridad + flechas --}}
                                                            <td
                                                                class="py-0.5
                                                            px-1">
                                                                <div class="flex items-center gap-2">
                                                                    <span
                                                                        class="font-semibold">{{ $ordP->prioridadUrd ?? '-' }}</span>
                                                                    <div class="ml-1 flex flex-col">
                                                                        <button type="button"
                                                                            class="btn-up text-[10px] leading-3"
                                                                            data-id="{{ $ordP->id }}"
                                                                            data-grupo="{{ $mc }}">▲</button>
                                                                        <button type="button"
                                                                            class="btn-down text-[10px] leading-3"
                                                                            data-id="{{ $ordP->id }}"
                                                                            data-grupo="{{ $mc }}">▼</button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td class="py-0.5 px-1">{{ $ordP->folio ?? '' }}</td>
                                                            <td class="py-0.5 px-1">{{ $ordP->tipo ?? '' }}</td>
                                                            <td class="py-0.5 px-1">{{ $fmtMetros($ordP->metros) ?? '' }}
                                                            </td>
                                                            <td class="py-0.5 px-1">{{ $ordP->lmaturdido ?? '' }}</td>
                                                            <td class="py-0.5 px-1">
                                                                {{ decimales($ordP->cuenta) ?? '' }}</td>
                                                            <td class="py-0.5 px-1">{{ $ordP->calibre ?? '' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8"
                                                                class="py-2 text-center text-gray-400 italic">Sin registros.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="selectedOrder" name="selectedOrder" value="">
            </div>
        </div>


        <script>
            // Usa el contenedor si existe; si no, delega desde document.
            const container = document.getElementById('orderList') || document;

            container.addEventListener('click', (e) => {
                // Captura clicks en cualquier elemento con .order-item (o hijos)
                const item = e.target.closest('.order-item');
                if (!item) return;
                // Si tenías un contenedor específico, asegura que el item esté dentro
                if (container !== document && !container.contains(item)) return;

                const folio = item.dataset.orden?.trim();
                if (!folio) return;

                const input = document.getElementById('folio');
                if (!input) return;

                if ('value' in input) input.value = folio; // inputs
                else input.textContent = folio; // spans/divs

                input.focus();
            });
        </script>
        <script>
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // 2) Flechas ▲ / ▼
            async function mover(id, dir, grupo) {
                try {
                    const res = await fetch("{{ route('urdido.prioridad.mover') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            id,
                            dir,
                            grupo
                        })
                    });
                    const data = await res.json();

                    if (data.status === 'info') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Aviso',
                            text: data.message || 'No se puede mover más en esa dirección.',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#2563eb'
                        });
                        return; // no recargues
                    }

                    if (!data.ok) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo mover',
                            text: data.message || 'Intenta de nuevo',
                            confirmButtonColor: '#2563eb'
                        });
                        return;
                    }

                    // éxito real
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Prioridad actualizada.',
                        confirmButtonText: 'Ok',
                        confirmButtonColor: '#2563eb'
                    });
                    location.reload();

                } catch (e) {
                    alert('Error al mover');
                }
            }

            document.querySelectorAll('.btn-up').forEach(b => {
                b.addEventListener('click', () => mover(Number(b.dataset.id), -1, b.dataset.grupo));
            });
            document.querySelectorAll('.btn-down').forEach(b => {
                b.addEventListener('click', () => mover(Number(b.dataset.id), +1, b.dataset.grupo));
            });
        </script>
    @endsection
