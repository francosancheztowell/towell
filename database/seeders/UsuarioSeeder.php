<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Sistema\Usuario;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // Usar insert para insertar múltiples registros sin disparar mutadores
        // Las contraseñas ya están hasheadas con bcrypt
        Usuario::insert([
            [
                'numero_empleado' => '1001',
                'nombre' => 'Juan Pérez',
                'contrasenia' => bcrypt('123'),
                'area' => 'Almacen',
                'foto' => 'fotos_usuarios/juan_perez2.jpg',
                'telefono' => '4491234567',
                'turno' => '1',
                'puesto' => 'Supervisor',
                'correo' => 'juan.perez@towell.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numero_empleado' => '1002',
                'nombre' => 'María López',
                'contrasenia' => bcrypt('123'),
                'area' => 'Urdido',
                'foto' => 'fotos_usuarios/maría_lopez.jpg',
                'telefono' => '4491234568',
                'turno' => '1',
                'puesto' => 'Operador',
                'correo' => 'maria.lopez@towell.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numero_empleado' => '1003',
                'nombre' => 'Carlos Ramírez',
                'contrasenia' => bcrypt('123'),
                'area' => 'Almacen',
                'foto' => 'fotos_usuarios/carlos_ramirez.jpg',
                'telefono' => '4491234569',
                'turno' => '2',
                'puesto' => 'Almacenista',
                'correo' => 'carlos.ramirez@towell.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numero_empleado' => '1004',
                'nombre' => 'Ana Torres',
                'contrasenia' => bcrypt('123'),
                'area' => 'Engomado',
                'foto' => 'fotos_usuarios/ana_torres.jpg',
                'telefono' => '4491234570',
                'turno' => '1',
                'puesto' => 'Operador',
                'correo' => 'ana.torres@towell.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'numero_empleado' => '1005',
                'nombre' => 'Pedro Gómez',
                'contrasenia' => bcrypt('123'),
                'area' => 'Tejido',
                'foto' => 'fotos_usuarios/pedro_gomez.jpg',
                'telefono' => '4491234571',
                'turno' => '3',
                'puesto' => 'Tejedor',
                'correo' => 'pedro.gomez@towell.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
