<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class DbProfileCommandTest extends TestCase
{
    public function test_it_aggregates_the_same_query_across_requests(): void
    {
        $path = sys_get_temp_dir().'/db-profile-'.uniqid();
        mkdir($path);

        foreach ([1, 2] as $i) {
            file_put_contents($path."/dump{$i}.json", json_encode([
                '__meta' => ['uri' => '/livewire/update'],
                'queries' => ['statements' => [
                    ['type' => 'connection', 'sql' => 'Connection Established', 'connection' => 'sqlsrv'],
                    ['type' => 'query', 'sql' => "select * from [T] where [id] = {$i}", 'duration' => 0.010, 'connection' => 'sqlsrv'],
                ]],
            ], JSON_THROW_ON_ERROR));
        }

        $this->artisan('db:profile', ['--path' => $path, '--uri' => 'livewire'])
            ->expectsOutputToContain('2 peticiones · 2 queries')   // el "Connection Established" no cuenta
            ->expectsOutputToContain('select * from [T] where [id] = ?') // los bindings se normalizan y agrupan
            ->assertSuccessful();

        $this->artisan('db:profile', ['--path' => $path, '--uri' => 'no-existe'])->assertFailed();

        array_map('unlink', glob($path.'/*.json') ?: []);
        rmdir($path);
    }
}
