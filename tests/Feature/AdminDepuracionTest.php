<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Alerta;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\EstadoActivo;
use App\Models\EvidenciaMantenimiento;
use App\Models\HistorialEvento;
use App\Models\ImportacionDetalle;
use App\Models\ImportacionLog;
use App\Models\Mantenimiento;
use App\Models\Personal;
use App\Models\User;
use App\Services\DepuracionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDepuracionTest extends TestCase
{
    use RefreshDatabase;

    private const CONTRASENA = 'clave-de-depuracion';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);

        config([
            'zaico.depuracion.password_hash' => Hash::make(self::CONTRASENA),
            'zaico.depuracion.frase' => 'BORRAR TODO EL INVENTARIO',
            'zaico.depuracion.habilitado_en_produccion' => false,
        ]);
    }

    private function estado(string $nombre, string $grupo): EstadoActivo
    {
        return EstadoActivo::firstOrCreate(
            ['Nombre' => $nombre],
            [
                'Grupo' => $grupo,
                'Deployed' => $grupo === 'Desincorporado',
                'Deployable' => false,
                'Estado' => 1,
            ]
        );
    }

    /**
     * Siembra una muestra representativa de datos de inventario y devuelve los
     * archivos creados en el disco local (evidencia e importación temporal).
     *
     * @return array<int, string>
     */
    private function sembrarInventario(): array
    {
        Storage::fake('local');

        $disponible = $this->estado('Disponible (deployable)', 'Disponible');

        $activo = Activo::create([
            'Nombre_de_activo' => 'Equipo TBADM01',
            'Etiqueta_activo' => 'TBADM01',
            'Estado' => $disponible->Grupo,
            'fk_estado' => $disponible->id,
        ]);

        $persona = Personal::create([
            'Nombre' => 'Ana',
            'Apellido' => 'Pérez',
            'Nombre_usuario' => 'aperez',
            'Cargo' => 'Analista',
            'Estado' => 'Activo',
        ]);

        Asignacion::create([
            'fk_Activo' => $activo->id,
            'fk_Personal' => $persona->id,
            'fk_usuario' => $this->admin->id,
            'Fecha_asignacion' => now()->toDateString(),
            'Estado' => 'Activa',
        ]);

        Compra::create(['fk_activo' => $activo->id, 'Proveedor' => 'Proveedor S.A.']);

        $mantenimiento = Mantenimiento::create([
            'fk_activo' => $activo->id,
            'Tipo' => 'Preventivo',
            'Descripcion' => 'Limpieza',
            'Fecha_mantenimiento' => now()->toDateString(),
            'fk_usuario' => $this->admin->id,
        ]);

        $rutaEvidencia = Storage::disk('local')->putFile(
            'uploads/mantenimientos',
            UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf')
        );

        EvidenciaMantenimiento::create([
            'fk_mantenimiento' => $mantenimiento->id,
            'Ruta_archivo' => $rutaEvidencia,
            'Nombre_original' => 'evidencia.pdf',
            'Tipo_mime' => 'application/pdf',
            'Tamano_bytes' => 100,
        ]);

        HistorialEvento::create([
            'fk_activo' => $activo->id,
            'Tipo_evento' => 'Creacion',
            'Descripcion' => 'Alta inicial',
            'fk_usuario' => $this->admin->id,
        ]);

        $log = ImportacionLog::create([
            'Nombre_archivo' => 'reporte.csv',
            'fk_usuario' => $this->admin->id,
            'Total_filas' => 1,
            'Insertadas' => 1,
            'Actualizadas' => 0,
            'Errores' => 0,
            'Modo' => 'Commit',
            'Estado' => 'Completado',
        ]);

        ImportacionDetalle::create([
            'fk_importacion' => $log->id,
            'Fila_numero' => 1,
            'Etiqueta' => 'TBADM01',
            'Accion' => 'Insertado',
        ]);

        Alerta::create([
            'Tipo' => Alerta::TIPO_GARANTIA,
            'fk_activo' => $activo->id,
            'Mensaje' => 'Garantía por vencer',
            'Fecha_referencia' => now()->toDateString(),
            'Estado' => 'Activa',
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AlertaInventario',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->admin->id,
            'data' => json_encode(['titulo' => 'Alerta', 'mensaje' => 'Mensaje']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rutaImportacion = Storage::disk('local')->putFile(
            'tmp/importaciones',
            UploadedFile::fake()->create('pendiente.csv', 10, 'text/csv')
        );

        return [$rutaEvidencia, $rutaImportacion];
    }

    public function test_admin_depura_el_inventario_y_conserva_usuarios_y_estados(): void
    {
        [$rutaEvidencia, $rutaImportacion] = $this->sembrarInventario();

        $usuariosAntes = User::count();
        $estadosAntes = EstadoActivo::count();

        $this->actingAs($this->admin)
            ->post(route('admin.depurar'), [
                'password' => self::CONTRASENA,
                'frase' => 'BORRAR TODO EL INVENTARIO',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('success');

        $restantes = [];
        foreach (array_keys(DepuracionService::TABLAS) as $tabla) {
            $cantidad = DB::table($tabla)->count();
            if ($cantidad !== 0) {
                $restantes[$tabla] = $cantidad;
            }
        }
        $this->assertSame([], $restantes);

        $this->assertSame($usuariosAntes, User::count());
        $this->assertSame($estadosAntes, EstadoActivo::count());

        $this->assertFalse(Storage::disk('local')->exists($rutaEvidencia));
        $this->assertFalse(Storage::disk('local')->exists($rutaImportacion));
    }

    public function test_contrasena_incorrecta_no_borra_nada(): void
    {
        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->from(route('admin.index'))
            ->post(route('admin.depurar'), [
                'password' => 'contrasena-equivocada',
                'frase' => 'BORRAR TODO EL INVENTARIO',
            ])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseCount('ACTIVOS', 1);
        $this->assertDatabaseCount('PERSONAL', 1);
    }

    public function test_frase_incorrecta_no_borra_nada(): void
    {
        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->from(route('admin.index'))
            ->post(route('admin.depurar'), [
                'password' => self::CONTRASENA,
                'frase' => 'borrar todo',
            ])
            ->assertSessionHasErrors('frase');

        $this->assertDatabaseCount('ACTIVOS', 1);
        $this->assertDatabaseCount('PERSONAL', 1);
    }

    public function test_no_se_puede_depurar_sin_hash_configurado(): void
    {
        config(['zaico.depuracion.password_hash' => null]);

        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->from(route('admin.index'))
            ->post(route('admin.depurar'), [
                'password' => self::CONTRASENA,
                'frase' => 'BORRAR TODO EL INVENTARIO',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('ACTIVOS', 1);
    }

    public function test_depuracion_bloqueada_en_produccion_sin_habilitar(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->post(route('admin.depurar'), [
                'password' => self::CONTRASENA,
                'frase' => 'BORRAR TODO EL INVENTARIO',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ACTIVOS', 1);
    }

    public function test_depuracion_habilitada_en_produccion_por_variable(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['zaico.depuracion.habilitado_en_produccion' => true]);

        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->post(route('admin.depurar'), [
                'password' => self::CONTRASENA,
                'frase' => 'BORRAR TODO EL INVENTARIO',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseCount('ACTIVOS', 0);
    }

    public function test_solo_admin_puede_acceder_y_depurar(): void
    {
        foreach (['SoporteTecnico', 'Auditor'] as $rol) {
            $usuario = User::factory()->create(['role' => $rol]);

            $this->actingAs($usuario)->get(route('admin.index'))->assertForbidden();
            $this->actingAs($usuario)
                ->post(route('admin.depurar'), [
                    'password' => self::CONTRASENA,
                    'frase' => 'BORRAR TODO EL INVENTARIO',
                ])
                ->assertForbidden();
        }
    }

    public function test_admin_ve_el_resumen_en_el_panel(): void
    {
        $this->sembrarInventario();

        $this->actingAs($this->admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Zona de peligro')
            ->assertSee('BORRAR TODO EL INVENTARIO');
    }
}
