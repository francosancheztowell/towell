<?php

namespace App\Services\Tejido\InventarioTrama;

use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Helpers\TurnoHelper;
use App\Models\Tejido\TejTrama;
use App\Models\Tejido\TejTramaConsumos;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NuevoRequerimientoService
{
    private const PROGRAMA_TABLE = 'ReqProgramaTejido';

    private const SALON_KARL_MAYER = 'KARL MAYER';

    /**
     * View-model que consume el Blade.
     *
     * @return array<string, mixed>
     */
    public function construirVm(?string $folioEditar): array
    {
        $hoyMx = Carbon::now('America/Mexico_City');
        $turno = TurnoHelper::getTurnoActual();

        $usuario = Auth::user();

        if ($folioEditar) {
            $folioInicial = $folioEditar;
        } else {
            // El folio nace guardado: entrar sin parámetro lo crea (leer+incrementar
            // en una transacción, sin sugerido+increment por separado que duplica
            // folios con doble clic).
            $folioInicial = DB::transaction(function () use ($hoyMx, $turno, $usuario) {
                $sec = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', 'Trama')->lockForUpdate()->first();
                $folio = $sec
                    ? ($sec->prefijo ?? $sec->Prefijo ?? 'TR').str_pad((string) ($sec->consecutivo ?? $sec->Consecutivo ?? 0), 5, '0', STR_PAD_LEFT)
                    : '';
                if ($folio === '') {
                    $folio = 'TR'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                } else {
                    DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', 'Trama')->increment('consecutivo');
                }
                TejTrama::create([
                    'Folio' => $folio,
                    'Fecha' => $hoyMx->toDateString(),
                    'Status' => 'En Proceso',
                    'Turno' => $turno,
                    'numero_empleado' => $usuario->numero_empleado ?? '',
                    'nombreEmpl' => $usuario->nombre ?? '',
                ]);

                return $folio;
            });
        }

        $enProceso = TejTrama::where('Folio', $folioInicial)->first();

        $vm = [
            'pageTitle' => $folioEditar ? 'Editar Requerimiento' : 'Nuevo Requerimiento',
            'folio' => $folioInicial,
            'fecha' => $folioEditar ? null : $hoyMx->toDateString(),
            'turnoDesc' => $folioEditar ? null : TurnoHelper::getTurnoFormato($turno),
            'enProcesoExists' => (bool) $enProceso,
            'consultaUrl' => route('tejido.inventario.trama.consultar.requerimiento'),
            'actualizarCantidadUrl' => route('modulo.nuevo.requerimiento.actualizar.cantidad'),
            'guardarUrl' => route('modulo.nuevo.requerimiento.store'),
            'listaTelares' => [],
            'telares' => [],
        ];

        if ($folioEditar) {
            $this->construirEdicion($vm, $folioEditar);
        } else {
            $this->construirNuevo($vm);
            // ponytail: el folio nace con sus líneas del programa en 0 (un INSERT),
            // consultar ya no llega vacío y no hay botón verde que picar
            $this->sembrarConsumos($folioInicial, $this->consumosDesdeTelares($vm['telares']));
        }

        $vm['telares'] = array_values($vm['telares']);

        return $vm;
    }

    /**
     * Sincroniza los consumos del folio y devuelve el estado persistido.
     *
     * @param  array<int, array<string, mixed>>  $consumos
     * @return array{folio: string, turno: string, consumos: array<int, array<string, mixed>>}
     */
    public function guardar(array $consumos, ?string $folio = null): array
    {
        return DB::transaction(function () use ($consumos, $folio) {
            $turno = TurnoHelper::getTurnoActual();
            $fecha = Carbon::now('America/Mexico_City')->toDateString();
            $proporcionado = trim((string) ($folio ?? ''));

            $folioFinal = $this->resolverFolio($proporcionado, $turno, $fecha);
            $this->sincronizarConsumos($folioFinal, $consumos);

            return [
                'folio' => $folioFinal,
                'turno' => $turno,
                'consumos' => $this->consumosDelFolio($folioFinal),
            ];
        });
    }

    /** Devuelve la cantidad persistida, o null si el consumo no existe. */
    public function actualizarCantidad(int $id, float $cantidad): ?float
    {
        $consumo = TejTramaConsumos::find($id);
        if (! $consumo) {
            return null;
        }

        $consumo->Cantidad = $cantidad;
        $consumo->save();

        return (float) $consumo->Cantidad;
    }

    // =========================================================
    // Construcción del VM
    // =========================================================

    /** @param array<string, mixed> $vm */
    private function construirNuevo(array &$vm): void
    {
        $telares = $this->listarTelares();
        $vm['listaTelares'] = array_column($telares, 'numero');

        $porNumero = $this->cargarPrograma($telares);

        foreach ($telares as $telar) {
            $esKm = TelarSalonResolver::esKarlMayer($telar['salon'], $telar['numero']);
            $pt = $this->resolverEnProceso($porNumero, $telar['salon'], $telar['numero']);
            $telarData = $this->mapTelarData($pt);

            $rows = $esKm
                ? $this->buildBarrasFromTelarData($telarData)
                : $this->buildRowsFromTelarData($telarData);

            $ordenSig = null;
            if (! empty($telarData['Inicio_Tejido'])) {
                $ordenSig = $this->resolverOrdenSiguiente($porNumero, $telar['salon'], $telar['numero'], $telarData['Inicio_Tejido']);
            }

            $vm['telares'][] = $this->armarTelar($telar, $esKm, $telarData, $rows, $ordenSig);
        }
    }

    /** @param array<string, mixed> $vm */
    private function construirEdicion(array &$vm, string $folio): void
    {
        $cabecera = DB::table('TejTrama')->where('Folio', $folio)->first();
        if (! $cabecera) {
            return;
        }

        $vm['folio'] = $cabecera->Folio ?? $vm['folio'];
        $vm['fecha'] = $cabecera->Fecha ?? $vm['fecha'];
        if (! empty($cabecera->Turno)) {
            $vm['turnoDesc'] = TurnoHelper::getTurnoFormato($cabecera->Turno);
        }

        $detalles = DB::table('TejTramaConsumos')->where('Folio', $folio)->get();
        $consumosPorTelar = [];
        foreach ($detalles as $d) {
            $telar = trim((string) ($d->NoTelarId ?? ''));
            if (! isset($consumosPorTelar[$telar])) {
                $salon = TelarSalonResolver::normalizeSalon((string) ($d->SalonTejidoId ?? ''), $telar);
                $consumosPorTelar[$telar] = [
                    'salon' => $salon ?: 'JACQUARD',
                    'orden' => (string) ($d->NoProduccion ?? ''),
                    'producto' => (string) ($d->NombreProducto ?? ''),
                    'items' => [],
                ];
            }
            $consumosPorTelar[$telar]['items'][] = [
                'id' => $d->Id,
                'barra' => null,
                'calibre' => $d->CalibreTrama,
                'fibra' => $d->FibraTrama ?: null,
                'cod_color' => $d->CodColorTrama ?: null,
                'color' => $d->ColorTrama ?: null,
                'cantidad' => (float) ($d->Cantidad ?? 0),
            ];
        }

        $vm['listaTelares'] = array_keys($consumosPorTelar);

        $telares = [];
        foreach ($consumosPorTelar as $numero => $info) {
            $telares[] = ['numero' => (string) $numero, 'salon' => $info['salon']];
        }

        $porNumero = $this->cargarPrograma($telares);

        foreach ($consumosPorTelar as $numero => $info) {
            $numero = (string) $numero;
            $esKm = TelarSalonResolver::esKarlMayer($info['salon'], $numero);

            $telarData = $this->mapTelarData(null, [
                'Orden_Prod' => $info['orden'],
                'Nombre_Producto' => $info['producto'],
                'Inicio_Tejido' => $cabecera->Fecha ?? null,
            ]);

            $pt = $this->resolverEnProceso($porNumero, $info['salon'], $numero);
            if ($pt) {
                $telarData = array_merge($telarData, $this->mapTelarData($pt));
            }

            $rows = $info['items'];
            $exist = [];
            $existBarras = [];
            if ($esKm) {
                // ponytail: barra derivada del programa vigente (columnas Barra1-4), no persistida
                $mapa = $this->mapaBarras($telarData);
                foreach ($rows as &$r) {
                    $r['barra'] = $mapa[$this->claveMaterial($r['calibre'] ?? null, $r['fibra'] ?? null, $r['cod_color'] ?? null, $r['color'] ?? null) ?? ''] ?? null;
                    if ($r['barra'] !== null) {
                        $existBarras['B'.$r['barra']] = true;
                    }
                }
                unset($r);
            } else {
                foreach ($rows as $r) {
                    if ($r['calibre'] !== null) {
                        $exist[number_format((float) $r['calibre'], 2)] = true;
                    }
                }
            }

            $faltantes = $esKm
                ? $this->buildBarrasFromTelarData($telarData, $existBarras)
                : $this->buildRowsFromTelarData($telarData, $exist);

            $vm['telares'][] = $this->armarTelar(
                ['numero' => $numero, 'salon' => $info['salon']],
                $esKm,
                $telarData,
                array_merge($rows, $faltantes),
                null
            );
        }
    }

    /**
     * @param  array{numero: string, salon: string}  $telar
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function armarTelar(array $telar, bool $esKm, array $telarData, array $rows, ?object $ordenSig): array
    {
        return [
            'numero' => $telar['numero'],
            'salon' => $telar['salon'],
            'tipo' => $this->tipoUi($telar['salon']),
            'es_karl_mayer' => $esKm,
            'telarData' => $telarData,
            'ordenSig' => $ordenSig,
            'rows' => array_values($rows),
        ];
    }

    /** @return array<int, array{numero: string, salon: string}> */
    private function listarTelares(): array
    {
        $rank = ['JACQUARD' => 1, 'ITEMA' => 2, 'SMIT' => 2, 'SULZER' => 3];

        $ordenados = DB::table('InvSecuenciaTrama')
            ->select('NoTelar', 'TipoTelar', 'Secuencia')
            ->get()
            ->sortBy(fn ($r) => [
                $rank[strtoupper(trim((string) $r->TipoTelar))] ?? 4,
                (int) $r->Secuencia,
            ])
            ->values();

        $telares = [];
        foreach ($ordenados as $r) {
            $numero = trim((string) $r->NoTelar);
            if ($numero === '') {
                continue;
            }
            $telares[$numero] = [
                'numero' => $numero,
                'salon' => TelarSalonResolver::normalizeSalon((string) $r->TipoTelar, $numero),
            ];
        }

        $km = DB::table(self::PROGRAMA_TABLE)
            ->whereIn('SalonTejidoId', TelarSalonResolver::salonAliases(self::SALON_KARL_MAYER))
            ->distinct()
            ->orderBy('NoTelarId')
            ->pluck('NoTelarId');

        foreach ($km as $raw) {
            $numero = trim((string) $raw);
            if ($numero === '' || isset($telares[$numero])) {
                continue;
            }
            $telares[$numero] = ['numero' => $numero, 'salon' => self::SALON_KARL_MAYER];
        }

        return array_values($telares);
    }

    /**
     * Una sola consulta con el programa de todos los telares.
     *
     * @param  array<int, array{numero: string, salon: string}>  $telares
     * @return Collection<string, Collection<int, object>>
     */
    private function cargarPrograma(array $telares): Collection
    {
        if ($telares === []) {
            return collect();
        }

        $candidatos = [];
        $salones = [];
        foreach ($telares as $t) {
            foreach (TelarSalonResolver::salonAliases($t['salon'], $t['numero']) as $alias) {
                $salones[$alias] = true;
            }
            foreach ($this->candidatosDe($t['salon'], $t['numero']) as $c) {
                $candidatos[$c] = true;
            }
        }

        return DB::table(self::PROGRAMA_TABLE)
            ->whereIn('SalonTejidoId', array_keys($salones))
            ->whereIn('NoTelarId', array_keys($candidatos))
            ->select($this->selectPrograma())
            ->get()
            ->groupBy(fn ($r) => trim((string) $r->NoTelarId));
    }

    /** @return array<int, string> */
    private function candidatosDe(string $salon, string $numero): array
    {
        $candidatos = [$numero];

        if (TelarSalonResolver::normalizeSalon($salon, $numero) === 'SMIT' && ctype_digit($numero)) {
            $candidatos[] = (string) (100 + ((int) $numero % 100));
        }

        return array_values(array_unique($candidatos));
    }

    private function resolverEnProceso(Collection $porNumero, string $salon, string $numero): ?object
    {
        $candidatos = $this->candidatosDe($salon, $numero);
        $preferido = $candidatos[0];

        $filas = collect($candidatos)->flatMap(fn ($c) => $porNumero[$c] ?? collect());

        return $filas
            ->filter(fn ($r) => (int) $r->EnProceso === 1)
            ->sortBy(fn ($r) => [
                trim((string) $r->NoTelarId) === $preferido ? 0 : 1,
                -1 * (int) strtotime((string) $r->FechaInicio),
                -1 * (int) $r->Id,
            ])
            ->first();
    }

    private function resolverOrdenSiguiente(Collection $porNumero, string $salon, string $numero, string $inicio): ?object
    {
        $candidatos = $this->candidatosDe($salon, $numero);
        $filas = collect($candidatos)->flatMap(fn ($c) => $porNumero[$c] ?? collect());

        return $filas
            ->filter(fn ($r) => (int) $r->EnProceso === 0)
            ->filter(fn ($r) => empty($r->FechaInicio) || (string) $r->FechaInicio > $inicio)
            ->sortBy(fn ($r) => (string) $r->FechaInicio)
            ->first();
    }

    /** @return array<int, string> */
    private function selectPrograma(): array
    {
        return [
            'Id', 'NoTelarId', 'SalonTejidoId', 'EnProceso', 'Posicion', 'FechaInicio', 'FechaFinal',
            'NoProduccion as Orden_Prod', 'FlogsId as Id_Flog', 'CategoriaCalidad as Calidad',
            'CustName as Cliente', 'TamanoClave as InventSizeId', 'ItemId',
            'NombreProducto as Nombre_Producto', 'TotalPedido as Saldos', 'Produccion',
            'CalibreTrama as CALIBRE_TRA', 'FibraTrama as FIBRA_TRA',
            'CodColorTrama as CODIGO_COLOR_TRAMA', 'ColorTrama as COLOR_TRAMA',
            'CalibreComb1 as CALIBRE_C1', 'FibraComb1 as FIBRA_C1', 'CodColorComb1 as CODIGO_COLOR_C1', 'NombreCC1 as COLOR_C1',
            'CalibreComb2 as CALIBRE_C2', 'FibraComb2 as FIBRA_C2', 'CodColorComb2 as CODIGO_COLOR_C2', 'NombreCC2 as COLOR_C2',
            'CalibreComb3 as CALIBRE_C3', 'FibraComb3 as FIBRA_C3', 'CodColorComb3 as CODIGO_COLOR_C3', 'NombreCC3 as COLOR_C3',
            'CalibreComb4 as CALIBRE_C4', 'FibraComb4 as FIBRA_C4', 'CodColorComb4 as CODIGO_COLOR_C4', 'NombreCC4 as COLOR_C4',
            'CalibreComb5 as CALIBRE_C5', 'FibraComb5 as FIBRA_C5', 'CodColorComb5 as CODIGO_COLOR_C5', 'NombreCC5 as COLOR_C5',
            'CalibreBarra1 as CALIBRE_B1', 'FibraBarra1 as FIBRA_B1', 'CodColorBarra1 as CODIGO_COLOR_B1', 'ColorBarra1 as COLOR_B1',
            'CalibreBarra2 as CALIBRE_B2', 'FibraBarra2 as FIBRA_B2', 'CodColorBarra2 as CODIGO_COLOR_B2', 'ColorBarra2 as COLOR_B2',
            'CalibreBarra3 as CALIBRE_B3', 'FibraBarra3 as FIBRA_B3', 'CodColorBarra3 as CODIGO_COLOR_B3', 'ColorBarra3 as COLOR_B3',
            'CalibreBarra4 as CALIBRE_B4', 'FibraBarra4 as FIBRA_B4', 'CodColorBarra4 as CODIGO_COLOR_B4', 'ColorBarra4 as COLOR_B4',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mapTelarData(?object $pt, array $overrides = []): array
    {
        $base = [
            'Orden_Prod' => null, 'Id_Flog' => null, 'Calidad' => null, 'Cliente' => null,
            'InventSizeId' => null, 'ItemId' => null, 'Nombre_Producto' => null,
            'Saldos' => null, 'Produccion' => null, 'Inicio_Tejido' => null, 'Fin_Tejido' => null,
            'CALIBRE_TRA' => null, 'FIBRA_TRA' => null, 'CODIGO_COLOR_TRAMA' => null, 'COLOR_TRAMA' => null,
            'CALIBRE_C1' => null, 'FIBRA_C1' => null, 'CODIGO_COLOR_C1' => null, 'COLOR_C1' => null,
            'CALIBRE_C2' => null, 'FIBRA_C2' => null, 'CODIGO_COLOR_C2' => null, 'COLOR_C2' => null,
            'CALIBRE_C3' => null, 'FIBRA_C3' => null, 'CODIGO_COLOR_C3' => null, 'COLOR_C3' => null,
            'CALIBRE_C4' => null, 'FIBRA_C4' => null, 'CODIGO_COLOR_C4' => null, 'COLOR_C4' => null,
            'CALIBRE_C5' => null, 'FIBRA_C5' => null, 'CODIGO_COLOR_C5' => null, 'COLOR_C5' => null,
            'CALIBRE_B1' => null, 'FIBRA_B1' => null, 'CODIGO_COLOR_B1' => null, 'COLOR_B1' => null,
            'CALIBRE_B2' => null, 'FIBRA_B2' => null, 'CODIGO_COLOR_B2' => null, 'COLOR_B2' => null,
            'CALIBRE_B3' => null, 'FIBRA_B3' => null, 'CODIGO_COLOR_B3' => null, 'COLOR_B3' => null,
            'CALIBRE_B4' => null, 'FIBRA_B4' => null, 'CODIGO_COLOR_B4' => null, 'COLOR_B4' => null,
        ];

        if ($pt) {
            $map = (array) $pt;
            foreach (array_keys($base) as $key) {
                if ($key === 'Inicio_Tejido') {
                    $base[$key] = ! empty($map['FechaInicio']) ? Carbon::parse($map['FechaInicio'])->toDateString() : null;

                    continue;
                }
                if ($key === 'Fin_Tejido') {
                    $base[$key] = ! empty($map['FechaFinal']) ? Carbon::parse($map['FechaFinal'])->toDateString() : null;

                    continue;
                }
                $base[$key] = $map[$key] ?? null;
            }
        }

        return array_merge($base, $overrides);
    }

    /**
     * Filas TRA + C1..C5 (telares con rizo/pie y combinaciones).
     *
     * @param  array<string, mixed>  $td
     * @param  array<string, bool>  $existKeys
     * @return array<int, array<string, mixed>>
     */
    private function buildRowsFromTelarData(array $td, array $existKeys = []): array
    {
        $candidatos = [
            [$td['CALIBRE_TRA'], $td['FIBRA_TRA'], $td['CODIGO_COLOR_TRAMA'], $td['COLOR_TRAMA']],
            [$td['CALIBRE_C1'], $td['FIBRA_C1'], $td['CODIGO_COLOR_C1'], $td['COLOR_C1']],
            [$td['CALIBRE_C2'], $td['FIBRA_C2'], $td['CODIGO_COLOR_C2'], $td['COLOR_C2']],
            [$td['CALIBRE_C3'], $td['FIBRA_C3'], $td['CODIGO_COLOR_C3'], $td['COLOR_C3']],
            [$td['CALIBRE_C4'], $td['FIBRA_C4'], $td['CODIGO_COLOR_C4'], $td['COLOR_C4']],
            [$td['CALIBRE_C5'], $td['FIBRA_C5'], $td['CODIGO_COLOR_C5'], $td['COLOR_C5']],
        ];

        return $this->construirFilas($candidatos, $existKeys);
    }

    /**
     * Cuatro barras de Karl Mayer (no hay rizo/pie ni combinaciones).
     *
     * @param  array<string, mixed>  $td
     * @param  array<string, bool>  $existKeys
     * @return array<int, array<string, mixed>>
     */
    private function buildBarrasFromTelarData(array $td, array $existKeys = []): array
    {
        $candidatos = [];
        for ($n = 1; $n <= 4; $n++) {
            $candidatos[] = [
                $td["CALIBRE_B{$n}"] ?? null,
                $td["FIBRA_B{$n}"] ?? null,
                $td["CODIGO_COLOR_B{$n}"] ?? null,
                $td["COLOR_B{$n}"] ?? null,
                $n,
            ];
        }

        return $this->construirFilas($candidatos, $existKeys);
    }

    /**
     * Mapa material => barra (1-4) del programa vigente.
     * La barra se deriva, no se persiste.
     *
     * @param  array<string, mixed>  $td
     * @return array<string, int>
     */
    private function mapaBarras(array $td): array
    {
        $mapa = [];
        for ($n = 1; $n <= 4; $n++) {
            $clave = $this->claveMaterial(
                $td["CALIBRE_B{$n}"] ?? null,
                $td["FIBRA_B{$n}"] ?? null,
                $td["CODIGO_COLOR_B{$n}"] ?? null,
                $td["COLOR_B{$n}"] ?? null
            );
            if ($clave !== null && ! isset($mapa[$clave])) {
                $mapa[$clave] = $n;
            }
        }

        return $mapa;
    }

    private function claveMaterial(mixed $calibre, mixed $fibra, mixed $codColor, mixed $color): ?string
    {
        if ($calibre === null || $calibre === '' || (float) $calibre == 0.0) {
            return null;
        }

        return implode('|', [
            number_format((float) $calibre, 2),
            trim((string) ($fibra ?? '')),
            trim((string) ($codColor ?? '')),
            trim((string) ($color ?? '')),
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $candidatos
     * @param  array<string, bool>  $existKeys
     * @return array<int, array<string, mixed>>
     */
    private function construirFilas(array $candidatos, array $existKeys = []): array
    {
        $rows = [];
        foreach ($candidatos as $c) {
            $cal = $c[0] ?? null;
            if ($cal === null || $cal === '' || (float) $cal == 0.0) {
                continue;
            }

            $barra = $c[4] ?? null;
            $calibre = (float) $cal;
            // ponytail: en KM la identidad es la barra 1-4, no el calibre; sin esto 2 barras iguales colapsan a 1 fila
            $clave = $barra !== null ? 'B'.$barra : number_format($calibre, 2);
            if (isset($existKeys[$clave])) {
                continue;
            }
            $existKeys[$clave] = true;

            $rows[] = [
                'id' => null,
                'barra' => $barra,
                'calibre' => $calibre,
                'fibra' => ($c[1] ?? null) ?: null,
                'cod_color' => ($c[2] ?? null) ?: null,
                'color' => ($c[3] ?? null) ?: null,
                'cantidad' => 0,
            ];
        }

        return $rows;
    }

    private function tipoUi(string $salon): string
    {
        return match (TelarSalonResolver::normalizeSalon($salon)) {
            'SMIT' => 'itema',
            self::SALON_KARL_MAYER => 'karl-mayer',
            default => 'jacquard',
        };
    }

    // =========================================================
    // Persistencia
    // =========================================================

    private function resolverFolio(string $provided, string $turno, string $fecha): string
    {
        if ($provided !== '') {
            $registro = TejTrama::where('Folio', $provided)->lockForUpdate()->first();
            if (! $registro) {
                throw new ModelNotFoundException('El folio indicado no existe');
            }

            $usuario = Auth::user();
            $registro->update([
                'numero_empleado' => $usuario->numero_empleado ?? '',
                'nombreEmpl' => $usuario->nombre ?? '',
            ]);

            return $registro->Folio;
        }

        $enProceso = TejTrama::where('Status', 'En Proceso')->lockForUpdate()->first();
        if ($enProceso) {
            return $enProceso->Folio;
        }

        $folio = FolioHelper::obtenerSiguienteFolio('Trama', 5);
        $usuario = Auth::user();

        TejTrama::create([
            'Folio' => $folio,
            'Fecha' => $fecha,
            'Status' => 'En Proceso',
            'Turno' => $turno,
            'numero_empleado' => $usuario->numero_empleado ?? '',
            'nombreEmpl' => $usuario->nombre ?? '',
        ]);

        return $folio;
    }

    /**
     * Aplana el VM de telares al formato que persiste guardar().
     *
     * @param  array<int, array<string, mixed>>  $telares
     * @return array<int, array<string, mixed>>
     */
    public function consumosDesdeTelares(array $telares): array
    {
        $consumos = [];
        foreach ($telares as $telar) {
            $telarNumero = (string) ($telar['numero'] ?? '');
            $salon = (string) ($telar['salon'] ?? '');
            $orden = (string) (($telar['telarData']['Orden_Prod'] ?? ''));
            $producto = (string) (($telar['telarData']['Nombre_Producto'] ?? ''));

            foreach ($telar['rows'] ?? [] as $row) {
                $consumos[] = [
                    'telar' => $telarNumero,
                    'salon' => $salon,
                    'orden' => $orden,
                    'producto' => $producto,
                    'calibre' => $row['calibre'] ?? null,
                    'fibra' => $row['fibra'] ?? null,
                    'cod_color' => $row['cod_color'] ?? null,
                    'color' => $row['color'] ?? null,
                    'cantidad' => $row['cantidad'] ?? 0,
                ];
            }
        }

        return $consumos;
    }

    /**
     * Siembra inicial del folio recién creado en un solo INSERT.
     * Todo es alta (el folio es nuevo), sin el costo fila-por-fila de sincronizar.
     *
     * @param  array<int, array<string, mixed>>  $consumos
     */
    private function sembrarConsumos(string $folio, array $consumos): void
    {
        $filas = [];
        foreach ($consumos as $consumo) {
            $item = $this->normalizarConsumo(is_array($consumo) ? $consumo : []);
            if ($item === null) {
                continue;
            }
            $filas[] = $item + ['Folio' => $folio];
        }

        if ($filas !== []) {
            TejTramaConsumos::insert($filas);
        }
    }

    /** @param array<int, array<string, mixed>> $consumos */
    private function sincronizarConsumos(string $folio, array $consumos): void
    {
        $porTelar = [];
        foreach ($consumos as $consumo) {
            $item = $this->normalizarConsumo(is_array($consumo) ? $consumo : []);
            if ($item === null) {
                continue;
            }
            $porTelar[$item['NoTelarId']][] = $item;
        }

        // ponytail: una sola lectura del folio, antes era una query por telar
        $existentes = TejTramaConsumos::where('Folio', $folio)->get()
            ->groupBy(fn ($e) => (string) $e->NoTelarId);

        foreach ($porTelar as $telar => $items) {
            $mapa = [];
            foreach ($existentes->get((string) $telar, collect()) as $existente) {
                $mapa[$this->claveModelo($existente)] = $existente;
            }

            $usadas = [];
            foreach ($items as $item) {
                $clave = $this->claveItem($item);

                if (isset($mapa[$clave])) {
                    $mapa[$clave]->fill($this->atributosConsumo($item))->save();
                    $usadas[$clave] = true;

                    continue;
                }

                $nuevo = TejTramaConsumos::create($this->atributosConsumo($item) + ['Folio' => $folio]);
                $mapa[$clave] = $nuevo;
                $usadas[$clave] = true;
            }

            foreach ($mapa as $clave => $modelo) {
                if (! isset($usadas[$clave])) {
                    $modelo->delete();
                }
            }
        }

        // Telares que salieron del payload (o payload vacío): borrar huérfanos.
        foreach ($existentes as $telar => $filas) {
            if (! isset($porTelar[(string) $telar]) && ! isset($porTelar[$telar])) {
                foreach ($filas as $fila) {
                    $fila->delete();
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $consumo
     * @return array<string, mixed>|null
     */
    private function normalizarConsumo(array $consumo): ?array
    {
        $telar = trim((string) ($consumo['telar'] ?? ''));
        if ($telar === '') {
            return null;
        }

        $calibre = ($consumo['calibre'] === null || $consumo['calibre'] === '' || $consumo['calibre'] === '-')
            ? null
            : (float) $consumo['calibre'];

        if ($calibre === null) {
            return null;
        }

        return [
            'NoTelarId' => StringTruncator::truncateToLength($telar, 10),
            'SalonTejidoId' => StringTruncator::truncateToLength(trim((string) ($consumo['salon'] ?? '')), 10),
            'NoProduccion' => StringTruncator::truncateToLength(trim((string) ($consumo['orden'] ?? '')), 15),
            'NombreProducto' => StringTruncator::truncateToLength(trim((string) ($consumo['producto'] ?? '')), 100),
            'FibraTrama' => $this->limpiarTexto($consumo['fibra'] ?? null, 15),
            'CodColorTrama' => $this->limpiarTexto($consumo['cod_color'] ?? null, 10),
            'ColorTrama' => $this->limpiarTexto($consumo['color'] ?? null, 80),
            'CalibreTrama' => $calibre,
            'Cantidad' => (float) ($consumo['cantidad'] ?? 0),
        ];
    }

    private function limpiarTexto(mixed $valor, int $limite): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '' || $texto === '-') {
            return null;
        }

        return StringTruncator::truncateToLength($texto, $limite);
    }

    /** @param array<string, mixed> $item */
    private function claveItem(array $item): string
    {
        return implode('|', [
            $item['NoTelarId'],
            number_format((float) $item['CalibreTrama'], 2),
            (string) ($item['FibraTrama'] ?? ''),
            (string) ($item['CodColorTrama'] ?? ''),
            (string) ($item['ColorTrama'] ?? ''),
        ]);
    }

    private function claveModelo(TejTramaConsumos $modelo): string
    {
        return implode('|', [
            (string) $modelo->NoTelarId,
            number_format((float) $modelo->CalibreTrama, 2),
            (string) ($modelo->FibraTrama ?? ''),
            (string) ($modelo->CodColorTrama ?? ''),
            (string) ($modelo->ColorTrama ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function atributosConsumo(array $item): array
    {
        return [
            'NoTelarId' => $item['NoTelarId'],
            'SalonTejidoId' => $item['SalonTejidoId'],
            'NoProduccion' => $item['NoProduccion'],
            'NombreProducto' => $item['NombreProducto'],
            'CalibreTrama' => $item['CalibreTrama'],
            'FibraTrama' => $item['FibraTrama'],
            'CodColorTrama' => $item['CodColorTrama'],
            'ColorTrama' => $item['ColorTrama'],
            'Cantidad' => $item['Cantidad'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function consumosDelFolio(string $folio): array
    {
        return TejTramaConsumos::where('Folio', $folio)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->Id,
                'folio' => $c->Folio,
                'telar' => $c->NoTelarId,
                'calibre' => $c->CalibreTrama,
                'fibra' => $c->FibraTrama,
                'cod_color' => $c->CodColorTrama,
                'color' => $c->ColorTrama,
                'cantidad' => $c->Cantidad,
            ])
            ->all();
    }
}
