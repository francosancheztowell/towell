@extends('layouts.app')

@section('page-title', 'Verificación ' . $folio)

@section('content')
<div class="flex h-full w-full flex-col overflow-hidden p-3 sm:p-4 md:p-6 lg:p-8">
    <div class="mx-auto flex w-full min-h-0 max-w-[110rem] flex-1 flex-col">
        <livewire:mecanicos.verifica-maquina.show :folio="$folio" />
    </div>
</div>
@endsection
