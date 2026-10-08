---
name: testing-php
description: Pruebas PHPUnit 12 en este repo — estructura Feature/Unit, RefreshDatabase con SQLite en memoria, testing de roles/403, form files, y salida JSON de laravel/pao. Usar al crear o modificar tests o al verificar que una tarea de plan.md está completa.
---

# Testing en este repo (PHPUnit 12)

## Comandos

```bash
php artisan test                          # suite completa (Unit + Feature)
php artisan test --filter=ActivoCRUDTest  # un archivo/clase
php artisan test --filter=test_puede_registrar_activo
vendor/bin/phpunit --testsuite Feature
composer test                             # config:clear + artisan test (canónico del repo)
```

### laravel/pao (salida para agentes)

El repo incluye `laravel/pao` en `require-dev`: cuando detecta que el runner es un agente, PHPUnit emite una **línea JSON final** con el resumen (`{"tool":"phpunit","tests":N,"passed":N,...}`) en lugar del banner humano. Es esperado; leer ese JSON como resultado. Desactivar con `PAO_DISABLE=1` si se necesita la salida clásica.

## Configuración actual

- `phpunit.xml`: `APP_ENV=testing`, **SQLite `:memory:`** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), cache/session/mail en array, `BCRYPT_ROUNDS=4`.
- Ojo: el plan (Tarea 0.10) prevé migrar la BD de pruebas a PostgreSQL `Zaico_test` para paridad con producción. Si se toca el esquema con features específicos de pgsql (`ilike`, `jsonb`, `timestampTz`), coordinar ese cambio antes de asumir compatibilidad con SQLite.
- No confundir con `database/database.sqlite` (BD local de desarrollo actual); las pruebas nunca la usan.

## Convenciones

- Tests **Feature** en `tests/Feature/` (HTTP + BD), **Unit** en `tests/Unit/` (servicios puros sin BD cuando sea posible).
- `use RefreshDatabase;` en toda clase que toque la BD; seedear lo mínimo en `setUp()` o por test (`EstadoActivo::create(...)`, `User::factory()->create(['role' => ...])`).
- Nombres de método en snake_case con prefijo `test_` (estilo del skeleton: `test_puede_registrar_activo`).
- `actingAs($user)` + `$this->post/put/delete(...)`; aserciones clave:
  - `assertRedirect(...)`, `assertForbidden()` (403 por rol), `assertSessionHasErrors('Campo')`, `assertSessionHas('success')`
  - `assertDatabaseHas('ACTIVOS', [...])`, `assertDatabaseCount('ACTIVOS', N)`, `assertDatabaseMissing(...)`
- Cubrir por proceso: camino feliz **y** un caso de error (duplicado, 403, regla de negocio `RuntimeException`).
- `UserFactory` actual no tiene `role` en definition; añadir estados o pasar `['role' => 'Admin']` explícito en `create()`.
- Archivos: `$this->post($ruta, ['archivo' => UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf')])` para evidencias/CSV.
- Form Requests: probar el rechazo vía HTTP (no unit-testear `rules()` directamente salvo refactor de reglas).

## Ejemplo mínimo de plantilla

```php
<?php

namespace Tests\Feature;

use App\Models\EstadoActivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EjemploTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        EstadoActivo::create(['Nombre' => 'Disponible (deployable)', 'Grupo' => 'Disponible', 'Estado' => 1]);
    }

    public function test_ruta_requiere_rol_escribir(): void
    {
        $auditor = User::factory()->create(['role' => 'Auditor']);

        $this->actingAs($auditor)
            ->put('/activos/1/estado', ['fk_estado' => 1, 'motivo' => 'x'])
            ->assertForbidden();
    }
}
```

## Criterio de verificación de tareas

Una tarea de `plan.md` solo se da por completa con `php artisan test` en verde **y** los criterios de aceptación de esa tarea marcados. Si la tarea agrega flujo HTTP nuevo, debe traer al menos un test Feature que lo ejecute.
