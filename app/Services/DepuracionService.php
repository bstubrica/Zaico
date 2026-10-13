<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Borrado físico de todos los datos de inventario.
 *
 * Conserva deliberadamente usuarios, catálogo de estados (ESTADOS_ACTIVO),
 * migraciones y estructura de la base de datos. También elimina los archivos
 * asociados (evidencias de mantenimiento e importaciones temporales).
 */
class DepuracionService
{
    /**
     * Tablas de datos de inventario que se vacían, con su etiqueta legible.
     * El orden va de hijos a padres; solo importa en motores cuyo truncado no
     * aplica cascada.
     *
     * @var array<string, string>
     */
    public const TABLAS = [
        'EVIDENCIAS_MANTENIMIENTO' => 'Evidencias de mantenimiento',
        'MANTENIMIENTOS' => 'Mantenimientos',
        'ASIGNACIONES' => 'Asignaciones',
        'COMPRAS' => 'Compras',
        'HISTORIAL_EVENTOS' => 'Historial de eventos',
        'IMPORTACIONES_DETALLE' => 'Detalle de importaciones',
        'IMPORTACIONES_LOG' => 'Importaciones',
        'ALERTAS' => 'Alertas',
        'notifications' => 'Notificaciones',
        'ACTIVOS' => 'Equipos',
        'PERSONAL' => 'Personal',
    ];

    /** Disco privado de la aplicación (storage/app/private). */
    private const DISCO = 'local';

    /** Directorio de evidencias de mantenimiento. */
    private const DIRECTORIO_EVIDENCIAS = 'uploads/mantenimientos';

    /** Directorio de CSV temporales de importación. */
    private const DIRECTORIO_IMPORTACIONES = 'tmp/importaciones';

    /**
     * Resumen previo: filas actuales por cada tabla de inventario.
     *
     * @return array<int, array{tabla: string, etiqueta: string, registros: int}>
     */
    public function resumen(): array
    {
        $resumen = [];

        foreach (self::TABLAS as $tabla => $etiqueta) {
            $resumen[] = [
                'tabla' => $tabla,
                'etiqueta' => $etiqueta,
                'registros' => DB::table($tabla)->count(),
            ];
        }

        return $resumen;
    }

    /**
     * Indica si la contraseña de depuración está configurada en el servidor.
     */
    public function estaConfigurado(): bool
    {
        return filled(config('zaico.depuracion.password_hash'));
    }

    /**
     * Indica si la depuración puede ejecutarse en el entorno actual. En
     * producción requiere habilitación explícita por variable de entorno.
     */
    public function estaHabilitado(): bool
    {
        if (app()->environment('production')) {
            return (bool) config('zaico.depuracion.habilitado_en_produccion');
        }

        return true;
    }

    /**
     * Frase exacta que debe escribir el administrador para confirmar.
     */
    public function fraseEsperada(): string
    {
        return (string) config('zaico.depuracion.frase');
    }

    /**
     * Vacía físicamente los datos de inventario y sus archivos. Devuelve el
     * conteo de registros eliminados por tabla y de archivos borrados.
     *
     * @return array{vaciados: array<string, int>, archivos: int, total: int}
     */
    public function vaciar(): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('La depuración no está configurada en el servidor.');
        }

        $vaciados = [];

        DB::transaction(function () use (&$vaciados): void {
            foreach (self::TABLAS as $tabla => $etiqueta) {
                $vaciados[$etiqueta] = DB::table($tabla)->count();
            }

            $this->vaciarTablas(array_keys(self::TABLAS));
        });

        $archivos = $this->borrarArchivos();

        return [
            'vaciados' => $vaciados,
            'archivos' => $archivos,
            'total' => array_sum($vaciados),
        ];
    }

    /**
     * Trunca las tablas de forma portable y reinicia los autoincrementales.
     *
     * @param  array<int, string>  $tablas
     */
    private function vaciarTablas(array $tablas): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $lista = collect($tablas)->map(fn (string $tabla) => '"'.$tabla.'"')->implode(', ');
            DB::statement("TRUNCATE TABLE {$lista} RESTART IDENTITY CASCADE");

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tablas as $tabla) {
                DB::table($tabla)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Borra los archivos asociados a los datos vaciados.
     */
    private function borrarArchivos(): int
    {
        $disco = Storage::disk(self::DISCO);

        $archivos = array_merge(
            $disco->allFiles(self::DIRECTORIO_EVIDENCIAS),
            $disco->allFiles(self::DIRECTORIO_IMPORTACIONES),
        );

        if ($archivos !== []) {
            $disco->delete($archivos);
        }

        return count($archivos);
    }
}
