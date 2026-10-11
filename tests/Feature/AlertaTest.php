<?php

namespace Tests\Feature;

use App\Models\Activo;
use App\Models\Alerta;
use App\Models\Compra;
use App\Models\HistorialEvento;
use App\Models\User;
use App\Notifications\AlertaInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertaTest extends TestCase
{
    use RefreshDatabase;

    private function activo(array $atributos = []): Activo
    {
        return Activo::create([
            'Nombre_de_activo' => 'Laptop Dell',
            'Etiqueta_activo' => 'TBSM'.fake()->unique()->numberBetween(1000, 9999),
            'Estado' => 'Disponible',
            ...$atributos,
        ]);
    }

    private function activoEnReparacion(int $diasEnReparacion): Activo
    {
        $activo = $this->activo(['Estado' => 'Reparacion']);

        HistorialEvento::create([
            'fk_activo' => $activo->id,
            'Tipo_evento' => 'CambioEstado',
            'Descripcion' => 'Envío a reparación',
            'Valor_nuevo' => 'Reparacion',
            'Fecha' => now()->subDays($diasEnReparacion),
        ]);

        return $activo;
    }

    public function test_reparacion_prolongada_genera_alerta_y_notifica(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'Admin']);
        $soporte = User::factory()->create(['role' => 'SoporteTecnico']);
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $activo = $this->activoEnReparacion(30);

        $this->artisan('inventario:alertas')->assertSuccessful();

        $this->assertDatabaseHas('ALERTAS', [
            'Tipo' => Alerta::TIPO_REPARACION,
            'fk_activo' => $activo->id,
            'Estado' => 'Activa',
        ]);

        Notification::assertSentTo([$admin, $soporte], AlertaInventario::class);
        Notification::assertNotSentTo($auditor, AlertaInventario::class);
    }

    public function test_reparacion_reciente_no_genera_alerta(): void
    {
        Notification::fake();

        User::factory()->create(['role' => 'Admin']);
        $activo = $this->activoEnReparacion(2);

        $this->artisan('inventario:alertas')->assertSuccessful();

        $this->assertDatabaseMissing('ALERTAS', [
            'Tipo' => Alerta::TIPO_REPARACION,
            'fk_activo' => $activo->id,
        ]);
    }

    public function test_garantia_por_vencer_genera_alerta(): void
    {
        Notification::fake();

        $activo = $this->activo();

        Compra::create([
            'fk_activo' => $activo->id,
            'Fecha_Compra' => now()->startOfDay()->subMonths(12)->addDays(10),
            'Tiempo_garantia' => '12 meses',
        ]);

        $this->artisan('inventario:alertas')->assertSuccessful();

        $this->assertDatabaseHas('ALERTAS', [
            'Tipo' => Alerta::TIPO_GARANTIA,
            'fk_activo' => $activo->id,
            'Estado' => 'Activa',
        ]);
    }

    public function test_garantia_con_fecha_explicita_genera_alerta(): void
    {
        Notification::fake();

        $activo = $this->activo();

        Compra::create([
            'fk_activo' => $activo->id,
            'Fecha_Compra' => now()->subYear(),
            'Garantia' => now()->addDays(15)->format('Y-m-d'),
        ]);

        $this->artisan('inventario:alertas')->assertSuccessful();

        $this->assertDatabaseHas('ALERTAS', [
            'Tipo' => Alerta::TIPO_GARANTIA,
            'fk_activo' => $activo->id,
        ]);
    }

    public function test_al_resolver_la_condicion_la_alerta_se_marca_resuelta(): void
    {
        Notification::fake();

        $activo = $this->activoEnReparacion(30);

        $this->artisan('inventario:alertas')->assertSuccessful();
        $this->assertDatabaseHas('ALERTAS', ['fk_activo' => $activo->id, 'Estado' => 'Activa']);

        $activo->update(['Estado' => 'Disponible']);

        $this->artisan('inventario:alertas')->assertSuccessful();
        $this->assertDatabaseHas('ALERTAS', ['fk_activo' => $activo->id, 'Estado' => 'Resuelta']);
    }

    public function test_admin_puede_revisar_alertas(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'Admin']);
        $activo = $this->activoEnReparacion(30);

        $this->actingAs($admin)
            ->post(route('alertas.revisar'))
            ->assertRedirect();

        $this->assertDatabaseHas('ALERTAS', ['fk_activo' => $activo->id, 'Estado' => 'Activa']);
    }

    public function test_auditor_no_puede_acceder_a_alertas(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)->get(route('alertas.index'))->assertForbidden();
        $this->actingAs($auditor)->post(route('alertas.revisar'))->assertForbidden();
    }

    public function test_no_consulta_el_ultimo_cambio_por_activo(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'Admin']);

        for ($i = 0; $i < 5; $i++) {
            $this->activoEnReparacion(30);
        }

        DB::enableQueryLog();
        $this->artisan('inventario:alertas')->assertSuccessful();
        $consultas = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $agregados = $consultas
            ->filter(fn (array $consulta) => str_contains($consulta['query'], 'HISTORIAL_EVENTOS')
                && str_contains($consulta['query'], 'MAX('))
            ->count();

        $this->assertSame(1, $agregados);
    }

    public function test_marcar_notificaciones_como_leidas(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $activo = $this->activo();

        $alerta = Alerta::create([
            'Tipo' => Alerta::TIPO_GARANTIA,
            'fk_activo' => $activo->id,
            'Mensaje' => 'Garantía por vencer.',
            'Fecha_referencia' => now()->toDateString(),
        ]);

        $admin->notify(new AlertaInventario($alerta));
        $this->assertSame(1, $admin->fresh()->unreadNotifications->count());

        $this->actingAs($admin)
            ->post(route('notificaciones.leidas'))
            ->assertRedirect();

        $this->assertSame(0, $admin->fresh()->unreadNotifications->count());
    }
}
