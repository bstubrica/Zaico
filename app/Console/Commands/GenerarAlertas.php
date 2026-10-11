<?php

namespace App\Console\Commands;

use App\Services\AlertaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventario:alertas')]
#[Description('Genera alertas de activos en reparación prolongada y de garantías por vencer')]
class GenerarAlertas extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AlertaService $alertas): int
    {
        $resultado = $alertas->generar();

        $this->info(sprintf(
            'Alertas activas: %d · nuevas: %d · resueltas: %d',
            $resultado['activas'],
            $resultado['nuevas'],
            $resultado['resueltas'],
        ));

        return self::SUCCESS;
    }
}
