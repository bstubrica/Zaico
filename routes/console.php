<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fase 10: importa el CSV más reciente dejado por Snipec IT.
Schedule::command('inventario:sincronizar')->dailyAt('06:30');

// Fase 8: revisa reparaciones prolongadas y garantías por vencer cada mañana.
Schedule::command('inventario:alertas')->dailyAt('07:00');
