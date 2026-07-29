@extends('layouts.app')

@section('page-title', 'Estado de máquina')

@section('content')
<div class="w-full p-3 sm:p-4 md:p-6 lg:p-8">
    <div class="mx-auto max-w-[96rem] space-y-4">
        <livewire:mecanicos.verifica-maquina.index />
    </div>
</div>
@endsection
