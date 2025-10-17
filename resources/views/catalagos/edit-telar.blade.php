@extends('layouts.app')

@section('title', 'Editar Telar')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Editar Telar: {{ $telar->Nombre }}
                    </h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Panel de información actual -->
                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Información Actual
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>ID:</strong> {{ $telar->id }}
                                </div>
                                <div class="col-md-3">
                                    <strong>Salón:</strong> {{ $telar->SalonTejidoId }}
                                </div>
                                <div class="col-md-3">
                                    <strong>Telar:</strong> {{ $telar->NoTelarId }}
                                </div>
                                <div class="col-md-3">
                                    <strong>Grupo:</strong> {{ $telar->Grupo ?: 'No especificado' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('telares.update', $telar) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="SalonTejidoId" class="form-label">
                                        <i class="fas fa-building me-1"></i>Salón de Tejido *
                                    </label>
                                    <input type="text" 
                                           class="form-control @error('SalonTejidoId') is-invalid @enderror" 
                                           id="SalonTejidoId" 
                                           name="SalonTejidoId" 
                                           value="{{ old('SalonTejidoId', $telar->SalonTejidoId) }}"
                                           placeholder="Ej: Jacquard, Smith"
                                           maxlength="20"
                                           required>
                                    @error('SalonTejidoId')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">El nombre del salón donde está ubicado el telar</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="NoTelarId" class="form-label">
                                        <i class="fas fa-cogs me-1"></i>Número de Telar *
                                    </label>
                                    <input type="text" 
                                           class="form-control @error('NoTelarId') is-invalid @enderror" 
                                           id="NoTelarId" 
                                           name="NoTelarId" 
                                           value="{{ old('NoTelarId', $telar->NoTelarId) }}"
                                           placeholder="Ej: 201, 202, 300"
                                           maxlength="10"
                                           required>
                                    @error('NoTelarId')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">El número identificador del telar</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="Grupo" class="form-label">
                                        <i class="fas fa-layer-group me-1"></i>Grupo
                                    </label>
                                    <input type="text" 
                                           class="form-control @error('Grupo') is-invalid @enderror" 
                                           id="Grupo" 
                                           name="Grupo" 
                                           value="{{ old('Grupo', $telar->Grupo) }}"
                                           placeholder="Ej: Jacquard Smith, Itema Nuevo"
                                           maxlength="30">
                                    @error('Grupo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Grupo o categoría del telar (opcional)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel de vista previa -->
                        <div class="card bg-light">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-eye me-2"></i>Vista Previa del Nombre
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Salón:</strong> <span id="preview-salon">-</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Telar:</strong> <span id="preview-telar">-</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <strong class="text-primary fs-5">Nombre Generado:</strong>
                                    <div class="fs-4 text-primary" id="preview-nombre">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('telares.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Telares
                            </a>
                            
                            <button type="submit" class="btn btn-warning" onclick="confirmarActualizacion()">
                                <i class="fas fa-save me-2"></i>Actualizar Telar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para generar nombre automáticamente
function generarNombre(salon, telar) {
    if (!salon || !telar) return '-';
    
    const salonUpper = salon.toUpperCase();
    let prefijo;
    
    if (salonUpper.includes('JACQUARD')) {
        prefijo = 'JAC';
    } else if (salonUpper.includes('SMITH')) {
        prefijo = 'Smith';
    } else {
        prefijo = salon.substring(0, 3).toUpperCase();
    }
    
    return prefijo + ' ' + telar;
}

// Actualizar vista previa en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const salonInput = document.getElementById('SalonTejidoId');
    const telarInput = document.getElementById('NoTelarId');
    const previewSalon = document.getElementById('preview-salon');
    const previewTelar = document.getElementById('preview-telar');
    const previewNombre = document.getElementById('preview-nombre');
    
    function updatePreview() {
        const salon = salonInput.value.trim();
        const telar = telarInput.value.trim();
        
        previewSalon.textContent = salon || '-';
        previewTelar.textContent = telar || '-';
        previewNombre.textContent = generarNombre(salon, telar);
    }
    
    salonInput.addEventListener('input', updatePreview);
    telarInput.addEventListener('input', updatePreview);
    
    // Actualizar inicial
    updatePreview();
});

// Confirmación con SweetAlert
function confirmarActualizacion() {
    event.preventDefault();
    
    const salon = document.getElementById('SalonTejidoId').value;
    const telar = document.getElementById('NoTelarId').value;
    const grupo = document.getElementById('Grupo').value;
    const nombre = generarNombre(salon, telar);
    
    Swal.fire({
        title: '¿Actualizar Telar?',
        html: `
            <div class="text-start">
                <p><strong>Salón:</strong> ${salon}</p>
                <p><strong>Telar:</strong> ${telar}</p>
                <p><strong>Nombre:</strong> <span class="text-primary">${nombre}</span></p>
                <p><strong>Grupo:</strong> ${grupo || 'No especificado'}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-save me-2"></i>Actualizar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Actualizando...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            setTimeout(() => {
                document.querySelector('form').submit();
            }, 500);
        }
    });
}
</script>
@endsection

