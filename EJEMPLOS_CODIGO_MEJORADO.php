<?php

/**
 * EJEMPLOS DE CÓDIGO MEJORADO
 * 
 * Este archivo contiene ejemplos prácticos de cómo mejorar el código existente.
 * Los ejemplos están organizados por archivo y línea de código original.
 */

// ============================================================================
// 1. USUARIOCONTROLLER.PHP - Optimización de consultas y caché
// ============================================================================

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\SYSRoles;
use App\Models\SYSUsuariosRoles;
use App\Services\ModuloService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UsuarioControllerImproved extends Controller
{
    /**
     * MEJORA 1: Método index() optimizado con caché y eager loading
     * 
     * Archivo original: app/Http/Controllers/UsuarioController.php
     * Línea: ~689
     */
    public function index(ModuloService $moduloService)
    {
        $usuarioActual = Auth::user();
        
        // Usar servicio en lugar de lógica directamente en el controlador
        $modulos = $moduloService->getModulosPrincipalesPorUsuario($usuarioActual->idusuario);
        
        return view('produccionProceso', [
            'modulos' => $modulos,
            'tieneConfiguracion' => $modulos->contains('nombre', 'Configuración'),
            'pageTitle' => 'Producción en Proceso'
        ]);
    }
    
    /**
     * MEJORA 2: Método select() con paginación manual
     * 
     * Archivo original: app/Http/Controllers/UsuarioController.php
     * Línea: ~81
     */
    public function select(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(10, (int) $request->input('per_page', 50))); // Entre 10 y 50
        $offset = ($page - 1) * $perPage;
        
        $query = Usuario::query()
            ->select('idusuario', 'numero_empleado', 'nombre', 'area', 'turno', 'telefono', 'foto', 'puesto', 'correo', 'enviarMensaje')
            ->orderBy('nombre');
        
        // Contar total antes de paginar
        $total = (clone $query)->count();
        
        // Obtener registros paginados
        $usuarios = $query->offset($offset)->limit($perPage)->get();
        
        return view('modulos.usuarios.select', [
            'usuarios' => $usuarios,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ]);
    }
    
    /**
     * MEJORA 3: Método showSubModulos() optimizado
     * 
     * Archivo original: app/Http/Controllers/UsuarioController.php
     * Línea: ~317
     */
    public function showSubModulos($moduloPrincipal)
    {
        $slugToNombre = [
            'planeacion' => 'Planeación',
            'tejido' => 'Tejido',
            'urdido' => 'Urdido',
            'engomado' => 'Engomado',
            'atadores' => 'Atadores',
            'tejedores' => 'Tejedores',
            'mantenimiento' => 'Mantenimiento',
            'programa-urd-eng' => 'Programa Urd / Eng',
            'configuracion' => 'Configuración',
        ];
        
        $buscado = $slugToNombre[$moduloPrincipal] ?? $moduloPrincipal;
        
        // Obtener módulo padre con caché
        $moduloPadre = Cache::remember("modulo_padre_{$buscado}", 3600, function() use ($buscado) {
            return SYSRoles::where('modulo', $buscado)
                ->where('Nivel', 1)
                ->whereNull('Dependencia')
                ->first();
        });
        
        if (!$moduloPadre) {
            return redirect('/produccionProceso')->with('error', 'Módulo no encontrado');
        }
        
        $usuarioActual = Auth::user();
        $idusuario = $usuarioActual->idusuario;
        
        // Cachear submódulos con eager loading
        $cacheKey = "submodulos_{$moduloPrincipal}_user_{$idusuario}";
        $subModulos = Cache::remember($cacheKey, 3600, function() use ($idusuario, $moduloPadre) {
            // Una sola consulta con join en lugar de múltiples consultas
            return SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where('r.Nivel', 2)
                ->where('r.Dependencia', $moduloPadre->orden)
                ->select(
                    'r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                    'SYSUsuariosRoles.acceso as usuario_acceso',
                    'SYSUsuariosRoles.crear as usuario_crear',
                    'SYSUsuariosRoles.modificar as usuario_modificar',
                    'SYSUsuariosRoles.eliminar as usuario_eliminar',
                    'SYSUsuariosRoles.registrar as usuario_registrar'
                )
                ->orderBy('r.orden')
                ->get()
                ->map(function($moduloDB) {
                    return [
                        'nombre' => $moduloDB->modulo,
                        'imagen' => $moduloDB->imagen ?? 'default.png',
                        'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden, $moduloDB->Dependencia),
                        'ruta_tipo' => 'url',
                        'orden' => $moduloDB->orden,
                        'nivel' => $moduloDB->Nivel,
                        'dependencia' => $moduloDB->Dependencia,
                        'acceso' => $moduloDB->usuario_acceso,
                        'crear' => $moduloDB->usuario_crear,
                        'modificar' => $moduloDB->usuario_modificar,
                        'eliminar' => $moduloDB->usuario_eliminar,
                        'registrar' => $moduloDB->usuario_registrar
                    ];
                })
                ->toArray();
        });
        
        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadre->modulo,
            'subModulos' => $subModulos,
            'rango' => ['inicio' => $moduloPadre->orden, 'nombre' => $moduloPadre->modulo]
        ]);
    }
}

// ============================================================================
// 2. SERVICIO: MODULOSERVICE.PHP - Nueva clase de servicio
// ============================================================================

namespace App\Services;

use App\Models\SYSRoles;
use App\Models\SYSUsuariosRoles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class ModuloService
{
    /**
     * Obtener módulos principales para un usuario con caché
     */
    public function getModulosPrincipalesPorUsuario(int $idusuario): Collection
    {
        $cacheKey = "modulos_principales_user_{$idusuario}";
        
        return Cache::remember($cacheKey, 3600, function() use ($idusuario) {
            // Una sola consulta con join optimizado
            return SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where('r.Nivel', 1)
                ->whereNull('r.Dependencia')
                ->select(
                    'r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                    'SYSUsuariosRoles.acceso as usuario_acceso',
                    'SYSUsuariosRoles.crear as usuario_crear',
                    'SYSUsuariosRoles.modificar as usuario_modificar',
                    'SYSUsuariosRoles.eliminar as usuario_eliminar',
                    'SYSUsuariosRoles.registrar as usuario_registrar'
                )
                ->orderBy('r.orden')
                ->get()
                ->map(function($moduloDB) {
                    return [
                        'nombre' => $moduloDB->modulo,
                        'imagen' => $moduloDB->imagen ?? 'default.png',
                        'ruta' => $this->generarRutaModulo($moduloDB->modulo, $moduloDB->orden),
                        'ruta_tipo' => 'url',
                        'orden' => $moduloDB->orden,
                        'nivel' => $moduloDB->Nivel,
                        'dependencia' => $moduloDB->Dependencia,
                        'acceso' => $moduloDB->usuario_acceso,
                        'crear' => $moduloDB->usuario_crear,
                        'modificar' => $moduloDB->usuario_modificar,
                        'eliminar' => $moduloDB->usuario_eliminar,
                        'registrar' => $moduloDB->usuario_registrar
                    ];
                });
        });
    }
    
    /**
     * Limpiar caché de módulos para un usuario
     */
    public function clearUserModuleCache(int $idusuario): void
    {
        Cache::forget("modulos_principales_user_{$idusuario}");
        // Limpiar patrones de submódulos
        $patterns = [
            "submodulos_planeacion_user_{$idusuario}",
            "submodulos_tejido_user_{$idusuario}",
            "submodulos_urdido_user_{$idusuario}",
            "submodulos_engomado_user_{$idusuario}",
            "submodulos_atadores_user_{$idusuario}",
            "submodulos_tejedores_user_{$idusuario}",
            "submodulos_mantenimiento_user_{$idusuario}",
            "submodulos_configuracion_user_{$idusuario}",
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
    
    private function generarRutaModulo(string $nombre, string $orden): string
    {
        // Lógica para generar rutas
        $rutas = [
            'Planeación' => '/planeacion',
            'Tejido' => '/tejido',
            'Urdido' => '/urdido',
            'Engomado' => '/engomado',
            'Atadores' => '/atadores',
            'Tejedores' => '/tejedores',
            'Mantenimiento' => '/mantenimiento',
            'Programa Urd / Eng' => '/programa-urd-eng',
            'Configuración' => '/configuracion',
        ];
        
        return $rutas[$nombre] ?? '/produccionProceso';
    }
}

// ============================================================================
// 3. PROGRAMATEJIDOCONTROLLER.PHP - Optimización de loops y consultas
// ============================================================================

namespace App\Http\Controllers;

use App\Models\ReqProgramaTejido;
use App\Repositories\ReqProgramaTejidoRepository;
use App\Actions\ProgramaTejido\CrearProgramaTejidoAction;
use App\Http\Requests\StoreProgramaTejidoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramaTejidoControllerImproved extends Controller
{
    public function __construct(
        private ReqProgramaTejidoRepository $repository,
        private CrearProgramaTejidoAction $crearAction
    ) {}
    
    /**
     * MEJORA 1: Método index() con select optimizado y caché
     * 
     * Archivo original: app/Http/Controllers/ProgramaTejidoController.php
     * Línea: ~17
     */
    public function index()
    {
        try {
            // Usar repository en lugar de consulta directa
            $registros = $this->repository->getRegistrosOrdenados([
                'Id', 'EnProceso', 'CuentaRizo', 'CalibreRizo2', 'SalonTejidoId', 
                'NoTelarId', 'Ultimo', 'CambioHilo', 'Maquina', 'Ancho',
                // ... otros campos necesarios
            ]);
            
            return view('modulos.req-programa-tejido', compact('registros'));
        } catch (\Throwable $e) {
            Log::error('Error al cargar programa de tejido', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return view('modulos.req-programa-tejido', [
                'registros' => collect(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * MEJORA 2: Método store() optimizado con acción y batch updates
     * 
     * Archivo original: app/Http/Controllers/ProgramaTejidoController.php
     * Línea: ~168
     */
    public function store(StoreProgramaTejidoRequest $request)
    {
        try {
            // Usar acción en lugar de lógica directa en el controlador
            $creados = $this->crearAction->execute($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Programa de tejido creado correctamente',
                'data' => $creados,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al crear programa de tejido', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear programa de tejido: ' . $e->getMessage(),
            ], 500);
        }
    }
}

// ============================================================================
// 4. REPOSITORIO: REQPROGRAMATEJIDOREPOSITORY.PHP - Nueva clase repositorio
// ============================================================================

namespace App\Repositories;

use App\Models\ReqProgramaTejido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReqProgramaTejidoRepository
{
    /**
     * Obtener registros ordenados con select específico
     */
    public function getRegistrosOrdenados(array $select = ['*']): Collection
    {
        return ReqProgramaTejido::select($select)
            ->ordenado()
            ->get();
    }
    
    /**
     * Obtener registro por ID
     */
    public function getRegistroPorId(int $id): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::find($id);
    }
    
    /**
     * MEJORA: Actualizar múltiples registros en una sola consulta
     * 
     * Antes: Consulta por cada telar en un loop
     * Después: Una sola consulta batch
     */
    public function actualizarUltimoFlagBatch(string $salon, array $telaresIds): void
    {
        if (empty($telaresIds)) {
            return;
        }
        
        // Una sola consulta en lugar de una por cada telar
        DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $salon)
            ->whereIn('NoTelarId', $telaresIds)
            ->where(function($q) {
                $q->where('Ultimo', '1')
                  ->orWhere('Ultimo', 'UL');
            })
            ->update(['Ultimo' => 0]);
    }
    
    /**
     * Crear registro
     */
    public function crearRegistro(array $data): ReqProgramaTejido
    {
        return ReqProgramaTejido::create($data);
    }
    
    /**
     * Crear múltiples registros en batch
     */
    public function crearRegistrosBatch(array $registros): void
    {
        // Insertar en batch es más eficiente que múltiples inserts
        $chunks = array_chunk($registros, 100); // Insertar de 100 en 100
        
        foreach ($chunks as $chunk) {
            ReqProgramaTejido::insert($chunk);
        }
    }
}

// ============================================================================
// 5. ACTION: CREARPROGRAMATEJIDOACTION.PHP - Nueva clase acción
// ============================================================================

namespace App\Actions\ProgramaTejido;

use App\Repositories\ReqProgramaTejidoRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrearProgramaTejidoAction
{
    public function __construct(
        private ReqProgramaTejidoRepository $repository
    ) {}
    
    /**
     * Ejecutar acción de crear programa de tejido
     */
    public function execute(array $data): array
    {
        DB::beginTransaction();
        try {
            $salon = $data['salon_tejido_id'];
            $telares = $data['telares'];
            
            // MEJORA: Agrupar actualizaciones en batch
            $telaresIds = array_column($telares, 'no_telar_id');
            $this->repository->actualizarUltimoFlagBatch($salon, $telaresIds);
            
            // Crear registros
            $creados = [];
            foreach ($telares as $fila) {
                $registro = $this->repository->crearRegistro([
                    'EnProceso' => 0,
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $fila['no_telar_id'],
                    'Ultimo' => 1,
                    // ... otros campos
                ]);
                
                $creados[] = $registro;
            }
            
            DB::commit();
            return $creados;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear programa de tejido', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}

// ============================================================================
// 6. FORM REQUEST: STOREPROGRAMATEJIDOREQUEST.PHP - Nueva clase form request
// ============================================================================

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramaTejidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Lógica de autorización
        return $this->user() !== null;
    }
    
    public function rules(): array
    {
        return [
            'salon_tejido_id' => 'required|string|max:50',
            'tamano_clave' => 'nullable|string|max:100',
            'hilo' => 'required|string|max:50',
            'idflog' => 'nullable|string|max:50',
            'calendario_id' => 'nullable|string|max:50',
            'aplicacion_id' => 'nullable|string|max:50',
            'telares' => 'required|array|min:1',
            'telares.*.no_telar_id' => 'required|string|max:50',
            'telares.*.fecha_inicio' => 'nullable|date',
            'telares.*.fecha_final' => 'nullable|date|after:telares.*.fecha_inicio',
            'telares.*.cantidad' => 'nullable|numeric|min:0',
            'telares.*.compromiso_tejido' => 'nullable|date',
            'telares.*.fecha_cliente' => 'nullable|date',
            'telares.*.fecha_entrega' => 'nullable|date',
        ];
    }
    
    public function messages(): array
    {
        return [
            'telares.required' => 'Debe seleccionar al menos un telar',
            'telares.*.fecha_final.after' => 'La fecha final debe ser posterior a la fecha de inicio',
            'telares.*.no_telar_id.required' => 'El número de telar es obligatorio',
        ];
    }
}

// ============================================================================
// 7. RESERVARPROGRAMARCONTROLLER.PHP - Optimización de búsquedas
// ============================================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReservarProgramarControllerImproved extends Controller
{
    /**
     * MEJORA: Optimizar búsquedas con LIKE
     * 
     * Archivo original: app/Http/Controllers/ReservarProgramarController.php
     * Línea: ~61
     */
    public function getInventarioTelares(Request $request)
    {
        try {
            $filtros = $request->input('filtros', $request->query('filtros', []));
            
            // Validación
            if (!empty($filtros)) {
                $request->validate([
                    'filtros' => ['array'],
                    'filtros.*.columna' => ['required', 'string', Rule::in(self::COLS_TELARES)],
                    'filtros.*.valor' => ['required', 'string'],
                ]);
            }
            
            // Cache key basado en filtros
            $cacheKey = 'inventario_telares_' . md5(json_encode($filtros));
            
            return Cache::remember($cacheKey, 300, function() use ($filtros) {
                $q = $this->baseQuery();
                
                foreach ($filtros as $f) {
                    $col = $f['columna'] ?? '';
                    $val = trim($f['valor'] ?? '');
                    
                    if ($col === '' || $val === '') {
                        continue;
                    }
                    
                    if ($col === 'fecha') {
                        if ($date = $this->parseDateFlexible($val)) {
                            $q->whereDate('fecha', $date->toDateString());
                        }
                        continue;
                    }
                    
                    // MEJORA: Optimizar búsquedas con LIKE
                    // Si la búsqueda tiene al menos 3 caracteres, usar comodín solo al final
                    if (strlen($val) >= 3) {
                        $q->where($col, 'like', "{$val}%"); // Más rápido con índice
                    } else {
                        // Para búsquedas cortas, usar búsqueda exacta o diferente estrategia
                        $q->where($col, $val);
                    }
                }
                
                $rows = $q->orderBy('no_telar')
                    ->orderBy('tipo')
                    ->limit(2000)
                    ->get();
                
                $data = $this->normalizeTelares($rows);
                
                return response()->json([
                    'success' => true,
                    'data' => $data->values(),
                    'total' => $data->count(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('getInventarioTelares: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario de telares',
            ], 500);
        }
    }
}

// ============================================================================
// 8. MODELO: SYSROLES.PHP - Agregar relaciones
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SYSRolesImproved extends Model
{
    protected $table = 'SYSRoles';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'idrol';
    
    /**
     * MEJORA: Agregar relación con permisos de usuario
     */
    public function permisosUsuario(): HasMany
    {
        return $this->hasMany(SYSUsuariosRoles::class, 'idrol', 'idrol');
    }
    
    /**
     * Scope para módulos principales
     */
    public function scopeModulosPrincipales($query)
    {
        return $query->where('Nivel', 1)
            ->whereNull('Dependencia');
    }
    
    /**
     * Scope para submódulos
     */
    public function scopeSubmodulos($query, $dependencia)
    {
        return $query->where('Nivel', 2)
            ->where('Dependencia', $dependencia);
    }
}

// ============================================================================
// 9. MIDDLEWARE: RATE LIMITING
// ============================================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $request->user()?->id ?? $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Demasiadas solicitudes. Por favor, intente más tarde.'
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        return $next($request);
    }
}

// ============================================================================
// 10. TRAIT: MANUALPAGINATION.PHP - Paginación manual
// ============================================================================

namespace App\Traits;

trait ManualPagination
{
    /**
     * Paginar consulta manualmente
     */
    protected function paginateQuery($query, $perPage = 50, $page = null)
    {
        $page = $page ?? request()->input('page', 1);
        $perPage = min($perPage, 100); // Máximo 100 por página
        $offset = ($page - 1) * $perPage;
        
        $total = (clone $query)->count();
        $items = $query->offset($offset)->limit($perPage)->get();
        
        return [
            'data' => $items,
            'current_page' => (int) $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }
}



