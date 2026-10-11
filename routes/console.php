<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fase 10: importa el CSV más reciente dejado por Snipec IT.
Schedule::command('inventario:sincronizar')->dailyAt('06:30');

// Fase 8: revisa reparaciones prolongadas y garantías por vencer cada mañana.
Schedule::command('inventario:alertas')->dailyAt('07:00');

// Limpieza: elimina CSVs temporales de importación que quedaron huérfanos
// (previsualizaciones nunca confirmadas o errores antes de confirmar).
Schedule::call(function () {
    $disco = Storage::disk('local');
    $limite = now()->subDay()->getTimestamp();

    foreach ($disco->files('tmp/importaciones') as $archivo) {
        if ($disco->lastModified($archivo) < $limite) {
            $disco->delete($archivo);
        }
    }
})->dailyAt('03:00')->name('limpiar-importaciones-temporales');
