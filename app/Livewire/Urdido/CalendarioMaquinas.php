<?php

declare(strict_types=1);

namespace App\Livewire\Urdido;

use App\Livewire\Concerns\ConCrud;
use App\Models\UrdEngomado\UrdEngCalMaquina;
use App\Models\Urdido\URDCatalogoMaquina;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Calendario de máquinas de urdido (UrdEngCalMaquinas): minutos disponibles por máquina y turno.
 * Vista de mes por máquina (semanas de lunes a domingo, los 3 horarios de cada día); clic en un turno
 * lo edita, clic en un horario vacío lo agrega. "Generar mes" llena el mes con la rotación.
 *
 * @property-read array<int, string> $maquinas
 */
class CalendarioMaquinas extends Component
{
    use ConCrud {
        abrirAlta as protected abrirAltaCrud;
        abrirEdicion as protected abrirEdicionCrud;
        eliminar as protected eliminarCrud;
    }

    public const MODULO = 211;

    public const MODELO = UrdEngCalMaquina::class;

    public const CAMPOS = ['Maquina', 'FechaInicio', 'Minutos', 'Turno'];

    public const DEPARTAMENTO = 'Urdido';

    /** Turnos (cuadrillas): el 4 cubre descansos según UrdEngCalMaquina::ROTACION_URDIDO. */
    public const TURNOS = [1, 2, 3, 4];

    public const NOMBRES_VENTANA = [1 => 'Mañana', 2 => 'Tarde', 3 => 'Noche'];

    /** URDCatalogoMaquinas.Id de la máquina que se ve; '' = la primera. */
    #[Url(except: '')]
    public string $maquinaFiltro = '';

    /** 'Y-m' del mes que se ve. */
    #[Url(except: '')]
    public string $mes = '';

    /** Fila abierta en el modal (ConCrud::eliminar borra esta). */
    public ?string $seleccionado = null;

    /** Formulario de "Generar mes"; null = modal cerrado. */
    public ?array $generar = null;

    public function mount(): void
    {
        abort_unless(userCan('acceso', self::MODULO), 403, 'No tienes acceso al calendario de máquinas.');

        if (! $this->mesValido()) {
            $this->mes = now()->format('Y-m');
        }
        if (! array_key_exists((int) $this->maquinaFiltro, $this->maquinas)) {
            $this->maquinaFiltro = (string) (array_key_first($this->maquinas) ?? '');
        }
    }

    /** @return array<int, string> Id => nombre de las máquinas del departamento. */
    #[Computed]
    public function maquinas(): array
    {
        return URDCatalogoMaquina::query()
            ->where('Departamento', self::DEPARTAMENTO)
            ->orderBy('MaquinaId')
            ->get(['Id', 'MaquinaId', 'Nombre'])
            ->mapWithKeys(fn (URDCatalogoMaquina $m): array => [$m->Id => $m->Nombre ?: $m->MaquinaId])
            ->all();
    }

    public function verMaquina(string $id): void
    {
        if (array_key_exists((int) $id, $this->maquinas)) {
            $this->maquinaFiltro = $id;
        }
    }

    public function moverMes(int $paso): void
    {
        $this->mes = $this->inicioMes()->addMonths($paso > 0 ? 1 : -1)->format('Y-m');
    }

    public function abrirAlta(): void
    {
        $this->abrirAltaCrud();
        $this->form = ['Maquina' => $this->maquinaFiltro, 'FechaInicio' => '', 'Minutos' => '480', 'Turno' => '1'];
    }

    /** Alta desde un horario vacío del calendario ('Y-m-d\TH:i'): máquina, inicio y turno ya puestos. */
    public function abrirAltaEn(string $inicio): void
    {
        $this->abrirAlta();
        $this->form['FechaInicio'] = $inicio;
        $this->updatedFormFechaInicio($inicio);
    }

    public function abrirEdicion(?string $id = null): void
    {
        $this->abrirEdicionCrud($id);
        if ($this->editando !== null) {
            // <input type="datetime-local"> pide 'Y-m-d\TH:i'.
            $this->form['FechaInicio'] = CarbonImmutable::parse($this->form['FechaInicio'])->format('Y-m-d\TH:i');
        }
    }

    /** Borra el turno abierto en el modal y lo cierra. */
    public function eliminar(): void
    {
        $this->eliminarCrud();
        $this->editando = null;
    }

    /** Inicio en una ventana de reloj (06:30 / 14:30 / 22:30): el turno lo dice la rotación. */
    public function updatedFormFechaInicio(string $valor): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valor) !== 1) {
            return;
        }
        $inicio = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $valor);
        $ventana = array_search($inicio->format('H:i'), UrdEngCalMaquina::VENTANAS, true);
        if ($ventana !== false) {
            $this->form['Turno'] = (string) UrdEngCalMaquina::ROTACION_URDIDO[$inicio->dayOfWeekIso][$ventana];
        }
    }

    public function guardar(): void
    {
        $esAlta = $this->editando === '';
        abort_unless(userCan($esAlta ? 'crear' : 'modificar', self::MODULO), 403);

        $datos = $this->validate([
            'form.Maquina' => ['required', Rule::in(array_keys($this->maquinas))],
            'form.FechaInicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'form.Minutos' => ['required', 'integer', 'min:1', 'max:'.UrdEngCalMaquina::MAX_MINUTOS],
            'form.Turno' => ['required', Rule::in(self::TURNOS)],
        ], attributes: [
            'form.Maquina' => 'máquina',
            'form.FechaInicio' => 'inicio',
            'form.Minutos' => 'minutos',
            'form.Turno' => 'turno',
        ])['form'];

        $datos = [
            'Maquina' => (int) $datos['Maquina'],
            'FechaInicio' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $datos['FechaInicio']),
            'Minutos' => (int) $datos['Minutos'],
            'Turno' => (int) $datos['Turno'],
        ];

        if (UrdEngCalMaquina::seEncima($datos['Maquina'], $datos['FechaInicio'], $datos['Minutos'], $esAlta ? null : (int) $this->editando)) {
            $this->addError('form.FechaInicio', 'Se encima con otro turno de la máquina.');

            return;
        }

        $esAlta ? UrdEngCalMaquina::create($datos) : $this->buscar((string) $this->editando)->update($datos);

        $this->editando = $this->seleccionado = null;
        $this->dispatch('aviso', tipo: 'success', texto: $esAlta ? 'Turno agregado.' : 'Turno actualizado.');
    }

    public function abrirGenerar(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $this->resetValidation();
        $this->generar = [
            'Maquina' => $this->maquinaFiltro,
            'mes' => $this->mes,
            // 1 = lunes … 7 = domingo; con la rotación se trabaja toda la semana.
            'dias' => ['1', '2', '3', '4', '5', '6', '7'],
            'ventanas' => collect(UrdEngCalMaquina::VENTANAS)
                ->map(fn (string $hora): array => ['activo' => true, 'hora' => $hora, 'minutos' => '480'])
                ->all(),
        ];
    }

    public function cerrarGenerar(): void
    {
        $this->generar = null;
        $this->resetValidation();
    }

    /** Crea los turnos del mes que no se encimen con los que ya hay; los demás se omiten. */
    public function generarMes(): void
    {
        abort_unless(userCan('crear', self::MODULO), 403);

        $g = $this->validate([
            'generar.Maquina' => ['required', Rule::in(array_keys($this->maquinas))],
            'generar.mes' => ['required', 'date_format:Y-m'],
            'generar.dias' => ['required', 'array', 'min:1'],
            'generar.dias.*' => [Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
            'generar.ventanas.*.hora' => ['required', 'date_format:H:i'],
            'generar.ventanas.*.minutos' => ['required', 'integer', 'min:1', 'max:'.UrdEngCalMaquina::MAX_MINUTOS],
        ], attributes: [
            'generar.Maquina' => 'máquina',
            'generar.mes' => 'mes',
            'generar.dias' => 'días',
            'generar.ventanas.*.hora' => 'hora de inicio',
            'generar.ventanas.*.minutos' => 'minutos',
        ])['generar'];

        $ventanas = collect($this->generar['ventanas'])
            ->filter(fn (array $v): bool => (bool) $v['activo'])
            ->map(fn (array $v, int|string $ventana): array => ['ventana' => (int) $ventana, 'hora' => $v['hora'], 'minutos' => (int) $v['minutos']])
            ->values()
            ->all();

        if ($ventanas === []) {
            $this->addError('generar.ventanas', 'Marca al menos un horario.');

            return;
        }

        $maquina = (int) $g['Maquina'];
        $filas = UrdEngCalMaquina::plantillaMes($g['mes'], $ventanas, array_map('intval', $g['dias']));

        $creados = DB::connection('sqlsrv')->transaction(function () use ($filas, $maquina): int {
            $creados = 0;
            foreach ($filas as $fila) {
                if (! UrdEngCalMaquina::seEncima($maquina, $fila['FechaInicio'], $fila['Minutos'])) {
                    UrdEngCalMaquina::create($fila + ['Maquina' => $maquina]);
                    $creados++;
                }
            }

            return $creados;
        });

        $omitidos = count($filas) - $creados;
        $this->maquinaFiltro = (string) $maquina;
        $this->mes = $g['mes'];
        $this->generar = null;
        $this->dispatch('aviso', tipo: 'success', texto: "Se agregaron {$creados} turnos."
            .($omitidos > 0 ? " {$omitidos} se omitieron porque se enciman con turnos que ya estaban." : ''));
    }

    public function render(): View
    {
        $inicioMes = $this->inicioMes();
        $desde = $inicioMes->startOfWeek(CarbonImmutable::MONDAY);
        $hasta = $inicioMes->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay()->startOfDay();

        $turnos = UrdEngCalMaquina::query()
            ->where('Maquina', (int) $this->maquinaFiltro)
            ->where('FechaInicio', '>=', $desde)
            ->where('FechaInicio', '<', $hasta)
            ->orderBy('FechaInicio')
            ->get();
        $porDia = $turnos->groupBy(fn (UrdEngCalMaquina $t): string => $t->FechaInicio->format('Y-m-d'));
        $delMes = $turnos->filter(fn (UrdEngCalMaquina $t): bool => $t->FechaInicio->format('Y-m') === $this->mes);

        return view('livewire.urdido.calendario-maquinas', [
            'semanas' => $this->semanas($desde, $hasta, $porDia->all()),
            'tituloMes' => ucfirst($inicioMes->locale('es')->isoFormat('MMMM YYYY')),
            'resumen' => ['turnos' => $delMes->count(), 'minutos' => (int) $delMes->sum('Minutos')],
            'puede' => $this->permisos(),
        ]);
    }

    /**
     * Semanas de lunes a domingo; cada día con sus casillas: una por horario de reloj (con el turno
     * que empieza a esa hora o vacía) y aparte los turnos que empiezan a otra hora.
     *
     * @param  array<string, Collection<int, UrdEngCalMaquina>>  $porDia
     * @return list<list<array<string, mixed>>>
     */
    private function semanas(CarbonImmutable $desde, CarbonImmutable $hasta, array $porDia): array
    {
        $dias = [];
        for ($dia = $desde; $dia < $hasta; $dia = $dia->addDay()) {
            $delDia = collect($porDia[$dia->format('Y-m-d')] ?? []);
            $casillas = [];
            foreach (UrdEngCalMaquina::VENTANAS as $ventana => $hora) {
                $casillas[] = [
                    'hora' => $hora,
                    'inicio' => $dia->format('Y-m-d').'T'.$hora,
                    'turno' => $delDia->first(fn (UrdEngCalMaquina $t): bool => $t->FechaInicio->format('H:i') === $hora),
                    'sugerido' => UrdEngCalMaquina::ROTACION_URDIDO[$dia->dayOfWeekIso][$ventana],
                ];
            }
            $dias[] = [
                'fecha' => $dia,
                'enMes' => $dia->format('Y-m') === $this->mes,
                'hoy' => $dia->isToday(),
                'casillas' => $casillas,
                'otros' => $delDia->reject(fn (UrdEngCalMaquina $t): bool => in_array($t->FechaInicio->format('H:i'), UrdEngCalMaquina::VENTANAS, true))->values(),
            ];
        }

        return array_chunk($dias, 7);
    }

    private function inicioMes(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->mesValido() ? $this->mes : now()->format('Y-m'));
    }

    private function mesValido(): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes) === 1;
    }
}
