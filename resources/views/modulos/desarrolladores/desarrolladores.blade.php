@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('navbar-right')
    <x-navbar.button-create/>
@endsection

@section('content')
    <div class="flex w-screen h-full overflow-hidden flex-col px-4 py-4 md:px-6 lg:px-6 bg-amber-500 ">
        <div class="bg-white flex flex-col flex-1 rounded-md overflow-hidden max-w-full">
            
        </div>
    </div>
@endsection