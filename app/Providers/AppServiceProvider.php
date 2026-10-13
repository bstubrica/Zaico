<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limita las mutaciones para amortiguar abuso o bucles accidentales.
        RateLimiter::for('escritura', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // La importación de CSV es una operación pesada y con efectos masivos.
        RateLimiter::for('importacion', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Consultas de lectura intensivas (exportaciones, API, auditoría).
        RateLimiter::for('consulta', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Operación destructiva e irreversible: margen muy estrecho.
        RateLimiter::for('depuracion', fn (Request $request) => Limit::perMinute(3)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
