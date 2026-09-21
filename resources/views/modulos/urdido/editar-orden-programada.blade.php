@extends('layouts.app')

@section('page-title', 'Editar Orden Urdido')

@section('navbar-right')
    @php
        $statusClass = match($orden->Status ?? '') {
            'Finalizado' => 'bg-green-100 text-green-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Programado' => 'bg-blue-100 text-blue-800',
            'Parcial' => 'bg-amber-100 text-amber-800',
            'Cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    @endphp
    <div class="flex items-center gap-2">
        <span class="px-3 py-2 text-md font-bold rounded-full {{ $statusClass }}">{{ $orden->Status ?? '-' }}</span>
        @if(($axUrdido ?? 0) === 1)
            <span class="px-2 py-1 text-sm font-bold rounded-full bg-red-600 text-white">AX Urdido</span>
        @endif
    </div>
@endsection

@section('content')
    <livewire:urd-eng.edicion-orden module="urdido" :orden-id="$orden->Id" :from-reimpresion="$fromReimpresion" />
@endsection

@push('scripts')
    @vite('resources/js/urd-eng/edicion-orden.ts')
@endpush
