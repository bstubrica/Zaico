<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registro de usuarios
    |--------------------------------------------------------------------------
    |
    | Sistema interno: el autorregistro público está deshabilitado por defecto.
    | Los usuarios se crean por seeder/administración. Si se habilita, las
    | cuentas nuevas reciben el rol de mínimo privilegio (solo lectura).
    |
    */

    'registro_publico' => (bool) env('ZAICO_REGISTRO_PUBLICO', false),
    'registro_role' => env('ZAICO_REGISTRO_ROLE', 'Auditor'),

    /*
    |--------------------------------------------------------------------------
    | Alertas de inventario (Fase 8)
    |--------------------------------------------------------------------------
    |
    | Umbrales usados por el comando `inventario:alertas` para detectar
    | activos en reparación prolongada y garantías próximas a vencer.
    |
    */

    'alertas' => [
        'reparacion_dias' => (int) env('ZAICO_REPARACION_DIAS', 15),
        'garantia_dias' => (int) env('ZAICO_GARANTIA_DIAS', 30),

        // Roles que reciben las notificaciones de alertas.
        'roles' => ['Admin', 'SoporteTecnico'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronización con Snipec IT (Fase 10)
    |--------------------------------------------------------------------------
    |
    | Carpeta donde el sistema externo deja el reporte CSV `custom-assets-report`.
    | El comando `inventario:sincronizar` importa el archivo más reciente y lo
    | mueve a `procesados/` para no reimportarlo.
    |
    */

    'sincronizacion' => [
        'directorio' => env('ZAICO_SNIPEC_DIR', storage_path('app/snipec')),
        'patron' => env('ZAICO_SNIPEC_PATRON', 'custom-assets-report-*.csv'),
        'usuario' => env('ZAICO_SNIPEC_USUARIO'),
    ],

];
