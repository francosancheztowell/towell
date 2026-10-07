<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Services\ModuloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de los usuarios vistos desde un módulo (Gestión de módulos).
 *
 * La pantalla de cada usuario edita sus permisos módulo por módulo; aquí es al revés:
 * un módulo y todos los usuarios, para poder quitar o dar permisos a varios a la vez.
 */
class ModuloPermisosController extends Controller
{
    public const CAMPOS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    public function __construct(private readonly ModuloService $moduloService) {}

    public function index(int $id): JsonResponse
    {
        SYSRoles::findOrFail($id);

        $permisos = SYSUsuariosRoles::where('idrol', $id)->get()->keyBy('idusuario');

        $usuarios = Usuario::orderBy('nombre')
            ->get(['idusuario', 'numero_empleado', 'nombre', 'area', 'puesto'])
            ->map(function (Usuario $u) use ($permisos) {
                $fila = $permisos->get($u->idusuario);
                $datos = [
                    'id' => (int) $u->idusuario,
                    'numero' => (string) $u->numero_empleado,
                    'nombre' => (string) $u->nombre,
                    'area' => (string) ($u->area ?? ''),
                    'puesto' => (string) ($u->puesto ?? ''),
                ];
                foreach (self::CAMPOS as $campo) {
                    $datos[$campo] = (bool) ($fila->{$campo} ?? false);
                }

                return $datos;
            });

        return response()->json(['usuarios' => $usuarios]);
    }

    /**
     * Aplica los mismos cambios a varios usuarios: {usuarios: [ids], permisos: {campo: bool}}.
     * Solo toca los campos enviados; un usuario sin fila para el módulo la recibe con el resto en 0.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        SYSRoles::findOrFail($id);

        $datos = $request->validate([
            'usuarios' => ['required', 'array', 'min:1', 'max:500'],
            'usuarios.*' => ['integer'],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => ['boolean'],
        ]);

        $cambios = array_intersect_key(
            array_map(fn ($v) => $v ? 1 : 0, $datos['permisos']),
            array_flip(self::CAMPOS)
        );
        if ($cambios === []) {
            return response()->json(['message' => 'No se indicó ningún permiso válido.'], 422);
        }

        // Solo usuarios que existen: una fila huérfana daría acceso a un id que nadie usa.
        $ids = Usuario::whereIn('idusuario', array_unique($datos['usuarios']))->pluck('idusuario')->all();

        DB::connection('sqlsrv')->transaction(function () use ($id, $ids, $cambios) {
            $existentes = SYSUsuariosRoles::where('idrol', $id)->whereIn('idusuario', $ids)->pluck('idusuario')->all();

            if ($existentes !== []) {
                SYSUsuariosRoles::where('idrol', $id)->whereIn('idusuario', $existentes)
                    ->update($cambios + ['assigned_at' => now()]);
            }

            $nuevos = array_diff($ids, $existentes);
            if ($nuevos !== []) {
                $base = array_fill_keys(self::CAMPOS, 0);
                SYSUsuariosRoles::insert(array_map(
                    fn ($idusuario) => ['idusuario' => $idusuario, 'idrol' => $id] + $cambios + $base + ['assigned_at' => now()],
                    array_values($nuevos)
                ));
            }
        });

        // El menú y los gates de cada usuario están en caché una hora.
        foreach ($ids as $idusuario) {
            $this->moduloService->limpiarCacheUsuario((int) $idusuario);
        }

        $n = count($ids);

        return response()->json([
            'message' => $n === 1 ? 'Permiso actualizado' : "Permisos actualizados para {$n} usuarios",
            'actualizados' => $n,
        ]);
    }
}
