<?php

namespace Database\Seeders;

use App\Models\EstadoActivo;
use Illuminate\Database\Seeder;

class EstadoSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo de estados observados en custom-assets-report (17 valores)
        $estados = [
            ['Asignado (deployed)', 'Asignado', true, false],
            ['Asignado (deployable)', 'Asignado', false, true],
            ['Disponible (deployed)', 'Disponible', true, false],
            ['Disponible (deployable)', 'Disponible', false, true],
            ['Disponible > Nuevo (deployed)', 'Disponible', true, false],
            ['Disponible > Nuevo (deployable)', 'Disponible', false, true],
            ['Disponible > Usado (deployed)', 'Disponible', true, false],
            ['Disponible > Usado (deployable)', 'Disponible', false, true],
            ['Defectuoso (pending)', 'Defectuoso', true, false],
            ['Defectuoso (deployed)', 'Defectuoso', true, false],
            ['Reparacion (pending)', 'Reparacion', true, false],
            ['Reparacion (deployed)', 'Reparacion', true, false],
            ['Desincorporado (deployed)', 'Desincorporado', true, false],
            ['Desincorporado (undeployable)', 'Desincorporado', false, false],
            ['Desincorporado > Deteriorado (deployed)', 'Desincorporado', true, false],
            ['Desincorporado > Deteriorado (deployable)', 'Desincorporado', false, true],
            ['Desincorporado>Deteriorado (undeployable)', 'Desincorporado', false, false],
        ];

        foreach ($estados as [$nombre, $grupo, $deployed, $deployable]) {
            EstadoActivo::updateOrCreate(
                ['Nombre' => $nombre],
                ['Grupo' => $grupo, 'Deployed' => $deployed, 'Deployable' => $deployable, 'Estado' => 1]
            );
        }
    }
}
