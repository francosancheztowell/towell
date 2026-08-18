<?php

namespace App\Http\Controllers\Planeacion\Auditoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Consulta de dbo.SYSAuditoria (solo ReqProgramaTejido).
 *
 * Las filas las escribe el trigger tr_ReqProgramaTejido_Audit; aquí solo se leen.
 * Restringido al área "Sistemas": es una bitácora técnica con IP y host de cada usuario.
 */
class AuditoriaProgramaTejidoController extends Controller
{
    private const AREA_PERMITIDA = 'Sistemas';

    public function index(Request $request)
    {
        $this->autorizar();

        $filtros = [
            'orden' => trim((string) $request->query('orden', '')),
            'usuario' => trim((string) $request->query('usuario', '')),
            'accion' => trim((string) $request->query('accion', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $query = DB::table('dbo.SYSAuditoria')
            ->where('Tabla', 'ReqProgramaTejido')
            ->orderByDesc('AuditId');

        // PK guarda 'Id=603 | Orden=TW-12345', así que el mismo campo sirve para buscar orden e Id.
        if ($filtros['orden'] !== '') {
            $query->whereRaw("PK LIKE ? ESCAPE '!'", ['%'.$this->escaparLike($filtros['orden']).'%']);
        }
        if ($filtros['usuario'] !== '') {
            $query->whereRaw("Usuario LIKE ? ESCAPE '!'", ['%'.$this->escaparLike($filtros['usuario']).'%']);
        }
        if ($filtros['accion'] !== '') {
            $query->where('Accion', $filtros['accion']);
        }
        if ($filtros['desde'] !== '') {
            $query->where('Fecha', '>=', $filtros['desde'].' 00:00:00');
        }
        if ($filtros['hasta'] !== '') {
            $query->where('Fecha', '<=', $filtros['hasta'].' 23:59:59');
        }

        return view('modulos.programa-tejido.auditoria.index', [
            'pageTitle' => 'Auditoría Programa de Tejido',
            'filtros' => $filtros,
            'registros' => $query->paginate(100)->withQueryString(),
        ]);
    }

    /**
     * Neutraliza los comodines de LIKE en lo que teclea el usuario.
     *
     * Sin esto, buscar la orden "A_123" también traía "AB123" y "AB%" traía todo lo que
     * empieza con AB. En SQL Server los especiales son % _ [ ] y el propio escape.
     *
     * El carácter de escape es '!' y no la barra invertida: así el literal SQL no necesita
     * a su vez escaparse y queda legible en el whereRaw.
     */
    private function escaparLike(string $valor): string
    {
        return str_replace(['!', '%', '_', '[', ']'], ['!!', '!%', '!_', '![', '!]'], $valor);
    }

    private function autorizar(): void
    {
        if ((Auth::user()->area ?? null) !== self::AREA_PERMITIDA) {
            throw new AccessDeniedHttpException('Solo el área de Sistemas puede consultar la auditoría.');
        }
    }
}
