{{-- ============================================================
     _scripts.blade.php
     Config del servidor para el JS de producción de urdido (19-01) y carga del bundle
     resources/js/modulos/urdido/produccion/index.ts (cálculos de Kg Neto, roturas,
     oficiales, julios, fechas, horas, Karl Mayer, casilla Fin y finalizar).
     Variables: $orden, $maxKgNeto, $isKarlMayer, $canEdit
     ============================================================ --}}
@php
    // isset($orden->Id) y no isset($orden) && $orden: un string no vacío pasaba el guard
    // viejo y reventaba en $orden->Id. isset() sobre una propiedad de algo que no es objeto
    // devuelve false sin lanzar error. Sin orden, el JS avisa "No hay orden seleccionada".
    $ordenIdProduccion = isset($orden->Id) ? (int) $orden->Id : null;
    $rutaProduccion = fn (string $nombre) => route('urdido.modulo.produccion.urdido.'.$nombre);
    $configProduccionUrdido = [
        'ordenId' => $ordenIdProduccion,
        'maxKgNeto' => $maxKgNeto ?? null,
        'esKarlMayer' => (bool) ($isKarlMayer ?? false),
        'puedeEditar' => (bool) ($canEdit ?? false),
        'rutas' => [
            'catalogoJulios' => $rutaProduccion('catalogos.julios'),
            'usuarios' => $rutaProduccion('usuarios.urdido'),
            'guardarOficial' => $rutaProduccion('guardar.oficial'),
            'eliminarOficial' => $rutaProduccion('eliminar.oficial'),
            'actualizarFecha' => $rutaProduccion('actualizar.fecha'),
            'actualizarJulioTara' => $rutaProduccion('actualizar.julio.tara'),
            'actualizarKgBruto' => $rutaProduccion('actualizar.kg.bruto'),
            'actualizarCampo' => $rutaProduccion('actualizar.campos.produccion'),
            'actualizarHoras' => $rutaProduccion('actualizar.horas'),
            'marcarListo' => $rutaProduccion('marcar.listo'),
            'finalizar' => $rutaProduccion('finalizar'),
            'pdf' => $ordenIdProduccion !== null
                ? route('urdido.modulo.produccion.urdido.pdf', ['orden_id' => $ordenIdProduccion, 'tipo' => 'urdido'])
                : null,
            'salida' => route('produccion.index'),
        ],
    ];
@endphp
<div id="produccion-urdido-config" hidden data-produccion-urdido='@json($configProduccionUrdido)'></div>

@push('scripts')
    @vite('resources/js/modulos/urdido/produccion/index.ts')
@endpush
