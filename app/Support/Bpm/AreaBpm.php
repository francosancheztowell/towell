<?php

declare(strict_types=1);

namespace App\Support\Bpm;

use App\Helpers\FolioHelper;
use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Engomado\EngBpmLineModel;
use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Tejedores\TelActividadesBPM;
use App\Models\Tejedores\TelBpmLineModel;
use App\Models\Tejedores\TelBpmModel;
use App\Models\Urdido\UrdActividadesBpmModel;
use App\Models\Urdido\UrdBpmLineModel;
use App\Models\Urdido\UrdBpmModel;
use Illuminate\Support\Facades\DB;

/**
 * Lo único que cambia entre los BPM de Urdido, Engomado y Tejedores: tablas, permisos (idrol de
 * SYSRoles), folio y cómo se guarda cada marca del checklist. Los componentes App\Livewire\Bpm\*
 * son los mismos para las tres áreas.
 */
enum AreaBpm: string
{
    case Urdido = 'urdido';
    case Engomado = 'engomado';
    case Tejedores = 'tejedores';

    public function titulo(): string
    {
        return 'BPM '.ucfirst($this->value);
    }

    /** idrol del documento BPM (acceso / crear / modificar / eliminar / registrar = autorizar). */
    public function modulo(): int
    {
        return match ($this) {
            self::Urdido => 35,
            self::Engomado => 41,
            self::Tejedores => 47,
        };
    }

    /** idrol del catálogo de actividades. */
    public function moduloActividades(): int
    {
        return match ($this) {
            self::Urdido => 144,
            self::Engomado => 164,
            self::Tejedores => 173,
        };
    }

    /** @return class-string<UrdBpmModel|EngBpmModel|TelBpmModel> */
    public function modelo(): string
    {
        return match ($this) {
            self::Urdido => UrdBpmModel::class,
            self::Engomado => EngBpmModel::class,
            self::Tejedores => TelBpmModel::class,
        };
    }

    /** @return class-string<UrdBpmLineModel|EngBpmLineModel|TelBpmLineModel> */
    public function modeloLinea(): string
    {
        return match ($this) {
            self::Urdido => UrdBpmLineModel::class,
            self::Engomado => EngBpmLineModel::class,
            self::Tejedores => TelBpmLineModel::class,
        };
    }

    /** @return class-string<UrdActividadesBpmModel|EngActividadesBpmModel|TelActividadesBPM> */
    public function modeloActividad(): string
    {
        return match ($this) {
            self::Urdido => UrdActividadesBpmModel::class,
            self::Engomado => EngActividadesBpmModel::class,
            self::Tejedores => TelActividadesBPM::class,
        };
    }

    /** Urdido escribe el nombre completo; Engomado y Tejedores, abreviado. */
    public function columnaAutoriza(): string
    {
        return $this === self::Urdido ? 'NombreEmplAutoriza' : 'NomEmplAutoriza';
    }

    /** Clave de SSYSFoliosSecuencias, prefijo (solo para crear la secuencia si falta) y dígitos. */
    public function folio(): array
    {
        return match ($this) {
            self::Urdido => ['Urdido BPM', 'BU', 3],
            self::Engomado => ['Engomado BPM', 'BE', 3],
            self::Tejedores => ['BPMTEjido', 'BT', 5],
        };
    }

    /**
     * Siguiente folio de SSYSFoliosSecuencias. Si la secuencia no existe se crea; si quedó atrás del
     * folio más alto de la tabla (capturas viejas por otro camino) se alinea antes de avanzar.
     */
    public function siguienteFolio(): string
    {
        [$modulo, $prefijo, $digitos] = $this->folio();

        return DB::connection('sqlsrv')->transaction(function () use ($modulo, $prefijo, $digitos): string {
            FolioHelper::asegurarSecuencia($modulo, $prefijo, ($this->modelo())::query());
            $maximo = (int) substr((string) ($this->modelo())::where('Folio', 'like', $prefijo.'%')->orderByDesc('Folio')->value('Folio'), strlen($prefijo));
            SSYSFoliosSecuencia::query()->where('modulo', $modulo)->where('consecutivo', '<', $maximo)->update(['consecutivo' => $maximo]);

            return FolioHelper::obtenerSiguienteFolio($modulo, $digitos);
        });
    }

    /**
     * Ciclo de cada marca del checklist tal como se guarda en *BPMLine.Valor (lo leen los reportes y
     * exports, por eso no se unifica): valor guardado => [ícono, color flux, etiqueta]. El primero es "sin marcar".
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function marcas(): array
    {
        return $this === self::Tejedores
            ? ['' => ['○', 'zinc', 'Sin marcar'], 'OK' => ['✓', 'green', 'Cumple'], 'X' => ['✗', 'red', 'No cumple'], 'M' => ['🔧', 'amber', 'Mantenimiento']]
            : ['0' => ['○', 'zinc', 'Sin marcar'], '1' => ['✓', 'green', 'Cumple'], '2' => ['✗', 'red', 'No cumple']];
    }

    /** Valor que se guarda al quitar la marca: Tejedores usa NULL, Urdido/Engomado '0'. */
    public function sinMarca(): ?string
    {
        return $this === self::Tejedores ? null : '0';
    }

    public function siguienteMarca(?string $actual): ?string
    {
        $valores = array_map('strval', array_keys($this->marcas()));
        $siguiente = $valores[(array_search((string) $actual, $valores, true) + 1) % count($valores)];

        return $siguiente === '' ? null : $siguiente;
    }

    /** Tejedores marca por telar; Urdido y Engomado tienen una sola columna (la máquina). */
    public function porTelar(): bool
    {
        return $this === self::Tejedores;
    }
}
