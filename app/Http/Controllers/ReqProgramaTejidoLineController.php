<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReqProgramaTejidoLineController extends Controller
{
    /* -------------------- Reglas compartidas -------------------- */
    private function rules(bool $isUpdate = false): array
    {
        $base = [
            'Fecha'      => ['nullable','date'],
            'Cantidad'   => ['nullable','numeric'],
            'Kilos'      => ['nullable','numeric'],
            'Aplicacion' => ['nullable','numeric'],
            'Trama'      => ['nullable','numeric'],
            'Combina1'   => ['nullable','numeric'],
            'Combina2'   => ['nullable','numeric'],
            'Combina3'   => ['nullable','numeric'],
            'Combina4'   => ['nullable','numeric'],
            'Combina5'   => ['nullable','numeric'],
            'Pie'        => ['nullable','numeric'],
            'Rizo'       => ['nullable','numeric'],
            'MtsRizo'    => ['nullable','numeric'],
            'MtsPie'     => ['nullable','numeric'],
        ];

        $progRule = ['integer','exists:ReqProgramaTejido,Id'];
        $base['ProgramaId'] = $isUpdate ? array_merge(['sometimes'], $progRule) : array_merge(['required'], $progRule);

        return $base;
    }

    private function sanitize(array $data): array
    {
        foreach ([
            'Cantidad','Kilos','Aplicacion','Trama',
            'Combina1','Combina2','Combina3','Combina4','Combina5',
            'Pie','Rizo','MtsRizo','MtsPie'
        ] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }
        return $data;
    }

    /* -------------------- Index (con filtros) -------------------- */
    public function index(Request $request): JsonResponse
    {
        // Si se consulta por programa_id sin filtros de fecha, aumentar el límite de paginación
        $defaultPerPage = $request->filled('programa_id') && !$request->filled('fecha') && (!$request->filled('desde') || !$request->filled('hasta'))
            ? 1000
            : 25;

        $perPage = max(1, min((int)$request->query('per_page', $defaultPerPage), 5000));

        $q = ReqProgramaTejidoLine::query();

        if ($request->filled('programa_id')) {
            $q->programa((int)$request->query('programa_id'));
        }
        if ($request->filled('fecha')) {
            $q->onDate((string)$request->query('fecha'));
        }
        if ($request->filled('desde') && $request->filled('hasta')) {
            $q->between((string)$request->query('desde'), (string)$request->query('hasta'));
        }

        // Orden seguro (whitelist)
        $sort = (string)$request->query('sort', 'Fecha');
        $dir  = strtolower((string)$request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (!in_array($sort, ['Fecha','Id','ProgramaId'], true)) {
            $sort = 'Fecha';
        }
        $q->orderBy($sort, $dir)->orderBy('Id', 'asc');

        return response()->json([
            'success' => true,
            'data'    => $q->paginate($perPage),
        ]);
    }

    /* -------------------- Store -------------------- */
    public function store(Request $request): JsonResponse
    {
        $data = $this->sanitize($request->validate($this->rules(false)));
        $created = ReqProgramaTejidoLine::create($data);

        return response()->json([
            'success' => true,
            'data'    => $created,
        ], 201);
    }

    /* -------------------- Show -------------------- */
    public function show(int $id): JsonResponse
    {
        $row = ReqProgramaTejidoLine::findOrFail($id);
        return response()->json(['success' => true, 'data' => $row]);
    }

    /* -------------------- Update -------------------- */
    public function update(Request $request, int $id): JsonResponse
    {
        $row  = ReqProgramaTejidoLine::findOrFail($id);
        $data = $this->sanitize($request->validate($this->rules(true)));

        $row->fill($data)->save();

        return response()->json(['success' => true, 'data' => $row]);
    }

    /* -------------------- Destroy -------------------- */
    public function destroy(int $id): JsonResponse
    {
        $row = ReqProgramaTejidoLine::findOrFail($id);
        $row->delete();

        return response()->json(['success' => true]);
    }
}
