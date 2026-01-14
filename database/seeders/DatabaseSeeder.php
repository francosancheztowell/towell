<?php

namespace Database\Seeders;

use App\Models\TipoMovimientos;
use App\Models\Sistema\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Puedes dejar comentado el factory si no lo necesitas
        // User::factory(10)->create();
    
        $this->call([
            OficialesSeeder::class,
            FallaSeeder::class,
            TipoMovimientos::class
        ]);
    }
    
}
