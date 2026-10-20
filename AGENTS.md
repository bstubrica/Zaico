# Memoria del proyecto (memory.md)

Este proyecto usa `memory.md` (raíz del repo) como memoria de trabajo persistente entre sesiones.

- **Antes de cada cambio o planificación**: lee `memory.md` para recuperar el contexto relevante. Si está vacío o no existe, continúa normalmente.
- **Después de cada tarea relevante**: actualiza `memory.md` con el contexto importante de forma estructurada (qué se hizo, decisiones tomadas, convenciones, pendientes, comandos útiles).
- **Límite estricto**: `memory.md` debe mantenerse siempre por debajo de 100 líneas. Antes de agregar contenido, borra o resume lo que ya no sea relevante.
- Mantén secciones cortas y reutilizables, por ejemplo: Estado actual · Decisiones · Convenciones · Pendiente · Comandos útiles.

# Autenticación

El inicio de sesión es por **usuario** (`users.username`), no por correo.

- `username`: 3–60 caracteres, minúsculas y `[a-z0-9._-]`, único; se normaliza con `trim` + `Str::lower` en `StoreUsuarioRequest`/`UpdateUsuarioRequest` y en `LoginRequest::prepareForValidation()`.
- El correo sigue siendo el dato de contacto y el canal de recuperación de contraseña (`PasswordResetLinkController`), pero ya no sirve para iniciar sesión. Los errores del login se muestran bajo la clave `username`.
- Solo `Admin` puede cambiar el usuario (formulario de administración); en el perfil propio aparece deshabilitado. Usuarios de desarrollo (`UserSeeder`): `admin`, `soporte`, `auditor`.
- Al tocar autenticación, cubre `tests/Feature/Auth/*`, `AdminUsuarioTest` y `UserSecurityTest`.

# Servidores y tarjetas de red

- Los datos de servidor (`IP`, `Sistema_Operativo`, `Memoria_RAM`, `Capacidad_Disco`, `Estatus_Servidor`) y las `TARJETAS_RED` solo aplican a equipos con `Categoria` de servidor (`config('zaico.servidores.categorias')`, comparación `Str::lower(trim(...))`).
- Reglas, mensajes y normalización compartidos en `App\Http\Requests\Concerns\ReglasServidor`; la sincronización de tarjetas (máx. 8, n.º único, auditoría) vive en `ServidorService` y la invoca `ActivoService`.
- El bloque de servidor se envía completo (`tarjetas_presente`): las filas ausentes se eliminan; un formulario sin el bloque no modifica tarjetas.
- UI (`resources/js/servidor.js`, componente Alpine `panelServidor`): el payload de `x-data` debe ser un **objeto plano** (si se anida, el Proxy raíz rompe `@click` y los botones fallan en silencio). Las filas se clonan desde `<template id="servidor-plantilla-tarjeta">` hacia `#servidor-filas-tarjetas` y hay que llamar `Alpine.initTree(fila)` tras insertarlas (Alpine no inicializa clones de `template.content`).
- Reportes/Power BI: columnas en el CSV de activos, `vw_inventario` y `vw_tarjetas_red`; cualquier cambio exige actualizar la migración de vistas y `tests/Feature/ServidorTest.php`.
- Tests de referencia: `ServidorTest`, `VistaInventarioTest`, `ReporteTest`.

# Nomenclatura de activos

- `App\Enums\CategoriaActivo` es la **única fuente de verdad** del catálogo: 25 categorías canónicas + sinónimos. `Categoria` en captura manual debe pertenecer al catálogo (los sinónimos se normalizan al canónico vía `canonico()`/`normalizar()`, comparación sin acentos ni mayúsculas). `Access Point`/`AP` pertenece a `Router`.
- `Nombre_de_activo` debe coincidir **exactamente** con la estructura `Tipo Fabricante Modelo [Serial] Etiqueta` (`config('zaico.nomenclatura.estructura')`); lo valida `App\Rules\NombreActivoNomenclatura`. Tipo = categoría canónica; Modelo = el del catálogo (`fk_modelo`→`Nombre`) con respaldo en el texto libre; **Serial es obligatorio en el nombre si el equipo tiene serial** (si no tiene, se omite); no se admiten términos arbitrarios ni texto intermedio.
- Reglas/mensajes/normalización compartidos en `App\Http\Requests\Concerns\ReglasNomenclatura` (patrón `ReglasServidor`); se aplican **solo** en `StoreActivoRequest`/`UpdateActivoRequest` (formulario). **No** aplican a la importación CSV ni al catálogo `MODELOS_ACTIVO`.
- Los registros legados no se rompen: solo se valida al escribir. En `UpdateActivoRequest` los campos usan `sometimes`, así que una edición parcial no revalida el nombre, pero si se envía un nombre debe cumplir la estructura del estado resultante.
- UI: `Categoria` es un `<select>` de `CategoriaActivo::valores()` (el controlador pasa `$categorias`); `Fabricante`/`Modelo` son `required`. Al tocar nomenclatura, cubre `tests/Feature/NomenclaturaTest.php` y los formularios de activos.

# Exportación e Importación CSV

- El servicio `ExportacionCompletaService` genera streaming CSV (delimitador coma RFC 4180 con BOM UTF-8) y soporta **multi-entidad** vía `GET importaciones/exportar?entidad=...` (whitelist en `ExportacionCompletaService::ETIQUETAS`): `activos` (por defecto), `personal`, `asignaciones`, `mantenimientos`, `desincorporaciones`, `catalogos`, `historial`, `usuarios`.
- El CSV de la entidad `activos` **no cambia de formato**: mantiene paridad 1:1 con `ImportacionService` (compras, asignaciones, NICs, desincorporaciones). Las demás entidades son volcados propios (no importables).
- Restricción de rol: `historial` y `usuarios` son **solo Admin** (`ExportacionCompletaService::SOLO_ADMIN`, validado en el controlador con 403, no solo oculto en la UI); el resto Admin/SoporteTecnico como la ruta ya existente. La exportación de `usuarios` **nunca** incluye `password` ni `remember_token`.
- La UI (`importaciones.index`) muestra un `<select>` de entidades que el controlador filtra según `isAdmin()`.
- `ImportacionService` debe mantener paridad bidireccional (*round-trip*): auto-detecta delimitador (coma o punto y coma) e interpreta los campos extendidos (`Direccion_MAC`, `IP`, datos de servidor, `Numero_factura`, `Tiempo_garantia`).
- Cobertura de tests: `tests/Feature/ExportacionImportableTest.php`, `tests/Feature/ExportacionEntidadesTest.php` y `tests/Feature/ImportacionTest.php`.

# Puesta en marcha (Windows)

- `setup.ps1` (wrapper: `setup.cmd`) instala y configura el sistema en un equipo nuevo; es **idempotente** y valida requisitos antes de tocar nada: PHP 8.3+ con extensiones, Composer 2+, Node 18+ y **conexión a PostgreSQL** (motor por defecto).
- Si PostgreSQL no responde, el script aborta con instrucciones; la alternativa sin servidor es `-Database sqlite`. Otros parámetros: `-Fresh` (destruye datos), `-SkipBuild`, `-Serve`, `-PgPassword`.
- Crea las bases `Zaico` y `Zaico_test`, `.env` (nunca lo sobreescribe), migraciones + seeders, `storage:link` y assets Vite. Documentación en `README.md` y `DOCUMENTACION.md` §5.
- Prueba de concepto al tocar el script: `powershell -NoProfile -ExecutionPolicy Bypass -File .\setup.ps1 -Database sqlite -SkipBuild` debe terminar en verde (no destructiva sin `-Fresh`).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
