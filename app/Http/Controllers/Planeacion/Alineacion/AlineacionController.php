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
     *
     * @var array<int, string>
     */
    private array $columnas = [
        'Id',
        'NoTelarId',
        'SalonTejidoId',
        'Posicion',
        'NoProduccion',
        'NombreProducto',
        'TotalPedido',
        'SaldoPedido',
        'Produccion',
        'FechaInicio',
        'FechaFinal',
        'ProgramarProd',
        'Programado',
        'TamanoClave',
        'CustName',
        'Peine',
        'AnchoToalla',
        'PesoGRM2',
        'peso_mues_max',
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
        foreach ($this->columnas as $key) {
            if ($key === 'peso_mues_max') {
                $item[$key] = $r->getAttribute('PesoMuesMax') ?? '';
                continue;
            }
            $value = $r->getAttribute($key);
            if ($value === null) {
                $item[$key] = '';
                continue;
            }
            if (in_array($key, ['FechaInicio', 'FechaFinal'], true) && $value) {
                $item[$key] = $this->formatDateAlineacion($value, 'd M Y H:i');
                continue;
            }
            if (in_array($key, ['ProgramarProd', 'Programado'], true) && $value) {
                $item[$key] = $this->formatDateAlineacion($value, 'd M Y');
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
