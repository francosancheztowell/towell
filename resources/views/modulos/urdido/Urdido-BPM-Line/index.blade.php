@extends('layouts.app')

@section('page-title', 'BPM - Checklist')

@section('navbar-right')
<div class="flex items-center gap-3">
    @if($header->Status === 'Creado')
        <form method="POST" action="{{ route('tel-bpm.finish', $header->Folio) }}" id="form-finish" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-sky-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-sky-600 transition-all duration-200 flex items-center gap-2 group" id="btn-finish">
                <i class="fa-solid fa-check w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Terminado
            </button>
        </form>
    @elseif($header->Status === 'Terminado')
        <form method="POST" action="{{ route('tel-bpm.authorize', $header->Folio) }}" id="form-authorize" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-green-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-green-600 transition-all duration-200 flex items-center gap-2 group" id="btn-authorize">
                <i class="fa-solid fa-thumbs-up w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Autorizar
            </button>
        </form>
        <form method="POST" action="{{ route('tel-bpm.reject', $header->Folio) }}" id="form-reject" class="inline">
            @csrf @method('PATCH')
            <button type="button" class="px-6 py-2.5 bg-amber-500 text-white font-semibold rounded-xl shadow-md hover:shadow-lg hover:bg-amber-600 transition-all duration-200 flex items-center gap-2 group" id="btn-reject">
                <i class="fa-solid fa-times w-4 h-4 group-hover:scale-110 transition-transform duration-200"></i>
                Rechazar
            </button>
        </form>
    @endif
</div>