<?php

namespace App\Services\Trazabilidad;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrazabilidadFlogsService
{
    private const TIPO_PEDIDO_LABELS = [
        '1' => 'Compra Especial',
        '2' => 'Stock',
    ];

    private const FLOG_IMAGEN_UNC_ROOT = '\\\\192.168.2.11\\ImagenFlog\\';

    /**
     * @return array{
     *     encontrado: bool,
     *     general: array<string, mixed>,
     *     etiquetas: array<int, array<string, mixed>>,
     *     empaques: array<int, array<string, mixed>>,
     *     lineas: array<int, array<string, mixed>>
     * }
     */
    public function build(?string $idFlog): array
    {
        $vacio = [
            'encontrado' => false,
            'general' => [],
            'etiquetas' => [],
            'empaques' => [],
            'lineas' => [],
        ];

        $idFlog = trim((string) $idFlog);
        if ($idFlog === '') {
            return $vacio;
        }

        try {
            $conn = DB::connection('sqlsrv_ti');

            $tabla = $conn->table('dbo.TwFlogsTable')
                ->where('IDFLOG', $idFlog)
                ->first();

            if (! $tabla) {
                return $vacio;
            }

            $cliente = $conn->table('dbo.TwFlogsCustomer')
                ->where('IDFLOG', $idFlog)
                ->first();

            $etiquetas = $conn->table('dbo.TwFlogsEtiquetasLinea')
                ->where('IDFLOG', $idFlog)
                ->orderBy('LINENUM')
                ->get();

            $empaques = $conn->table('dbo.TwBomEmpaque')
                ->where('IDFLOG', $idFlog)
                ->orderBy('RECID')
                ->get();

            $lineas = $conn->table('dbo.TwFlogsItemLine')
                ->where('IDFLOG', $idFlog)
                ->orderBy('LINENUM')
                ->get();
        } catch (\Throwable) {
            return $vacio;
        }

        $custAccount = $this->txt($cliente->CUSTACCOUNT ?? $tabla->CUSTACCOUNT ?? null);
        $custName = $this->txt($cliente->CUSTNAME ?? $tabla->CUSTNAME ?? null);
        $cAgente = $this->txt($cliente->CAGENTE ?? null);
        $nAgente = $this->txt($cliente->NAGENTE ?? null);

        $general = [
            'idFlog' => $this->txt($tabla->IDFLOG ?? $idFlog),
            'tipoPedido' => $this->resolverTipoPedido($tabla->TIPOPEDIDO ?? null, $idFlog),
            'nameProyect' => $this->txt($tabla->NAMEPROYECT ?? null),
            'empresa' => $this->txt($tabla->EMPRESA ?? null),
            'transDate' => $this->formatearFecha($tabla->TRANSDATE ?? null),
            'custAccount' => $custAccount,
            'custName' => $custName,
            'cliente' => trim($custAccount.' '.$custName),
            'numProveedor' => $this->txt($cliente->NUMPROVEEDOR ?? $tabla->NUMPROVEEDOR ?? null),
            'tipoClienteId' => $this->txt($cliente->TIPOCLIENTEID ?? null),
            'categoriaCalidad' => $this->txt($cliente->CATEGORIACALIDAD ?? null),
            'procesoCatMex' => $this->formatearSiNo($cliente->PROCESOCATMEX ?? null),
            'agente' => $this->unirConSeparador([$cAgente, $nAgente], ' — '),
            'pruebasLab' => $this->formatearPruebasLab($cliente->PRUEBASLAB ?? null, $cliente->PRUEBASLABTXT ?? null),
            'twSuavizante' => $this->resolverSuavizante($cliente->TWSUAVIZANTE ?? null, $cliente->SUAVISANTEEXPTXT ?? null),
            'avisoEspecialTxt' => $this->txt($cliente->AVISOESPECIALTXT ?? null),
            'infoImportante' => $this->txt($cliente->INFOIMPORTANTE ?? null),
        ];

        return [
            'encontrado' => true,
            'general' => $general,
            'etiquetas' => $etiquetas->map(fn ($row) => [
                'itemId' => $this->txt($row->ITEMID ?? null),
                'name' => $this->txt($row->NAME ?? null),
                'comentarios' => $this->txt($row->COMENTARIOS ?? null),
                'imagenPath' => $this->txt($row->IMAGENETIQUETA ?? null),
                'imagenUrl' => $this->resolverUrlImagen($row->IMAGENETIQUETA ?? null),
            ])->values()->all(),
            'empaques' => $empaques->map(fn ($row) => [
                'idEmpaque' => $this->txt($row->IDEMPAQUE ?? null),
                'otroEmpaque' => $this->txt($row->OTROEMPAQUE ?? null),
                'imagenPath' => $this->txt($row->FILEOTROEMPAQUE ?? null),
                'imagenUrl' => $this->resolverUrlImagen($row->FILEOTROEMPAQUE ?? null),
            ])->values()->all(),
            'lineas' => $lineas->map(fn ($row) => $this->mapearLineaFlog($row))->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapearLineaFlog(object $row): array
    {
        return [
            'lineNum' => $this->txt($row->LINENUM ?? null),
            'estadoLinea' => $this->txt($row->ESTADOLINEA ?? null),
            'fechaCancelacion' => $this->formatearFecha($row->FECHACANCELACION ?? null),
            'itemId' => $this->txt($row->ITEMID ?? null),
            'itemName' => $this->txt($row->ITEMNAME ?? null),
            'tipoHiloId' => $this->txt($row->TIPOHILOID ?? null),
            'inventSizeId' => $this->txt($row->INVENTSIZEID ?? null),
            'inventColorId' => $this->txt($row->INVENTCOLORID ?? null),
            'colorName' => $this->txt($row->COLORNAME ?? null),
            'rasuradoCrudo' => $this->txt($row->RASURADOCRUDO ?? null),
            'tipoDobladillo' => $this->txt($row->TIPODOBLADILLO ?? null),
            'tipoCostura' => $this->txt($row->TIPOCOSTURA ?? null),
            'tipoCorteBataId' => $this->txt($row->TIPOCORTEBATAID ?? null),
            'valorAgregado' => $this->txt($row->VALORAGREGADO ?? null),
            'puntadasBordado' => $this->txt($row->PUNTADASBORDADO ?? null),
            'infoAdicional' => $this->txt($row->INFOADICIONAL ?? null),
            'ancho' => $this->txt($row->ANCHO ?? null),
            'largo' => $this->txt($row->LARGO ?? null),
            'pesoAcabado' => $this->txt($row->PESOACABADO ?? null),
            'densidad' => $this->txt($row->DENSIDAD ?? null),
            'inventQty' => $this->txt($row->INVENTQTY ?? null),
            'salesUnit' => $this->txt($row->SALESUNIT ?? null),
            'purchBarCode' => $this->txt($row->PURCHBARCODE ?? null),
            'dun14' => $this->txt($row->DUN14 ?? null),
            'retailLink' => $this->txt($row->RETAILLINK ?? null),
            'nombreEtiqueta' => $this->txt($row->NOMBREETIQUETA ?? null),
            'createdDate' => $this->formatearFecha($row->CREATEDDATE ?? null),
            'simulacionVtas' => $this->txt($row->SIMULACIONVTAS ?? null),
            'simulacionDiseno' => $this->txt($row->SIMULACIONDISENO ?? null),
        ];
    }

    public function resolverUrlImagen(?string $ruta): ?string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }

        $normalizada = str_replace('/', '\\', $ruta);
        $base = rtrim(self::FLOG_IMAGEN_UNC_ROOT, '\\').'\\';

        if (stripos($normalizada, $base) === 0) {
            $archivo = basename($normalizada);

            return $archivo !== ''
              ? route('trazabilidad.flog-archivo', ['file' => $archivo])
              : null;
        }

        $archivo = basename($normalizada);

        return $archivo !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $archivo)
          ? route('trazabilidad.flog-archivo', ['file' => $archivo])
          : null;
    }

    public function rutaAbsolutaImagen(string $archivo): ?string
    {
        $archivo = basename($archivo);
        if ($archivo === '' || ! preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $archivo)) {
            return null;
        }

        $ruta = self::FLOG_IMAGEN_UNC_ROOT.$archivo;

        return is_file($ruta) ? $ruta : null;
    }

    private function resolverTipoPedido(mixed $tipoPedido, string $idFlog): string
    {
        $codigo = trim((string) $tipoPedido);
        if ($codigo !== '' && isset(self::TIPO_PEDIDO_LABELS[$codigo])) {
            return self::TIPO_PEDIDO_LABELS[$codigo];
        }

        if (strlen($idFlog) >= 2) {
            $pref = strtoupper(substr($idFlog, 0, 2));
            if ($pref === 'CE') {
                return 'Compra Especial';
            }
        }

        return $codigo !== '' ? $codigo : '—';
    }

    private function resolverSuavizante(mixed $codigo, mixed $texto): string
    {
        $texto = $this->txt($texto);
        if ($texto !== '') {
            return $texto;
        }

        $codigo = trim((string) $codigo);
        if ($codigo === '' || $codigo === '0' || $codigo === '3') {
            return 'Ninguno';
        }

        return $codigo;
    }

    private function formatearPruebasLab(mixed $flag, mixed $texto): string
    {
        $texto = $this->txt($texto);
        $flag = trim((string) $flag);

        if ($texto === '' && ($flag === '' || $flag === '0')) {
            return '—';
        }

        if ($texto !== '') {
            $tienePrueba = $flag !== '' && $flag !== '0';

            return ($tienePrueba ? 'Sí — ' : '').$texto;
        }

        return $this->formatearSiNo($flag);
    }

    private function formatearSiNo(mixed $valor): string
    {
        $v = trim((string) $valor);
        if ($v === '' || $v === '0') {
            return 'No';
        }

        return in_array(strtolower($v), ['1', 'si', 'sí', 'yes', 'true'], true) ? 'Sí' : $v;
    }

    private function formatearFecha(mixed $fecha): string
    {
        if (blank($fecha)) {
            return '—';
        }

        try {
            return Carbon::parse($fecha)->timezone('America/Mexico_City')->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private function unirConSeparador(array $partes, string $sep): string
    {
        $limpias = array_values(array_filter(array_map(fn ($p) => $this->txt($p), $partes)));

        return $limpias !== [] ? implode($sep, $limpias) : '—';
    }

    private function txt(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }
}
