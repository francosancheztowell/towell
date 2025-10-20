@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="text-3xl font-bold text-center mb-10">Agregar Registro de Velocidad</h1>
        <form action="{{ route('planeacion.velocidad.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="telar">Telar</label>
                <input type="text" class="form-control" id="telar" name="telar" required>
            </div>
            <div class="form-group">
                <label for="salon">Salón</label>
                <input type="text" class="form-control" id="salon" name="salon" required>
            </div>
            <div class="form-group">
                <label for="tipo_hilo">Tipo de Hilo</label>
                <input type="text" class="form-control" id="tipo_hilo" name="tipo_hilo" required>
            </div>
            <div class="form-group">
                <label for="velocidad">Velocidad</label>
                <input type="number" step="0.01" class="form-control" id="velocidad" name="velocidad" required>
            </div>
            <div class="form-group">
                <label for="densidad">Densidad</label>
                <input type="text" class="form-control" id="densidad" name="densidad" required>
            </div>
            <BR></BR><button type="submit" class="btn btn-primary">Guardar Velocidad</button>
        </form>
    </div>
@endsection
