@extends('layouts.app')

@section('content')
    @php
        function iniciales2($nombre)
        {
            $p = preg_split('/\s+/', trim($nombre));
            $ini = '';
            foreach ($p as $x) {
                if ($x !== '') {
                    $ini .= mb_strtoupper(mb_substr($x, 0, 1));
                }
                if (mb_strlen($ini) >= 2) {
                    break;
                }
            }
            return mb_substr($ini, 0, 2);
        }
    @endphp

    <div class="w-full mx-auto px-3">
        {{-- Encabezado --}}
        <div class="rounded-xl bg-gradient-to-r from-blue-50 via-blue-100 to-blue-50 border border-blue-200 p-1 mb-1">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-extrabold text-blue-800 tracking-tight">
                    ✏️ EDITANDO USUARIO #{{ $usuario->numero_empleado }}
                </h1>

                <a href="{{ route('configuracion.usuarios.select') }}"
                    class="text-[11px] px-2 py-1 rounded-full bg-blue-200 text-blue-900 font-bold">
                    ← Volver al listado
                </a>
            </div>
        </div>

        {{-- Mensajes --}}
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: @json(session('success')),
                            confirmButtonColor: '#2563eb'
                        });
                    }
                });
            </script>
        @endif

        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                <div class="font-semibold mb-1">Corrige los siguientes campos:</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-blue-200 bg-white p-3">
            <div class="flex items-center gap-2 mb-1">
                <div class="flex-shrink-0">
                    @if (!empty($usuario->foto))
                        <img src="{{ $usuario->foto }}" alt="Foto"
                            class="h-12 w-12 rounded-full object-cover ring-2 ring-blue-200">
                    @else
                        <img src="{{ asset('images/fondosTowell/TOWELLIN.png') }}" alt="Towellin"
                            class="h-12 w-12 rounded-full object-cover ring-2 ring-blue-200">
                    @endif
                </div>
                <div>
                    <div class="text-[13px] font-semibold text-blue-900">
                        {{ $usuario->nombre }}
                    </div>
                    <div class="text-[11px] text-blue-900/70">
                        Número empleado: <span class="font-semibold">#{{ $usuario->numero_empleado }}</span>
                    </div>
                </div>
            </div>

            <form action="{{ route('configuracion.usuarios.update', $usuario) }}" method="POST"
                class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                @method('PUT')

                {{-- numero_empleado solo lectura --}}
                <div class="">
                    <label class="w-[120px] text-[11px] font-semibold text-blue-900 mb-0.5 text-right">NÚMERO DE
                        EMPLEADO</label>
                    <input type="text" value="{{ $usuario->numero_empleado }}" disabled
                        class="w-3/4 text-sm p-1 rounded border border-blue-200 bg-gray-50">
                </div>

                <div>
                    <label class="w-[120px] text-[12px] font-semibold text-blue-900 mb-0.5 text-right">NOMBRE</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" required
                        class="w-3/4 text-sm p-1 rounded border border-blue-200">
                </div>

                <div class="flex items-center gap-2 w-[570px]">
                    <label class="w-[160px] text-[12px] font-semibold text-blue-900 text-right">
                        ÁREA
                    </label>
                    <select id="area" name="area"
                        class="inline-block h-8 text-sm p-1 rounded border border-blue-200" required>
                        <option value="" disabled {{ old('area', $usuario->area) ? '' : 'selected' }}>
                            Selecciona el área
                        </option>
                        @foreach ([
            'Almacén' => 'Almacen',
            'Urdido' => 'Urdido',
            'Engomado' => 'Engomado',
            'Tejido' => 'Tejido',
            'Atadores' => 'Atadores',
            'Tejedores' => 'Tejedores',
            'Mantenimiento' => 'Mantenimiento',
        ] as $label => $val)
                            <option value="{{ $val }}"
                                {{ old('area', $usuario->area) === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>



                <div>
                    <label class="w-[120px] text-[12px] font-semibold text-blue-900 mb-0.5 text-right">TELÉFONO</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $usuario->telefono) }}"
                        class="w-3/4 text-sm p-1 rounded border border-blue-200">
                </div>

                <div>
                    <label class="w-[120px] text-[12px] font-semibold text-blue-900 mb-0.5 text-right">TURNO</label>
                    <input type="text" name="turno" value="{{ old('turno', $usuario->turno) }}"
                        class="w-3/4 text-sm p-1 rounded border border-blue-200">
                </div>

                <div class="">
                    <label class="w-[120px] text-[12px] font-semibold text-blue-900 mb-0.5 text-right">FOTO (URL)</label>
                    <input type="url" name="foto" value="{{ old('foto', $usuario->foto) }}"
                        class="w-3/4 text-sm p-1 rounded border border-blue-200">
                </div>

                {{-- Opcional, solo si la usas (no se actualiza si la dejas vacía) --}}
                {{-- <div class="md:col-span-2">
                    <label class="block text-[11px] font-semibold text-blue-900 mb-0.5">Contraseña</label>
                    <input type="text" name="contrasenia" placeholder="Dejar vacío para no cambiar"
                           class="w-full text-sm p-2 rounded border border-blue-200">
                </div> --}}

                <div class="md:col-span-2">
                    <div class="text-[11px] font-semibold text-blue-900 mb-1">Permisos / módulos</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @php
                            $mods = [
                                'enviarMensaje' => 'Avisos ON',
                                'almacen' => 'Almacén',
                                'urdido' => 'Urdido',
                                'engomado' => 'Engomado',
                                'tejido' => 'Tejido',
                                'atadores' => 'Atadores',
                                'tejedores' => 'Tejedores',
                                'mantenimiento' => 'Mantenimiento',
                                'planeacion' => 'Planeación',
                                'configuracion' => 'Configuración',
                                'UrdidoEngomado' => 'Urdido+Engomado',
                            ];
                        @endphp

                        @foreach ($mods as $field => $label)
                            <label
                                class="inline-flex items-center gap-2 text-[12px] text-blue-900 bg-blue-50 border border-blue-200 rounded-lg px-2 py-1">
                                <input type="checkbox" name="{{ $field }}" value="1"
                                    @checked(old($field, (bool) $usuario->{$field}))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="md:col-span-2 flex items-right gap-2 pt-1">
                    <button type="submit"
                        class="w-1/4 inline-flex text-center text-[12px] px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold">
                        💾 Guardar cambios
                    </button>

                    <a href="{{ route('configuracion.usuarios.select') }}"
                        class="w-1/4 inline-flex items-center text-[12px] px-3 py-1.5 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-900 font-semibold border border-blue-300">
                        ← Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
