<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\PaginacionCompat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Estado compartido de las tablas de la app: búsqueda, orden, paginación y
 * selección de fila. El componente que lo use solo declara sus columnas y su
 * consulta; la UI la pinta <x-tabla>.
 *
 * Uso mínimo:
 *   class Operadores extends Component {
 *       use ConTabla;
 *       public function columnas(): array { return [['campo'=>'NomEmpl','titulo'=>'Nombre']]; }
 *       public function render() {
 *           return view('...', ['filas' => $this->paginar($this->aplicarTabla(Modelo::query(), ['NomEmpl']))]);
 *       }
 *   }
 */
trait ConTabla
{
    use WithPagination;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $ordenPor = '';

    #[Url(except: 'asc')]
    public string $ordenDir = 'asc';

    #[Url(except: 25)]
    public int $porPagina = 25;

    public ?string $seleccionado = null;

    /**
     * Filtros por columna (fila de inputs bajo el encabezado de x-tabla): índice de la
     * columna en columnas() => texto. Por índice y no por campo: los campos con punto
     * (Tabla.Columna) Livewire los anidaría. Solo cuentan las columnas con 'filtro'.
     *
     * @var array<int|string, string>
     */
    public array $filtrosColumna = [];

    /**
     * Columnas de la tabla. Cada una:
     *   campo   string  columna real (se usa para ordenar y leer el valor)
     *   titulo  string  encabezado visible
     *   orden   bool    ordenable (default true)
     *   clase   string  clases de la celda (ej. 'hidden md:table-cell' para móvil)
     *   alinear string  'start' (default), 'center' o 'end': encabezado y celdas (align de flux:table)
     *   valor   Closure opcional, recibe la fila y devuelve el texto a pintar
     *   filtro  true = filtro por columna sobre `campo` (contiene, LIKE); string = columna real a filtrar
     *           (para alias de un JOIN: SQL Server no acepta alias en el WHERE); Closure(Builder, list<string>)
     *           = filtro propio con las partes ya separadas por coma (ej. App\Support\FiltroAx::mes('Mes'),
     *           sintaxis de AX: rangos, >, !). Default: sin filtro
     *   filtroAyuda string  ejemplo que se ve en el input del filtro (placeholder)
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function columnas(): array;

    public function ordenar(string $campo): void
    {
        if (! in_array($campo, $this->camposOrdenables(), true)) {
            return;
        }

        if ($this->ordenPor === $campo) {
            $this->ordenDir = $this->ordenDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenPor = $campo;
            $this->ordenDir = 'asc';
        }

        $this->resetPage();
    }

    public function seleccionar(?string $id): void
    {
        $this->seleccionado = $this->seleccionado === $id ? null : $id;
    }

    public function updatedBuscar(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedFiltrosColumna(): void
    {
        $this->seleccionado = null;
        $this->resetPage();
    }

    public function updatedPorPagina(): void
    {
        $this->resetPage();
    }

    /**
     * Aplica búsqueda y orden a la consulta. El orden se valida contra las
     * columnas declaradas: `ordenPor` viene de la URL y nunca toca el SQL crudo.
     *
     * @param  array<int, string>  $buscables  Columnas incluidas en el LIKE.
     */
    protected function aplicarTabla(Builder $query, array $buscables): Builder
    {
        $termino = trim($this->buscar);

        foreach ($this->columnasFiltrables() as $indice => $columna) {
            $partes = $this->partesFiltro((string) ($this->filtrosColumna[$indice] ?? ''));
            $query->when($partes !== [], fn (Builder $q): Builder => $q->where(function (Builder $sub) use ($columna, $partes): void {
                if ($columna instanceof \Closure) {
                    $columna($sub, $partes);

                    return;
                }
                foreach ($partes as $parte) {
                    $sub->orWhere($columna, 'like', '%'.$parte.'%');
                }
            }));
        }

        return $query
            ->when(
                $termino !== '' && $buscables !== [],
                fn (Builder $q): Builder => $q->where(function (Builder $sub) use ($buscables, $termino): void {
                    foreach ($buscables as $columna) {
                        $sub->orWhere($columna, 'like', '%'.$termino.'%');
                    }
                })
            )
            ->when(
                in_array($this->ordenPor, $this->camposOrdenables(), true),
                fn (Builder $q): Builder => $q->orderBy($this->ordenPor, $this->ordenDir === 'desc' ? 'desc' : 'asc')
            );
    }

    /**
     * Página actual del listado. El servidor es SQL Server 2008 y no admite
     * OFFSET/FETCH, así que la paginación pasa por PaginacionCompat.
     */
    protected function paginar(Builder $query): LengthAwarePaginator
    {
        return PaginacionCompat::paginar($query, $this->porPagina, $this->getPage());
    }

    /**
     * Como en AX: comas = varios valores (OR). "2229, 2039,,2049" → ['2229', '2039', '2049'].
     * Tope de 20 partes de 80 caracteres para no armar un WHERE desmedido.
     *
     * @return array<int, string>
     */
    protected function partesFiltro(string $texto): array
    {
        $partes = array_map(fn (string $parte): string => mb_substr(trim($parte), 0, 80), explode(',', $texto));

        return array_slice(array_values(array_filter($partes, fn (string $parte): bool => $parte !== '')), 0, 20);
    }

    /**
     * Índice => columna real (o filtro propio) de cada columna con filtro. Lista blanca: las claves
     * y valores de filtrosColumna vienen del cliente; solo los textos llegan al SQL, como binding.
     *
     * @return array<int, string|\Closure>
     */
    protected function columnasFiltrables(): array
    {
        return collect($this->columnas())
            ->filter(fn (array $columna): bool => ! empty($columna['filtro']) && filled($columna['campo'] ?? null))
            ->map(fn (array $columna): string|\Closure => match (true) {
                $columna['filtro'] instanceof \Closure, is_string($columna['filtro']) => $columna['filtro'],
                default => $columna['campo'],
            })
            ->all();
    }

    /** @return array<int, string> */
    protected function camposOrdenables(): array
    {
        return collect($this->columnas())
            ->filter(fn (array $columna): bool => ($columna['orden'] ?? true) && filled($columna['campo'] ?? null))
            ->pluck('campo')
            ->all();
    }
}
