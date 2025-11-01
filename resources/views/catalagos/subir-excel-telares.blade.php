@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-file-excel me-2"></i>
                        Subir Excel - Telares
                    </h4>
                </div>
                <div class="card-body">
                    <form id="excelForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label for="excel_file" class="form-label">
                                <i class="fas fa-upload me-1"></i>
                                Seleccionar archivo Excel
                            </label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                   accept=".xlsx,.xls" required>
                            <div class="form-text">
                                Formatos permitidos: .xlsx, .xls (Máximo 10MB)
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6>Instrucciones:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>El archivo debe contener las columnas: Salón, Telar, Nombre, Grupo</li>
                                <li><i class="fas fa-check text-success me-2"></i>La primera fila debe contener los encabezados</li>
                                <li><i class="fas fa-check text-success me-2"></i>Los datos se importarán a la tabla de telares</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-secondary me-md-2" onclick="history.back()">
                                <i class="fas fa-arrow-left me-1"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-upload me-1"></i>
                                Subir Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('excelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Deshabilitar botón y mostrar loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Subiendo...';
    
    fetch('/telares/excel/upload', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: '¡Excelito!',
                text: data.message,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '/telares';
            });
        } else {
            throw new Error(data.message || 'Error al subir el archivo');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: error.message || 'Error al subir el archivo',
            icon: 'error'
        });
    })
    .finally(() => {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>
@endsection





















