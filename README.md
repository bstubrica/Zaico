# Zaico — Sistema de Inventario de Activos TI

Aplicación web para la gestión, asignación, mantenimiento y auditoría del inventario de
activos de TI, con importación de datos desde el reporte `custom-assets-report` de
**Snipec IT**.

Construida como **monolito Laravel 13** (Blade + Breeze + Eloquent) sobre **PostgreSQL 18**,
siguiendo el patrón **MVC + capa de servicios**.

> La documentación técnica completa está en [`DOCUMENTACION.md`](DOCUMENTACION.md) y el
> registro de desarrollo en [`plan.md`](plan.md).

---

## Características

- **Gestión de activos:** registro, consulta con filtros, edición y catálogo de estados.
- **Ficha del activo** con **foto del equipo**, **datos de compra** editables y **foto de la
  factura** (servida por descarga autenticada).
- **Vistas de tabla o tarjetas** conmutables en Equipos y Personal, con la preferencia
  recordada en el navegador (`localStorage`).
- **Asignación y devolución** de equipos al personal, con historial de asignaciones.
- **Desincorporación** individual y masiva mediante cambio de estado (sin borrado físico)
  con motivo obligatorio.
- **Mantenimientos** con evidencias adjuntas (JPG/PNG/PDF/MP4) de descarga autenticada.
- **Catálogos** de ubicaciones, modelos de activos y estados (CRUD).
- **Búsqueda rápida** por etiqueta/serial (compatible con lector USB y cámara).
- **Reportes CSV** (activos, personal, historial y mantenimientos).
- **Alertas** de reparación prolongada y garantía por vencer, con notificaciones.
- **Auditoría** completa de cada cambio relevante (`HISTORIAL_EVENTOS`).
- **Importación CSV** de Snipec IT con previsualización (dry-run), idempotencia y log por fila.
- **Administración** (depuración de datos y gestión de usuarios) exclusiva de `Admin`.
- **Control de acceso por roles:** `Admin`, `SoporteTecnico`, `Auditor`.
- **Dashboard** con estadísticas por grupo de estado y últimos activos registrados.

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.3 |
| Framework | Laravel 13 |
| Base de datos | PostgreSQL 18 |
| Frontend | Blade · Tailwind CSS 3.4 · Alpine.js |
| Autenticación | Laravel Breeze (sesiones) |
| Build | Vite 8 |
| Pruebas | PHPUnit 12 |
| Formato | Laravel Pint |

---

## Requisitos

- PHP **8.3+** con extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`.
- Composer 2
- Node.js + npm
- PostgreSQL 18 en ejecución (base `Zaico`; `Zaico_test` para las pruebas)

> También puedes ejecutar todo el stack con Docker (no necesitas PHP, Node ni PostgreSQL en el host); ver [Docker](#docker-laravel--postgresql--nginx).

---

## Instalación

### Windows (recomendado): script automático

```powershell
# Ejecutar desde la raíz del repositorio
powershell -ExecutionPolicy Bypass -File .\setup.ps1
```

`setup.ps1` (con `setup.cmd` como alias) hace todo el proceso de forma idempotente:

1. Verifica requisitos y los localiza automáticamente (PHP 8.3+, Composer 2, Node 18+).
2. Comprueba que **PostgreSQL** esté accesible (si no lo está, aborta con instrucciones).
3. `composer install`, crea `.env` (si no existe) y genera `APP_KEY`.
4. Crea las bases `Zaico` y `Zaico_test`, ejecuta migraciones + seeders.
5. Crea el enlace de `storage` y compila los assets (Vite).

Parámetros útiles:

| Parámetro | Efecto |
|---|---|
| `-Database sqlite` | Sin PostgreSQL: usa `database/database.sqlite` |
| `-Fresh` | Recrea la BD desde cero (**destruye datos**) |
| `-SkipBuild` | Omite `npm install` / `npm run build` |
| `-Serve` | Levanta `php artisan serve` al terminar |
| `-PgPassword "clave"` | Contraseña de PostgreSQL (si no, la pide) |
| `-Optimize` | Al terminar: `php artisan optimize` (producción: cache de config, rutas y vistas) |

### Manual (cualquier sistema)

```bash
# 1. Dependencias PHP
composer install

# 2. Entorno
copy .env.example .env          # Git Bash: cp .env.example .env
php artisan key:generate

# 3. Base de datos (recrea el esquema y siembra estados, personal y usuarios)
php artisan migrate:fresh --seed

# 4. Assets
npm install
npm run build                   # o: npm run dev

# 5. Arrancar
php artisan serve               # http://127.0.0.1:8000
```

> `composer run setup` automatiza los pasos 1–4. Para desarrollo, `composer run dev`
> levanta servidor, colas, logs (Pail) y Vite en paralelo.

Los usuarios de prueba inician sesión con su **usuario** (no correo): `admin` / `soporte` / `auditor`.

### Docker (Laravel + PostgreSQL + Nginx)

Requisito: Docker Desktop con el motor Linux iniciado.

```bash
docker compose up -d --build
```

- Aplicación: <http://localhost:8000>
- Servicios: `app` (PHP 8.5-FPM + Nginx + Supervisor), `db` (PostgreSQL 17) y `scheduler` (`php artisan schedule:work`).
- Datos persistentes: volúmenes `storage`, `dbdata` y `backups`.
- El `APP_KEY` se genera en el primer arranque y se guarda en `storage/.app_key`.
- Los seeders solo se ejecutan si la tabla `users` está vacía (no resetean contraseñas en cada reinicio).
- Variables de conexión: `POSTGRES_DB`, `POSTGRES_USER` y `POSTGRES_PASSWORD` (por defecto `zaico` / `zaico` / `secret`). Cámbialas en producción.
- El código va dentro de la imagen: tras cambiar PHP, Blade o JS, reconstruye con `docker compose up -d --build`.

Comandos útiles:

```bash
docker compose logs -f app
docker compose exec app php artisan migrate:status
docker compose exec app php artisan about
docker compose exec db psql -U zaico -d zaico
docker compose --profile backup run --rm backup
docker compose down          # conserva los volúmenes
docker compose down -v       # destruye BD, uploads y backups
```

> `pg_trgm`, `vw_inventario` y `vw_tarjetas_red` los crean las migraciones. El scheduler
> ejecuta `inventario:sincronizar` (06:30) e `inventario:alertas` (07:00) con la zona
> horaria de la aplicación (UTC). Para producción: `APP_ENV=production`,
> `APP_DEBUG=false`, contraseña fuerte, `RUN_SEEDERS=false`, HTTPS y backups programados.

---

## Configuración

Variables relevantes de `.env`:

```dotenv
APP_NAME=Zaico
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_VE

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=Zaico
DB_USERNAME=postgres
DB_PASSWORD=tu_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> Los secretos viven **solo** en `.env` (nunca se versionan).
>
> En Docker la conexión se define en `docker-compose.yml` (`POSTGRES_DB`, `POSTGRES_USER`,
> `POSTGRES_PASSWORD`). El `.env` local no se inyecta en los contenedores para no heredar
> la configuración SQLite.

---

## Usuarios de prueba

Creados por `UserSeeder` (solo desarrollo — **cámbialos antes de producción**):

| Usuario | Correo | Rol | Contraseña |
|---|---|---|---|
| `admin` | `admin@ti.local` | Admin | `Admin123!` |
| `soporte` | `soporte@ti.local` | SoporteTecnico | `Soporte123!` |
| `auditor` | `auditor@ti.local` | Auditor | `Auditor123!` |

> El inicio de sesión es por **usuario y contraseña**. El correo se conserva como dato de
> contacto y para la recuperación de contraseña; ya no sirve para entrar.

---

## Roles y permisos

| Rol | Consulta | Escritura (activos/personal/mantenimientos) | Importación CSV | Auditoría | Administración |
|---|:---:|:---:|:---:|:---:|:---:|
| **Admin** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SoporteTecnico** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Auditor** | ✅ | ❌ | ❌ | ✅ | ❌ |

Se aplican con el middleware `role:` (alias de `App\Http\Middleware\EnsureRole`) y con
`authorize()` en los Form Requests.

---

## Importación CSV (Snipec IT)

Flujo: **Subir → Previsualizar (dry-run) → Confirmar (commit)**.

- Clave de upsert: **`Etiqueta_activo`** (idempotente: reimportar produce `SinCambios`).
- `Serial` `N/A`/vacío → `NULL`; si ya existe en otro activo, se conserva el existente y se avisa.
- Estados nuevos detectados en el CSV se crean automáticamente en el catálogo.
- Cada corrida queda registrada en `IMPORTACIONES_LOG` / `IMPORTACIONES_DETALLE`.
- Accesible solo para `Admin` y `SoporteTecnico` en `/importaciones`.

---

## Pruebas

```bash
php artisan test --compact                       # suite completa
php artisan test --compact --filter=Importacion  # un archivo/filtro
vendor/bin/phpunit tests/Feature/ActivoCRUDTest.php
```

Las pruebas usan `RefreshDatabase` contra PostgreSQL (`Zaico_test`). Estado actual:
**189 tests / 625 aserciones en verde**.

> En equipos sin PostgreSQL (por ejemplo, CI o entornos locales ligeros) puede forzarse
> SQLite en memoria:
>
> ```powershell
> $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact
> ```

---

## Estructura del proyecto

```
app/
├── Http/
│   ├── Controllers/   # 15 controladores de negocio (+ Auth/ de Breeze)
│   ├── Middleware/    # EnsureRole
│   └── Requests/      # 21 Form Requests (validación en español)
├── Models/            # 14 modelos Eloquent (tablas en mayúsculas)
└── Services/          # 12 servicios de negocio (Activo, Personal, Mantenimiento,
                       # Historial, Importación, Reporte, Alerta, Usuario, Catálogos…)
database/
├── migrations/        # Esquema (nombres exactos para Power BI) + vista vw_inventario
└── seeders/           # Estados (17), personal, usuarios/roles
resources/
├── css/app.css        # Tailwind + clases de componentes (.card, .btn, .table…)
├── js/app.js          # Alpine + componentes (selección por lote, vista tabla/tarjeta)
└── views/             # Vistas Blade + Tailwind
routes/
├── web.php            # Rutas de negocio (auth + role)
└── auth.php           # Autenticación (Breeze)
```

Tablas de negocio (nombres en mayúsculas para compatibilidad con Power BI):
`ACTIVOS`, `ESTADOS_ACTIVO`, `UBICACIONES`, `MODELOS_ACTIVO`, `PERSONAL`, `ASIGNACIONES`,
`COMPRAS`, `MANTENIMIENTOS`, `EVIDENCIAS_MANTENIMIENTO`, `HISTORIAL_EVENTOS`, `ALERTAS`,
`IMPORTACIONES_LOG`, `IMPORTACIONES_DETALLE`, más las tablas de infraestructura
(`users`, `notifications`, `cache`, `jobs`) y la vista `vw_inventario`.

---

## Comandos útiles

```bash
php artisan migrate:fresh --seed     # recrear esquema + seeders
php artisan storage:link             # enlace para evidencias (storage/app/public)
php artisan route:list               # listar rutas
vendor/bin/pint --dirty              # formatear PHP (Pint)
npm run build                        # compilar assets para producción
```

---

## Documentación

- [`DOCUMENTACION.md`](DOCUMENTACION.md) — documentación técnica completa (arquitectura, esquema, rutas, servicios, reglas de negocio, importación y testing).
- [`plan.md`](plan.md) — plan de desarrollo por fases (0–11 completadas) y roadmap.

---

## Licencia

MIT.
