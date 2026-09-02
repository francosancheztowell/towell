<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crudo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crudo\ListCrudoAuditsRequest;
use App\Http\Requests\Crudo\StoreCrudoAuditRequest;
use App\Http\Requests\Crudo\StoreCrudoAuditWithStopRequest;
use App\Http\Resources\Crudo\CrudoAuditHistoryResource;
use App\Models\Crudo\CrudoAuditoria;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoAlineacionNotifier;
use App\Services\Crudo\CrudoAuditService;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use App\Support\Crudo\CrudoProductionDay;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CrudoAuditController extends Controller
{
    public function __construct(
        private readonly CrudoAccess $access,
        private readonly CrudoAuditService $audits,
        private readonly ParoTelegramNotifier $notifier,
        private readonly CrudoAlineacionNotifier $alineacion,
    ) {}

    public function today(ListCrudoAuditsRequest $request): AnonymousResourceCollection
    {
        $this->access->authorize();
        $audits = $this->audits->todayForMachine((string) $request->validated('telar'));

        return CrudoAuditHistoryResource::collection($audits)->additional([
            'meta' => [
                'fecha' => CrudoProductionDay::forInstant(now(config('app.timezone'))),
                'total' => $audits->count(),
            ],
        ]);
    }

    public function store(StoreCrudoAuditRequest $request): JsonResponse
    {
        $this->access->authorizeRegister();
        $audit = $this->audits->storeAudit($request->validated(), $this->user($request));
        $this->avisarAlineacion($audit);

        return response()->json([
            'success' => true,
            'message' => 'Auditoría guardada correctamente.',
            'data' => [
                'auditoria_id' => $audit->Id,
                'paro_id' => null,
            ],
        ], 201);
    }

    public function storeWithStop(StoreCrudoAuditWithStopRequest $request): JsonResponse
    {
        $this->access->authorizeRegister();
        $result = $this->audits->storeAuditWithStop($request->validated(), $this->user($request));
        $stop = $result['stop'];
        $notifier = $this->notifier;
        $this->avisarAlineacion($result['audit']);

        defer(
            static fn () => $notifier->notifyCreated($stop),
            name: 'crudo-paro-telegram-'.$stop->Id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Auditoría y paro guardados. La notificación fue programada.',
            'data' => [
                'auditoria_id' => $result['audit']->Id,
                'paro_id' => $result['stop']->Id,
                'folio' => $result['stop']->Folio,
                'notificacion_programada' => true,
                'defecto_principal' => [
                    'id' => $result['principal_defect']->Id,
                    'falla' => $result['principal_defect']->Falla,
                    'descripcion' => $result['principal_defect']->Descripcion,
                    'piezas' => $result['pieces'],
                ],
            ],
        ], 201);
    }

    /** Aviso de alineación incorrecta: fuera del ciclo de respuesta, como el Telegram del paro. */
    private function avisarAlineacion(CrudoAuditoria $audit): void
    {
        if ($audit->AlineacionOrden !== false) {
            return;
        }

        $notifier = $this->alineacion;
        defer(
            static fn () => $notifier->notify($audit),
            name: 'crudo-alineacion-mail-'.$audit->getKey(),
        );
    }

    private function user(StoreCrudoAuditRequest $request): Authenticatable
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Usuario no autenticado.');

        return $user;
    }
}
