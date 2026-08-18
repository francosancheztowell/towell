<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JsonException;

/**
 * Perfilador de queries por petición.
 *
 * ponytail: no instrumenta nada. Debugbar ya guarda cada petición (incluidas las
 * de Livewire) en storage/debugbar/*.json con sus queries y duraciones; esto solo
 * lee esos volcados y los agrega. Si Debugbar está apagado no hay nada que leer.
 */
final class DbProfileCommand extends Command
{
    protected $signature = 'db:profile
        {--uri= : Filtrar por parte de la URI (ej. Crudo, livewire)}
        {--minutes=10 : Antigüedad máxima de las peticiones a leer}
        {--limit=15 : Cuántas queries distintas mostrar}
        {--path= : Carpeta de volcados (por defecto storage/debugbar)}';

    protected $description = 'Agrega las queries que Debugbar ya capturó: cuántas por petición, cuáles y cuánto tardan.';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?? '') ?: storage_path('debugbar');
        $files = glob(rtrim($path, '\\/').'/*.json') ?: [];
        $since = time() - ((int) $this->option('minutes') * 60);
        $uri = (string) ($this->option('uri') ?? '');

        $requests = 0;
        $totalQueries = 0;
        $totalMs = 0.0;
        /** @var array<string, array{count: int, ms: float, max: float, conn: string}> $porQuery */
        $porQuery = [];

        foreach ($files as $file) {
            if (filemtime($file) < $since) {
                continue;
            }

            try {
                $dump = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue; // volcado a medio escribir
            }

            if ($uri !== '' && ! str_contains((string) ($dump['__meta']['uri'] ?? ''), $uri)) {
                continue;
            }

            $requests++;

            foreach ($dump['queries']['statements'] ?? [] as $statement) {
                if (($statement['type'] ?? '') !== 'query') {
                    continue; // "Connection Established" y demás ruido
                }

                $ms = (float) ($statement['duration'] ?? 0) * 1000;
                $clave = $this->normalize((string) ($statement['sql'] ?? ''));

                $totalQueries++;
                $totalMs += $ms;
                $porQuery[$clave] ??= ['count' => 0, 'ms' => 0.0, 'max' => 0.0, 'conn' => (string) ($statement['connection'] ?? '')];
                $porQuery[$clave]['count']++;
                $porQuery[$clave]['ms'] += $ms;
                $porQuery[$clave]['max'] = max($porQuery[$clave]['max'], $ms);
            }
        }

        if ($requests === 0) {
            $this->warn("Sin peticiones en {$path} de los últimos ".$this->option('minutes').' min'.($uri !== '' ? " para «{$uri}»" : '').'.');
            $this->line('¿Debugbar apagado? Requiere APP_DEBUG=true o DEBUGBAR_ENABLED=true.');

            return self::FAILURE;
        }

        uasort($porQuery, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        $this->newLine();
        $this->info(sprintf(
            '%d peticiones · %d queries (%.1f por petición) · %.0f ms de SQL (%.0f ms por petición)',
            $requests,
            $totalQueries,
            $totalQueries / $requests,
            $totalMs,
            $totalMs / $requests,
        ));

        $filas = array_map(
            static fn (string $sql, array $d): array => [
                $d['count'],
                number_format($d['ms'], 1),
                number_format($d['max'], 1),
                $d['conn'],
                mb_substr($sql, 0, 90),
            ],
            array_keys($porQuery),
            $porQuery,
        );

        $this->table(
            ['veces', 'total ms', 'max ms', 'conexión', 'query'],
            array_slice($filas, 0, (int) $this->option('limit')),
        );

        return self::SUCCESS;
    }

    /**
     * Agrupa la misma query aunque cambien los valores: Debugbar guarda el SQL
     * con los bindings ya sustituidos.
     */
    private function normalize(string $sql): string
    {
        $sql = (string) preg_replace("/'[^']*'/", '?', $sql);
        $sql = (string) preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);

        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
