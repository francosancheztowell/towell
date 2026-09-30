{{-- Programa Tejido / Muestras · shell Livewire v2 (PT 03), solo con ShellV2 activo.
     Mismo layout, modales, menús y bundle que req-programa-tejido.blade.php; los assets v2
     se cargan SOLO desde aquí (con el canary apagado la página legacy no los referencia). --}}
@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $pageTitle ?? 'Programa de Tejido')

@section('content')
<div class="w-full pt-page">
  <livewire:planeacion.programa-tejido-board :superficie="$superficie->value" :ocultas="$hiddenFields" />
</div>

@include('modulos.programa-tejido.partials.complementos')
@endsection

@push('scripts')
  {!! view('modulos.programa-tejido.scripts.main', [
    'columns' => $columns,
    'hiddenFields' => $hiddenFields,
    'basePath' => $basePath,
    'apiPath' => $apiPath,
    'linePath' => $linePath,
    'capacidades' => $capacidades,
    'isMuestras' => $isMuestras,
  ])->render() !!}
  @vite('resources/js/modulos/programa-tejido-v2/index.ts')
@endpush
