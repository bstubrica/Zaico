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
    | Servidores
    |--------------------------------------------------------------------------
    |
    | `ACTIVOS.Categoria` es texto libre, así que la categoría se compara
    | normalizada (minúsculas y sin espacios) contra esta lista para decidir si
    | un equipo es un servidor y si puede usarse como switch del inventario.
    | `maximo_tarjetas` acota las tarjetas de red por equipo en el formulario.
    |
    */

    'servidores' => [
        'categorias' => ['servidor', 'servidores'],
        'categorias_switch' => ['switch', 'switches'],
        'maximo_tarjetas' => 8,
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Depuración total del inventario (Administración)
    |--------------------------------------------------------------------------
    |
    | Módulo de emergencia que borra físicamente los datos de inventario
    | (equipos, personal, movimientos, mantenimientos, importaciones, alertas
    | y notificaciones), conservando usuarios, catálogo de estados y la
    | estructura de la base de datos.
    |
    | La contraseña se guarda como hash (bcrypt) generado con:
    |   php artisan tinker --execute 'echo Hash::make("tu-contraseña");'
    |
    | Por seguridad la operación se bloquea en producción salvo que se habilite
    | explícitamente con ZAICO_DEPURACION_HABILITADO=true.
    |
    */

    'depuracion' => [
        'password_hash' => env('ZAICO_DEPURACION_PASSWORD_HASH'),
        'frase' => env('ZAICO_DEPURACION_FRASE', 'BORRAR TODO EL INVENTARIO'),
        'habilitado_en_produccion' => (bool) env('ZAICO_DEPURACION_HABILITADO', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Carta de desincorporación (membrete y firmas)
    |--------------------------------------------------------------------------
    |
    | Datos del encabezado de la carta/acta de desincorporación generada en el
    | apartado de equipos desincorporados. Si no se configuran, se usan valores
    | neutros para no romper la descarga.
    |
    */

    'carta' => [
        'titulo' => env('ZAICO_CARTA_TITULO', 'Carta de desincorporación'),
        'folio_prefijo' => env('ZAICO_CARTA_FOLIO_PREFIJO', 'DES'),
        'organizacion' => [
            // `env()` anidado falla con `config:cache` (el interno devuelve
            // null al no leerse el .env); se resuelve con config('app.name').
            'nombre' => env('ZAICO_EMPRESA_NOMBRE') ?: config('app.name', 'Zaiko'),
            'rif' => env('ZAICO_EMPRESA_RIF'),
            'direccion' => env('ZAICO_EMPRESA_DIRECCION'),
            'ciudad' => env('ZAICO_EMPRESA_CIUDAD'),
        ],
        'firmas' => [
            'entrega' => env('ZAICO_CARTA_FIRMA_ENTREGA', 'Entrega'),
            'recibe' => env('ZAICO_CARTA_FIRMA_RECIBE', 'Recibe'),
        ],
    ],

];
