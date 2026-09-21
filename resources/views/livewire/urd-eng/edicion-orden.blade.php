@php
    $bloqueoAx = $esUrdido && $ax === 1;
    $claseBloqueo = 'bg-gray-100 text-gray-600 cursor-not-allowed';
    $tituloAx = 'Bloqueado: Urdido ya está en AX';
    $statusEditable = $esUrdido ? ['En Proceso', 'Programado'] : ['En Proceso', 'Programado', 'Parcial'];
    $editablePorStatus = in_array($status, $statusEditable, true);
    $soloLectura = ! $puedeEditar;
    $input = 'w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500';
    $label = 'block text-sm font-semibold text-gray-700 mb-0.5';
    $inerte = 'w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed';

    // Un campo se bloquea por permiso, por AX o porque el estado no lo permite.
    $bloqueado = function (string $campo) use ($soloLectura, $bloqueoAx, $editablePorStatus, $esUrdido, $status): bool {
        if ($soloLectura) {
            return true;
        }
        if ($bloqueoAx && $campo !== 'RizoPie') {
            return true;
        }
        if ($esUrdido && $campo === 'InventSizeId' && $status === 'Parcial') {
            return true;
        }
        $porStatus = $esUrdido
            ? ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaId', 'BomId']
            : ['RizoPie', 'Cuenta', 'Calibre', 'Fibra', 'MaquinaEng', 'BomEng', 'BomFormula', 'NoTelas'];

        return in_array($campo, $porStatus, true) && ! $editablePorStatus;
    };
@endphp

<div class="w-full"
     data-edicion-orden
     data-modulo="{{ $module }}"
     data-ruta-horas="{{ route($module.'.modulo.produccion.'.$module.'.actualizar.horas') }}"
     data-ruta-fecha="{{ route($module.'.modulo.produccion.'.$module.'.actualizar.fecha') }}"
     data-ruta-campos="{{ route($module.'.modulo.produccion.'.$module.'.actualizar.campos.produccion') }}"
     data-ruta-usuarios="{{ route($module.'.modulo.produccion.'.$module.'.usuarios.'.$module) }}"
     data-ruta-guardar-oficial="{{ route($module.'.modulo.produccion.'.$module.'.guardar.oficial') }}"
     data-ruta-eliminar-oficial="{{ route($module.'.modulo.produccion.'.$module.'.eliminar.oficial') }}"
     @if($esUrdido)
         data-ruta-bom="{{ route('programa.urd.eng.buscar.bom.urdido') }}"
     @else
         data-ruta-bom="{{ route('programa.urd.eng.buscar.bom.engomado') }}"
         data-ruta-bom-formula="{{ route('programa.urd.eng.buscar.bom.formula') }}"
     @endif
     data-ruta-lote="{{ route('programa.urd.eng.buscar.lote.proveedor') }}"
>

    @unless($puedeEditar)
        <div class="mb-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Solo un supervisor puede editar esta orden. Estás viendo los datos en modo lectura.
        </div>
    @endunless

    @if($bloqueoAx)
        <div class="mb-2 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
            Urdido ya está en AX: solo el campo <strong>Tipo</strong> sigue siendo editable.
        </div>
    @elseif(! $editablePorStatus)
        <div class="mb-2 rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
            Orden en estado <strong>{{ $status ?: '—' }}</strong>: cuenta, calibre, fibra, máquina y bom no se pueden cambiar.
        </div>
    @endif

    <div class="bg-white p-3 mb-4">
        {{-- Campos de la orden: cada uno se guarda solo al salir del campo --}}
        <div class="grid gap-1.5 mb-1.5" style="grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));">
            <div>
                <label class="{{ $label }}">Folio</label>
                <input type="text" value="{{ $orden->Folio }}" readonly disabled class="{{ $inerte }}" title="El folio no se puede editar">
            </div>

            @if($esUrdido)
                <div>
                    <label class="{{ $label }}">Folio Consumo</label>
                    <input type="text" wire:model.blur="form.FolioConsumo" @disabled($bloqueado('FolioConsumo'))
                        title="{{ $bloqueoAx ? $tituloAx : '' }}"
                        class="{{ $input }} {{ $bloqueado('FolioConsumo') ? $claseBloqueo : '' }}">
                </div>
            @endif

            <div>
                <label class="{{ $label }}">No. Telar</label>
                <input type="text" wire:model.blur="form.NoTelarId" @disabled($bloqueado('NoTelarId'))
                    class="{{ $input }} {{ $bloqueado('NoTelarId') ? $claseBloqueo : '' }}">
            </div>

            @unless($esUrdido)
                <div>
                    <label class="{{ $label }}">No. de Telas</label>
                    <input type="number" min="0" wire:model.blur="form.NoTelas" @disabled($bloqueado('NoTelas'))
                        class="{{ $input }} {{ $bloqueado('NoTelas') ? $claseBloqueo : '' }}">
                </div>
            @endunless

            <div>
                <label class="{{ $label }}">{{ $isKarlMayer ? 'Barras' : 'Tipo' }}</label>
                <select wire:model.blur="form.RizoPie" @disabled($bloqueado('RizoPie'))
                    class="{{ $input }} {{ $bloqueado('RizoPie') ? $claseBloqueo : '' }}">
                    <option value="">Seleccionar...</option>
                    @if($isKarlMayer)
                        @foreach(range(1, 4) as $barra)
                            <option value="{{ $barra }}">{{ $barra }}</option>
                        @endforeach
                    @else
                        <option value="Rizo">Rizo</option>
                        <option value="Pie">Pie</option>
                    @endif
                </select>
            </div>

            <div>
                <label class="{{ $label }}">Cuenta</label>
                <input type="number" wire:model.blur="form.Cuenta" @disabled($bloqueado('Cuenta'))
                    class="{{ $input }} {{ $bloqueado('Cuenta') ? $claseBloqueo : '' }}">
            </div>

            <div>
                <label class="{{ $label }}">Calibre</label>
                <input type="number" step="0.01" wire:model.blur="form.Calibre" @disabled($bloqueado('Calibre'))
                    class="{{ $input }} {{ $bloqueado('Calibre') ? $claseBloqueo : '' }}">
            </div>

            <div>
                <label class="{{ $label }}">Metros</label>
                <input type="number" step="0.01" wire:model.blur="form.Metros" @disabled($bloqueado('Metros'))
                    class="{{ $input }} {{ $bloqueado('Metros') ? $claseBloqueo : '' }}">
            </div>

            @if($esUrdido)
                <div>
                    <label class="{{ $label }}">Kilos</label>
                    <input type="number" step="0.01" value="{{ $orden->Kilos }}" readonly disabled class="{{ $inerte }}">
                </div>
            @endif

            <div>
                <label class="{{ $label }}">Fibra</label>
                <select wire:model.blur="form.Fibra" @disabled($bloqueado('Fibra'))
                    class="{{ $input }} {{ $bloqueado('Fibra') ? $claseBloqueo : '' }}">
                    <option value="">Seleccionar...</option>
                    @foreach(array_unique(array_filter(array_merge([$orden->Fibra], $opcionesFibra))) as $fibra)
                        <option value="{{ $fibra }}">{{ $fibra }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="{{ $label }}">Salón de Tejido</label>
                <select wire:model.blur="form.SalonTejidoId" @disabled($bloqueado('SalonTejidoId'))
                    class="{{ $input }} {{ $bloqueado('SalonTejidoId') ? $claseBloqueo : '' }}">
                    <option value="">Seleccionar...</option>
                    <option value="JACQUARD">JACQUARD</option>
                    <option value="SMIT">SMIT</option>
                    @if($esUrdido)
                        <option value="Karl Mayer">Karl Mayer</option>
                    @endif
                </select>
            </div>

            <div>
                <label class="{{ $label }}">Máquina</label>
                @php $campoMaquina = $esUrdido ? 'MaquinaId' : 'MaquinaEng'; @endphp
                <select wire:model.blur="form.{{ $campoMaquina }}" @disabled($bloqueado($campoMaquina))
                    class="{{ $input }} {{ $bloqueado($campoMaquina) ? $claseBloqueo : '' }}">
                    <option value="">Seleccionar...</option>
                    @if($esUrdido && ! $maquinas->contains(fn ($m) => stripos(($m->MaquinaId ?? '').($m->Nombre ?? ''), 'karl') !== false))
                        <option value="Karl Mayer">Karl Mayer</option>
                    @endif
                    @foreach($maquinas as $maquina)
                        <option value="{{ $maquina->MaquinaId ?? $maquina->Nombre }}">{{ $maquina->Nombre ?? $maquina->MaquinaId }}</option>
                    @endforeach
                </select>
            </div>

            @if($esUrdido)
                <div>
                    <label class="{{ $label }}">Fecha Programada</label>
                    <input type="date" wire:model.blur="form.FechaProg" @disabled($bloqueado('FechaProg'))
                        class="{{ $input }} {{ $bloqueado('FechaProg') ? $claseBloqueo : '' }}">
                </div>
            @endif

            <div>
                <label class="{{ $label }}">Tipo Atado</label>
                <select wire:model.blur="form.TipoAtado" @disabled($bloqueado('TipoAtado'))
                    class="{{ $input }} {{ $bloqueado('TipoAtado') ? $claseBloqueo : '' }}">
                    <option value="">Seleccionar...</option>
                    <option value="Normal">Normal</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>

            <div>
                <label class="{{ $label }}">Lote Proveedor</label>
                <input type="text" autocomplete="off" wire:model.blur="form.LoteProveedor" data-autocomplete="lote"
                    @disabled($bloqueado('LoteProveedor')) class="{{ $input }} {{ $bloqueado('LoteProveedor') ? $claseBloqueo : '' }}">
            </div>

            @if($esUrdido)
                <div>
                    <label class="{{ $label }}">Bom Urdido</label>
                    <input type="text" autocomplete="off" wire:model.blur="form.BomId" data-autocomplete="bom"
                        @disabled($bloqueado('BomId')) class="{{ $input }} {{ $bloqueado('BomId') ? $claseBloqueo : '' }}">
                </div>
                <div>
                    <label class="{{ $label }}">Fecha Req. Hilo</label>
                    <input type="text" readonly disabled class="{{ $inerte }}" title="Fecha en que se requiere el material del hilo"
                        value="{{ $fechaRequerimientoHilo ? \Carbon\Carbon::parse($fechaRequerimientoHilo)->format('d/m/Y H:i') : '—' }}">
                </div>
            @else
                <div>
                    <label class="{{ $label }}">Bom Engomado</label>
                    <input type="text" autocomplete="off" wire:model.blur="form.BomEng" data-autocomplete="bom"
                        @disabled($bloqueado('BomEng')) class="{{ $input }} {{ $bloqueado('BomEng') ? $claseBloqueo : '' }}">
                </div>
                <div>
                    <label class="{{ $label }}">Bom Fórmula</label>
                    <input type="text" autocomplete="off" wire:model.blur="form.BomFormula" data-autocomplete="bom-formula"
                        @disabled($bloqueado('BomFormula')) class="{{ $input }} {{ $bloqueado('BomFormula') ? $claseBloqueo : '' }}">
                </div>
            @endif

            <div>
                <label class="{{ $label }}">Tamaño</label>
                <input type="text" list="listaTamanos" wire:model.blur="form.InventSizeId" @disabled($bloqueado('InventSizeId'))
                    title="{{ $esUrdido && $status === 'Parcial' ? 'No se puede editar el tamaño cuando el estado es Parcial' : '' }}"
                    class="{{ $input }} {{ $bloqueado('InventSizeId') ? $claseBloqueo : '' }}">
                <datalist id="listaTamanos">
                    @foreach($opcionesTamano as $tamano)
                        <option value="{{ $tamano }}"></option>
                    @endforeach
                </datalist>
            </div>
        </div>

        <div class="grid gap-2 mb-2" style="grid-template-columns: {{ $esUrdido ? 'minmax(16rem, 1fr) 2fr' : '1fr' }};">
            @if($esUrdido)
                {{-- Plan de julios --}}
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="min-w-full text-sm">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-2 py-1 text-center font-semibold">No. Julio</th>
                                <th class="px-2 py-1 text-center font-semibold">Hilos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($julios as $i => $julio)
                                @php
                                    // Con Finalizado solo se corrigen los Hilos de un julio que ya existe.
                                    $julioBloqueado = $soloLectura || $bloqueoAx || (! $editablePorStatus && ! ($status === 'Finalizado'));
                                    $noJulioBloqueado = $julioBloqueado || $status === 'Finalizado';
                                @endphp
                                <tr wire:key="julio-{{ $i }}">
                                    <td class="px-2 py-1 text-center">
                                        <input type="number" min="1" step="1" wire:model.blur="julios.{{ $i }}.no_julio"
                                            @disabled($noJulioBloqueado)
                                            class="{{ $input }} {{ $noJulioBloqueado ? $claseBloqueo : '' }}">
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <input type="number" min="1" step="1" wire:model.blur="julios.{{ $i }}.hilos"
                                            @disabled($julioBloqueado)
                                            class="{{ $input }} {{ $julioBloqueado ? $claseBloqueo : '' }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex flex-col">
                <label class="{{ $label }}">Observaciones</label>
                <textarea rows="3" maxlength="{{ $observacionesMaxLength }}" wire:model.blur="form.Observaciones"
                    @disabled($bloqueado('Observaciones')) style="resize: none;"
                    class="{{ $input }} flex-1 {{ $bloqueado('Observaciones') ? $claseBloqueo : '' }}"></textarea>
            </div>
        </div>

        @if($produccion->isNotEmpty())
            {{-- Produccion: la captura sigue siendo del modulo de produccion --}}
            <div class="overflow-x-auto border border-gray-200 rounded" id="tabla-produccion">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-500 text-white">
                        <tr>
                            <th class="px-1 py-1 text-center font-semibold">Fecha</th>
                            <th class="px-1 py-1 text-left font-semibold">No. Empleado</th>
                            <th class="px-1 py-1 text-center font-semibold">H. Inicio</th>
                            <th class="px-1 py-1 text-center font-semibold">H. Fin</th>
                            <th class="px-1 py-1 text-center font-semibold">No. Julio</th>
                            @if($esUrdido)
                                <th class="px-1 py-1 text-center font-semibold">Hilos</th>
                            @endif
                            <th class="px-1 py-1 text-center font-semibold">Kg. Bruto</th>
                            <th class="px-1 py-1 text-center font-semibold">Tara</th>
                            <th class="px-1 py-1 text-center font-semibold">Kg. Neto</th>
                            <th class="px-1 py-1 text-center font-semibold">Metros</th>
                            @if($esUrdido)
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Hilat.</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Maq.</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Operac.</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Transf.</th>
                            @else
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Canoa1</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Canoa2</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Sólidos</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Roturas</th>
                                <th class="px-1 py-1 text-center font-semibold bg-blue-700">Ubicación</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($produccion as $reg)
                            @php
                                $axFila = (int) ($reg->AX ?? 0) === 1;
                                $bloqueoFila = $axFila || $soloLectura;
                                $claseFila = $bloqueoFila ? $claseBloqueo : '';
                                $tituloFila = $axFila ? 'Fila bloqueada (AX procesado)' : '';
                                $metros = (float) ($reg->Metros1 ?? 0) + (float) ($reg->Metros2 ?? 0) + (float) ($reg->Metros3 ?? 0);
                                $empleados = array_filter([trim($reg->CveEmpl1 ?? ''), trim($reg->CveEmpl2 ?? ''), trim($reg->CveEmpl3 ?? '')]);
                                $oficiales = [];
                                for ($i = 1; $i <= 3; $i++) {
                                    $cve = trim((string) ($reg->{"CveEmpl{$i}"} ?? ''));
                                    $nom = trim((string) ($reg->{"NomEmpl{$i}"} ?? ''));
                                    $turno = $reg->{"Turno{$i}"} ?? null;
                                    $metrosOf = $reg->{"Metros{$i}"} ?? null;
                                    if ($cve !== '' || $nom !== '' || $turno !== null || $metrosOf !== null) {
                                        $oficiales[] = [
                                            'numero' => $i,
                                            'cve' => $cve ?: null,
                                            'nombre' => $nom ?: null,
                                            'turno' => $turno !== null && $turno !== '' ? (int) $turno : null,
                                            'metros' => $metrosOf !== null && $metrosOf !== '' ? (float) $metrosOf : null,
                                        ];
                                    }
                                }
                                $fecha = $reg->Fecha ? \Illuminate\Support\Facades\Date::parse($reg->Fecha) : now();
                                $puedeEditarFecha = ! $bloqueoFila && $fecha->format('Y-m') === now()->format('Y-m');
                                $celda = 'produccion-input px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500';
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $axFila ? 'bg-gray-50' : '' }}"
                                data-registro-id="{{ $reg->Id }}" data-no-julio="{{ trim((string) ($reg->NoJulio ?? '')) }}"
                                @if($tituloFila) title="{{ $tituloFila }}" @endif>
                                <td class="px-1 py-1 text-center">
                                    <input type="date" data-field="fecha" data-registro-id="{{ $reg->Id }}"
                                        value="{{ $reg->Fecha ? $fecha->format('Y-m-d') : '' }}"
                                        min="{{ $fecha->copy()->startOfMonth()->format('Y-m-d') }}"
                                        max="{{ $fecha->copy()->endOfMonth()->format('Y-m-d') }}"
                                        @disabled(! $puedeEditarFecha)
                                        class="{{ $celda }} w-28 {{ $puedeEditarFecha ? '' : $claseBloqueo }}"
                                        title="{{ $puedeEditarFecha ? 'Cambiar fecha' : ($axFila ? $tituloFila : 'Solo editable en el mes actual') }}">
                                </td>
                                <td class="px-1 py-0.5 text-left align-top max-w-[180px]">
                                    <div class="flex items-start justify-between gap-1">
                                        <div class="text-sm leading-tight flex-1 min-w-0" data-empleados-info>
                                            <div class="text-gray-800 font-semibold">{{ count($empleados) ? implode(', ', $empleados) : '-' }}</div>
                                            @foreach($oficiales as $of)
                                                @if($of['nombre'])
                                                    <div class="text-sm text-gray-600">{{ $of['nombre'] }} <span class="text-amber-600">(T{{ $of['turno'] ?? '-' }})</span></div>
                                                @endif
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn-editar-empleados shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded {{ $bloqueoFila ? 'opacity-40 pointer-events-none' : '' }}"
                                            data-registro-id="{{ $reg->Id }}" data-oficiales='@json($oficiales)'
                                            @disabled($bloqueoFila) title="{{ $bloqueoFila ? $tituloFila : 'Editar empleados' }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-0.5 py-0.5 text-center">
                                    <input type="time" data-field="h_inicio" data-registro-id="{{ $reg->Id }}"
                                        value="{{ $reg->HoraInicial ? substr((string) $reg->HoraInicial, 0, 5) : '' }}"
                                        @disabled($bloqueoFila) title="{{ $tituloFila }}" class="{{ $celda }} w-24 {{ $claseFila }}">
                                </td>
                                <td class="px-0.5 py-0.5 text-center">
                                    <input type="time" data-field="h_fin" data-registro-id="{{ $reg->Id }}"
                                        value="{{ $reg->HoraFinal ? substr((string) $reg->HoraFinal, 0, 5) : '' }}"
                                        @disabled($bloqueoFila) title="{{ $tituloFila }}" class="{{ $celda }} w-24 {{ $claseFila }}">
                                </td>
                                <td class="px-1 py-1 text-center">{{ $reg->NoJulio ?? '-' }}</td>
                                @if($esUrdido)
                                    <td class="px-1 py-1 text-center">
                                        <input type="number" min="0" data-field="hilos" data-registro-id="{{ $reg->Id }}"
                                            value="{{ $mapaJuliosHilos[trim((string) ($reg->NoJulio ?? ''))] ?? $reg->Hilos }}"
                                            @disabled($bloqueoFila) title="{{ $tituloFila }}" class="{{ $celda }} w-16 {{ $claseFila }}">
                                    </td>
                                @endif
                                <td class="px-1 py-1 text-center">{{ ($reg->KgBruto ?? '') !== '' ? number_format((float) $reg->KgBruto, 2) : '-' }}</td>
                                <td class="px-1 py-1 text-center">{{ ($reg->Tara ?? '') !== '' ? number_format((float) $reg->Tara, 2) : '-' }}</td>
                                <td class="px-1 py-1 text-center">{{ ($reg->KgNeto ?? '') !== '' ? number_format((float) $reg->KgNeto, 2) : '-' }}</td>
                                <td class="px-1 py-1 text-center">{{ $metros > 0 ? number_format($metros, 0) : '-' }}</td>
                                @foreach(($esUrdido ? ['hilatura' => 'w-12', 'maquina' => 'w-12', 'operac' => 'w-12', 'transf' => 'w-12'] : ['canoa1' => 'w-12', 'canoa2' => 'w-12', 'solidos' => 'w-14', 'roturas' => 'w-12']) as $campo => $ancho)
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" @if($campo === 'solidos') step="0.01" @endif
                                            data-field="{{ $campo }}" data-registro-id="{{ $reg->Id }}"
                                            value="{{ $reg->{ucfirst($campo)} ?? ($campo === 'solidos' || $campo === 'roturas' ? '' : 0) }}"
                                            @disabled($bloqueoFila) title="{{ $tituloFila }}" class="{{ $celda }} {{ $ancho }} {{ $claseFila }}">
                                    </td>
                                @endforeach
                                @unless($esUrdido)
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <select data-field="ubicacion" data-registro-id="{{ $reg->Id }}" @disabled($bloqueoFila)
                                            class="{{ $celda }} w-24 {{ $claseFila }}">
                                            <option value="">-</option>
                                            @foreach($ubicaciones as $ub)
                                                <option value="{{ $ub->Codigo }}" @selected(($reg->Ubicacion ?? '') === $ub->Codigo)>{{ $ub->Codigo }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
