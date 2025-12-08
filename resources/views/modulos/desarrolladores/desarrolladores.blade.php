@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('navbar-right')
    <x-navbar.button-create/>
@endsection

@section('content')
    <div class="flex w-screen h-full overflow-hidden flex-col px-4 py-4 md:px-6 lg:px-6 bg-none-500 ">
        <div class="bg-white flex flex-col flex-1 rounded-md overflow-hidden max-w-full p-6">
            
            <!-- Select de Telares -->
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Seleccionar Telar</label>
                               <select name="telar_operador" id="telarOperador" class=" w-60 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="" disabled selected>Selecciona un Telar</option>
                    @foreach ($telares ?? [] as $telar)
                        <option value="{{ $telar->NoTelarId }}">
                            {{ $telar->NoTelarId }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
@endsection
