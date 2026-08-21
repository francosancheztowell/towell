<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejedores\TelTelaresOperador;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Calculos que la captura de desarrollador y la de muestras hacian por duplicado,
 * byte a byte, en dos archivos distintos. No tocan base de datos: solo normalizan
 * horas, codigos y longitudes.
 *
 * Tenerlos en un solo sitio es lo que evita que se repita lo que ya paso: el fork
 * de muestras se quedo sin la regla de longitud del codigo y sin la normalizacion
 * de eficiencias porque las correcciones se aplicaron una sola vez.
 */
trait ArmaDatosDesarrollador
{
    /**
     * Ancla una hora capturada al dia al que realmente pertenece.
     *
     * Con Carbon::today() la hora se pegaba siempre al dia del servidor: el turno 3
     * captura 23:50 y si envia pasada la medianoche el registro quedaba sellado al dia
     * siguiente. Se elige la ocurrencia de esa hora mas cercana a ahora, que para una
     * jornada de 8 horas es siempre la correcta.
     */
    private function anclarAlDiaMasCercano(?Carbon $momento): ?Carbon
    {
        if (! $momento) {
            return null;
        }

        $ahora = Carbon::now();

        if ($momento->greaterThan($ahora->copy()->addHours(12))) {
            return $momento->copy()->subDay();
        }

        if ($momento->lessThan($ahora->copy()->subHours(12))) {
            return $momento->copy()->addDay();
        }

        return $momento;
    }

    private function construirFechaInicioProgramada(?string $horaFinal): ?string
    {
        if (empty($horaFinal)) {
            return null;
        }

        try {
            $horaFinalCarbon = Carbon::createFromFormat('H:i', $horaFinal);

            $momento = Carbon::today()->setTimeFromTimeString($horaFinalCarbon->format('H:i'));

            return $this->anclarAlDiaMasCercano($momento)?->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function normalizarLongitudLucha($longitudLuchaRaw): ?int
    {
        return $longitudLuchaRaw !== null && $longitudLuchaRaw !== ''
            ? (int) round((float) $longitudLuchaRaw)
            : null;
    }

    private function normalizeCodigoDibujo(?string $value, ?string $telarId = null): string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/\.(?:JC5|JCS)$/i', '')
            ->toString();

        if ($normalized === '') {
            return '';
        }

        $suffix = $this->resolverSufijoCodigoPorTelar($telarId);

        return $suffix === '' ? $normalized : ($normalized.'.'.$suffix);
    }

    private function resolverSufijoCodigoPorTelar(?string $telarId): string
    {
        $n = (int) trim((string) ($telarId ?? ''));
        if ($n >= 300) {
            return '';
        }

        return 'JC5';
    }

    private function calcularMinutosCambio(?string $horaInicio, ?string $horaFinal): ?int
    {
        if (! $horaInicio || ! $horaFinal) {
            return null;
        }
        try {
            $inicio = Carbon::createFromFormat('H:i', $horaInicio);
            $final = Carbon::createFromFormat('H:i', $horaFinal);
            if ($final->lt($inicio)) {
                $final->addDay();
            }

            return max(0, $inicio->diffInMinutes($final));
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Clase del modelo de programa sobre la que opera cada pantalla:
     * ReqProgramaTejido para la captura normal, Muestras para la de muestras.
     * Muestras hereda de ReqProgramaTejido, asi que los type hints usan el padre.
     *
     * @return class-string<ReqProgramaTejido>
     */
    abstract protected function modeloPrograma(): string;

    private function resolverContextoDestino(array $validated, ReqProgramaTejido $programaOrigen): array
    {
        $salonOrigen = trim((string) ($programaOrigen->SalonTejidoId ?? ''));
        $telarOrigen = trim((string) ($programaOrigen->NoTelarId ?? ''));
        $esCambioTelar = (bool) ($validated['CambioTelarActivo'] ?? false);

        $salonDestino = $salonOrigen;
        $telarDestino = $telarOrigen;

        if ($esCambioTelar) {
            $rawDestino = trim((string) ($validated['TelarDestino'] ?? ''));
            $partes = explode('|', $rawDestino, 2);

            if (count($partes) !== 2) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El formato de telar destino es invalido.',
                ]);
            }

            $salonDestino = trim((string) ($partes[0] ?? ''));
            $telarDestino = trim((string) ($partes[1] ?? ''));

            if ($salonDestino === '' || $telarDestino === '') {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'Debes seleccionar un telar destino valido.',
                ]);
            }

            if ($salonDestino === $salonOrigen && $telarDestino === $telarOrigen) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El telar destino debe ser diferente al telar origen.',
                ]);
            }

            $existeDestino = ($this->modeloPrograma())::query()
                ->where('SalonTejidoId', $salonDestino)
                ->where('NoTelarId', $telarDestino)
                ->exists();

            if (! $existeDestino) {
                $existeDestino = TelTelaresOperador::query()
                    ->where('NoTelarId', $telarDestino)
                    ->where(function ($query) use ($salonDestino) {
                        $query->whereNull('SalonTejidoId')
                            ->orWhere('SalonTejidoId', $salonDestino);
                    })
                    ->exists();
            }

            if (! $existeDestino) {
                throw ValidationException::withMessages([
                    'TelarDestino' => 'El telar destino seleccionado no existe o no esta disponible.',
                ]);
            }
        }

        return [
            'esCambioTelar' => $esCambioTelar,
            'salonOrigen' => $salonOrigen,
            'telarOrigen' => $telarOrigen,
            'salonDestino' => $salonDestino,
            'telarDestino' => $telarDestino,
        ];
    }

    private function resolverModeloDestinoYCopiaSiAplica(
        ReqProgramaTejido $programaOrigen,
        array $contextoDestino
    ): ?ReqModelosCodificados {
        $tamanoClave = trim((string) ($programaOrigen->TamanoClave ?? ''));
        if ($tamanoClave === '') {
            return null;
        }

        $salonDestino = $contextoDestino['salonDestino'];
        $telarDestino = $contextoDestino['telarDestino'];

        $modeloDestino = ReqModelosCodificados::query()
            ->where('TamanoClave', $tamanoClave)
            ->where('SalonTejidoId', $salonDestino)
            ->first();

        if ($modeloDestino) {
            return $modeloDestino;
        }

        if (! $contextoDestino['esCambioTelar']) {
            return null;
        }

        $salonOrigen = $contextoDestino['salonOrigen'];
        $modeloOrigen = ReqModelosCodificados::query()
            ->where('TamanoClave', $tamanoClave)
            ->where('SalonTejidoId', $salonOrigen)
            ->first();

        if (! $modeloOrigen) {
            $modeloOrigen = ReqModelosCodificados::query()
                ->where('TamanoClave', $tamanoClave)
                ->first();
        }

        if (! $modeloOrigen) {
            return null;
        }

        $nuevoModelo = $modeloOrigen->replicate();
        $columnasModelo = Schema::getColumnListing($nuevoModelo->getTable());

        if (in_array('SalonTejidoId', $columnasModelo, true)) {
            $nuevoModelo->SalonTejidoId = $salonDestino;
        }
        if (in_array('NoTelarId', $columnasModelo, true)) {
            $nuevoModelo->NoTelarId = $telarDestino;
        }
        if (in_array('OrdenTejido', $columnasModelo, true)) {
            $nuevoModelo->OrdenTejido = $programaOrigen->NoProduccion;
        }
        if (in_array('CodigoDibujo', $columnasModelo, true)) {
            $nuevoModelo->CodigoDibujo = null;
        }
        if (in_array('CodificacionModelo', $columnasModelo, true)) {
            $nuevoModelo->CodificacionModelo = null;
        }

        $nuevoModelo->save();

        return $nuevoModelo;
    }

    private function actualizarProgramaAntesDeMovimiento(
        ReqProgramaTejido $programaOrigen,
        ?ReqModelosCodificados $modeloDestino,
        array $contextoDestino
    ): void {
        if (! $contextoDestino['esCambioTelar']) {
            return;
        }

        [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar(
            $programaOrigen,
            $modeloDestino,
            $contextoDestino['telarDestino'],
            $contextoDestino['salonDestino']
        );

        if (! is_null($nuevaEficiencia)) {
            $programaOrigen->EficienciaSTD = round((float) $nuevaEficiencia, 2);
        }
        if (! is_null($nuevaVelocidad)) {
            $programaOrigen->VelocidadSTD = (float) $nuevaVelocidad;
        }

        $programaOrigen->Maquina = TejidoHelpers::construirMaquinaConSalon(
            $programaOrigen->Maquina ?? null,
            $contextoDestino['salonDestino'],
            $contextoDestino['telarDestino']
        );

        if ($modeloDestino) {
            if (! is_null($modeloDestino->CuentaRizo)) {
                $programaOrigen->CuentaRizo = (string) $modeloDestino->CuentaRizo;
            }
            if (! is_null($modeloDestino->CuentaPie)) {
                $programaOrigen->CuentaPie = (string) $modeloDestino->CuentaPie;
            }
        }

        $programaOrigen->saveQuietly();
    }

    private function actualizarProgramasRelacionados(
        ReqProgramaTejido $programaInicial,
        $fuenteDatos,
        ?string $fechaInicioProgramada
    ): Collection {
        $programas = collect();

        if (! empty($programaInicial->OrdCompartida)) {
            $programas = ($this->modeloPrograma())::query()
                ->where('OrdCompartida', (int) $programaInicial->OrdCompartida)
                ->lockForUpdate()
                ->get();
        } else {
            $programas = ($this->modeloPrograma())::query()
                ->where('NoProduccion', $programaInicial->NoProduccion)
                ->where('SalonTejidoId', $programaInicial->SalonTejidoId)
                ->lockForUpdate()
                ->get();
        }

        if ($programas->isEmpty()) {
            return collect([$programaInicial]);
        }

        if (! $fuenteDatos) {
            return $programas;
        }

        $columnasPrograma = Schema::getColumnListing($programas->first()->getTable());
        $payloadPrograma = [
            'CalibreTrama' => $fuenteDatos->Tra ?? $fuenteDatos->CalibreTrama ?? null,
            'CalibreTrama2' => $fuenteDatos->CalibreTrama2 ?? null,
            'FibraTrama' => $fuenteDatos->FibraId ?? $fuenteDatos->FibraTrama ?? null,
            'PasadasTrama' => $fuenteDatos->PasadasTramaFondoC1 ?? $fuenteDatos->PasadasTrama ?? null,
            'PasadasComb1' => $fuenteDatos->PasadasComb1 ?? null,
            'PasadasComb2' => $fuenteDatos->PasadasComb2 ?? null,
            'PasadasComb3' => $fuenteDatos->PasadasComb3 ?? null,
            'PasadasComb4' => $fuenteDatos->PasadasComb4 ?? null,
            'PasadasComb5' => $fuenteDatos->PasadasComb5 ?? null,
            'CodColorTrama' => $fuenteDatos->CodColorTrama ?? null,
            'ColorTrama' => $fuenteDatos->ColorTrama ?? null,
            'CalibreComb1' => $fuenteDatos->CalibreComb1 ?? null,
            'CalibreComb12' => $fuenteDatos->CalibreComb12 ?? null,
            'FibraComb1' => $fuenteDatos->FibraComb1 ?? null,
            'CodColorComb1' => $fuenteDatos->CodColorC1 ?? $fuenteDatos->CodColorComb1 ?? null,
            'NombreCC1' => $fuenteDatos->NomColorC1 ?? $fuenteDatos->NombreCC1 ?? null,
            'CalibreComb2' => $fuenteDatos->CalibreComb2 ?? null,
            'CalibreComb22' => $fuenteDatos->CalibreComb22 ?? null,
            'FibraComb2' => $fuenteDatos->FibraComb2 ?? null,
            'CodColorComb2' => $fuenteDatos->CodColorC2 ?? $fuenteDatos->CodColorComb2 ?? null,
            'NombreCC2' => $fuenteDatos->NomColorC2 ?? $fuenteDatos->NombreCC2 ?? null,
            'CalibreComb3' => $fuenteDatos->CalibreComb3 ?? null,
            'CalibreComb32' => $fuenteDatos->CalibreComb32 ?? null,
            'FibraComb3' => $fuenteDatos->FibraComb3 ?? null,
            'CodColorComb3' => $fuenteDatos->CodColorC3 ?? $fuenteDatos->CodColorComb3 ?? null,
            'NombreCC3' => $fuenteDatos->NomColorC3 ?? $fuenteDatos->NombreCC3 ?? null,
            'CalibreComb4' => $fuenteDatos->CalibreComb4 ?? null,
            'CalibreComb42' => $fuenteDatos->CalibreComb42 ?? null,
            'FibraComb4' => $fuenteDatos->FibraComb4 ?? null,
            'CodColorComb4' => $fuenteDatos->CodColorC4 ?? $fuenteDatos->CodColorComb4 ?? null,
            'NombreCC4' => $fuenteDatos->NomColorC4 ?? $fuenteDatos->NombreCC4 ?? null,
            'CalibreComb5' => $fuenteDatos->CalibreComb5 ?? null,
            'CalibreComb52' => $fuenteDatos->CalibreComb52 ?? null,
            'FibraComb5' => $fuenteDatos->FibraComb5 ?? null,
            'CodColorComb5' => $fuenteDatos->CodColorC5 ?? $fuenteDatos->CodColorComb5 ?? null,
            'NombreCC5' => $fuenteDatos->NomColorC5 ?? $fuenteDatos->NombreCC5 ?? null,
        ];

        if ($fechaInicioProgramada) {
            $payloadPrograma['FechaInicio'] = $fechaInicioProgramada;
        }

        /** @var ReqProgramaTejido $programa */
        foreach ($programas as $programa) {
            foreach ($payloadPrograma as $column => $value) {
                if (! in_array($column, $columnasPrograma, true)) {
                    continue;
                }
                $programa->setAttribute($column, $value);
            }
            $programa->saveQuietly();
        }

        return ($this->modeloPrograma())::query()
            ->whereIn('Id', $programas->pluck('Id')->all())
            ->get();
    }

    private function enviarNotificacion(
        array $validated,
        ReqProgramaTejido $programa,
        string $codigoDibujo,
        array $contextoDestino,
        ?string $codigoDibujoAnterior = null
    ): void {
        $payload = $validated;
        $payload['NoTelarId'] = $contextoDestino['telarDestino'];

        if (! empty($contextoDestino['esCambioTelar'])) {
            $payload['CambioTelarActivo'] = true;
            $payload['NoTelarOrigen'] = $contextoDestino['telarOrigen'];
            $payload['SalonOrigen'] = $contextoDestino['salonOrigen'];
            $payload['NoTelarDestino'] = $contextoDestino['telarDestino'];
            $payload['SalonDestino'] = $contextoDestino['salonDestino'];
            if ($codigoDibujoAnterior) {
                $payload['CodigoDibujoAnterior'] = $codigoDibujoAnterior;
            }
        }

        $this->telegramService->enviarProcesoCompletado($payload, $programa, $codigoDibujo);
    }
}
