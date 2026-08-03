<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

interface CrudoFlogProvider
{
    /**
     * @param  array<string, mixed>|null  $program
     * @param  list<string>  $purchBarcodes
     * @return array<string, mixed>
     */
    public function find(?array $program, array $purchBarcodes = []): array;
}
