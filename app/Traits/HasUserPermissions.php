<?php

namespace App\Traits;

use App\Models\Sistema\SYSUsuariosRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasUserPermissions
 *
 * Proporciona métodos reutilizables para verificar permisos de usuarios
 * basados en roles y módulos del sistema.
 *
 * Uso:
 * use App\Traits\HasUserPermissions;
 *
 * class YourController extends Controller {
 *     use HasUserPermissions;
 *
 *     public function index() {
 *         $canCreate = $this->userCan('crear', 'Telares');
 *         $canEdit = $this->userCan('modificar', 'Telares');
 *     }
 * }
 */
trait HasUserPermissions
{
    /**
     * Cache de permisos para evitar múltiples consultas
     */
    protected static $permissionsCache = [];

    /**
     * Verificar si el usuario tiene un permiso específico en un módulo
     *
     * @param string $action - Acción: 'crear', 'modificar', 'eliminar', 'acceso', 'registrar'
     * @param string|int $module - Nombre del módulo o ID del rol
     * @param int|null $userId - ID del usuario (null = usuario actual)
     * @return bool
     */
    public function userCan(string $action, $module, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            return false;
        }

        // Delegar al helper global memoizado (app/Helpers/permission-helpers.php)
        $permission = \userPermissions($module, $userId);

        return $permission !== null && isset($permission->$action) && $permission->$action == 1;
    }

    /**
     * Obtener todos los permisos del usuario para un módulo
     *
     * @param string|int $module - Nombre del módulo o ID del rol
     * @param int|null $userId - ID del usuario (null = usuario actual)
     * @return object|null
     */
    public function getUserPermissions($module, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            return null;
        }

        return \userPermissions($module, $userId);
    }

    /**
     * Verificar múltiples permisos (lógica AND)
     *
     * @param array $permissions - Array de ['action' => 'module']
     * @param int|null $userId - ID del usuario
     * @return bool
     */
    public function userCanAll(array $permissions, ?int $userId = null): bool
    {
        foreach ($permissions as $action => $module) {
            if (!$this->userCan($action, $module, $userId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verificar al menos uno de los permisos (lógica OR)
     *
     * @param array $permissions - Array de ['action' => 'module']
     * @param int|null $userId - ID del usuario
     * @return bool
     */
    public function userCanAny(array $permissions, ?int $userId = null): bool
    {
        foreach ($permissions as $action => $module) {
            if ($this->userCan($action, $module, $userId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Limpiar cache de permisos (útil después de actualizar permisos)
     */
    public function clearPermissionsCache(): void
    {
        self::$permissionsCache = [];
    }
}
