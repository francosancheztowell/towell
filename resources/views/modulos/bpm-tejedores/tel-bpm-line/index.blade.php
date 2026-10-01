@extends('layouts.app')

@section('page-title', 'BPM - Checklist')

@section('navbar-right')
<div class="flex items-center gap-2">
    @if($header->Status === 'Creado')
        <form method="POST" action="{{ route('tel-bpm.finish', $header->Folio) }}" id="form-finish" class="inline">
            @csrf @method('PATCH')
            <flux:button id="btn-finish" variant="primary" color="blue" icon="check" class="min-h-touch">Terminado</flux:button>
        </form>
    @elseif($header->Status === 'Terminado')
        @if(!empty($esSupervisor))
            <form method="POST" action="{{ route('tel-bpm.authorize', $header->Folio) }}" id="form-authorize" class="inline">
                @csrf @method('PATCH')
                <flux:button id="btn-authorize" variant="primary" color="green" icon="check-badge" class="min-h-touch">Autorizar</flux:button>
            </form>
            <form method="POST" action="{{ route('tel-bpm.reject', $header->Folio) }}" id="form-reject" class="inline">
                @csrf @method('PATCH')
                <flux:button id="btn-reject" variant="danger" icon="x-mark" class="min-h-touch">Rechazar</flux:button>
            </form>
        @else
            <flux:button disabled icon="check-badge" class="min-h-touch" title="Solo un supervisor puede autorizar">Autorizar</flux:button>
            <flux:button disabled icon="x-mark" class="min-h-touch" title="Solo un supervisor puede rechazar">Rechazar</flux:button>
        @endif
    @endif
</div>
@endsection

@section('content')
@php
    $configLinea = [
        'editable' => $header->Status === 'Creado',
        'turnoRecibe' => (string) $header->TurnoRecibe,
        'comentarios' => (string) ($comentarios ?? ''),
        'rutas' => [
            'toggle' => route('tel-bpm-line.toggle', $header->Folio),
            'comentarios' => route('tel-bpm-line.comentarios', $header->Folio),
            'indice' => route('tel-bpm.index'),
        ],
    ];
@endphp
<div id="tel-bpm-line-pagina" class="pantalla-completa gap-2 p-2" data-tel-bpm-line='@json($configLinea)'>
    {{-- Header --}}
    <div class="bg-white rounded-xl border p-4 shrink-0">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-md text-slate-500">Folio</div>
                <div class="text-xl font-semibold">{{ $header->Folio }}</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Fecha</div>
                <div class="font-medium">{{ optional($header->Fecha)->format('d/m/Y H:i') }}</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Recibe</div>
                <div class="font-medium">{{ $header->CveEmplRec }} — {{ $header->NombreEmplRec }} (Turno {{ $header->TurnoRecibe }})</div>
            </div>
            <div>
                <div class="text-md text-slate-500">Entrega</div>
                <div class="font-medium">{{ $header->CveEmplEnt }} — {{ $header->NombreEmplEnt }} (Turno {{ $header->TurnoEntrega }})</div>
            </div>
            <div>
                <flux:badge :color="['Autorizado' => 'green', 'Terminado' => 'amber'][$header->Status] ?? 'zinc'">{{ $header->Status }}</flux:badge>
            </div>
        </div>
    </div>

    {{-- Tabla de checklist --}}
    {{-- flux:table + .tabla-cebra (app.css). Primera columna (# y actividad) fija al desplazar en
         horizontal; los botones los repinta tel-bpm-line/index.ts. --}}
    @php
        $map = [];
        foreach ($lineas as $ln) {
            $map[$ln->Orden][$ln->NoTelarId] = $ln->Valor;
        }
        $clasesCelda = [
            'OK' => 'bg-green-100 border-green-400 text-green-700 hover:bg-green-200',
            'X' => 'bg-red-100 border-red-400 text-red-700 hover:bg-red-200',
            'M' => 'bg-amber-100 border-amber-400 text-amber-700 hover:bg-amber-200',
        ];
    @endphp
    <div class="tabla-pantalla rounded-lg bg-white shadow-sm overflow-hidden">
        <flux:table id="grid" class="tabla-cebra">
            <flux:table.columns sticky class="bg-white">
                <flux:table.column sticky class="bg-white min-w-[160px]">Actividad</flux:table.column>
                @foreach($telares as $t)
                    <flux:table.column align="center" class="min-w-[60px]" data-telar="{{ $t }}">{{ $t }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse($actividades as $a)
                    <flux:table.row>
                        <flux:table.cell sticky variant="strong" class="bg-inherit max-w-[160px] truncate" title="{{ $a['Actividad'] }}">
                            <span class="text-zinc-400 me-1">{{ $loop->iteration }}</span>{{ $a['Actividad'] }}
                        </flux:table.cell>
                        @foreach($telares as $t)
                            @php $val = $map[$a['Orden']][$t] ?? null; @endphp
                            <flux:table.cell align="center" class="py-1!">
                                <button
                                    id="cell-{{ $a['Orden'] }}-{{ $t }}"
                                    type="button"
                                    class="cell-btn inline-flex items-center justify-center size-touch rounded border-2 transition {{ $clasesCelda[$val] ?? 'bg-gray-50 border-gray-300 text-gray-400 hover:bg-gray-100' }}"
                                    data-orden="{{ $a['Orden'] }}"
                                    data-actividad="{{ $a['Actividad'] }}"
                                    data-telar="{{ $t }}"
                                    data-salon="{{ $salonPorTelar[$t] ?? '' }}"
                                    data-valor="{{ $val ?? '' }}"
                                    aria-label="{{ $a['Actividad'] }} · telar {{ $t }}"
                                    @disabled($header->Status !== 'Creado')>
                                    <span class="cell-icon font-bold text-base" aria-hidden="true">
                                        @if($val === 'M')<i class="fas fa-wrench"></i>@else{{ ['OK' => '✓', 'X' => '✗'][$val] ?? '○' }}@endif
                                    </span>
                                </button>
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="20" class="py-4 text-center">No hay actividades configuradas.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Sección de Comentarios --}}
    <div class="bg-white rounded-lg border p-2 shrink-0">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-base font-semibold text-gray-700">Comentarios</h3>
            <span id="comentarios-status" class="text-xs text-slate-400"></span>
        </div>
        <textarea
            id="comentarios-textarea"
            rows="3"
            maxlength="150"
            placeholder="Ingrese comentarios..."
            class="w-full rounded border px-2 py-1 text-sm resize-none focus:ring-1 focus:ring-blue-500 {{ $header->Status !== 'Creado' ? 'bg-gray-50' : '' }}"
            {{ $header->Status !== 'Creado' ? 'readonly' : '' }}
        >{{ $comentarios }}</textarea>
        <div class="text-right text-xs text-slate-500 mt-1">
            <span id="char-count">{{ strlen($comentarios ?? '') }}</span>/150
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/modulos/tejedores/tel-bpm-line/index.ts')
@endpush
@endsection
