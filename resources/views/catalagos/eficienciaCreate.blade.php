@extends('layouts.app')

@section('content')
<br><br>
    <div class="container">
        <h1 class="text-3xl font-bold text-center mb-10">Agregar Eficiencia</h1>
        <form action="{{ route('planeacion.eficiencia.store') }}" method="POST">
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
                <label for="eficiencia">Eficiencia (%)</label>
                <input type="number" class="form-control" id="eficiencia" name="eficiencia" required>
            </div>
            <div class="form-group">
                <label for="densidad">Densidad</label>
                <input type="text" class="form-control" id="densidad" name="densidad" required>
            </div><BR></BR>
            <button type="submit" class="btn btn-primary">Guardar Eficiencia</button>
        </form>
    </div>
@endsection
