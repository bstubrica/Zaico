# Zaico — Sistema de Inventario de Activos TI

> Documentación técnica completa del proyecto **Zaico**, aplicación web para la gestión,
> asignación, mantenimiento y auditoría del inventario de activos de TI, con importación
> de datos desde el reporte de Snipec IT.

| Campo | Valor |
|---|---|
| Nombre | Zaico |
| Tipo | Aplicación web (monolito) |
| Framework | Laravel 13 |
| Lenguaje | PHP 8.3 |
| Base de datos | PostgreSQL 18 |
| Frontend | Blade + Tailwind CSS 4 + Alpine.js (Breeze) |
| Autenticación | Laravel Breeze (sesiones) |
| Roles | Admin · SoporteTecnico · Auditor |
| Estado | Fases 0–5 completadas (MVP operativo) |
| Suite de pruebas | 44 tests / 138 aserciones (verde) |

---

## Tabla de contenido

1. [Descripción general](#1-descripción-general)
2. [Stack tecnológico](#2-stack-tecnológico)
3. [Arquitectura](#3-arquitectura)
4. [Estructura del proyecto](#4-estructura-del-proyecto)
5. [Instalación y configuración](#5-instalación-y-configuración)
6. [Modelo de datos](#6-modelo-de-datos)
7. [Roles y permisos](#7-roles-y-permisos)
8. [Rutas de la aplicación](#8-rutas-de-la-aplicación)
9. [Controladores](#9-controladores)
10. [Capa de servicios (lógica de negocio)](#10-capa-de-servicios-lógica-de-negocio)
11. [Modelos Eloquent](#11-modelos-eloquent)
12. [Validación (Form Requests)](#12-validación-form-requests)
13. [Interfaz de usuario (vistas)](#13-interfaz-de-usuario-vistas)
14. [Catálogo de estados](#14-catálogo-de-estados)
15. [Importación de CSV (Snipec IT)](#15-importación-de-csv-snipec-it)
16. [Reglas de negocio](#16-reglas-de-negocio)
17. [Pruebas automatizadas](#17-pruebas-automatizadas)
18. [Comandos útiles](#18-comandos-útiles)
19. [Seguridad y buenas prácticas](#19-seguridad-y-buenas-prácticas)
20. [Roadmap (fases futuras)](#20-roadmap-fases-futuras)

---

## 1. Descripción general

**Zaico** centraliza el ciclo de vida de los activos de TI de la organización:

- **Registro y consulta** de equipos (computadores, monitores, impresoras, periféricos, etc.).
- **Asignación y devolución** de equipos al personal (con trazabilidad histórica).
- **Cambio de estado / desincorporación** sin borrado físico.
- **Mantenimientos** con evidencias adjuntas (fotos, PDF, video).
- **Auditoría** completa de cada cambio relevante sobre un activo.
- **Importación masiva** del reporte `custom-assets-report` de **Snipec IT** (CSV) con
  previsualización (dry-run) e idempotencia.
- **Control de acceso por roles** de extremo a extremo (rutas, controladores y UI).

El sistema migró desde un diseño original ASP.NET Core / Blazor hacia un stack
**PHP Laravel 13 + Blade/Breeze + PostgreSQL**.

---

## 2. Stack tecnológico

### Backend

| Componente | Versión | Uso |
|---|---|---|
| PHP | `^8.3` (8.3.30 Laragon) | Lenguaje |
| laravel/framework | `^13.17` | Framework web |
| laravel/tinker | `^3.0` | REPL |
| laravel/breeze | `^2.4` (dev) | Scaffolding de autenticación (Blade) |
| laravel/boost | `^2.10` (dev) | Herramientas MCP + guías |
| laravel/pint | `^1.27` (dev) | Formateo de código |
| laravel/pail | `^1.2.5` (dev) | Visor de logs |
| laravel/pao | `^1.0.6` (dev) | Salida JSON de tests |
| phpunit/phpunit | `^12.5.12` (dev) | Framework de pruebas |
| fakerphp/faker | `^1.23` (dev) | Datos de prueba |

### Frontend

| Componente | Versión | Uso |
|---|---|---|
| tailwindcss | `^3.1` / `^4.0` (plugin Vite) | Estilos utilitarios |
| @tailwindcss/forms | `^0.5.2` | Normalización de formularios |
| alpinejs | `^3.4.2` | Interactividad ligera |
| vite | `^8.0` | Bundler |
| laravel-vite-plugin | `^3.1` | Integración Laravel/Vite |

### Base de datos

- **PostgreSQL 18**, puerto `5432`.
- Bases:
  - `Zaico` — desarrollo/producción.
  - `Zaico_test` — pruebas automatizadas.
  - `zaico_legacy_backup` — respaldo del sistema anterior.
- Nombres de tablas y columnas **en mayúsculas** (compatibilidad con Power BI).
- Extensiones PHP requeridas: `pdo_pgsql` y `pgsql`.

---

## 3. Arquitectura

Monolito Laravel siguiendo el patrón **MVC + capa de servicios**. Regla de oro: la lógica
de negocio vive en `app/Services`, no en controladores ni vistas.

```
           ┌────────────────────────────────────────────────────────┐
   HTTP    │  routes/web.php  (auth + verified)                      │
  request  │      │                                                  │
   ────────┼─►    ▼                                                  │
           │  Middleware: EnsureRole (role:Admin,SoporteTecnico…)    │
           │      │                                                  │
           │      ▼                                                  │
           │  Form Request  ──►  Controller  ──►  Service            │
           │  (validación)        (orquesta)      (reglas de negocio)│
           │                                          │             │
           │                                          ▼             │
           │                                     Eloquent Models    │
           │                                          │             │
           │                                          ▼             │
           │                                    PostgreSQL          │
           │                                                        │
           │  Controller ──► Blade View (Tailwind) ──► HTML          │
           └────────────────────────────────────────────────────────┘
```

**Capas:**

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Rutas | `routes/web.php`, `routes/auth.php` | Enrutado y agrupación por middleware |
| Middleware | `app/Http/Middleware/EnsureRole.php` | Autorización por rol (`role:…`) |
| Form Requests | `app/Http/Requests/` | Validación + autorización de entrada |
| Controladores | `app/Http/Controllers/` | Orquestación, respuesta (vista/redirect/JSON) |
| Servicios | `app/Services/` | Reglas de negocio y transacciones |
| Modelos | `app/Models/` | Acceso a datos (Eloquent) |
| Vistas | `resources/views/` | Presentación Blade + Tailwind |

---

## 4. Estructura del proyecto

```
Zaico/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # 8 controladores de negocio + Profile + Auth/
│   │   ├── Middleware/
│   │   │   └── EnsureRole.php     # Autorización por rol
│   │   └── Requests/              # 9 Form Requests (incluye ProfileUpdateRequest)
│   ├── Models/                    # 11 modelos Eloquent
│   └── Services/                  # 5 servicios de negocio
├── bootstrap/
│   └── app.php                    # Alias de middleware 'role', routing, exceptions
├── database/
│   ├── factories/                 # UserFactory, …
│   ├── migrations/                # 10 migraciones de negocio + 3 base (users/cache/jobs)
│   └── seeders/                   # EstadoSeeder, PersonalSemillaSeeder, UserSeeder
├── resources/views/               # Vistas Blade (layouts, components, por dominio)
├── routes/
│   ├── web.php                    # Rutas de negocio
│   └── auth.php                   # Rutas de autenticación (Breeze)
├── tests/                         # Feature + Unit (PHPUnit)
├── .opencode/skills/              # Skills de dominio para agentes de IA
├── AGENTS.md                      # Guías de Laravel Boost
├── plan.md                        # Plan de desarrollo (fuente de verdad)
├── phpunit.xml                    # Configuración de pruebas (pgsql/Zaico_test)
├── composer.json                  # Dependencias PHP
└── package.json                   # Dependencias JS
```

---

## 5. Instalación y configuración

### 5.1 Requisitos previos

- PHP 8.3 con extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`.
- Composer 2.
- Node.js + npm.
- PostgreSQL 18 en ejecución.

### 5.2 Puesta en marcha

```bash
# 1. Dependencias PHP
composer install

# 2. Copiar entorno y generar clave
copy .env.example .env        # (Git Bash: cp .env.example .env)
php artisan key:generate

# 3. Crear la base de datos y migrar + sembrar
php artisan migrate:fresh --seed

# 4. Dependencias JS y compilación de assets
npm install
npm run build                 # o: npm run dev   (desarrollo)

# 5. Arrancar el servidor de desarrollo
php artisan serve             # http://127.0.0.1:8000
```

> El script `composer run setup` automatiza los pasos 1–4.

### 5.3 Variables de entorno clave (`.env`)

```dotenv
APP_NAME=Zaico
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_VE

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=Zaico
DB_USERNAME=postgres
DB_PASSWORD=********          # secreto: nunca versionado en el repo

SESSION_DRIVER=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### 5.4 Base de datos de pruebas

`phpunit.xml` fuerza una conexión independiente sobre PostgreSQL:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="Zaico_test"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="CACHE_STORE" value="array"/>
```

> **Nota:** al usar PostgreSQL (no SQLite en memoria), la base `Zaico_test` debe existir
> antes de correr las pruebas. Cada test aplica `RefreshDatabase` (transacción con rollback).

---

## 6. Modelo de datos

### 6.1 Diagrama entidad-relación (resumido)

```
users ──< ASIGNACIONES >── ACTIVOS ──< MANTENIMIENTOS ──< EVIDENCIAS_MANTENIMIENTO
                │              │
                │              ├──< HISTORIAL_EVENTOS
                │              ├──1 COMPRAS
                │              └──1 ESTADOS_ACTIVO (fk_estado)
              PERSONAL

users ──< IMPORTACIONES_LOG ──< IMPORTACIONES_DETALLE
```

### 6.2 Tablas

Ocho tablas de negocio. Nombres en mayúsculas por compatibilidad con Power BI.

#### `users` (extendida)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar | |
| email | varchar unique | |
| password | varchar | hashed |
| **role** | varchar(30) | `Admin` \| `SoporteTecnico` \| `Auditor` (default `SoporteTecnico`) |
| remember_token, timestamps | | |

#### `ESTADOS_ACTIVO`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre | varchar(60) unique | Ej. `Asignado (deployed)` |
| Grupo | varchar(30) | Grupo normalizado (Ej. `Asignado`) |
| Deployed | boolean | |
| Deployable | boolean | |
| Estado | smallint | `1` = activo |

#### `ACTIVOS`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre_de_activo | varchar(150) | |
| Ubicacion | varchar(150) null | |
| Ubicacion_Predeterminada | varchar(150) null | |
| Serial | varchar(100) **unique** null | `N/A`/vacío → `NULL` |
| Fabricante | varchar(100) null | |
| Categoria | varchar(100) null | |
| Modelo | varchar(100) null | |
| Observaciones | text null | |
| Etiqueta_activo | varchar(100) **unique** | Clave de upsert de importación |
| Estado | varchar(20) | Grupo de estado |
| Direccion_MAC | varchar(17) null | |
| Imagen_Activo | varchar(255) null | |
| fk_estado | bigint null → `ESTADOS_ACTIVO` | `nullOnDelete` |
| Creado_el / Actualizado_el | timestamptz | |

Índices: `idx_activos_nombre`, `idx_activos_categoria`, `idx_activos_estado`.

#### `PERSONAL`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre, Apellido | varchar(100) | |
| Nombre_usuario | varchar(50) unique | |
| Cargo | varchar(100) null | |
| Observaciones | text null | |
| Estado | varchar(20) | `Activo` \| `Inactivo` (baja lógica) |

#### `ASIGNACIONES`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_Activo | bigint → `ACTIVOS` | `restrictOnDelete` |
| fk_Personal | bigint → `PERSONAL` | `restrictOnDelete` |
| fk_usuario | bigint → `users` | `cascadeOnDelete` |
| Fecha_asignacion | date | |
| Fecha_devolucion | date null | |
| Observaciones | text null | |
| Estado | varchar(20) | `Activa` \| `Cerrada` |

#### `COMPRAS` (1:1 con activo)

| Columna | Tipo |
|---|---|
| id | bigint PK |
| fk_activo | bigint **unique** → `ACTIVOS` (cascadeOnDelete) |
| Numero_Requisicion, Numero_factura, Proveedor, Garantia, Tiempo_garantia | varchar null |
| Fecha_Compra | date null |
| Costo_compra | decimal(12,2) null |
| Imagen_Factura | varchar(255) null |

#### `MANTENIMIENTOS`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_activo | bigint → `ACTIVOS` | cascadeOnDelete |
| Tipo | varchar(30) | `Preventivo` \| `Correctivo` \| `Otro` |
| Descripcion | text | |
| Fecha_mantenimiento | date | |
| fk_usuario | bigint → `users` | |
| Creado_el | timestamptz | |

#### `EVIDENCIAS_MANTENIMIENTO`

| Columna | Tipo |
|---|---|
| id | bigint PK |
| fk_mantenimiento | bigint → `MANTENIMIENTOS` (cascadeOnDelete) |
| Ruta_archivo | varchar(300) |
| Nombre_original | varchar(255) |
| Tipo_mime | varchar(100) null |
| Tamano_bytes | bigint null |
| Subido_el | timestamptz |

> Los archivos se almacenan en el disco `public` bajo `uploads/mantenimientos`, y se
> sirven únicamente mediante **descarga autenticada** (`Storage::download`).

#### `HISTORIAL_EVENTOS` (auditoría)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_activo | bigint → `ACTIVOS` | cascadeOnDelete |
| Tipo_evento | varchar(40) | `Creacion`, `Modificacion`, `Asignacion`, `Devolucion`, `CambioEstado`, `Mantenimiento`, `Importacion` |
| Descripcion | text null | |
| Valor_anterior / Valor_nuevo | text null | |
| fk_usuario | bigint null → `users` | |
| Fecha | timestamptz | |

#### `IMPORTACIONES_LOG` y `IMPORTACIONES_DETALLE`

**LOG** (una fila por corrida de importación):

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre_archivo | varchar(255) | |
| fk_usuario | bigint → `users` | |
| Fecha | timestamptz | |
| Total_filas, Insertadas, Actualizadas, Errores | integer | |
| Modo | varchar(20) | `DryRun` \| `Commit` |
| Estado | varchar(20) | `EnProceso` \| `Completado` \| `Fallida` |

**DETALLE** (una fila por línea del CSV):

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_importacion | bigint → `IMPORTACIONES_LOG` | cascadeOnDelete |
| Fila_numero | integer | |
| Etiqueta | varchar(100) null | |
| Accion | varchar(20) | `Insertado` \| `Actualizado` \| `SinCambios` \| `Error` |
| Mensaje | text null | |
| Datos_previos | jsonb null | Valores anteriores en actualizaciones |

---

## 7. Roles y permisos

Los roles se almacenan en `users.role` y se aplican con el middleware `EnsureRole`
(alias `role:` registrado en `bootstrap/app.php`).

| Rol | Lectura | Escritura activos/personal | Importación CSV | Auditoría (eventos) |
|---|:---:|:---:|:---:|:---:|
| **Admin** | ✅ | ✅ | ✅ | ✅ |
| **SoporteTecnico** | ✅ | ✅ | ✅ | ❌ |
| **Auditor** | ✅ | ❌ | ❌ | ✅ |

**Helpers en `User`:**

```php
public function isAdmin(): bool                    // $this->role === 'Admin'
public function puedeEscribir(): bool              // Admin | SoporteTecnico
```

**Middleware (`EnsureRole`):**

```php
// Uso: ->middleware('role:Admin,SoporteTecnico')
if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
    abort(403, 'No tiene permisos para esta operación.');
}
```

Grupos de middleware en `routes/web.php`:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // ... consulta (cualquier usuario autenticado)

    Route::middleware('role:Admin,SoporteTecnico')->group(function () {
        // Escritura: activos, personal, mantenimientos, importaciones
    });

    // Auditoría: solo Admin / Auditor
    Route::get('activos/{activo}/eventos', ...)->middleware('role:Admin,Auditor');
});
```

---

## 8. Rutas de la aplicación

Todas bajo `middleware(['auth', 'verified'])`. Las de escritura requieren también `role:…`.

| Método | URI | Nombre | Acción | Rol |
|---|---|---|---|---|
| GET | `/` | `dashboard` | `DashboardController@index` | autenticado |
| GET | `/activos` | `activos.index` | `ActivosController@index` | autenticado |
| GET | `/activos/{activo}` | `activos.show` | `ActivosController@show` | autenticado |
| GET | `/activos/nuevo` | `activos.create` | `ActivosController@create` | Admin/Soporte |
| POST | `/activos` | `activos.store` | `ActivosController@store` | Admin/Soporte |
| GET | `/activos/{activo}/editar` | `activos.edit` | `ActivosController@edit` | Admin/Soporte |
| PUT | `/activos/{activo}` | `activos.update` | `ActivosController@update` | Admin/Soporte |
| POST | `/activos/{activo}/asignacion` | `activos.asignar` | `ActivosController@asignar` | Admin/Soporte |
| POST | `/activos/{activo}/devolucion` | `activos.devolver` | `ActivosController@devolver` | Admin/Soporte |
| PUT | `/activos/{activo}/estado` | `activos.estado` | `ActivosController@cambiarEstado` | Admin/Soporte |
| GET | `/activos/{activo}/eventos` | `activos.eventos` | `ActivosController@eventos` (JSON) | Admin/Auditor |
| GET | `/activos/{activo}/mantenimientos` | `activos.mantenimientos` | `MantenimientosController@porActivo` | autenticado |
| POST | `/activos/{activo}/mantenimientos` | `activos.mantenimientos.store` | `MantenimientosController@store` | Admin/Soporte |
| GET | `/api/activos` | `api.activos` | `ActivosController@apiIndex` (JSON) | autenticado |
| GET | `/personal` | `personal.index` | `PersonalController@index` | Admin/Soporte |
| POST | `/personal` | `personal.store` | `PersonalController@store` | Admin/Soporte |
| GET | `/personal/crear` | `personal.create` | `PersonalController@create` | Admin/Soporte |
| GET | `/personal/{personal}` | `personal.show` | `PersonalController@show` | Admin/Soporte |
| GET | `/personal/{personal}/editar` | `personal.edit` | `PersonalController@edit` | Admin/Soporte |
| PUT | `/personal/{personal}` | `personal.update` | `PersonalController@update` | Admin/Soporte |
| DELETE | `/personal/{personal}` | `personal.destroy` | `PersonalController@destroy` (baja lógica) | Admin/Soporte |
| GET | `/mantenimientos` | `mantenimientos.index` | `MantenimientosController@index` | autenticado |
| GET | `/mantenimientos/evidencias/{evidencia}` | `mantenimientos.evidencia` | `MantenimientosController@evidencia` (descarga) | autenticado |
| GET | `/historial` | `historial.index` | `HistorialController@index` | autenticado |
| GET | `/estados` | `estados.index` | `EstadosController@index` | autenticado |
| GET | `/importaciones` | `importaciones.index` | `ImportacionesController@index` | Admin/Soporte |
| POST | `/importaciones` | `importaciones.store` | `ImportacionesController@store` | Admin/Soporte |
| POST | `/importaciones/confirmar` | `importaciones.confirmar` | `ImportacionesController@confirmar` | Admin/Soporte |
| GET | `/importaciones/{importacion}` | `importaciones.show` | `ImportacionesController@show` | Admin/Soporte |
| GET/PATCH/DELETE | `/profile` | `profile.*` | `ProfileController` (Breeze) | autenticado |

**Autenticación** (`routes/auth.php`, Breeze): login, registro, logout, recuperación y
reseteo de contraseña, verificación de email y confirmación de contraseña.

---

## 9. Controladores

| Controlador | Métodos | Descripción |
|---|---|---|
| `DashboardController` | `index` | Estadísticas por grupo de estado + 10 activos recientes |
| `ActivosController` | `index`, `show`, `create`, `store`, `edit`, `update`, `asignar`, `devolver`, `cambiarEstado`, `eventos`, `apiIndex` | CRUD de activos + asignación/devolución/estado |
| `PersonalController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | CRUD de personal (baja lógica) |
| `MantenimientosController` | `index`, `porActivo`, `store`, `evidencia` | Registro global/por activo + descarga de evidencias |
| `HistorialController` | `index` | Historial consolidado de asignaciones con filtros |
| `EstadosController` | `index` | Catálogo de estados con conteo de activos (HTML/JSON) |
| `ImportacionesController` | `index`, `store`, `confirmar`, `show` | Flujo de importación CSV |
| `ProfileController` | `edit`, `update`, `destroy` | Perfil del usuario (Breeze) |
| `Auth/*` | … | Autenticación (Breeze) |

Los controladores delegan la lógica de negocio a los servicios inyectados por
constructor:

```php
class ActivosController extends Controller
{
    public function __construct(private readonly ActivoService $activos) {}
    // ...
}
```

---

## 10. Capa de servicios (lógica de negocio)

### 10.1 `HistorialService`

Registro centralizado de auditoría. Todos los demás servicios lo inyectan.

```php
registrar(int $activoId, string $tipo, string $descripcion,
          ?string $anterior = null, ?string $nuevo = null, ?int $usuarioId = null): HistorialEvento
eventosDeActivo(int $activoId): Collection
```

**Tipos de evento:** `Creacion`, `Modificacion`, `Asignacion`, `Devolucion`,
`CambioEstado`, `Mantenimiento`, `Importacion`.

### 10.2 `ActivoService`

| Método | Descripción |
|---|---|
| `listar(array $filtros)` | Listado paginado con filtros (`termino`, `categoria`, `estado`, `ubicacion`, `asignado_a`) |
| `crear(array $datos, int $usuarioId)` | Alta en transacción + evento `Creacion` |
| `actualizar(Activo, array, int)` | Modificación con `Valor_anterior`/`Valor_nuevo` |
| `cambiarEstado(Activo, int $fkEstado, string $motivo, int)` | Cambio de estado con motivo obligatorio |
| `asignar(Activo, array, int)` | Asigna equipo al personal (valida duplicados y estado) |
| `devolver(Activo, array, int)` | Registra devolución y libera el activo |
| `historialAsignaciones(Activo)` | Historial de asignaciones del activo |

Detalles de implementación:
- Búsqueda de texto con `ILIKE` (PostgreSQL) sobre nombre, serial, etiqueta y modelo.
- Filtro por grupo de estado mediante `whereHas('estadoCatalogo')`.
- Filtro por persona asignada con expresión cruda `trim(Nombre || ' ' || Apellido) ILIKE ?`.
- Paginación server-side de 50 con `withQueryString()`.

### 10.3 `PersonalService`

| Método | Descripción |
|---|---|
| `listar(array $filtros)` | Listado con conteo de asignaciones activas |
| `crear(array)` | Alta de persona |
| `actualizar(Personal, array)` | Modificación |
| `darDeBaja(Personal)` | Baja **lógica** (bloquea si tiene equipos activos) |
| `equiposAsignados(Personal)` | Equipos con asignación activa |

### 10.4 `MantenimientoService`

| Método | Descripción |
|---|---|
| `crear(Activo, array, int, UploadedFile[] )` | Registro en transacción + evidencias en `uploads/mantenimientos` + evento `Mantenimiento` |
| `listarPorActivo(Activo)` | Mantenimientos de un activo |
| `listarGlobal(array $filtros)` | Listado global con filtros de fechas y activo |
| `descargarEvidencia(EvidenciaMantenimiento)` | Descarga autenticada (`Storage::download`) |

### 10.5 `ImportacionService`

Servicio central de importación CSV (ver [sección 15](#15-importación-de-csv-snipec-it)).

---

## 11. Modelos Eloquent

11 modelos. Los de negocio usan `$table` en mayúsculas y `$timestamps = false`,
gestionando sus marcas de tiempo con hooks (`booted`) o columnas propias.

| Modelo | Tabla | Relaciones destacadas |
|---|---|---|
| `User` | `users` | Helpers `isAdmin()`, `puedeEscribir()`; atributos `#[Fillable]`, `#[Hidden]` |
| `Activo` | `ACTIVOS` | `estadoCatalogo`, `asignaciones`, `asignacionActiva`, `mantenimientos`, `compra`, `eventos`; accesor `asignado_a` |
| `EstadoActivo` | `ESTADOS_ACTIVO` | `activos` |
| `Personal` | `PERSONAL` | `asignaciones`; accesor `nombre_completo` |
| `Asignacion` | `ASIGNACIONES` | `activo`, `personal`, `usuario`; scope `activas()` |
| `Compra` | `COMPRAS` | `activo` |
| `Mantenimiento` | `MANTENIMIENTOS` | `activo`, `usuario`, `evidencias` |
| `EvidenciaMantenimiento` | `EVIDENCIAS_MANTENIMIENTO` | `mantenimiento` |
| `HistorialEvento` | `HISTORIAL_EVENTOS` | `activo`, `usuario` |
| `ImportacionLog` | `IMPORTACIONES_LOG` | `usuario`, `detalles` |
| `ImportacionDetalle` | `IMPORTACIONES_DETALLE` | `importacion` (cast `Datos_previos => array`) |

**Ejemplo — hook de timestamp y relación:**

```php
protected static function booted(): void
{
    static::creating(fn (self $activo) => $activo->Creado_el = now());
    static::updating(fn (self $activo) => $activo->Actualizado_el = now());
}

public function asignacionActiva(): HasOne
{
    return $this->hasOne(Asignacion::class, 'fk_Activo', 'id')
        ->where('Estado', 'Activa');
}
```

**Modelo `User` (Laravel 13, atributos PHP):**

```php
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function isAdmin(): bool { return $this->role === 'Admin'; }
    public function puedeEscribir(): bool { return in_array($this->role, ['Admin', 'SoporteTecnico'], true); }
}
```

---

## 12. Validación (Form Requests)

Cada Form Request valida (`rules`) y autoriza (`authorize` → `puedeEscribir()`), con
mensajes y nombres de atributo **en español**.

| Form Request | Uso | Reglas destacadas |
|---|---|---|
| `StoreActivoRequest` | Alta de activo | `Nombre_de_activo` `min:3|max:150`; `Etiqueta_activo` `unique:ACTIVOS`; `Serial` `unique|nullable`; `Direccion_MAC` `mac_addr` |
| `UpdateActivoRequest` | Edición de activo | Igual que Store pero `Rule::unique(...)->ignore($id)` |
| `CambiarEstadoRequest` | Cambio de estado | `fk_estado` `exists:ESTADOS_ACTIVO,id`; `motivo` obligatorio `max:500` |
| `AsignarEquipoRequest` | Asignación | `fk_Personal` `exists:PERSONAL,id`; `Fecha_asignacion` date |
| `DevolverEquipoRequest` | Devolución | `Fecha_devolucion` date |
| `StoreMantenimientoRequest` | Mantenimiento | `Tipo` `in:Preventivo,Correctivo,Otro`; `evidencias.*` `mimes:jpg,jpeg,png,pdf,mp4|max:20480` |
| `StorePersonalRequest` | Alta/edición personal | `Nombre_usuario` `unique:PERSONAL` |
| `ImportarCsvRequest` | Importación | `archivo` `file|mimes:csv,txt|max:20480` |
| `ProfileUpdateRequest` | Perfil (Breeze) | email único ignorando el propio |

---

## 13. Interfaz de usuario (vistas)

Construida con **Blade + Tailwind CSS 4 + Alpine.js**. Layout principal con navegación
responsive, badge de rol y mensajes flash.

### 13.1 Vistas por dominio

| Dominio | Vistas |
|---|---|
| Dashboard | `dashboard.blade.php` (contadores por grupo + últimos registrados) |
| Activos | `index`, `create`, `edit`, `show`, `partials/form` |
| Personal | `index`, `create`, `edit`, `show`, `partials/form` |
| Mantenimientos | `index`, `por-activo` |
| Historial | `index` |
| Estados | `index` |
| Importaciones | `index` (subida + historial), `show` (detalle por fila) |
| Layouts / Auth / Profile | `layouts/*`, `auth/*`, `profile/*` (Breeze) |

### 13.2 Componentes Blade propios y de Breeze

- **Propios:** `status-badge` (badge por grupo de estado), `modal`, entre otros.
- **Breeze:** `dropdown`, `nav-link`, `responsive-nav-link`, `primary-button`,
  `text-input`, `input-error`, `input-label`, etc.

**Ejemplo — `components/status-badge.blade.php`:**

```blade
@php
    $colores = [
        'Asignado'       => 'bg-blue-100 text-blue-800',
        'Disponible'     => 'bg-green-100 text-green-800',
        'Reparacion'     => 'bg-amber-100 text-amber-800',
        'Defectuoso'     => 'bg-amber-100 text-amber-800',
        'Desincorporado' => 'bg-red-100 text-red-800',
    ];
@endphp
<span class="{{ $colores[$grupo] ?? 'bg-gray-100 text-gray-600' }} ...">{{ $grupo }}</span>
```

### 13.3 Estados de UI cubiertos

- **Vacío:** mensajes `@empty` / "no hay registros".
- **Error de validación:** resumen de errores en el layout + `x-input-error`.
- **Éxito:** mensajes flash (`session('success')`).
- **Responsive:** navegación de escritorio y móvil; control de acceso por rol en la UI
  (p. ej. el enlace "Importación CSV" solo aparece si `puedeEscribir()`).

---

## 14. Catálogo de estados

`EstadoSeeder` siembra los **17 estados** observados en el reporte de Snipec IT,
normalizados por grupo:

| Nombre (estado) | Grupo | Deployed | Deployable |
|---|:---:|:---:|:---:|
| Asignado (deployed) | Asignado | ✅ | |
| Asignado (deployable) | Asignado | | ✅ |
| Disponible (deployed) | Disponible | ✅ | |
| Disponible (deployable) | Disponible | | ✅ |
| Disponible > Nuevo (deployed) | Disponible | ✅ | |
| Disponible > Nuevo (deployable) | Disponible | | ✅ |
| Disponible > Usado (deployed) | Disponible | ✅ | |
| Disponible > Usado (deployable) | Disponible | | ✅ |
| Defectuoso (pending) | Defectuoso | ✅ | |
| Defectuoso (deployed) | Defectuoso | ✅ | |
| Reparacion (pending) | Reparacion | ✅ | |
| Reparacion (deployed) | Reparacion | ✅ | |
| Desincorporado (deployed) | Desincorporado | ✅ | |
| Desincorporado (undeployable) | Desincorporado | | |
| Desincorporado > Deteriorado (deployed) | Desincorporado | ✅ | |
| Desincorporado > Deteriorado (deployable) | Desincorporado | | ✅ |
| Desincorporado>Deteriorado (undeployable) | Desincorporado | | |

**Grupos normalizados:** `Asignado`, `Disponible`, `Defectuoso`, `Reparacion`,
`Desincorporado`. El campo `ACTIVOS.Estado` almacena el **grupo**; `fk_estado` apunta al
estado exacto del catálogo.

---

## 15. Importación de CSV (Snipec IT)

### 15.1 Origen de datos

Reporte `custom-assets-report` de **Snipec IT** en CSV (UTF-8, delimitador `,`, comillas
dobles, 21 columnas, ~1.167 filas).

| Columna CSV | Destino |
|---|---|
| Nombre de Activo | `ACTIVOS.Nombre_de_activo` |
| Etiqueta de Activo | `ACTIVOS.Etiqueta_activo` (**clave de upsert**) |
| Modelo | `ACTIVOS.Modelo` |
| Modelo nú. | `ACTIVOS.Observaciones` (prefijo `Modelo n.:`) |
| Categoría | `ACTIVOS.Categoria` |
| Fabricante | `ACTIVOS.Fabricante` |
| Serial | `ACTIVOS.Serial` (`N/A`/vacío → `NULL`) |
| Comprado | `COMPRAS.Fecha_Compra` |
| Costo | `COMPRAS.Costo_compra` |
| Número de Orden | `COMPRAS.Numero_Requisicion` |
| Proveedor | `COMPRAS.Proveedor` |
| Localización | `ACTIVOS.Ubicacion` |
| Ubicación Predeterminada | `ACTIVOS.Ubicacion_Predeterminada` |
| Asignado | nombre de persona (si `Tipo = user`) |
| Tipo | `user` \| `location` |
| Username | `PERSONAL.Nombre_usuario` |
| Estado | `ESTADOS_ACTIVO` + `ACTIVOS.Estado`/`fk_estado` |
| Notas | `ACTIVOS.Observaciones` |

### 15.2 Arquitectura del servicio

`ImportacionService` ejecuta tres fases:

```
Fase 1  Planificación (solo lectura)
        └─ por cada fila: decide Insertar / Actualizar / SinCambios / Error
Fase 2  Aplicación (solo si NO es dry-run)
        └─ lotes de 500 en transacción; si un lote falla, reintento fila-a-fila
Fase 3  Persistencia del log
        └─ IMPORTACIONES_LOG + IMPORTACIONES_DETALLE (siempre)
```

### 15.3 Flujo de la UI

```
Subir CSV  →  Previsualizar (DryRun, no modifica nada)  →  Confirmar (Commit)
                         │                                          │
                         ▼                                          ▼
              Vista previa por fila                    Importación real + resumen
```

- La previsualización guarda el archivo temporal en `storage/app/tmp/importaciones`
  y su ruta en sesión (`importacion_pendiente`) para habilitar el botón **Confirmar**.
- Si el CSV no tiene las columnas requeridas (`nombre de activo`, `etiqueta de activo`,
  `estado`), se devuelve un error de validación.

### 15.4 Reglas de importación

- **Upsert por `Etiqueta_activo`**: la etiqueta determina insertar o actualizar.
- **Idempotencia:** reimportar el mismo archivo produce `SinCambios` (0 inserciones/actualizaciones).
- **Serial `N/A`/vacío → `NULL`**; si el serial ya existe en otro activo → se conserva el
  existente y se deja vacío, con aviso.
- **Duplicados dentro del archivo:**
  - Etiqueta repetida → se conserva el primer registro (`SinCambios` + aviso).
  - Serial repetido → se deja vacío y se conserva el primer registro.
- **Estados nuevos** detectados en el CSV se crean automáticamente en `ESTADOS_ACTIVO`
  agrupados por el primer segmento (`>` / `(`).
- **Personal/asignación:** para filas `Tipo = user`, se crea/actualiza `PERSONAL` por
  `Nombre_usuario`; si el estado empieza por `Asignado`, se crea la asignación activa.
- **Compras:** si hay datos de compra, se crea/actualiza `COMPRAS`.
- **Auditoría:** cada fila insertada/actualizada registra un evento `Importacion`.

### 15.5 Resultado del archivo real (Tarea 4.4)

Importación verificada del reporte real (1.167 filas):

| Métrica | Valor |
|---|---|
| Filas procesadas | **1.167** |
| Activos insertados | **1.165** |
| Etiquetas repetidas conservadas | 2 (`SinCambios`) |
| Errores | **0** |
| Personal creado/actualizado | 149 |
| Asignaciones | 728 |
| Compras | 8 |
| Reimportación (idempotencia) | 0 insertadas / 0 actualizadas / 0 errores |
| Distribución por estado | **idéntica al CSV** (salvo las 2 etiquetas duplicadas) |

---

## 16. Reglas de negocio

1. **Etiqueta única** por activo; **serial único cuando no está vacío/nulo**.
2. **No borrado físico:** la desincorporación es un *cambio de estado*; el personal se da
   de **baja lógica** (`Estado = Inactivo`).
3. **Una sola asignación activa** por activo: para reasignar hay que devolver primero.
4. **No se asigna** a un activo desincorporado ni a una persona inactiva.
5. **Devolución** cierra la asignación (`Estado = Cerrada`, `Fecha_devolucion`) y libera el
   activo (grupo `Disponible`).
6. **Cambio de estado** exige **motivo** y queda auditado con valor anterior/nuevo.
7. **Baja de personal** bloqueada si la persona tiene equipos asignados activos.
8. **Auditoría automática** de cada cambio relevante en `HISTORIAL_EVENTOS`.
9. **Descarga de evidencias siempre autenticada** (nunca se sirve el directorio público
   directamente).

---

## 17. Pruebas automatizadas

- **Framework:** PHPUnit 12 con `RefreshDatabase` contra PostgreSQL (`Zaico_test`).
- **Salida:** `laravel/pao` (JSON de resultados).
- **Estado actual:** **44 tests / 138 aserciones — todos en verde.**

| Archivo | Cobertura |
|---|---|
| `ActivoCRUDTest` (8) | Alta, etiqueta duplicada, 403 por rol, login redirect, auditor lista, asignar+devolver, doble asignación, eventos solo Admin/Auditor |
| `ImportacionTest` (6) | Dry-run no persiste, commit idempotente, duplicados en el archivo, cabecera inválida, archivo no-CSV, auditor 403 |
| `MantenimientoTest` (4) | Alta con evidencia, archivo inválido rechazado, auditor 403, listado requiere sesión |
| `DashboardTest` (1) | Estadísticas por grupo autenticado |
| `ProfileTest` (5) | Perfil: ver, actualizar, verificación de email, eliminar cuenta |
| `Auth/*` (17) | Login, registro, verificación de email, reseteo y confirmación de contraseña |
| `ExampleTest`, `Unit/ExampleTest` (2) | Invitado redirigido a login |

**Ejecución:**

```bash
php artisan test --compact                     # toda la suite
php artisan test --compact --filter=Importacion # un archivo/filtro
vendor/bin/phpunit tests/Feature/ActivoCRUDTest.php
```

---

## 18. Comandos útiles

```bash
# Aplicación
php artisan serve                      # servidor de desarrollo
npm run dev                            # Vite en modo desarrollo
npm run build                          # compilar assets para producción

# Base de datos
php artisan migrate:fresh --seed       # recrear esquema + seeders
php artisan db:seed                    # solo seeders
php artisan migrate:status             # estado de migraciones

# Almacenamiento
php artisan storage:link               # enlace storage público (evidencias)

# Calidad de código
vendor/bin/pint --format agent         # formatear PHP (Pint)
vendor/bin/pint --test                 # verificar sin modificar

# Pruebas
php artisan test --compact             # suite completa

# Diagnóstico
php artisan route:list                 # listar rutas
php artisan config:show database       # ver configuración
php artisan tinker                     # consola interactiva
```

Utilidades de producción: `php artisan config:cache route:cache view:cache`.

---

## 19. Seguridad y buenas prácticas

- **Secretos** solo en `.env` (nunca versionados). `DB_PASSWORD` y `APP_KEY` fuera del repo.
- **Autorización** en dos niveles: middleware `role:` (rutas) + `authorize()` en Form
  Requests.
- **Validación** en el borde con Form Requests; **escape automático** de Blade contra XSS;
  consultas parametrizadas de Eloquent contra SQLi.
- **Evidencias** descargadas solo con `auth` + `Storage::download` (nunca exposición directa).
- **Auditoría** con usuario y fecha en cada operación relevante.
- **Rendimiento:** eager loading (`with`) para evitar N+1, paginación server-side,
  índices en PostgreSQL para campos de búsqueda frecuente.
- **PostgreSQL + identificadores en mayúsculas:** en columnas/tablas con mayúsculas usar
  SQL crudo entre comillas dobles (`"ESTADOS_ACTIVO"."Grupo"`); el query builder comilla
  automáticamente, pero `selectRaw` no.

---

## 20. Roadmap (fases futuras)

Más allá del MVP (fases 0–5), el plan contempla:

- **Fase 6:** Exportación de reportes (CSV/Excel) desde la UI.
- **Fase 7:** Escáner de códigos/etiquetas (búsqueda rápida por etiqueta o serial).
- **Fase 8:** Notificaciones de activos en reparación prolongada o garantía por vencer
  (Laravel Notifications/Queues).
- **Fase 9:** Conexión directa de Power BI a PostgreSQL (vista `vw_inventario` opcional).
- **Fase 10:** Sincronización periódica con el reporte de Snipec IT (job programado).
- **Fase 11:** Despliegue en producción (nginx + php-fpm + Docker) con backups automáticos.

---

## Apéndice · Usuarios sembrados (solo desarrollo)

| Email | Rol | Contraseña |
|---|---|---|
| `admin@ti.local` | Admin | `Admin123!` |
| `soporte@ti.local` | SoporteTecnico | `Soporte123!` |
| `auditor@ti.local` | Auditor | `Auditor123!` |

> Credenciales de **desarrollo** creadas por `UserSeeder`. Cámbielas antes de cualquier
> despliegue real.

---

*Documentación generada a partir del código fuente del proyecto. Última actualización: 2026-10-08.*
