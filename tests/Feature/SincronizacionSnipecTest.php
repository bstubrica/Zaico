<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\ImportacionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SincronizacionSnipecTest extends TestCase
{
    use RefreshDatabase;

    private string $directorio;

    private string $csv = <<<'CSV'
        "Nombre de activo","Etiqueta de activo","Estado","Serial","Categoria","Localizacion"
        "Laptop Lenovo","TBSM9001","Disponible > Nuevo (deployable)","SN-9001","Laptop","Oficina"
        CSV;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = storage_path('app/testing-snipec-'.uniqid());
        File::ensureDirectoryExists($this->directorio);
        config(['zaico.sincronizacion.directorio' => $this->directorio]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);

        parent::tearDown();
    }

    public function test_importa_el_csv_mas_reciente_y_lo_archiva(): void
    {
        User::factory()->create(['role' => 'Admin']);
        File::put($this->directorio.'/custom-assets-report-2026-10-10.csv', $this->csv);

        $this->artisan('inventario:sincronizar')->assertSuccessful();

        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBSM9001']);
        $this->assertSame(1, ImportacionLog::where('Modo', 'Commit')->where('Estado', 'Completado')->count());

        $this->assertFalse(File::exists($this->directorio.'/custom-assets-report-2026-10-10.csv'));
        $this->assertCount(1, File::files($this->directorio.'/procesados'));
    }

    public function test_no_hace_nada_si_no_hay_archivos(): void
    {
        User::factory()->create(['role' => 'Admin']);

        $this->artisan('inventario:sincronizar')->assertSuccessful();

        $this->assertSame(0, ImportacionLog::count());
        $this->assertSame(0, Activo::count());
    }

    public function test_ruta_explicita_no_archiva_el_archivo(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'email' => 'admin@zaico.test']);
        $ruta = $this->directorio.'/manual.csv';
        File::put($ruta, $this->csv);

        $this->artisan('inventario:sincronizar', ['--ruta' => $ruta, '--usuario' => $admin->email])
            ->assertSuccessful();

        $this->assertDatabaseHas('ACTIVOS', ['Etiqueta_activo' => 'TBSM9001']);
        $this->assertTrue(File::exists($ruta));
        $this->assertFalse(File::isDirectory($this->directorio.'/procesados'));
    }

    public function test_falla_si_no_hay_usuario_que_firme_la_importacion(): void
    {
        File::put($this->directorio.'/custom-assets-report-2026-10-10.csv', $this->csv);

        $this->artisan('inventario:sincronizar')->assertFailed();
    }
}
