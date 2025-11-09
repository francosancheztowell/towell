# Mejoras y Optimizaciones para el Proyecto Towell

Este documento contiene recomendaciones específicas para mejorar el rendimiento, mantenibilidad y escalabilidad del proyecto.

## Índice

1. [Optimización de Consultas de Base de Datos](#1-optimización-de-consultas-de-base-de-datos)
2. [Implementación de Caché](#2-implementación-de-caché)
3. [Arquitectura y Separación de Responsabilidades](#3-arquitectura-y-separación-de-responsabilidades)
4. [Validación con Form Requests](#4-validación-con-form-requests)
5. [Indexación de Base de Datos](#5-indexación-de-base-de-datos)
6. [Paginación y Límites](#6-paginación-y-límites)
7. [Eliminación de Código Duplicado](#7-eliminación-de-código-duplicado)
8. [Servicios y Repositorios](#8-servicios-y-repositorios)
9. [Optimización de Rendimiento General](#9-optimización-de-rendimiento-general)
10. [Seguridad](#10-seguridad)
11. [Testing y Calidad de Código](#11-testing-y-calidad-de-código)

---

## 1. Optimización de Consultas de Base de Datos

### 1.1. Problema: Consultas N+1

**Ubicación**: Múltiples controladores

**Problema actual**:
```php
// UsuarioController.php - línea 20
$modulos = SYSRoles::orderBy('orden')->get();
// Luego se hace un loop y se consultan permisos individualmente
```

**Solución**: Usar Eager Loading
```php
// Mejorado
$modulos = SYSRoles::with('permisosUsuario')
    ->whereHas('permisosUsuario', function($q) use ($idusuario) {
        $q->where('idusuario', $idusuario)->where('acceso', true);
    })
    ->orderBy('orden')
    ->get();
```

### 1.2. Problema: Consultas sin Select Específico

**Ubicación**: `ProgramaTejidoController.php` - línea 21-34

**Problema actual**:
```php
$registros = ReqProgramaTejido::select([...80 campos...])->get();
```

**Solución**: 
- Usar select solo cuando sea necesario
- Considerar lazy loading para campos pesados
- Implementar API Resources para transformar datos

### 1.3. Problema: Consultas con LIKE sin Índices

**Ubicación**: `ReservarProgramarController.php` - línea 61

**Problema actual**:
```php
$q->where($col, 'like', "%{$val}%"); // LIKE con comodín al inicio es lento
```

**Solución**:
```php
// Para búsquedas de texto, usar FULL-TEXT INDEX en SQL Server
// O implementar búsqueda con comodín solo al final cuando sea posible
if (strlen($val) >= 3) {
    $q->where($col, 'like', "{$val}%"); // Más rápido con índice
} else {
    // Para búsquedas cortas, considerar búsqueda exacta o diferente estrategia
}
```

### 1.4. Problema: Consultas dentro de Loops

**Ubicación**: `ProgramaTejidoController.php` - línea 221

**Problema actual**:
```php
foreach ($request->input('telares', []) as $fila) {
    // Consulta dentro del loop
    $this->marcarCambioHiloAnterior($salon, $noTelarId, $hilo);
    DB::statement("UPDATE ReqProgramaTejido..."); // Query por cada iteración
}
```

**Solución**: Usar consultas batch
```php
// Agrupar actualizaciones
$updates = [];
foreach ($request->input('telares', []) as $fila) {
    $updates[] = [
        'salon' => $salon,
        'no_telar_id' => $fila['no_telar_id'],
        'hilo' => $hilo
    ];
}

// Ejecutar una sola consulta batch
DB::table('ReqProgramaTejido')
    ->whereIn('NoTelarId', array_column($updates, 'no_telar_id'))
    ->update([...]);
```

### 1.5. Problema: Consultas sin Límites

**Ubicación**: `UsuarioController.php` - línea 83-86

**Problema actual**:
```php
$usuarios = Usuario::query()
    ->select([...])
    ->orderBy('nombre')
    ->get(); // Sin límite
```

**Solución**: Implementar paginación manual o usar cursor
```php
// Opción 1: Paginación manual
$page = $request->input('page', 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

$usuarios = Usuario::query()
    ->select([...])
    ->orderBy('nombre')
    ->offset($offset)
    ->limit($perPage)
    ->get();

// Opción 2: Cursor pagination (mejor para grandes volúmenes)
$usuarios = Usuario::query()
    ->select([...])
    ->orderBy('nombre')
    ->cursor()
    ->take(100); // Procesar en chunks
```

---

## 2. Implementación de Caché

### 2.1. Caché de Consultas Frecuentes

**Ubicación**: Múltiples controladores

**Implementar**:
```php
// Ejemplo: UsuarioController - módulos principales
public function index()
{
    $usuarioActual = Auth::user();
    $cacheKey = "modulos_principales_user_{$usuarioActual->idusuario}";
    
    $modulos = Cache::remember($cacheKey, 3600, function() use ($usuarioActual) {
        return SYSRoles::with(['permisosUsuario' => function($q) use ($usuarioActual) {
            $q->where('idusuario', $usuarioActual->idusuario);
        }])
        ->where('Nivel', 1)
        ->whereNull('Dependencia')
        ->where('acceso', true)
        ->orderBy('orden')
        ->get();
    });
    
    return view('produccionProceso', compact('modulos'));
}
```

### 2.2. Caché de Resultados de API

**Ubicación**: Controladores que retornan JSON

**Implementar**:
```php
// ReservarProgramarController.php
public function getInventarioTelares(Request $request)
{
    $filtros = $request->input('filtros', []);
    $cacheKey = 'inventario_telares_' . md5(json_encode($filtros));
    
    return Cache::remember($cacheKey, 300, function() use ($filtros) {
        // Lógica de consulta
        return response()->json([...]);
    });
}
```

### 2.3. Invalidación de Caché

**Implementar eventos para limpiar caché**:
```php
// En AppServiceProvider o EventServiceProvider
SYSRoles::updated(function ($modulo) {
    // Limpiar caché de módulos afectados
    Cache::tags(['modulos', "modulo_{$modulo->idrol}"])->flush();
});

SYSUsuariosRoles::updated(function ($permiso) {
    // Limpiar caché de permisos del usuario
    Cache::forget("modulos_principales_user_{$permiso->idusuario}");
    Cache::forget("submodulos_*_user_{$permiso->idusuario}");
});
```

### 2.4. Usar Redis para Caché

**Configuración**: Cambiar de database a Redis en producción

```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## 3. Arquitectura y Separación de Responsabilidades

### 3.1. Crear Servicios para Lógica de Negocio

**Problema**: Lógica de negocio mezclada en controladores

**Solución**: Crear servicios

```php
// app/Services/ModuloService.php
namespace App\Services;

use App\Models\SYSRoles;
use App\Models\SYSUsuariosRoles;
use Illuminate\Support\Facades\Cache;

class ModuloService
{
    public function getModulosPrincipalesPorUsuario(int $idusuario)
    {
        $cacheKey = "modulos_principales_user_{$idusuario}";
        
        return Cache::remember($cacheKey, 3600, function() use ($idusuario) {
            return SYSRoles::with(['permisosUsuario' => function($q) use ($idusuario) {
                $q->where('idusuario', $idusuario)->where('acceso', true);
            }])
            ->where('Nivel', 1)
            ->whereNull('Dependencia')
            ->orderBy('orden')
            ->get();
        });
    }
    
    public function limpiarCacheModulos(int $idusuario = null)
    {
        if ($idusuario) {
            Cache::forget("modulos_principales_user_{$idusuario}");
        } else {
            Cache::flush();
        }
    }
}
```

**Uso en Controlador**:
```php
// UsuarioController.php
use App\Services\ModuloService;

public function index(ModuloService $moduloService)
{
    $usuarioActual = Auth::user();
    $modulos = $moduloService->getModulosPrincipalesPorUsuario($usuarioActual->idusuario);
    
    return view('produccionProceso', compact('modulos'));
}
```

### 3.2. Crear Repositorios para Acceso a Datos

**Problema**: Consultas SQL directas en controladores

**Solución**: Crear repositorios

```php
// app/Repositories/ReqProgramaTejidoRepository.php
namespace App\Repositories;

use App\Models\ReqProgramaTejido;
use Illuminate\Support\Collection;

class ReqProgramaTejidoRepository
{
    public function getRegistrosOrdenados(array $select = ['*']): Collection
    {
        return ReqProgramaTejido::select($select)
            ->ordenado()
            ->get();
    }
    
    public function getRegistroPorId(int $id): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::find($id);
    }
    
    public function actualizarUltimoFlag(string $salon, string $noTelarId): void
    {
        ReqProgramaTejido::where('SalonTejidoId', $salon)
            ->where('NoTelarId', $noTelarId)
            ->whereIn('Ultimo', ['1', 'UL'])
            ->update(['Ultimo' => 0]);
    }
    
    public function crearRegistro(array $data): ReqProgramaTejido
    {
        return ReqProgramaTejido::create($data);
    }
}
```

### 3.3. Usar Actions para Operaciones Complejas

**Problema**: Métodos muy largos en controladores

**Solución**: Crear Actions

```php
// app/Actions/ProgramaTejido/CrearProgramaTejidoAction.php
namespace App\Actions\ProgramaTejido;

use App\Models\ReqProgramaTejido;
use App\Repositories\ReqProgramaTejidoRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrearProgramaTejidoAction
{
    public function __construct(
        private ReqProgramaTejidoRepository $repository
    ) {}
    
    public function execute(array $data): array
    {
        DB::beginTransaction();
        try {
            $creados = [];
            
            foreach ($data['telares'] as $fila) {
                $this->repository->actualizarUltimoFlag(
                    $data['salon'],
                    $fila['no_telar_id']
                );
                
                $registro = $this->repository->crearRegistro([
                    // datos del registro
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
```

---

## 4. Validación con Form Requests

### 4.1. Crear Form Requests

**Problema**: Validación directamente en controladores

**Solución**: Crear Form Requests

```php
// app/Http/Requests/StoreProgramaTejidoRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramaTejidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // O lógica de autorización
    }
    
    public function rules(): array
    {
        return [
            'salon_tejido_id' => 'required|string',
            'tamano_clave' => 'nullable|string',
            'hilo' => 'required|string',
            'idflog' => 'nullable|string',
            'calendario_id' => 'nullable|string',
            'aplicacion_id' => 'nullable|string',
            'telares' => 'required|array|min:1',
            'telares.*.no_telar_id' => 'required|string',
            'telares.*.fecha_inicio' => 'nullable|date',
            'telares.*.fecha_final' => 'nullable|date|after:telares.*.fecha_inicio',
            'telares.*.cantidad' => 'nullable|numeric|min:0',
        ];
    }
    
    public function messages(): array
    {
        return [
            'telares.required' => 'Debe seleccionar al menos un telar',
            'telares.*.fecha_final.after' => 'La fecha final debe ser posterior a la fecha de inicio',
        ];
    }
}
```

**Uso en Controlador**:
```php
public function store(StoreProgramaTejidoRequest $request, CrearProgramaTejidoAction $action)
{
    $creados = $action->execute($request->validated());
    
    return response()->json([
        'success' => true,
        'message' => 'Programa de tejido creado correctamente',
        'data' => $creados,
    ]);
}
```

---

## 5. Indexación de Base de Datos

### 5.1. Índices Recomendados

**Crear migración para índices**:

```php
// database/migrations/YYYY_MM_DD_add_indexes_for_performance.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para SYSUsuariosRoles
        DB::statement('CREATE NONCLUSTERED INDEX IX_SYSUsuariosRoles_idusuario_idrol 
            ON SYSUsuariosRoles (idusuario, idrol) 
            INCLUDE (acceso, crear, modificar, eliminar, registrar)');
        
        // Índice para SYSRoles
        DB::statement('CREATE NONCLUSTERED INDEX IX_SYSRoles_Nivel_Dependencia 
            ON SYSRoles (Nivel, Dependencia) 
            INCLUDE (orden, modulo, imagen)');
        
        // Índice para ReqProgramaTejido
        DB::statement('CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_Salon_NoTelar 
            ON ReqProgramaTejido (SalonTejidoId, NoTelarId) 
            INCLUDE (EnProceso, Ultimo, FechaInicio, FechaFinal)');
        
        // Índice para búsquedas de texto
        DB::statement('CREATE NONCLUSTERED INDEX IX_ReqProgramaTejido_TamanoClave 
            ON ReqProgramaTejido (TamanoClave)');
        
        // Full-text index para búsquedas de texto completo (si es necesario)
        // DB::statement('CREATE FULLTEXT INDEX ON ReqProgramaTejido (NombreProducto, NombreProyecto)');
    }
    
    public function down(): void
    {
        DB::statement('DROP INDEX IX_SYSUsuariosRoles_idusuario_idrol ON SYSUsuariosRoles');
        DB::statement('DROP INDEX IX_SYSRoles_Nivel_Dependencia ON SYSRoles');
        DB::statement('DROP INDEX IX_ReqProgramaTejido_Salon_NoTelar ON ReqProgramaTejido');
        DB::statement('DROP INDEX IX_ReqProgramaTejido_TamanoClave ON ReqProgramaTejido');
    }
};
```

---

## 6. Paginación y Límites

### 6.1. Implementar Paginación Manual

**Problema**: Consultas sin límites

**Solución**: Crear trait para paginación manual

```php
// app/Traits/ManualPagination.php
namespace App\Traits;

trait ManualPagination
{
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
        ];
    }
}
```

**Uso**:
```php
// UsuarioController.php
use App\Traits\ManualPagination;

class UsuarioController extends Controller
{
    use ManualPagination;
    
    public function select(Request $request)
    {
        $query = Usuario::query()
            ->select([...])
            ->orderBy('nombre');
        
        $result = $this->paginateQuery($query, 50);
        
        return view('modulos.usuarios.select', $result);
    }
}
```

---

## 7. Eliminación de Código Duplicado

### 7.1. Crear Traits para Funcionalidades Comunes

**Problema**: Código duplicado en múltiples controladores

**Solución**: Crear traits

```php
// app/Traits/HasCacheInvalidation.php
namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCacheInvalidation
{
    protected function clearUserModuleCache(int $idusuario): void
    {
        $patterns = [
            "modulos_principales_user_{$idusuario}",
            "submodulos_*_user_{$idusuario}",
        ];
        
        foreach ($patterns as $pattern) {
            // Implementar lógica de limpieza de caché por patrón
            Cache::forget($pattern);
        }
    }
}
```

### 7.2. Centralizar Lógica de Validación

**Problema**: Validaciones repetidas

**Solución**: Crear validadores personalizados

```php
// app/Rules/ValidTelar.php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidTelar implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Lógica de validación
        return true;
    }
    
    public function message(): string
    {
        return 'El telar especificado no es válido.';
    }
}
```

---

## 8. Servicios y Repositorios

### 8.1. Crear Servicio para Gestión de Permisos

```php
// app/Services/PermissionService.php
namespace App\Services;

use App\Models\SYSUsuariosRoles;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    public function userHasPermission(int $idusuario, int $idrol, string $permission): bool
    {
        $cacheKey = "permission_{$idusuario}_{$idrol}_{$permission}";
        
        return Cache::remember($cacheKey, 3600, function() use ($idusuario, $idrol, $permission) {
            $permiso = SYSUsuariosRoles::where('idusuario', $idusuario)
                ->where('idrol', $idrol)
                ->first();
            
            return $permiso && $permiso->{$permission} == 1;
        });
    }
    
    public function clearUserPermissionsCache(int $idusuario): void
    {
        Cache::tags(["user_permissions_{$idusuario}"])->flush();
    }
}
```

### 8.2. Crear Repositorio para Inventario

```php
// app/Repositories/InventarioRepository.php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class InventarioRepository
{
    public function getInventarioTelares(array $filtros = [], int $limit = 2000): Collection
    {
        $query = DB::table('TejInventarioTelares')
            ->select([...]);
        
        foreach ($filtros as $filtro) {
            $this->applyFilter($query, $filtro);
        }
        
        return $query->orderBy('no_telar')
            ->orderBy('tipo')
            ->limit($limit)
            ->get();
    }
    
    private function applyFilter($query, array $filtro): void
    {
        // Lógica de filtrado
    }
}
```

---

## 9. Optimización de Rendimiento General

### 9.1. Usar Queue para Operaciones Pesadas

**Implementar jobs para operaciones asíncronas**:

```php
// app/Jobs/ProcessExcelImport.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private string $filePath,
        private string $importType
    ) {}
    
    public function handle(): void
    {
        // Procesar importación de Excel
    }
}
```

### 9.2. Optimizar Consultas con Chunking

**Problema**: Procesar grandes volúmenes de datos

**Solución**: Usar chunk

```php
// Procesar en chunks de 1000 registros
ReqProgramaTejido::chunk(1000, function ($registros) {
    foreach ($registros as $registro) {
        // Procesar registro
    }
});
```

### 9.3. Implementar Lazy Eager Loading

```php
// Cargar relaciones solo cuando se necesiten
$registros = ReqProgramaTejido::all();
$registros->load('modeloCodificado'); // Cargar relación después
```

### 9.4. Usar Database Transactions Eficientemente

```php
// Agrupar operaciones en transacciones
DB::transaction(function () use ($data) {
    // Múltiples operaciones
}, 3); // Reintentos
```

---

## 10. Seguridad

### 10.1. Sanitizar Inputs

**Implementar sanitización**:

```php
// app/Http/Middleware/SanitizeInput.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();
        
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = trim(strip_tags($value));
            }
        });
        
        $request->merge($input);
        
        return $next($request);
    }
}
```

### 10.2. Rate Limiting

**Implementar rate limiting**:

```php
// routes/web.php
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    // Rutas protegidas
});
```

### 10.3. Validar Permisos en Middleware

```php
// app/Http/Middleware/CheckModulePermission.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PermissionService;

class CheckModulePermission
{
    public function __construct(
        private PermissionService $permissionService
    ) {}
    
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        $moduleId = $request->route('module_id');
        
        if (!$this->permissionService->userHasPermission($user->idusuario, $moduleId, $permission)) {
            abort(403, 'No tiene permisos para realizar esta acción');
        }
        
        return $next($request);
    }
}
```

---

## 11. Testing y Calidad de Código

### 11.1. Implementar Tests Unitarios

```php
// tests/Unit/Services/ModuloServiceTest.php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ModuloService;
use App\Models\SYSRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuloServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_get_modulos_principales_por_usuario()
    {
        $service = new ModuloService();
        $modulos = $service->getModulosPrincipalesPorUsuario(1);
        
        $this->assertNotEmpty($modulos);
    }
}
```

### 11.2. Implementar Tests de Integración

```php
// tests/Feature/ProgramaTejidoTest.php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProgramaTejidoTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_usuario_puede_crear_programa_tejido()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/planeacion/programa-tejido', [
                // datos de prueba
            ]);
        
        $response->assertStatus(200);
    }
}
```

---

## Priorización de Implementación

### Alta Prioridad (Implementar Primero)
1. ✅ Indexación de base de datos
2. ✅ Implementar paginación en consultas sin límites
3. ✅ Optimizar consultas N+1 con eager loading
4. ✅ Implementar caché para consultas frecuentes
5. ✅ Crear Form Requests para validación

### Media Prioridad
1. Crear servicios para lógica de negocio
2. Implementar repositorios para acceso a datos
3. Eliminar código duplicado con traits
4. Implementar rate limiting
5. Optimizar consultas con LIKE

### Baja Prioridad (Mejoras Continuas)
1. Implementar tests
2. Crear jobs para operaciones asíncronas
3. Implementar API Resources
4. Optimizaciones avanzadas de caché
5. Documentación de código

---

## Métricas de Mejora Esperadas

- **Reducción de tiempo de carga**: 40-60%
- **Reducción de consultas a BD**: 50-70%
- **Mejora en tiempo de respuesta de API**: 30-50%
- **Reducción de uso de memoria**: 20-30%
- **Mejora en experiencia de usuario**: Significativa

---

## Notas Finales

- Implementar mejoras de forma incremental
- Medir el impacto de cada mejora
- Realizar pruebas antes de implementar en producción
- Documentar los cambios realizados
- Mantener backups antes de cambios importantes

---

## Recursos Adicionales

- [Laravel Performance Best Practices](https://laravel.com/docs/performance)
- [SQL Server Index Optimization](https://docs.microsoft.com/sql/relational-databases/indexes)
- [Redis Caching Strategies](https://redis.io/docs/manual/patterns/)


