<?php

namespace App\Http\Controllers\Planeacion\Alineacion;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class AlineacionController extends Controller
{
    /**
     * Columnas en orden de visualización (keys usados en cada fila de datos).
     * Campos del modelo ReqProgramaTejido se mapean; los que no existen quedan en blanco.
     *
     * @var array<int, string>
     */
    private array $columnas = [
        'NoTelarId',
        'NoProduccion',
        'FechaCambio',
        'FechaCompromiso',
        'ItemId',
        'NombreProducto',
        'Tolerancia',
        'RazSN',
        'TipoRizo',
        'CalibreRizo',
        'Ancho',
        'LargoCrudo',
        'PesoCrudo',
        'Luchaje',
        'TipoPlano',
        'MedidaPlano',
        'NoTiras',
        'CuentaRizo',
        'CuentaPie',
        'CalibreTrama',
        'PasadasComb1',
        'PasadasComb2',
        'PasadasComb3',
        'PasadasComb4',
        'AnchoToalla',
        'PesoGRM2',
        'PesoMin',
        'PesoMax',
        'MuestraMin',
        'MuestraMax',
        'TotalPedido',
        'ProdAcumMesAnt',
        'ProdAcumMes',
        'Produccion',
        'SaldoPedido',
        'DiasEficiencia',
        'ProdKgDia',
        'DiasPorEjecutar',
        'Observaciones',
    ];

    /**
     * Vista principal de Alineación (programa de tejido en proceso).
     */
    public function index(): View
    {
        $registros = ReqProgramaTejido::query()
            ->enProceso(true)
            ->ordenado()
            ->get();

        $items = $registros->map(fn (ReqProgramaTejido $r) => $this->mapearProgramaTejidoAItem($r))->all();

        // La vista define su propia estructura de columnas; el controlador solo envía los datos
        return view('planeacion.alineacion.index', [
            'items' => $items,
        ]);
    }

    /**
     * Mapea un registro ReqProgramaTejido al array asociativo esperado por la vista.
     *
     * @return array<string, mixed>
     */
    private function mapearProgramaTejidoAItem(ReqProgramaTejido $r): array
    {
        $item = [];
        $mapeoEspecial = [
            'FechaCambio' => null,      // No existe en modelo, vacío
            'FechaCompromiso' => 'EntregaCte',
            'Tolerancia' => null,
            'RazSN' => null,
            'TipoRizo' => null,
            'TipoPlano' => null,
            'PesoMin' => 'PesoMuesMin',
            'PesoMax' => 'PesoMuesMax',
            'MuestraMin' => null,
            'MuestraMax' => null,
            'ProdAcumMesAnt' => null,
            'ProdAcumMes' => null,
            'DiasPorEjecutar' => null,
        ];

        foreach ($this->columnas as $key) {
            if (array_key_exists($key, $mapeoEspecial)) {
                $attr = $mapeoEspecial[$key];
                if ($attr === null) {
                    $item[$key] = '';
                    continue;
                }
                $value = $r->getAttribute($attr);
                $item[$key] = $value ? (
                    $attr === 'UpdatedAt' ? $this->formatDateAlineacion($value, 'd M Y H:i') : (
                        $attr === 'EntregaCte' ? $this->formatDateAlineacion($value, 'd M Y') : $value
                    )
                ) : '';
                continue;
            }
            $value = $r->getAttribute($key);
            if ($value === null) {
                $item[$key] = '';
                continue;
            }
            $item[$key] = $value;
        }
        return $item;
    }

    /**
     * Formatea fecha/datetime en español para la vista Alineación.
     *
     * @param mixed $value
     */
    private function formatDateAlineacion($value, string $format): string
    {
        try {
            return Carbon::parse($value)->locale('es')->translatedFormat($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
