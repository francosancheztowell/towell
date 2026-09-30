<?php

namespace App\Http\Controllers\Atadores\Catalogos\Comentarios;

use App\Http\Controllers\Atadores\Catalogos\CatalogosAtadoresVista;
use App\Http\Controllers\Atadores\Catalogos\RespondeCatalogo;
use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaComentariosModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de comentarios. Dos llaves de ruta (HANDOFF 16 C3):
 *  - {nota1}: la de siempre (texto libre; un "/" en la nota no llega a la ruta). Se conserva.
 *  - id/{id}: la que usa la pantalla cuando dbo.AtaComentarios ya tiene la columna Id.
 */
class AtaComentariosController extends Controller
{
    use RespondeCatalogo;

    private const NO_ENCONTRADO = 'Comentario no encontrado';

    /**
     * Mostrar la vista principal con todos los comentarios
     */
    public function index()
    {
        // Vista única de los tres catálogos de atadores (piloto DS-12).
        return view('modulos.catalogos-atadores.index', [
            'catalogo' => CatalogosAtadoresVista::comentarios(),
            'filas' => AtaComentariosModel::all(),
        ]);
    }

    /**
     * Guardar un nuevo comentario. Devuelve la fila guardada (con su Id si la columna existe).
     */
    public function store(Request $request)
    {
        $datos = $this->validar($request, null);

        return $this->escribir(function () use ($datos) {
            AtaComentariosModel::create($datos);

            return AtaComentariosModel::where('Nota1', $datos['Nota1'])->first();
        }, 'Comentario creado exitosamente', 'No se pudo crear el comentario');
    }

    public function show($nota1): JsonResponse
    {
        return $this->mostrar($this->porNota1($nota1));
    }

    public function update(Request $request, $nota1): JsonResponse
    {
        return $this->actualizar($request, $this->porNota1($nota1));
    }

    public function destroy($nota1): JsonResponse
    {
        return $this->eliminar($this->porNota1($nota1));
    }

    public function showPorId(int $id): JsonResponse
    {
        return $this->mostrar($this->porId($id));
    }

    public function updatePorId(Request $request, int $id): JsonResponse
    {
        return $this->actualizar($request, $this->porId($id));
    }

    public function destroyPorId(int $id): JsonResponse
    {
        return $this->eliminar($this->porId($id));
    }

    private function porNota1(mixed $nota1): ?AtaComentariosModel
    {
        return AtaComentariosModel::where('Nota1', (string) $nota1)->first();
    }

    private function porId(int $id): ?AtaComentariosModel
    {
        return AtaComentariosModel::tieneId() ? AtaComentariosModel::where('Id', $id)->first() : null;
    }

    private function mostrar(?AtaComentariosModel $comentario): JsonResponse
    {
        return $comentario
            ? response()->json(['success' => true, 'data' => $comentario])
            : $this->noEncontrado(self::NO_ENCONTRADO);
    }

    private function actualizar(Request $request, ?AtaComentariosModel $comentario): JsonResponse
    {
        if (! $comentario) {
            return $this->noEncontrado(self::NO_ENCONTRADO);
        }

        $datos = $this->validar($request, $comentario);

        return $this->escribir(function () use ($comentario, $datos) {
            $comentario->update($datos);

            return AtaComentariosModel::where('Nota1', $datos['Nota1'])->first();
        }, 'Comentario actualizado exitosamente', 'No se pudo actualizar el comentario');
    }

    private function eliminar(?AtaComentariosModel $comentario): JsonResponse
    {
        if (! $comentario) {
            return $this->noEncontrado(self::NO_ENCONTRADO);
        }

        return $this->escribir(function () use ($comentario) {
            $comentario->delete();
        }, 'Comentario eliminado exitosamente', 'No se pudo eliminar el comentario');
    }

    /**
     * Rule::unique()->ignore() en lugar de 'unique:…,'.$nota1: una coma en la nota rompía la regla.
     *
     * @return array{Nota1: string, Nota2?: string|null}
     */
    private function validar(Request $request, ?AtaComentariosModel $actual): array
    {
        $unica = Rule::unique(AtaComentariosModel::class, 'Nota1');
        if ($actual) {
            $unica->ignore($actual->Nota1, 'Nota1');
        }

        return $request->validate([
            'Nota1' => ['required', 'string', 'max:500', $unica],
            'Nota2' => 'nullable|string|max:500',
        ]);
    }
}
