<?php

namespace Database\Seeders;

use App\Models\Personal;
use Illuminate\Database\Seeder;

class PersonalSemillaSeeder extends Seeder
{
    public function run(): void
    {
        Personal::updateOrCreate(
            ['Nombre_usuario' => 'Grodriguez'],
            ['Nombre' => 'Glenire', 'Apellido' => 'Rodriguez', 'Estado' => 'Activo']
        );
        Personal::updateOrCreate(
            ['Nombre_usuario' => 'wlinares'],
            ['Nombre' => 'Wilenyer', 'Apellido' => 'Linares', 'Estado' => 'Activo']
        );
    }
}
