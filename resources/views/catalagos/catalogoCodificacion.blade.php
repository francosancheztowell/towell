@extends('layouts.app')

@section('content')
<div class="container">
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Clave</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Nombre</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection
