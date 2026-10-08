{{--
    Vista host del panel /admin. Cada ruta de routes/modules/admin.php pasa el componente
    Livewire a montar y el título; /admin/errores/{id} además el id.
--}}
@extends('layouts.admin')

@section('title', 'Monitoreo · '.$titulo)

@section('encabezado', $titulo)

@if (isset($id))
    @section('migas')
        <a href="{{ route('admin.errores') }}" class="hover:text-(--adm-ink)">Errores</a>
        <flux:icon.chevron-right variant="micro" class="size-3" />
        <span class="adm-mono">#{{ $id }}</span>
    @endsection
@endif

@section('content')
    @livewire($componente, isset($id) ? ['errorId' => (int) $id] : [])
@endsection
