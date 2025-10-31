@extends('layouts.app')

@section('page-title', 'Altas Especiales')

@section('content')
<div class="max-w-5xl mx-auto p-6">
	<h1 class="text-2xl font-bold text-gray-800 mb-2">Altas Especiales</h1>
	@if(isset($prop) && $prop !== null && $prop !== '')
		<p class="text-gray-600">Prop: <span class="font-semibold">{{ $prop }}</span></p>
	@else
		<p class="text-gray-600">Pantalla de altas especiales (placeholder).</p>
	@endif
</div>
@endsection

