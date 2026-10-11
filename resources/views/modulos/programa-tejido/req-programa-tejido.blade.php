@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $pageTitle ?? 'Programa de Tejido')

@section('content')
<div class="w-full pt-page">
  <div class="bg-white overflow-hidden w-full pt-page-card">
    {{-- Filtros activos (barra-filtros.ts): oculta mientras no haya filtros. --}}
    <div id="ptFiltrosBarra" hidden class="flex flex-none items-center gap-3 border-b border-slate-200 bg-white px-3"></div>

    
          @include('modulos.programa-tejido.partials.grilla')
  </div>
</div>


@include('modulos.programa-tejido.partials.complementos')

@endsection

@push('scripts')
  {!! view('modulos.programa-tejido.scripts.main', [
    'columns' => $columns ?? [],
    'hiddenFields' => $hiddenFields ?? [],
    'basePath' => $basePath ?? null,
    'apiPath' => $apiPath ?? null,
    'linePath' => $linePath ?? null,
    'capacidades' => $capacidades ?? [],
    'isMuestras' => $isMuestras ?? false,
  ])->render() !!}
@endpush
