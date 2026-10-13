<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ImportacionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('inventario:sincronizar {--ruta= : Ruta explícita al CSV} {--usuario= : ID o email del usuario que firma la importación}')]
#[Description('Sincroniza el inventario importando el CSV más reciente de Snipec IT')]
class SincronizarSnipec extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImportacionService $importacion): int
    {
        $rutaExplicita = $this->option('ruta');
        $directorio = (string) config('zaico.sincronizacion.directorio');

        $ruta = $rutaExplicita ?: $this->archivoMasReciente($directorio);

        if ($ruta === null || ! File::isFile($ruta)) {
            $this->warn('No se encontró ningún archivo CSV para sincronizar.');

            return self::SUCCESS;
        }

        $usuario = $this->resolverUsuario($this->option('usuario'));

        if ($usuario === null) {
            $this->error('No hay un usuario (Admin) para registrar la importación. Use --usuario o cree un administrador.');

            return self::FAILURE;
        }

        try {
            $log = $importacion->importarCsv($ruta, File::basename($ruta), false, $usuario->id);
        } catch (Throwable $error) {
            $this->error("No se pudo importar «{$ruta}»: {$error->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Importación #%d completada → insertadas: %d, actualizadas: %d, errores: %d.',
            $log->id,
            $log->Insertadas,
            $log->Actualizadas,
            $log->Errores,
        ));

        if ($rutaExplicita === null) {
            $this->archivar($ruta, $directorio);
        }

        return self::SUCCESS;
    }

    private function archivoMasReciente(string $directorio): ?string
    {
        if (! File::isDirectory($directorio)) {
            return null;
        }

        $archivos = File::glob(rtrim($directorio, '/\\').DIRECTORY_SEPARATOR.config('zaico.sincronizacion.patron'));

        if ($archivos === false || $archivos === []) {
            return null;
        }

        return collect($archivos)
            ->sortByDesc(fn (string $archivo) => File::lastModified($archivo))
            ->first();
    }

    private function resolverUsuario(?string $referencia): ?User
    {
        $referencia = $referencia ?: config('zaico.sincronizacion.usuario');

        if (is_string($referencia)) {
            $referencia = trim($referencia);
        }

        if ($referencia !== null && $referencia !== '') {
            $columna = ctype_digit((string) $referencia) ? 'id' : 'email';

            return User::query()->where($columna, $referencia)->first();
        }

        return User::query()->where('role', 'Admin')->orderBy('id')->first();
    }

    private function archivar(string $ruta, string $directorio): void
    {
        $destinoDirectorio = rtrim($directorio, '/\\').DIRECTORY_SEPARATOR.'procesados';
        File::ensureDirectoryExists($destinoDirectorio);

        $destino = $destinoDirectorio.DIRECTORY_SEPARATOR.now()->format('Ymd_His').'_'.File::basename($ruta);
        File::move($ruta, $destino);

        $this->line("Archivo archivado en: {$destino}");
    }
}
