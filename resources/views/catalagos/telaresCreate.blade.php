@extends('layouts.app')

@section('content')
<br><br>
    <div class="container">
        <h1 class="text-3xl font-bold text-center mb-10">Agregar Telar</h1>
        <form action="{{ route('planeacion.telares.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="salon">Salón</label>
                <input type="text" class="form-control" id="salon" name="salon" required>
            </div>
            <div class="form-group">
                <label for="telar">Telar</label>
                <input type="text" class="form-control" id="telar" name="telar" required>
            </div>
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="cuenta">Cuenta</label>
                <input type="text" class="form-control" id="cuenta" name="cuenta" required>
            </div>
            <div class="form-group">
                <label for="piel">Piel</label>
                <input type="text" class="form-control" id="piel" name="piel" required>
            </div>
            <div class="form-group">
                <label for="ancho">Ancho</label>
                <input type="number" step="0.01" class="form-control" id="ancho" name="ancho">
            </div><BR></BR>
            <button type="submit" class="btn btn-primary">Guardar Telar</button>

        </form>
    </div>
@endsection
