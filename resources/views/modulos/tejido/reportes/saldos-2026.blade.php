@extends('layouts.app')

@section('page-title', 'Saldos 2026')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <a href="{{ route('tejido.reportes.saldos-2026.excel') }}"
           class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
            <i class="fas fa-file-excel"></i>
            <span>Exportar a Excel</span>
        </a>
    </div>
@endsection

@section('content')
<div class="w-full flex flex-col bg-white" style="height: calc(100vh - 64px);">

    {{-- Table card --}}
    <div class="flex flex-col overflow-hidden" style="flex: 1; min-height: 0;">
        @if ($registros->isEmpty())
            <div class="flex-1 flex flex-col items-center justify-center text-center py-16">
                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                    <i class="fas fa-inbox text-3xl text-gray-300"></i>
                </div>
                <p class="text-gray-500 font-medium text-lg">Sin registros con orden de producción</p>
                <p class="text-gray-400 text-sm mt-1">Aún no hay órdenes liberadas en el programa de tejido.</p>
            </div>
        @else
            {{-- Sticky table container --}}
            <div class="overflow-auto flex-1" id="saldos-table-container">
                <table class="min-w-full border-collapse text-xs" id="saldos-table" style="border-spacing: 0;">
                    <thead style="position: sticky; top: 0; z-index: 20;">
                        {{-- Fila 1: headers principales --}}
                        <tr>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:52px;">TELAR</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:90px;">Orden Vinculada</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:120px;">Orden Jefe Líder</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">REPASO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:100px;">Fecha Inicio Telar</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:120px;">No. Orden</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:100px;">Fecha Orden</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:110px;">Fecha Cumplimiento</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:110px;">Departamento</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">Prioridad</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:160px;">Modelo</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:100px;">CLAVE MODELO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:100px;">CLAVE AX</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:90px;">TOLERANCIA</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:120px;">CÓDIGO DE DIBUJO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:100px;">Fecha Compromiso</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:160px;">Nombre Formato Logístico</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:80px;">Clave</th>
                            <th rowspan="2" class="saldos-th saldos-th-main saldos-th-solsaldo" style="min-width:90px;">Cant. a Producir</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">Peine</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">Ancho</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;background:#16a34a !important;border-color:#22c55e !important;">Largo</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:65px;">Peso crudo</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:65px;">Luchaje</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:65px;">Tra</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:80px;">Hilo</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:80px;">OBS.</th>
                            <th rowspan="2" class="saldos-th saldos-th-main saldos-pending" style="min-width:80px;">Tipo plano</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:80px;">Med plano</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:90px;">TIPO DE RIZO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:90px;">ALTURA DE RIZO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:80px;">OBS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:90px;">Veloc. Mínima</th>
                            {{-- RIZO group --}}
                            <th colspan="3" class="saldos-th saldos-th-group saldos-group-rizo">RIZO</th>
                            {{-- PIE group --}}
                            <th colspan="3" class="saldos-th saldos-th-group saldos-group-pie">PIE</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">C1</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">OBS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">C2</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">OBS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">C3</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">OBS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:55px;">C4</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:70px;">OBS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:95px;">Med. de Cenefa</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:130px;">Med. inicio rizo a cenefa</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:75px;">RAZURADA</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:52px;">TIRAS</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:95px;">Rep. por corte</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:85px;background:#16a34a !important;border-color:#22c55e !important;">Rollos prog.</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:85px;">Toallas Tejidas</th>
                            <th rowspan="2" class="saldos-th saldos-th-main saldos-th-solsaldo" style="min-width:70px;">SALDO</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:60px;">Faltan</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:60px;">Avance</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:85px;background:#16a34a !important;border-color:#22c55e !important;">Rollos x Tejer</th>
                            <th rowspan="2" class="saldos-th saldos-th-main" style="min-width:160px;">Observaciones</th>
                        </tr>
                        {{-- Fila 2: sub-headers Rizo y Pie --}}
                        <tr>
                            <th class="saldos-th saldos-th-sub saldos-group-rizo">Cuenta</th>
                            <th class="saldos-th saldos-th-sub saldos-group-rizo">Calibre</th>
                            <th class="saldos-th saldos-th-sub saldos-group-rizo">Fibra</th>
                            <th class="saldos-th saldos-th-sub saldos-group-pie">Cuenta</th>
                            <th class="saldos-th saldos-th-sub saldos-group-pie">Calibre</th>
                            <th class="saldos-th saldos-th-sub saldos-group-pie">Fibra</th>
                        </tr>
                    </thead>
                    <tbody id="saldos-tbody">
                        @foreach ($registros as $i => $r)
                            @php
                                $esRepaso   = !empty($r->NoExisteBase);
                                $solicitado = (float) ($r->TotalPedido ?? 0);
                                $saldo      = (float) ($r->SaldoPedido ?? 0);
                                $faltan     = $solicitado - $saldo;
                                $avance     = $solicitado > 0 ? round($saldo / $solicitado * 100, 1) : 0;
                                $tiras      = (float) ($r->NoTiras ?? 0);
                                $reps       = (float) ($r->Repeticiones ?? 0);
                                $rollosXTejer = ($tiras > 0 && $reps > 0) ? ceil($faltan / ($tiras * $reps)) : '—';
                            @endphp
                            <tr class="saldos-row {{ $esRepaso ? 'saldos-row-repaso' : ($i % 2 === 0 ? 'saldos-row-even' : 'saldos-row-odd') }}"
                                data-search="{{ strtolower(($r->NoTelarId ?? '') . ' ' . ($r->NoProduccion ?? '') . ' ' . ($r->NombreProducto ?? '') . ' ' . ($r->ItemId ?? '') . ' ' . ($r->TamanoClave ?? '')) }}">
                                <td class="saldos-td font-semibold text-center text-blue-700">
                                    {{ $r->NoTelarId }}
                                    @if ($r->EnProceso)
                                        <span title="En proceso" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#16a34a;margin-left:4px;vertical-align:middle;box-shadow:0 0 0 2px #bbf7d0;"></span>
                                    @endif
                                </td>
                                <td class="saldos-td text-center font-mono text-gray-700">{{ $r->OrdCompartida ?? '—' }}</td>
                                <td class="saldos-td text-center font-mono {{ $r->OrdCompartidaLider ? 'font-semibold text-amber-700' : 'text-gray-600' }}">
                                    {{ $r->OrdenLider ?? '—' }}
                                    @if ($r->OrdCompartidaLider)
                                        <span title="Es líder" style="margin-left:3px;">&#9733;</span>
                                    @endif
                                </td>
                                <td class="saldos-td text-center" style="{{ $r->NoExisteBase ? 'background:#fee2e2;color:#b91c1c;font-weight:700;' : '' }}">{{ $r->NoExisteBase ?? '—' }}</td>
                                <td class="saldos-td text-center text-gray-600">
                                    {{ $r->FechaInicio ? \Carbon\Carbon::parse($r->FechaInicio)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="saldos-td font-mono font-medium text-gray-800">{{ $r->NoProduccion }}</td>
                                <td class="saldos-td text-center text-gray-600">{{ $r->FechaCreacion ? \Carbon\Carbon::parse($r->FechaCreacion)->format('d/m/Y') : '—' }}</td>
                                <td class="saldos-td text-center text-gray-600">{{ $r->EntregaCte ? \Carbon\Carbon::parse($r->EntregaCte)->format('d/m/Y') : '—' }}</td>
                                <td class="saldos-td text-center">
                                    @if ($r->SalonTejidoId)
                                        @php
                                            $salon = strtoupper($r->SalonTejidoId);
                                            $badgeSalon = str_contains($salon, 'JACQUARD') ? 'background:#dcfce7;color:#15803d;border:1px solid #86efac;'
                                                : (str_contains($salon, 'SMIT') ? 'background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd;'
                                                : (str_contains($salon, 'ITEMA') ? 'background:#fef3c7;color:#b45309;border:1px solid #fcd34d;'
                                                : (str_contains($salon, 'KARL') ? 'background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd;'
                                                : 'background:#f3f4f6;color:#374151;border:1px solid #d1d5db;')));
                                        @endphp
                                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.62rem;font-weight:700;letter-spacing:0.04em;{{ $badgeSalon }}">
                                            {{ $r->SalonTejidoId }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="saldos-td text-center">
                                    @if (!is_null($r->Prioridad) && $r->Prioridad !== '')
                                        @php
                                            $p = (int) $r->Prioridad;
                                            $badge = match(true) {
                                                $p === 1 => 'saldos-badge-red',
                                                $p === 2 => 'saldos-badge-orange',
                                                $p === 3 => 'saldos-badge-yellow',
                                                default  => 'saldos-badge-gray',
                                            };
                                        @endphp
                                        <span class="saldos-badge {{ $badge }}">{{ $r->Prioridad }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="saldos-td text-gray-800 truncate" style="max-width:160px;" title="{{ $r->NombreProducto }}">{{ $r->NombreProducto ?? '—' }}</td>
                                <td class="saldos-td text-gray-700">{{ $r->TamanoClave ?? '—' }}</td>
                                <td class="saldos-td font-mono text-gray-700">{{ $r->ItemId ?? '—' }}</td>
                                <td class="saldos-td text-center text-gray-700">{{ $r->Tolerancia ?? '—' }}</td>{{-- TOLERANCIA --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->CodigoDibujo ?? '—' }}</td>{{-- CÓDIGO DE DIBUJO --}}
                                <td class="saldos-td text-center text-gray-600">{{ $r->EntregaProduc ? \Carbon\Carbon::parse($r->EntregaProduc)->format('d/m/Y') : '—' }}</td>
                                <td class="saldos-td text-gray-700 truncate" style="max-width:140px;" title="{{ $r->FlogsId }}">{{ $r->FlogsId ?? '—' }}</td>{{-- Nombre Formato Logístico --}}
                                <td class="saldos-td text-gray-700">{{ $r->Clave ?? '—' }}</td>{{-- Clave --}}
                                <td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold">{{ $r->TotalPedido !== null ? number_format((float)$r->TotalPedido, 0) : '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->Peine ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->Ancho ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums" style="background:#dcfce7;border-color:#86efac;">{{ $r->LargoCrudo ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->PesoCrudo ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->Luchaje ?? '—' }}</td>
                                <td class="saldos-td text-center">{{ $r->CalibreTrama2 ?? '—' }}</td>
                                <td class="saldos-td text-center">{{ $r->FibraTrama ?? '—' }}</td>
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsModelo ?? '—' }}</td>{{-- OBS. --}}
                                <td class="saldos-td text-center text-gray-300">—</td>{{-- Tipo plano --}}
                                <td class="saldos-td text-center">{{ $r->MedidaPlano ?? '—' }}</td>
                                <td class="saldos-td text-center text-gray-700">{{ $r->TipoRizo ?? '—' }}</td>{{-- TIPO DE RIZO --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->AlturaRizo ?? '—' }}</td>{{-- ALTURA DE RIZO --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsModelo ?? '—' }}</td>{{-- OBS --}}
                                <td class="saldos-td text-right tabular-nums">{{ $r->VelocidadSTD ?? '—' }}</td>
                                {{-- RIZO --}}
                                <td class="saldos-td text-center" style="background:#dcfce7;border-color:#86efac;">{{ $r->CuentaRizo ?? '—' }}</td>
                                <td class="saldos-td text-center" style="background:#dcfce7;border-color:#86efac;">{{ $r->CalibreRizo2 ?? '—' }}</td>
                                <td class="saldos-td" style="background:#dcfce7;border-color:#86efac;">{{ $r->FibraRizo ?? '—' }}</td>
                                {{-- PIE --}}
                                <td class="saldos-td text-center" style="background:#fef3c7;border-color:#fcd34d;">{{ $r->CuentaPie ?? '—' }}</td>
                                <td class="saldos-td text-center" style="background:#dcfce7;border-color:#86efac;">{{ $r->CalibrePie2 ?? '—' }}</td>
                                <td class="saldos-td" style="background:#dcfce7;border-color:#86efac;">{{ $r->FibraPie ?? '—' }}</td>
                                <td class="saldos-td text-center text-gray-700">{{ $r->C1 ?? '—' }}</td>{{-- C1 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsC1 ?? '—' }}</td>{{-- OBS C1 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->C2 ?? '—' }}</td>{{-- C2 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsC2 ?? '—' }}</td>{{-- OBS C2 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->C3 ?? '—' }}</td>{{-- C3 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsC3 ?? '—' }}</td>{{-- OBS C3 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->C4 ?? '—' }}</td>{{-- C4 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->ObsC4 ?? '—' }}</td>{{-- OBS C4 --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->MedidaCenefa ?? '—' }}</td>{{-- Med. de Cenefa --}}
                                <td class="saldos-td text-center text-gray-700">{{ $r->MedIniRizoCenefa ?? '—' }}</td>{{-- Med. inicio rizo a cenefa --}}
                                @php $esRasurada = strtolower(trim($r->Rasurado ?? '')) === 'si'; @endphp
                                <td class="saldos-td text-center" style="{{ $esRasurada ? 'background:#fee2e2;' : '' }}">{{ $r->Rasurado ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->NoTiras ?? '—' }}</td>
                                <td class="saldos-td text-right tabular-nums">{{ $r->Repeticiones !== null ? number_format((float)$r->Repeticiones, 0) : '—' }}</td>
                                <td class="saldos-td text-right tabular-nums" style="background:#dcfce7;border-color:#86efac;">{{ $r->TotalRollos !== null ? number_format((float)$r->TotalRollos, 0) : '—' }}</td>
                                <td class="saldos-td text-right tabular-nums font-medium text-gray-800">{{ $r->Produccion !== null ? number_format((float)$r->Produccion, 0) : '—' }}</td>
                                <td class="saldos-td saldos-td-solsaldo text-right tabular-nums font-semibold {{ ($r->SaldoPedido ?? 0) > 0 ? 'text-indigo-700' : 'text-gray-400' }}">
                                    {{ $r->SaldoPedido !== null ? number_format((float)$r->SaldoPedido, 0) : '—' }}
                                </td>
                                <td class="saldos-td text-right tabular-nums {{ $faltan > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $solicitado > 0 ? number_format($faltan, 0) : '—' }}</td>{{-- Faltan --}}
                                <td class="saldos-td text-right tabular-nums {{ $avance >= 100 ? 'text-green-600 font-semibold' : 'text-blue-600' }}">{{ $solicitado > 0 ? $avance . '%' : '—' }}</td>{{-- Avance --}}
                                <td class="saldos-td text-right tabular-nums text-gray-700" style="background:#dcfce7;border-color:#86efac;">{{ is_numeric($rollosXTejer) ? number_format($rollosXTejer, 0) : $rollosXTejer }}</td>{{-- Rollos x Tejer --}}
                                <td class="saldos-td text-gray-500 truncate" style="max-width:160px;" title="{{ $r->Observaciones }}">
                                    {{ $r->Observaciones ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @endif
    </div>
</div>

{{-- ── Context Menu ── --}}
<div id="saldos-ctx" role="menu" aria-hidden="true">
    <div class="ctx-header" id="ctx-col-label">Columna</div>

    <div class="ctx-section">Columna</div>
    <button class="ctx-btn" data-action="freeze"><i class="ctx-ico fas fa-thumbtack"></i><span id="ctx-freeze-lbl">Fijar columna</span></button>
    <button class="ctx-btn" data-action="hide"><i class="ctx-ico fas fa-eye-slash"></i>Ocultar columna</button>

    <div class="ctx-sep"></div>
    <div class="ctx-section">Filtrar / Ordenar</div>
    <button class="ctx-btn" data-action="filter"><i class="ctx-ico fas fa-filter"></i>Filtrar esta columna</button>
    <button class="ctx-btn" data-action="sort-asc"><i class="ctx-ico fas fa-arrow-up-a-z"></i>Ordenar A → Z</button>
    <button class="ctx-btn" data-action="sort-desc"><i class="ctx-ico fas fa-arrow-down-z-a"></i>Ordenar Z → A</button>

    <div class="ctx-sep"></div>
    <button class="ctx-btn ctx-btn-muted" data-action="clear-filters"><i class="ctx-ico fas fa-xmark"></i>Limpiar filtros</button>
    <button class="ctx-btn ctx-btn-muted" data-action="show-cols"><i class="ctx-ico fas fa-eye"></i>Mostrar columnas ocultas</button>
    <button class="ctx-btn ctx-btn-muted" data-action="reset-sort"><i class="ctx-ico fas fa-rotate-left"></i>Restablecer orden</button>
</div>

@push('styles')
<style>
/* ===== Layout base ===== */
.saldos-th {
    padding: 0 10px;
    white-space: nowrap;
    border: 1px solid #d1d5db;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.03em;
}

/* Main header row */
.saldos-th-main {
    background: #1e3a8a;
    color: #ffffff;
    height: 36px;
    text-align: center;
    vertical-align: middle;
    border-color: #2563eb;
}

/* Group header (RIZO / PIE) */
.saldos-th-group {
    height: 22px;
    text-align: center;
    vertical-align: middle;
    font-size: 0.65rem;
}

/* Sub-header row */
.saldos-th-sub {
    height: 20px;
    text-align: center;
    vertical-align: middle;
    font-size: 0.62rem;
}

/* Pending columns (not yet defined) */
.saldos-pending {
    background: #374151 !important;
    opacity: 0.7;
    font-style: italic;
}

/* RIZO color scheme */
.saldos-group-rizo {
    background: #16a34a;
    color: #ffffff;
    border-color: #22c55e;
}
.saldos-cell-rizo {
    background-color: #ede9fe;
    border-left-color: #c4b5fd;
    border-right-color: #c4b5fd;
}

/* PIE color scheme */
.saldos-group-pie {
    background: #d97706;
    color: #ffffff;
    border-color: #f59e0b;
}
.saldos-cell-pie {
    background-color: #cffafe;
    border-left-color: #67e8f9;
    border-right-color: #67e8f9;
}

/* Salon section label */
.saldos-salon-row td {
    padding: 6px 14px;
}
.saldos-salon-label {
    background: linear-gradient(90deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #0369a1;
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    border-top: 2px solid #bae6fd;
    border-bottom: 1px solid #bae6fd;
}

/* Data rows */
.saldos-td {
    padding: 5px 8px;
    border: 1px solid #e5e7eb;
    font-size: 0.72rem;
    white-space: nowrap;
    vertical-align: middle;
    color: #374151;
}

.saldos-row-even td { background-color: #ffffff; }
.saldos-row-odd td  { background-color: #f8faff; }
.saldos-row-repaso td { background-color: #fee2e2 !important; }

.saldos-row:hover td {
    background-color: #eff6ff !important;
    cursor: default;
}

/* Priority badges */
.saldos-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 20px;
    padding: 0 6px;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}
.saldos-badge-red    { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.saldos-badge-orange { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
.saldos-badge-yellow { background: #fef9c3; color: #92400e; border: 1px solid #fde68a; }
.saldos-badge-gray   { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

/* Solicitado / Saldo paired columns */
.saldos-th-solsaldo {
    background: #4338ca !important;
    border-color: #6366f1 !important;
}
.saldos-td-solsaldo {
    background-color: #eef2ff !important;
    border-color: #c7d2fe !important;
    color: #3730a3;
}
.saldos-row:hover .saldos-td-solsaldo {
    background-color: #e0e7ff !important;
}

/* Hidden row when filtered out */
.saldos-hidden { display: none !important; }

/* ── Context menu ── */
#saldos-ctx {
    display: none;
    position: fixed;
    z-index: 9999;
    min-width: 210px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.14), 0 2px 6px rgba(0,0,0,.08);
    overflow: hidden;
    padding: 4px 0;
    font-size: 0.78rem;
}
.ctx-header {
    padding: 6px 14px 4px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: .08em;
    color: #6366f1;
    text-transform: uppercase;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 210px;
}
.ctx-section {
    padding: 4px 14px 2px;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: .07em;
    color: #9ca3af;
    text-transform: uppercase;
}
.ctx-sep {
    height: 1px;
    background: #f3f4f6;
    margin: 3px 0;
}
.ctx-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 6px 14px;
    border: none;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-size: 0.78rem;
    color: #374151;
    transition: background .12s, color .12s;
    white-space: nowrap;
}
.ctx-btn:hover { background: #eff6ff; color: #1d4ed8; }
.ctx-btn-muted { color: #6b7280; }
.ctx-btn-muted:hover { background: #f9fafb; color: #374151; }
.ctx-ico { width: 16px; text-align: center; flex-shrink: 0; font-size: 0.8rem; color: #6366f1; }
.ctx-btn-muted .ctx-ico { color: #9ca3af; }
.ctx-btn:hover .ctx-ico { color: #1d4ed8; }
.ctx-btn-muted:hover .ctx-ico { color: #6b7280; }

/* ── Frozen column ── */
.saldos-col-frozen {
    box-shadow: 3px 0 8px -2px rgba(30,58,138,.25) !important;
}
thead .saldos-col-frozen { background: #1e3a8a !important; }
tbody .saldos-col-frozen { background: #f0f4ff !important; }

/* ── Column filter row ── */
#saldos-filter-row { display: none; }
#saldos-filter-row th {
    background: #f0fdf4;
    border: 1px solid #86efac;
    padding: 2px 3px;
    position: sticky;
    z-index: 15;
}
.saldos-filter-inp {
    width: 100%;
    min-width: 40px;
    font-size: 0.65rem;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    padding: 2px 4px;
    outline: none;
    box-sizing: border-box;
    background: #fff;
    color: #111827;
    transition: border-color .15s;
}
.saldos-filter-inp:focus { border-color: #6366f1; box-shadow: 0 0 0 2px #e0e7ff; }

/* Sort indicator on headers */
.saldos-th[data-sort-dir="asc"]::after  { content: ' ↑'; font-size:.7em; opacity:.8; }
.saldos-th[data-sort-dir="desc"]::after { content: ' ↓'; font-size:.7em; opacity:.8; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const table   = document.getElementById('saldos-table');
    const tbody   = document.getElementById('saldos-tbody');
    const ctxMenu = document.getElementById('saldos-ctx');
    if (!table || !ctxMenu) return;

    /* ═══════════════════════════════════════════════════════════
       1. Build column map
       Maps each cell → visual start-column index (accounts for
       rowspan / colspan). colCells[idx] = [all single-col cells].
    ═══════════════════════════════════════════════════════════ */
    const cellToCol = new Map();
    const colCells  = {};
    let   totalCols = 0;

    (function buildMap() {
        const occupied = [];
        function isOcc(r,c){ return !!(occupied[r] && occupied[r][c]); }
        function setOcc(r,c){ if (!occupied[r]) occupied[r]={}; occupied[r][c]=true; }

        Array.from(table.rows).forEach(function(row, ri) {
            var ci = 0;
            Array.from(row.cells).forEach(function(cell) {
                while (isOcc(ri, ci)) ci++;
                var cs = cell.colSpan || 1, rs = cell.rowSpan || 1;
                cellToCol.set(cell, ci);
                if (cs === 1) {
                    if (!colCells[ci]) colCells[ci] = [];
                    colCells[ci].push(cell);
                    if (ci + 1 > totalCols) totalCols = ci + 1;
                }
                for (var r=0; r<rs; r++) for (var c=0; c<cs; c++) setOcc(ri+r, ci+c);
                ci += cs;
            });
        });
    })();

    /* ═══════════════════════════════════════════════════════════
       2. Filter row — one input per visual column
    ═══════════════════════════════════════════════════════════ */
    const thead      = table.tHead;
    const filterRow  = document.createElement('tr');
    filterRow.id     = 'saldos-filter-row';
    const filterInps = {};   // colIdx → input
    const colFilters = {};   // colIdx → string

    for (var ci = 0; ci < totalCols; ci++) {
        var th  = document.createElement('th');
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.placeholder = '…';
        inp.className   = 'saldos-filter-inp';
        inp.dataset.col = ci;
        (function(colIdx, input){
            input.addEventListener('input', function(){
                colFilters[colIdx] = this.value.toLowerCase().trim();
                applyFilters();
            });
        })(ci, inp);
        filterInps[ci] = inp;
        th.appendChild(inp);
        filterRow.appendChild(th);
    }
    thead.appendChild(filterRow);

    // Set sticky top for filter row after render
    requestAnimationFrame(function(){
        var h = 0;
        Array.from(thead.rows).forEach(function(r){ if (r !== filterRow) h += r.offsetHeight; });
        Array.from(filterRow.cells).forEach(function(th){ th.style.top = h + 'px'; });
    });

    /* ═══════════════════════════════════════════════════════════
       3. Combined filter (global search bar + column filters)
    ═══════════════════════════════════════════════════════════ */
    var globalQ   = '';
    var counter   = document.getElementById('saldos-count');
    var visibleEl = document.getElementById('saldos-visible');
    var searchInp = document.getElementById('saldos-search');

    if (searchInp) {
        searchInp.addEventListener('input', function(){
            globalQ = this.value.toLowerCase().trim();
            applyFilters();
        });
    }

    function getCellText(row, colIdx) {
        var cells = Array.from(row.cells);
        for (var i=0; i<cells.length; i++) {
            if (cellToCol.get(cells[i]) === colIdx)
                return cells[i].textContent.trim().toLowerCase();
        }
        return '';
    }

    function applyFilters() {
        var rows  = Array.from(tbody.querySelectorAll('tr.saldos-row'));
        var shown = 0;
        rows.forEach(function(row) {
            var show = !globalQ || (row.dataset.search || '').includes(globalQ);
            if (show) {
                for (var idx in colFilters) {
                    var v = colFilters[idx];
                    if (v && !getCellText(row, parseInt(idx)).includes(v)) { show = false; break; }
                }
            }
            row.classList.toggle('saldos-hidden', !show);
            if (show) shown++;
        });
        if (counter)   counter.textContent   = shown;
        if (visibleEl) visibleEl.textContent = shown;
    }

    /* ═══════════════════════════════════════════════════════════
       4. State
    ═══════════════════════════════════════════════════════════ */
    var frozenCols   = new Set();
    var hiddenCols   = new Set();
    var origOrder    = tbody ? Array.from(tbody.rows) : [];
    var ctxColIdx    = null;
    var ctxCell      = null;
    var ctxRow       = null;

    /* ═══════════════════════════════════════════════════════════
       5. Context menu — show / hide
    ═══════════════════════════════════════════════════════════ */
    function openCtx(e, cell) {
        e.preventDefault();
        ctxCell   = cell;
        ctxRow    = cell.closest('tr');
        ctxColIdx = cellToCol.get(cell);

        // Column label (header text of that column)
        var label = document.getElementById('ctx-col-label');
        if (label) {
            var cells = colCells[ctxColIdx] || [];
            var hdrCell = cells.find(function(c){ return c.closest('thead'); });
            label.textContent = hdrCell ? hdrCell.textContent.trim().substring(0,30) : ('Col ' + (ctxColIdx+1));
        }

        // Freeze button label
        var fl = document.getElementById('ctx-freeze-lbl');
        if (fl) fl.textContent = frozenCols.has(ctxColIdx) ? 'Desfijar columna' : 'Fijar columna';

        // Position (keep inside viewport)
        var vw = window.innerWidth, vh = window.innerHeight;
        var x = e.clientX, y = e.clientY;
        if (x + 220 > vw) x = vw - 224;
        if (y + 340 > vh) y = vh - 344;
        ctxMenu.style.left    = x + 'px';
        ctxMenu.style.top     = y + 'px';
        ctxMenu.style.display = 'block';
        ctxMenu.setAttribute('aria-hidden', 'false');
    }

    function closeCtx() {
        ctxMenu.style.display = 'none';
        ctxMenu.setAttribute('aria-hidden', 'true');
    }

    table.addEventListener('contextmenu', function(e) {
        var cell = e.target.closest('td, th');
        if (cell && table.contains(cell)) openCtx(e, cell);
    });
    document.addEventListener('click',   function(e){ if (!ctxMenu.contains(e.target)) closeCtx(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeCtx(); });

    /* ═══════════════════════════════════════════════════════════
       6. Actions
    ═══════════════════════════════════════════════════════════ */
    ctxMenu.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        closeCtx();
        handleAction(btn.dataset.action);
    });

    function handleAction(action) {
        switch (action) {

            case 'freeze':
                if (ctxColIdx === null) return;
                if (frozenCols.has(ctxColIdx)) frozenCols.delete(ctxColIdx);
                else frozenCols.add(ctxColIdx);
                reapplyFreeze();
                break;

            case 'hide':
                if (ctxColIdx === null) return;
                hiddenCols.add(ctxColIdx);
                setColVisible(ctxColIdx, false);
                break;

            case 'filter':
                if (ctxColIdx === null) return;
                filterRow.style.display = '';
                var inp = filterInps[ctxColIdx];
                if (inp) { inp.scrollIntoView({block:'nearest'}); inp.focus(); }
                break;

            case 'sort-asc':  sortByCol(ctxColIdx, 'asc');  break;
            case 'sort-desc': sortByCol(ctxColIdx, 'desc'); break;

            case 'clear-filters':
                for (var k in colFilters) delete colFilters[k];
                Object.values(filterInps).forEach(function(i){ i.value=''; });
                globalQ = '';
                if (searchInp) searchInp.value = '';
                applyFilters();
                break;

            case 'show-cols':
                hiddenCols.forEach(function(ci){ setColVisible(ci, true); });
                hiddenCols.clear();
                break;

            case 'reset-sort':
                if (!tbody) break;
                // Remove sort indicators
                table.querySelectorAll('[data-sort-dir]').forEach(function(el){
                    el.removeAttribute('data-sort-dir');
                });
                origOrder.forEach(function(row){ tbody.appendChild(row); });
                break;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       7. Freeze columns
    ═══════════════════════════════════════════════════════════ */
    function reapplyFreeze() {
        var sorted = Array.from(frozenCols).sort(function(a,b){ return a-b; });
        // Compute cumulative left offset
        var leftOf = {};
        var accum  = 0;
        sorted.forEach(function(ci) {
            leftOf[ci] = accum;
            var cells  = colCells[ci] || [];
            var w      = cells[0] ? cells[0].getBoundingClientRect().width : 80;
            accum += w;
        });

        for (var idx in colCells) {
            var col  = parseInt(idx);
            var isFrz = frozenCols.has(col);
            (colCells[col] || []).forEach(function(cell) {
                if (isFrz) {
                    cell.style.position = 'sticky';
                    cell.style.left     = leftOf[col] + 'px';
                    cell.style.zIndex   = cell.closest('thead') ? '25' : '10';
                    cell.classList.add('saldos-col-frozen');
                } else {
                    cell.style.position = '';
                    cell.style.left     = '';
                    cell.style.zIndex   = '';
                    cell.classList.remove('saldos-col-frozen');
                }
            });
        }
    }

    /* ═══════════════════════════════════════════════════════════
       8. Hide / show column
    ═══════════════════════════════════════════════════════════ */
    function setColVisible(colIdx, visible) {
        (colCells[colIdx] || []).forEach(function(cell){
            cell.style.display = visible ? '' : 'none';
        });
        var inp = filterInps[colIdx];
        if (inp) inp.closest('th').style.display = visible ? '' : 'none';
    }

    /* ═══════════════════════════════════════════════════════════
       9. Sort by column
    ═══════════════════════════════════════════════════════════ */
    function sortByCol(colIdx, dir) {
        if (!tbody || colIdx === null) return;
        var rows = Array.from(tbody.querySelectorAll('tr.saldos-row'));
        rows.sort(function(a, b) {
            var ta = getCellText(a, colIdx);
            var tb = getCellText(b, colIdx);
            var na = parseFloat(ta.replace(/,/g,'')), nb = parseFloat(tb.replace(/,/g,''));
            var cmp = (!isNaN(na) && !isNaN(nb)) ? (na - nb) : ta.localeCompare(tb, 'es', {sensitivity:'base'});
            return dir === 'asc' ? cmp : -cmp;
        });
        rows.forEach(function(r){ tbody.appendChild(r); });

        // Update sort indicator on header
        table.querySelectorAll('[data-sort-dir]').forEach(function(el){ el.removeAttribute('data-sort-dir'); });
        var hdrCells = colCells[colIdx] || [];
        var hdrCell  = hdrCells.find(function(c){ return c.closest('thead') && c.tagName === 'TH'; });
        if (hdrCell) hdrCell.setAttribute('data-sort-dir', dir);
    }

})();
</script>
@endpush
@endsection
