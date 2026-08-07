<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

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
 *           return view('...', ['filas' => $this->aplicarTabla(Modelo::query(), ['NomEmpl'])->paginate($this->porPagina)]);
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
     * Columnas de la tabla. Cada una:
     *   campo   string  columna real (se usa para ordenar y leer el valor)
     *   titulo  string  encabezado visible
     *   orden   bool    ordenable (default true)
     *   clase   string  clases de la celda (ej. 'hidden md:table-cell' para móvil)
     *   valor   Closure opcional, recibe la fila y devuelve el texto a pintar
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

    /** @return array<int, string> */
    protected function camposOrdenables(): array
    {
        return collect($this->columnas())
            ->filter(fn (array $columna): bool => ($columna['orden'] ?? true) && filled($columna['campo'] ?? null))
            ->pluck('campo')
            ->all();
    }
}
