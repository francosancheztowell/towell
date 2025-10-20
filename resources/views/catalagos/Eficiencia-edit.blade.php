@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4 text-3xl font-bold text-center">Editar Registro de Eficiencia</h1>

    <form action="{{ route('planeacion.eficiencia.update', $registro->id) }}" method="POST">
        @csrf
        @method('PUT') <!--Envía una petición PUT a eficiencia.update para actualizar el registro.-->

        <div class="form-group">
            <label for="telar">Telar</label>
            <input type="text" class="form-control" id="telar" name="telar" value="{{ $registro->telar }}" required>
        </div>

        <div class="form-group">
            <label for="salon">Salón</label>
            <input type="text" class="form-control" id="salon" name="salon" value="{{ $registro->salon }}" required>
        </div>

        <div class="form-group">
            <label for="tipo_hilo">Tipo de Hilo</label>
            <input type="text" class="form-control" id="tipo_hilo" name="tipo_hilo" value="{{ $registro->tipo_hilo }}" required>
        </div>

        <div class="form-group">
            <label for="eficiencia">Eficiencia</label>
            <input type="text" class="form-control" id="eficiencia" name="eficiencia" value="{{ $registro->eficiencia }}" required>
        </div>

        <div class="form-group">
            <label for="densidad">Densidad</label>
            <input type="text" class="form-control" id="densidad" name="densidad" value="{{ $registro->densidad }}" required>
        </div>

        <button type="submit" class="btn btn-success mt-3">Actualizar Registro</button>
        <a href="{{ route('planeacion.catalogos.eficiencia') }}" class="btn btn-secondary mt-3">Cancelar</a>
    </form>
</div>
@endsection
