<?php

namespace App\Services\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevivirOrdenProgramaDesdeCatService
{
    /**
     * Limpia FechaFinaliza en cat, crea fila en ReqProgramaTejido al final del telar (o EnProceso si se pide).
     *
     * @return array{programa_id: int, cat_id: int}
     */
    public function ejecutar(int $catId, bool $colocarEnProceso = false): array
    {
        $dispatcher = ReqProgramaTejido::suppressObservers();

        try {
            return DB::transaction(function () use ($catId, $colocarEnProceso) {
                /** @var CatCodificados|null $cat */
                $cat = CatCodificados::query()->whereKey($catId)->lockForUpdate()->first();
                if (! $cat) {
                    throw ValidationException::withMessages([
                        'cat_id' => 'No existe el registro de codificación.',
                    ]);
                }

                $orden = trim((string) ($cat->OrdenTejido ?? ''));
                if ($orden === '') {
                    throw ValidationException::withMessages([
                        'OrdenTejido' => 'El registro no tiene orden de tejido; no se puede revivir al programa.',
                    ]);
                }

                $telarRaw = $cat->TelarId;
                $telarNorm = TelarSalonResolver::normalizeTelar($telarRaw !== null && $telarRaw !== '' ? (string) $telarRaw : '');
                if ($telarNorm === '') {
                    throw ValidationException::withMessages([
                        'TelarId' => 'El registro no tiene telar válido.',
                    ]);
                }

                $deptoRaw = trim((string) ($cat->Departamento ?? ''));
                if ($deptoRaw === '') {
                    throw ValidationException::withMessages([
                        'Departamento' => 'El registro no tiene departamento/salón.',
                    ]);
                }

                $salonNorm = TelarSalonResolver::normalizeSalon($deptoRaw, $telarNorm);

                $yaExiste = TelarSalonResolver::applyTelarFilter(
                    ReqProgramaTejido::query()->where('NoProduccion', $orden),
                    $salonNorm,
                    $telarNorm
                )->exists();

                if ($yaExiste) {
                    throw ValidationException::withMessages([
                        'NoProduccion' => 'Ya existe esta orden en el programa para el mismo salón y telar.',
                    ]);
                }

                $cat->FechaFinaliza = null;
                $cat->save();

                $posicion = $this->siguientePosicionAlFinal($salonNorm, $telarNorm);

                [$fechaInicio, $fechaFinal] = $this->resolverFechasParaNuevoRegistro($salonNorm, $telarNorm);

                $attrs = $this->mapearCatAPrograma($cat, $salonNorm, $telarNorm, $posicion, $fechaInicio, $fechaFinal, $colocarEnProceso);

                $programa = new ReqProgramaTejido($attrs);
                $programa->save();

                $idReal = $this->resolverIdRealPrograma($orden, $salonNorm, $telarNorm, $posicion);
                if ($idReal === null) {
                    throw ValidationException::withMessages([
                        'programa_id' => 'No fue posible confirmar el Id real del programa revivido.',
                    ]);
                }

                $programa->setAttribute('Id', $idReal);
                $programa->exists = true;
                $programa->syncOriginalAttribute('Id');

                if ($colocarEnProceso) {
                    TelarSalonResolver::applyTelarFilter(
                        ReqProgramaTejido::query(),
                        $salonNorm,
                        $telarNorm
                    )->update(['EnProceso' => 0]);

                    ReqProgramaTejido::query()
                        ->whereKey($idReal)
                        ->update(['EnProceso' => 1]);

                    $programa->EnProceso = true;
                }

                $formulas = TejidoHelpers::calcularFormulasEficienciaPorContexto(
                    $programa,
                    TejidoHelpers::FORMULAS_CTX_PEDIDO_INHERIT
                );
                if ($formulas !== []) {
                    $formulasParaGuardar = [];
                    foreach ($formulas as $campo => $valor) {
                        if ($valor !== null && in_array($campo, $programa->getFillable(), true)) {
                            $programa->setAttribute($campo, $valor);
                            $formulasParaGuardar[$campo] = $valor;
                        }
                    }
                    if ($formulasParaGuardar !== []) {
                        ReqProgramaTejido::query()
                            ->whereKey($idReal)
                            ->update($formulasParaGuardar);
                    }
                }

                ReqProgramaTejido::regenerarLineas([$programa]);

                return [
                    'programa_id' => (int) $programa->Id,
                    'cat_id' => (int) $cat->Id,
                ];
            });
        } finally {
            ReqProgramaTejido::restoreObservers($dispatcher);
        }
    }

    private function resolverIdRealPrograma(string $orden, string $salon, string $telar, int $posicion): ?int
    {
        $id = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query()
                ->where('NoProduccion', $orden)
                ->where('Posicion', $posicion)
                ->orderByDesc('Id'),
            $salon,
            $telar
        )->value('Id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Coloca la nueva orden después de la última posición (no rellena huecos).
     */
    private function siguientePosicionAlFinal(string $salon, string $telar): int
    {
        $max = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query(),
            $salon,
            $telar
        )->max('Posicion');

        return $max !== null ? (int) $max + 1 : 1;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverFechasParaNuevoRegistro(string $salon, string $telar): array
    {
        $ultimo = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query()
                ->orderByDesc('Posicion')
                ->orderByDesc('Id'),
            $salon,
            $telar
        )->first();

        if ($ultimo && ! empty($ultimo->FechaFinal)) {
            $inicio = Carbon::parse($ultimo->FechaFinal);
        } elseif ($ultimo && ! empty($ultimo->FechaInicio)) {
            $inicio = Carbon::parse($ultimo->FechaInicio)->addDay();
        } else {
            $inicio = Carbon::now();
        }

        $fin = $inicio->copy()->addDays(TejidoHelpers::DEFAULT_DURACION_DIAS);

        return [$inicio, $fin];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearCatAPrograma(
        CatCodificados $c,
        string $salonNorm,
        string $telarNorm,
        int $posicion,
        Carbon $fechaInicio,
        Carbon $fechaFinal,
        bool $enProceso
    ): array {
        $ahora = Carbon::now();
        $eficiencia = $this->parseEficiencia($c->EficienciaSTD ?? null);
        $velocidad = $this->parseEntero($c->VelocidadSTD ?? null);

        $tamano = trim((string) ($c->ClaveModelo ?? ''));

        $maquina = TejidoHelpers::construirMaquinaConSalon(null, $salonNorm, $telarNorm);

        $base = [
            'CreatedAt' => $ahora,
            'UpdatedAt' => $ahora,
            'EnProceso' => $enProceso,
            'SalonTejidoId' => $salonNorm,
            'NoTelarId' => $telarNorm,
            'Maquina' => $maquina,
            'Posicion' => $posicion,
            'NoProduccion' => trim((string) ($c->OrdenTejido ?? '')),
            'NombreProducto' => trim((string) ($c->Nombre ?? '')) ?: null,
            'TamanoClave' => $tamano !== '' ? $tamano : null,
            'ItemId' => trim((string) ($c->ItemId ?? '')) ?: null,
            'InventSizeId' => trim((string) ($c->InventSizeId ?? '')) ?: null,
            'FlogsId' => trim((string) ($c->FlogsId ?? '')) ?: null,
            'NombreProyecto' => trim((string) ($c->NombreProyecto ?? '')) ?: null,
            'CustName' => trim((string) ($c->CustName ?? '')) ?: null,
            'Peine' => $this->parseEntero($c->Peine ?? null),
            'Ancho' => $this->parseFloat($c->Ancho ?? null),
            'Luchaje' => $this->parseFloat($c->Luchaje ?? null),
            'PesoCrudo' => $this->parseFloat($c->P_crudo ?? null),
            'DobladilloId' => trim((string) ($c->DobladilloId ?? '')) ?: null,
            'MedidaPlano' => $this->parseFloat($c->MedidaPlano ?? null),
            'Rasurado' => $c->Razurada,
            'CalibreRizo' => $this->parseFloat($c->CalibreRizo ?? null),
            'CalibreRizo2' => $this->parseFloat($c->CalibreRizo2 ?? null),
            'CuentaRizo' => $c->CuentaRizo !== null && $c->CuentaRizo !== '' ? (string) $c->CuentaRizo : null,
            'FibraRizo' => trim((string) ($c->FibraRizo ?? '')) ?: null,
            'CalibrePie' => $this->parseFloat($c->CalibrePie ?? null),
            'CalibrePie2' => $this->parseFloat($c->CalibrePie2 ?? null),
            'CuentaPie' => $c->CuentaPie !== null && $c->CuentaPie !== '' ? (string) $c->CuentaPie : null,
            'FibraPie' => trim((string) ($c->FibraPie ?? '')) ?: null,
            'NoTiras' => $this->parseFloat($c->NoTiras ?? null),
            'Repeticiones' => $this->parseFloat($c->Repeticiones ?? null),
            'VelocidadSTD' => $velocidad ?? 0,
            'EficienciaSTD' => $eficiencia ?? 0.0,
            'CalibreTrama' => $this->parseFloat($c->Tra ?? null),
            'CalibreTrama2' => $this->parseFloat($c->CalibreTrama2 ?? null),
            'CodColorTrama' => trim((string) ($c->CodColorTrama ?? '')) ?: null,
            'ColorTrama' => trim((string) ($c->ColorTrama ?? '')) ?: null,
            'FibraTrama' => trim((string) ($c->FibraId ?? '')) ?: null,
            'PasadasTrama' => $this->parseFloat($c->PasadasTramaFondoC1 ?? null),
            'PasadasComb1' => $this->parseFloat($c->PasadasComb1 ?? null),
            'PasadasComb2' => $this->parseFloat($c->PasadasComb2 ?? null),
            'PasadasComb3' => $this->parseFloat($c->PasadasComb3 ?? null),
            'PasadasComb4' => $this->parseFloat($c->PasadasComb4 ?? null),
            'PasadasComb5' => $this->parseFloat($c->PasadasComb5 ?? null),
            'CalibreComb1' => $this->parseFloat($c->CalibreComb1 ?? null),
            'CalibreComb12' => $this->parseFloat($c->CalibreComb12 ?? null),
            'FibraComb1' => trim((string) ($c->FibraComb1 ?? '')) ?: null,
            'CodColorComb1' => trim((string) ($c->CodColorC1 ?? '')) ?: null,
            'NombreCC1' => trim((string) ($c->NomColorC1 ?? '')) ?: null,
            'CalibreComb2' => $this->parseFloat($c->CalibreComb2 ?? null),
            'CalibreComb22' => $this->parseFloat($c->CalibreComb22 ?? null),
            'FibraComb2' => trim((string) ($c->FibraComb2 ?? '')) ?: null,
            'CodColorComb2' => trim((string) ($c->CodColorC2 ?? '')) ?: null,
            'NombreCC2' => trim((string) ($c->NomColorC2 ?? '')) ?: null,
            'CalibreComb3' => $this->parseFloat($c->CalibreComb3 ?? null),
            'CalibreComb32' => $this->parseFloat($c->CalibreComb32 ?? null),
            'FibraComb3' => trim((string) ($c->FibraComb3 ?? '')) ?: null,
            'CodColorComb3' => trim((string) ($c->CodColorC3 ?? '')) ?: null,
            'NombreCC3' => trim((string) ($c->NomColorC3 ?? '')) ?: null,
            'CalibreComb4' => $this->parseFloat($c->CalibreComb4 ?? null),
            'CalibreComb42' => $this->parseFloat($c->CalibreComb42 ?? null),
            'FibraComb4' => trim((string) ($c->FibraComb4 ?? '')) ?: null,
            'CodColorComb4' => trim((string) ($c->CodColorC4 ?? '')) ?: null,
            'NombreCC4' => trim((string) ($c->NomColorC4 ?? '')) ?: null,
            'CalibreComb5' => $this->parseFloat($c->CalibreComb5 ?? null),
            'CalibreComb52' => $this->parseFloat($c->CalibreComb52 ?? null),
            'FibraComb5' => trim((string) ($c->FibraComb5 ?? '')) ?: null,
            'CodColorComb5' => trim((string) ($c->CodColorC5 ?? '')) ?: null,
            'NombreCC5' => trim((string) ($c->NomColorC5 ?? '')) ?: null,
            'TotalPedido' => $this->parseFloat($c->Pedido ?? $c->Cantidad ?? null),
            'Produccion' => $this->parseFloat($c->Produccion ?? null),
            'SaldoPedido' => $this->parseFloat($c->Saldos ?? null),
            'SaldoMarbete' => $this->parseFloat($c->NoMarbete ?? null),
            'MtsRollo' => $this->parseFloat($c->MtsRollo ?? null),
            'PzasRollo' => $this->parseFloat($c->PzasRollo ?? null),
            'TotalRollos' => $this->parseFloat($c->TotalRollos ?? null),
            'TotalPzas' => $this->parseFloat($c->TotalPzas ?? null),
            'CombinaTram' => trim((string) ($c->CombinaTram ?? '')) ?: null,
            'BomId' => trim((string) ($c->BomId ?? '')) ?: null,
            'BomName' => trim((string) ($c->BomName ?? '')) ?: null,
            'CreaProd' => $c->CreaProd,
            'Densidad' => $this->parseFloat($c->Densidad ?? null),
            'HiloAX' => trim((string) ($c->HiloAX ?? '')) ?: null,
            'ActualizaLmat' => $c->ActualizaLmat,
            'PesoMuestra' => $this->parseFloat($c->PesoMuestra ?? null),
            'OrdCompartida' => $this->parseEntero($c->OrdCompartida ?? null),
            'OrdCompartidaLider' => $this->parseBool($c->OrdCompartidaLider ?? null),
            'OrdPrincipal' => $this->parseEntero($c->OrdPrincipal ?? null),
            'CategoriaCalidad' => trim((string) ($c->CategoriaCalidad ?? '')) ?: null,
            'Prioridad' => $c->Prioridad !== null && $c->Prioridad !== '' ? (string) $c->Prioridad : null,
            'CambioHilo' => trim((string) ($c->CambioRepaso ?? '')) ?: null,
            'Observaciones' => trim((string) ($c->Obs5 ?? '')) ?: null,
            'FechaInicio' => $fechaInicio->format('Y-m-d H:i:s'),
            'FechaFinal' => $fechaFinal->format('Y-m-d H:i:s'),
            'FechaArranque' => null,
            'FechaFinaliza' => null,
            'Ultimo' => null,
            'Programado' => null,
            'ProgramarProd' => null,
            'Reprogramar' => null,
        ];

        if ($tamano !== '') {
            $datosModelo = TejidoHelpers::obtenerDatosModeloCodificadoArray($tamano, $salonNorm);
            if (is_array($datosModelo)) {
                foreach (['AnchoToalla', 'LargoToalla'] as $campoModelo) {
                    if (
                        (empty($base[$campoModelo]) || $base[$campoModelo] === 0)
                        && array_key_exists($campoModelo, $datosModelo)
                        && $datosModelo[$campoModelo] !== null && $datosModelo[$campoModelo] !== ''
                    ) {
                        $base[$campoModelo] = $datosModelo[$campoModelo];
                    }
                }
                if (($base['NombreProducto'] ?? '') === '' && ! empty($datosModelo['Nombre'])) {
                    $base['NombreProducto'] = trim((string) $datosModelo['Nombre']);
                }
                if (($velocidad === null || $velocidad === 0) && isset($datosModelo['VelocidadSTD'])) {
                    $v = $this->parseEntero($datosModelo['VelocidadSTD']);
                    if ($v !== null) {
                        $base['VelocidadSTD'] = $v;
                    }
                }
            }
        }

        $fillable = (new ReqProgramaTejido)->getFillable();
        $out = [];
        foreach ($base as $k => $v) {
            if (in_array($k, $fillable, true)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function parseFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $s = str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', (string) $v) ?? '');

        return $s !== '' && is_numeric($s) ? (float) $s : null;
    }

    private function parseEntero(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }

    private function parseEficiencia(mixed $v): ?float
    {
        $f = $this->parseFloat($v);
        if ($f === null) {
            return null;
        }
        if ($f > 1) {
            return $f / 100;
        }

        return $f;
    }

    private function parseBool(mixed $v): ?bool
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_bool($v)) {
            return $v;
        }
        $n = (int) $v;

        return $n === 1 ? true : ($n === 0 ? false : null);
    }
}
