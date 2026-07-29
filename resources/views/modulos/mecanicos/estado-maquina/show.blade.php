@extends('layouts.app')

@section('page-title', 'Verificación ' . $folio)

@section('content')
<div class="w-full p-3 sm:p-4 md:p-6 lg:p-8">
    <div class="mx-auto max-w-[110rem] space-y-4">
        <livewire:mecanicos.verifica-maquina.show :folio="$folio" />
    </div>
</div>
@endsection
