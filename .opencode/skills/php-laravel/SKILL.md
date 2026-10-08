---
name: php-laravel
description: Desarrollo en PHP 8.3 y Laravel 13 (monolito Blade + Breeze + Eloquent + PostgreSQL) en este repo. Usar al escribir o modificar código PHP, modelos, migraciones, controladores, servicios, rutas, vistas Blade o configuración del proyecto.
---

# PHP / Laravel 13 en este repo

## Verificaciones (ejecutar tras cada cambio de código PHP)

```bash
vendor/bin/pint --test        # estilo PSR-12 (Laravel Pint) — debe pasar
composer test                 # config:clear + php artisan test (canónico del repo)
php artisan migrate:status    # si se tocó esquema: migraciones consistentes
```

En desarrollo: `composer dev` (o `php artisan serve` + `npm run dev`) en http://localhost:8000.
Compilar assets solo cuando corresponda: `npm run build`.
Pruebas: ver la skill `testing-php` (SQLite en memoria, laravel/pao, plantillas). Reglas del negocio: ver `dominio-zaico`.

## Restricciones duras del entorno

- PHP **8.3+**, Laravel **13** (`laravel/framework ^13.17`), estructura moderna (sin `app/Http/Kernel.php`; configuración en `bootstrap/app.php`).
- Plataforma Windows (win32), PowerShell 5.1 como shell (sin `&&`; encadenar con `;` o `cmd1; if ($?) { cmd2 }`).
- Base de datos: **PostgreSQL** en el plan (`DB_CONNECTION=pgsql` en `.env`); el `.env` actual aún apunta a SQLite local — migrar a pgsql según Tarea 0.1 de `plan.md`. `docker-compose.yml` (Tarea 0.9) levanta PostgreSQL 16 + PgAdmin.
- Laravel Boost está **pendiente de instalar** (`composer require laravel/boost --dev && php artisan boost:install`); tras instalarlo, releer `AGENTS.md` (Boost regenera ese archivo con guías a medida).
- Frontend: **Blade + Tailwind CSS 4 + Alpine** (Breeze stack `blade`, aún no instalado — Tarea 1.1). Sin Bootstrap, sin jQuery, sin Livewire salvo aprobación. Tailwind se configura vía `@import 'tailwindcss'` + `@theme` en `resources/css/app.css` (sin `tailwind.config.js`).

## Convenciones del proyecto

- Estructura: `app/Models/`, `app/Services/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Middleware/`, `routes/web.php`, `resources/views/`, `database/migrations/`, `database/seeders/`.
- **Capas:** datos (Eloquent) → lógica (`app/Services`) → HTTP (controlador: validar con Form Request, orquestar servicio, devolver vista/redirect) → vistas Blade. Las reglas de negocio **nunca** van en controladores ni vistas.
- BD con esquema en MAYÚSCULAS heredado de `database.sql` (`ACTIVOS.Nombre_de_activo`); los modelos Eloquent declaran `$table` y `$fillable` con esos nombres exactos. No renombrar tablas/columnas.
- Modelos con `public $timestamps = false` cuando la tabla usa columnas propias (`Creado_el`/`Actualizado_el`); poblar en `booted()` con `creating`/`updating`.
- Roles (`Admin`, `SoporteTecnico`, `Auditor`) en la columna `users.role` + middleware alias `role:` (`app/Http/Middleware/EnsureRole.php`). Sin dependencias nuevas (p.ej. spatie/laravel-permission) salvo aprobación.
- Auth: Laravel Breeze (Blade), sesiones de Laravel; rutas en `routes/auth.php`.
- Reglas de negocio del inventario (estados, asignaciones, auditoría, importación CSV): ver la skill `dominio-zaico`; la referencia completa está en `plan.md`.

## Eloquent con PostgreSQL

- Eager loading siempre en listados (`with([...])`); paginar con `paginate(n)->withQueryString()`; nunca `get()` de tablas completas sin límite.
- Transacciones para operaciones multi-tabla: `DB::transaction(fn () => ...)`.
- Índices/únicos/FKs se definen en migraciones (`Blueprint`); nombres de índice replican los de `database.sql` cuando existan.
- Upserts y búsquedas de texto: `ilike` para mayúsculas-insensitivo en PostgreSQL (`where('Serial', 'ilike', "%$t%")`).
- `DELETE` físico prohibido para activos/personal → solo cambio de estado / baja lógica (`Estado = 'Inactivo'`).
- Archivos subidos: validar en Form Request (`file|mimes:...|max:...`), guardar en `storage/app/public/` + `php artisan storage:link`; servir descargas con `Storage::download` bajo `auth`, nunca exponer la carpeta directamente.

## Estilo de código

- PSR-12 (Pint formatea); tipos en firmas (`array $datos`, `?string $x`); `fn()` arrow functions para closures cortas.
- Validación en **Form Requests** (`app/Http/Requests/`) con mensajes/atributos en español; el controlador recibe el Form Request y usa `$request->validated()`.
- Salida a vistas: colecciones/arrays eager-loaded; para JSON puntual `response()->json(...)`.
- Errores de negocio → `RuntimeException` (o resultado explícito) capturada en el controlador → `back()->withErrors([...])` o abort(403/404); nunca excepciones sin capturar al usuario.
- Logging con `Illuminate\Support\Facades\Log`; nunca registrar contraseñas/tokens/secretos.
- Comentarios solo donde el *porqué* no es obvio; no documentar código evidente.
- Seguridad: secretos solo en `.env` (jamás hardcodeados); Form Requests en toda escritura; Blade escapa por defecto (no usar `{!! !!}` con input de usuario).

## Comandos útiles

```bash
php artisan make:model Activo -m        # modelo + migración
php artisan make:request StoreActivoRequest
php artisan make:service ActivoService  # si el stub existe; si no, crear archivo a mano en app/Services
php artisan make:seeder EstadoSeeder
php artisan migrate:fresh --seed        # solo local: recrea BD con seeders
php artisan tinker                      # inspección interactiva
php artisan storage:link                # symlink para archivos públicos
```
