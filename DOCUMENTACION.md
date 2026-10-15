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
| Frontend | Blade + Tailwind CSS 3.4 + Alpine.js (Breeze) |
| Autenticación | Laravel Breeze (sesiones) |
| Roles | Admin · SoporteTecnico · Auditor |
| Estado | Fases 0–11 completadas (MVP + extras operativo) |
| Suite de pruebas | 189 tests / 625 aserciones (verde) |

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
- **Ficha del activo** con **foto del equipo**, **datos de compra** editables y **foto de la
  factura** (servida por descarga autenticada).
- **Vistas de tabla o tarjetas** conmutables en los listados de Equipos y Personal, con la
  preferencia recordada en el navegador (`localStorage`).
- **Asignación y devolución** de equipos al personal (con trazabilidad histórica).
- **Cambio de estado / desincorporación** (individual y masiva) sin borrado físico.
- **Mantenimientos** con evidencias adjuntas (fotos, PDF, video).
- **Catálogos** de ubicaciones, modelos de activos y estados.
- **Búsqueda rápida** por etiqueta/serial (lector USB y cámara).
- **Reportes CSV** y **alertas** (reparación prolongada / garantía por vencer) con
  notificaciones en la barra superior.
- **Auditoría** completa de cada cambio relevante sobre un activo.
- **Importación masiva** del reporte `custom-assets-report` de **Snipec IT** (CSV) con
  previsualización (dry-run) e idempotencia.
- **Administración** (depuración de datos y gestión de usuarios) exclusiva de `Admin`.
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
| tailwindcss | `^3.4` (3.4.19, vía PostCSS) | Estilos utilitarios |
| @tailwindcss/forms | `^0.5.2` | Normalización de formularios |
| alpinejs | `^3.4.2` | Interactividad ligera |
| vite | `^8.0` | Bundler |
| laravel-vite-plugin | `^3.1` | Integración Laravel/Vite |

> Tailwind se integra mediante `postcss.config.js` + `tailwind.config.js` (sintaxis v3:
> `@tailwind base/components/utilities`). El paquete `@tailwindcss/vite` (`^4.0`) está
> instalado pero **no se usa** en `vite.config.js`.

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
│   │   ├── Controllers/          # 15 controladores de negocio + Profile + Auth/
│   │   ├── Middleware/
│   │   │   └── EnsureRole.php     # Autorización por rol
│   │   └── Requests/              # 21 Form Requests (incluye ProfileUpdateRequest)
│   ├── Models/                    # 14 modelos Eloquent
│   └── Services/                  # 12 servicios de negocio
├── bootstrap/
│   └── app.php                    # Alias de middleware 'role', rate limiters, routing
├── database/
│   ├── factories/                 # UserFactory, …
│   ├── migrations/                # 20 migraciones de negocio + 3 base (users/cache/jobs)
│   └── seeders/                   # EstadoSeeder, PersonalSemillaSeeder, UserSeeder
├── resources/
│   ├── css/app.css                # Tailwind + clases de componentes (.card, .btn, .table…)
│   ├── js/app.js                  # Alpine + componentes (selección por lote, vista)
│   └── views/                     # Vistas Blade (layouts, components, por dominio)
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
                │              ├──1 ESTADOS_ACTIVO    (fk_estado)
                │              ├──1 UBICACIONES       (fk_ubicacion)
                │              ├──1 MODELOS_ACTIVO    (fk_modelo)
                │              └──< ALERTAS
              PERSONAL

users ──< IMPORTACIONES_LOG ──< IMPORTACIONES_DETALLE
```

### 6.2 Tablas

Catorce tablas de negocio. Nombres en mayúsculas por compatibilidad con Power BI.

#### `users` (extendida)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | varchar | |
| email | varchar unique | |
| password | varchar | hashed |
| **role** | varchar(30) | `Admin` \| `SoporteTecnico` \| `Auditor` (default `Auditor`) |
| **activo** | boolean | `true` = acceso habilitado (baja lógica de cuentas) |
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
| Imagen_Activo | varchar(255) null | Ruta de la foto del equipo (disco `local`) |
| fk_estado | bigint null → `ESTADOS_ACTIVO` | `nullOnDelete` |
| fk_ubicacion | bigint null → `UBICACIONES` | `nullOnDelete` (se conserva `Ubicacion` denormalizado) |
| fk_modelo | bigint null → `MODELOS_ACTIVO` | `nullOnDelete` (se conserva `Modelo` denormalizado) |
| Creado_el / Actualizado_el | timestamptz | |

Índices: `idx_activos_nombre`, `idx_activos_categoria`, `idx_activos_estado`,
`idx_activos_ubicacion`, `idx_activos_modelo`, `idx_activos_estado_valor`
(columna `Estado`, añadido en la corrección de auditoría 2026-10-15).

#### `UBICACIONES` (catálogo)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre | varchar(150) **unique** | |
| Descripcion | text null | |
| Estado | varchar(20) | `Activo` \| `Inactivo` (baja lógica) |
| Creado_el / Actualizado_el | timestamptz | |

#### `MODELOS_ACTIVO` (catálogo)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Nombre | varchar(100) **unique** | |
| Fabricante | varchar(100) null | |
| Categoria | varchar(100) null | |
| Estado | varchar(20) | `Activo` \| `Inactivo` (baja lógica) |
| Creado_el / Actualizado_el | timestamptz | |

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
| Imagen_Factura | varchar(255) null (ruta de la foto de la factura, disco `local`) |

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

#### `ALERTAS` (Fase 8)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| Tipo | varchar(30) | Ej. `ReparacionProlongada`, `GarantiaPorVencer` |
| fk_activo | bigint → `ACTIVOS` | cascadeOnDelete |
| Mensaje | varchar(255) | |
| Fecha_referencia | date null | |
| Estado | varchar(20) | `Activa` \| `Revisada` |
| timestamps | | |

Único `(Tipo, fk_activo)`; índice `idx_alertas_estado`. Se generan con el comando
`inventario:alertas` y se consultan en `/alertas`. Las notificaciones se guardan en la
tabla estándar de Laravel `notifications`.

#### `HISTORIAL_EVENTOS` (auditoría)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_activo | bigint → `ACTIVOS` | cascadeOnDelete |
| Tipo_evento | varchar(40) | `Creacion`, `Modificacion`, `Asignacion`, `Devolucion`, `CambioEstado`, `Mantenimiento`, `Importacion`, `Desincorporacion`, `Reincorporacion` |
| Descripcion | text null | |
| Valor_anterior / Valor_nuevo | text null | |
| fk_usuario | bigint null → `users` | |
| Fecha | timestamptz | |

#### `DESINCORPORACIONES` (detalle de la baja)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| fk_activo | bigint → `ACTIVOS` | restrictOnDelete |
| Tipo | varchar(20) | `Venta`, `Donacion`, `Transferencia`, `Desecho`, `Robo`, `Otra` |
| Destinatario | varchar(150) | |
| Detalle_destino | varchar(255) null | |
| Referencia | varchar(100) null | Factura, acta, ticket |
| Monto | decimal(12,2) null | Obligatorio cuando `Tipo = 'Venta'` |
| Moneda | varchar(3) null | Por defecto `USD` en ventas |
| Motivo | text | Obligatorio |
| Fecha | date | Fecha de la baja |
| Estado | varchar(20) | `Vigente` / `Anulada` |
| fk_usuario | bigint → `users` | Quién registró la baja |
| Creado_el | timestamptz | Por defecto `now()` |

Índices `idx_desinc_activo`, `idx_desinc_tipo`, `idx_desinc_fecha`. Los equipos
desincorporados se **ocultan** del inventario activo y se listan en
`/desincorporados`; al reincorporar, la fila vigente pasa a `Anulada` (nunca se borra).

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

| Rol | Lectura | Escritura activos/personal | Importación CSV | Auditoría (eventos) | Administración |
|---|:---:|:---:|:---:|:---:|:---:|
| **Admin** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SoporteTecnico** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Auditor** | ✅ | ❌ | ❌ | ✅ | ❌ |

> Los usuarios deshabilitados (`users.activo = false`) no pueden iniciar sesión ni permanecer
> conectados. Las rutas de escritura aplican además *rate limiters* dedicados
> (`escritura`, `importacion`, `consulta`, `depuracion`).

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
    // Consulta (cualquier usuario autenticado): activos, desincorporados,
    // personal, historial, mantenimientos, estados, reportes, notificaciones.

    // Escritura: activos, personal, catálogos e importaciones (+ rate limit)
    Route::middleware(['role:Admin,SoporteTecnico', 'throttle:escritura'])->group(function () {
        // ...
    });

    // Auditoría: solo Admin / Auditor
    Route::get('activos/{activo}/eventos', ...)
        ->middleware(['role:Admin,Auditor', 'throttle:consulta']);

    // Administración: solo Admin (depuración y usuarios del sistema)
    Route::middleware('role:Admin')->prefix('administracion')->name('admin.')->group(function () {
        // ...
    });
});
```

---

## 8. Rutas de la aplicación

Todas bajo `middleware(['auth', 'verified'])`. Las de escritura requieren también `role:…`.

| Método | URI | Nombre | Acción | Rol |
|---|---|---|---|---|
| GET | `/` | `dashboard` | `DashboardController@index` | autenticado |
| GET | `/activos` | `activos.index` | `ActivosController@index` | autenticado |
| GET | `/activos/buscar` | `activos.buscar` | `ActivosController@buscar` | autenticado |
| GET | `/activos/nuevo` | `activos.create` | `ActivosController@create` | Admin/Soporte |
| POST | `/activos` | `activos.store` | `ActivosController@store` | Admin/Soporte |
| GET | `/activos/{activo}` | `activos.show` | `ActivosController@show` | autenticado |
| GET | `/activos/{activo}/editar` | `activos.edit` | `ActivosController@edit` | Admin/Soporte |
| PUT | `/activos/{activo}` | `activos.update` | `ActivosController@update` | Admin/Soporte |
| PUT | `/activos/{activo}/compra` | `activos.compra.update` | `ActivosController@guardarCompra` | Admin/Soporte |
| GET | `/activos/{activo}/imagen` | `activos.imagen` | `ActivosController@imagen` | autenticado |
| GET | `/activos/{activo}/factura` | `activos.factura` | `ActivosController@imagenFactura` | autenticado |
| POST | `/activos/{activo}/asignacion` | `activos.asignar` | `ActivosController@asignar` | Admin/Soporte |
| POST | `/activos/{activo}/devolucion` | `activos.devolver` | `ActivosController@devolver` | Admin/Soporte |
| PUT | `/activos/{activo}/estado` | `activos.estado` | `ActivosController@cambiarEstado` | Admin/Soporte |
| POST | `/activos/desincorporar` | `activos.desincorporar-masivo` | `ActivosController@desincorporarMasivo` | Admin/Soporte |
| POST | `/activos/{activo}/desincorporar` | `activos.desincorporar` | `ActivosController@desincorporar` | Admin/Soporte |
| POST | `/activos/{activo}/reincorporar` | `activos.reincorporar` | `DesincorporadosController@reincorporar` | Admin/Soporte |
| GET | `/desincorporados` | `desincorporados.index` | `DesincorporadosController@index` | autenticado |
| PUT | `/desincorporados/{desincorporacion}` | `desincorporados.update` | `DesincorporadosController@actualizar` | Admin/Soporte |
| GET | `/activos/{activo}/eventos` | `activos.eventos` | `ActivosController@eventos` (JSON) | Admin/Auditor |
| GET | `/activos/{activo}/mantenimientos` | `activos.mantenimientos` | `MantenimientosController@porActivo` | autenticado |
| POST | `/activos/{activo}/mantenimientos` | `activos.mantenimientos.store` | `MantenimientosController@store` | Admin/Soporte |
| GET | `/api/activos` | `api.activos` | `ActivosController@apiIndex` (JSON) | autenticado |
| GET | `/personal` | `personal.index` | `PersonalController@index` | autenticado |
| GET | `/personal/crear` | `personal.create` | `PersonalController@create` | Admin/Soporte |
| POST | `/personal` | `personal.store` | `PersonalController@store` | Admin/Soporte |
| GET | `/personal/{personal}` | `personal.show` | `PersonalController@show` | autenticado |
| GET | `/personal/{personal}/editar` | `personal.edit` | `PersonalController@edit` | Admin/Soporte |
| PUT | `/personal/{personal}` | `personal.update` | `PersonalController@update` | Admin/Soporte |
| DELETE | `/personal/{personal}` | `personal.destroy` | `PersonalController@destroy` (baja lógica) | Admin/Soporte |
| POST | `/personal/baja` | `personal.baja-masiva` | `PersonalController@bajaMasiva` | Admin/Soporte |
| GET | `/mantenimientos` | `mantenimientos.index` | `MantenimientosController@index` | autenticado |
| GET | `/mantenimientos/evidencias/{evidencia}` | `mantenimientos.evidencia` | `MantenimientosController@evidencia` (descarga) | autenticado |
| DELETE | `/mantenimientos/{mantenimiento}` | `mantenimientos.destroy` | `MantenimientosController@destroy` | Admin/Soporte |
| POST | `/mantenimientos/eliminar` | `mantenimientos.eliminar-masivo` | `MantenimientosController@destroyMasivo` | Admin/Soporte |
| GET | `/historial` | `historial.index` | `HistorialController@index` | autenticado |
| GET | `/estados` | `estados.index` | `EstadosController@index` | autenticado |
| GET | `/ubicaciones` | `ubicaciones.index` | `UbicacionController@index` | autenticado |
| GET | `/modelos` | `modelos.index` | `ModeloActivoController@index` | autenticado |
| GET | `/importaciones` | `importaciones.index` | `ImportacionesController@index` | Admin/Soporte |
| POST | `/importaciones` | `importaciones.store` | `ImportacionesController@store` | Admin/Soporte |
| POST | `/importaciones/confirmar` | `importaciones.confirmar` | `ImportacionesController@confirmar` | Admin/Soporte |
| GET | `/importaciones/{importacion}` | `importaciones.show` | `ImportacionesController@show` | Admin/Soporte |
| DELETE | `/importaciones/{importacion}` | `importaciones.destroy` | `ImportacionesController@destroy` | Admin/Soporte |
| POST | `/importaciones/eliminar` | `importaciones.eliminar-masivo` | `ImportacionesController@destroyMasivo` | Admin/Soporte |
| GET | `/alertas` | `alertas.index` | `AlertaController@index` | Admin/Soporte |
| POST | `/alertas/revisar` | `alertas.revisar` | `AlertaController@revisar` | Admin/Soporte |
| GET | `/reportes/activos` | `reportes.activos` | `ReporteController@activos` | autenticado |
| GET | `/reportes/personal` | `reportes.personal` | `ReporteController@personal` | autenticado |
| GET | `/reportes/historial` | `reportes.historial` | `ReporteController@historial` | autenticado |
| GET | `/reportes/mantenimientos` | `reportes.mantenimientos` | `ReporteController@mantenimientos` | autenticado |
| GET | `/reportes/desincorporados` | `reportes.desincorporados` | `ReporteController@desincorporados` | autenticado |
| POST | `/notificaciones/leidas` | `notificaciones.leidas` | `NotificacionesController@marcarLeidas` | autenticado |
| GET/PATCH/DELETE | `/profile` | `profile.*` | `ProfileController` (Breeze) | autenticado |

**Administración** (`/administracion`, solo `Admin`): `admin.index`, `admin.depurar`,
`admin.usuarios.index`, `admin.usuarios.create`, `admin.usuarios.store`,
`admin.usuarios.edit`, `admin.usuarios.update`, `admin.usuarios.toggle`.

**Autenticación** (`routes/auth.php`, Breeze): login, registro, logout, recuperación y
reseteo de contraseña, verificación de email y confirmación de contraseña.

---

## 9. Controladores

| Controlador | Métodos | Descripción |
|---|---|---|
| `DashboardController` | `index` | Estadísticas por grupo de estado + 10 activos recientes |
| `ActivosController` | `__construct`, `index`, `show`, `buscar`, `create`, `store`, `edit`, `update`, `guardarCompra`, `imagen`, `imagenFactura`, `asignar`, `devolver`, `cambiarEstado`, `desincorporar`, `desincorporarMasivo`, `eventos`, `apiIndex` | CRUD de activos, fotos (equipo y factura), compra editable, asignación/devolución/estado (individual y masivo) |
| `PersonalController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `bajaMasiva` | CRUD de personal (baja lógica, individual y masiva) |
| `MantenimientosController` | `index`, `porActivo`, `store`, `evidencia`, `destroy`, `destroyMasivo` | Registro + evidencias + eliminación (individual y masiva) |
| `DesincorporadosController` | `index`, `actualizar`, `reincorporar` | Apartado de equipos desincorporados (consulta para todos; edición/reincorporación para Admin/Soporte) |
| `HistorialController` | `index` | Historial consolidado de asignaciones con filtros |
| `EstadosController` | `index`, `create`, `store`, `edit`, `update` | Catálogo de estados |
| `UbicacionController` | `index`, `create`, `store`, `edit`, `update` | Catálogo de ubicaciones |
| `ModeloActivoController` | `index`, `create`, `store`, `edit`, `update` | Catálogo de modelos |
| `ImportacionesController` | `index`, `store`, `confirmar`, `show`, `destroy`, `destroyMasivo` | Flujo de importación CSV (previsualizar/confirmar, eliminar) |
| `ReporteController` | `activos`, `personal`, `historial`, `mantenimientos`, `desincorporados` | Exportación CSV |
| `AlertaController` | `index`, `revisar` | Alertas de reparación/prolongación/garantía |
| `AdminController` | `index`, `depurar` | Administración (depuración) |
| `AdminUsuarioController` | `index`, `create`, `store`, `edit`, `update`, `toggleActivo` | Gestión de usuarios (solo Admin) |
| `NotificacionesController` | `marcarLeidas` | Notificaciones |
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
| `consultaListado`, `listar`, `idsDeFiltro` | Filtros, búsqueda y listado paginado (con filtros avanzados) |
| `crear(array $datos, int $usuarioId, ?UploadedFile $imagen = null)` | Alta en transacción + guarda imagen (`Imagen_Activo`) + evento `Creacion` |
| `actualizar(Activo, array, int, ?UploadedFile $imagen = null)` | Modificación con imágenes y auditoría (`Valor_anterior`/`Valor_nuevo`) |
| `cambiarEstado(Activo, int $fkEstado, string $motivo, int)` | Cambio de estado con motivo obligatorio |
| `desincorporar(Activo, string $motivo, int)`, `desincorporarLote(array $ids, string $motivo, int)` | Desincorporación individual y masiva (baja lógica) |
| `asignar(Activo, array, int)` | Asigna equipo al personal (valida duplicados y estado) |
| `devolver(Activo, array, int)` | Registra devolución y libera el activo |
| `historialAsignaciones(Activo)` | Historial de asignaciones del activo |
| `guardarCompra(Activo, array $datos, int $usuarioId, ?UploadedFile $imagenFactura = null)` | Crea/actualiza compra + guarda factura |
| `respuestaImagen(Activo)`, `respuestaImagenFactura(Activo)` | Devuelve respuestas para servir imágenes autenticadas |
| `buscarPorCodigo(string $codigo)` | Búsqueda rápida por etiqueta/serial |

Detalles de implementación:
- Búsqueda de texto con `ILIKE` (PostgreSQL) sobre nombre, serial, etiqueta y modelo.
- Filtro por grupo de estado mediante `whereHas('estadoCatalogo')`.
- Filtro por persona asignada con expresión cruda `trim(Nombre || ' ' || Apellido) ILIKE ?`.
- Paginación server-side de 50 con `withQueryString()`.

### 10.3 `PersonalService`

| Método | Descripción |
|---|---|
| `consultaListado`, `listar`, `idsDeFiltro` | Listado con conteo de asignaciones activas + filtros |
| `crear(array)` | Alta de persona |
| `actualizar(Personal, array)` | Modificación |
| `darDeBaja(Personal)` | Baja **lógica** (bloquea si tiene equipos activos) |
| `darDeBajaLote(array $ids)` | Baja masiva (baja lógica) |
| `equiposAsignados(Personal)` | Equipos con asignación activa |

### 10.4 `MantenimientoService`

| Método | Descripción |
|---|---|
| `crear(Activo, array, int, UploadedFile[] )` | Registro en transacción + evidencias en `uploads/mantenimientos` + evento `Mantenimiento` |
| `listarPorActivo(Activo)` | Mantenimientos de un activo |
| `listarGlobal(array $filtros)` | Listado global con filtros de fechas y activo |
| `descargarEvidencia(EvidenciaMantenimiento)` | Descarga autenticada (`Storage::download`) |
| `eliminar(Mantenimiento, int $usuarioId)`, `eliminarLote(array $ids, int $usuarioId)` | Eliminación individual y masiva |

### 10.5 `ImportacionService`

Servicio central de importación CSV (ver [sección 15](#15-importación-de-csv-snipec-it)).

---

## 11. Modelos Eloquent

11 modelos. Los de negocio usan `$table` en mayúsculas y `$timestamps = false`,
gestionando sus marcas de tiempo con hooks (`booted`) o columnas propias.

| Modelo | Tabla | Relaciones destacadas |
|---|---|---|
| `User` | `users` | Helpers `isAdmin()`, `puedeEscribir()`; atributo `activo` (baja lógica); atributos `#[Fillable]`, `#[Hidden]` |
| `Activo` | `ACTIVOS` | `estadoCatalogo`, `ubicacionCatalogo`, `modeloCatalogo`, `asignaciones`, `asignacionActiva`, `mantenimientos`, `compra`, `eventos`; accesores `asignado_a`, imagen/factura |
| `EstadoActivo` | `ESTADOS_ACTIVO` | `activos` |
| `Ubicacion` | `UBICACIONES` | `activos` |
| `ModeloActivo` | `MODELOS_ACTIVO` | `activos` |
| `Personal` | `PERSONAL` | `asignaciones`; accesor `nombre_completo` |
| `Asignacion` | `ASIGNACIONES` | `activo`, `personal`, `usuario`; scope `activas()` |
| `Compra` | `COMPRAS` | `activo` |
| `Mantenimiento` | `MANTENIMIENTOS` | `activo`, `usuario`, `evidencias` |
| `EvidenciaMantenimiento` | `EVIDENCIAS_MANTENIMIENTO` | `mantenimiento` |
| `Alerta` | `ALERTAS` | `activo` |
| `HistorialEvento` | `HISTORIAL_EVENTOS` | `activo`, `usuario` |
| `ImportacionLog` | `IMPORTACIONES_LOG` | `usuario`, `detalles` |
| `ImportacionDetalle` | `IMPORTACIONES_DETALLE` | `importacion` (cast `Datos_previos => array`) |
| `Desincorporacion` | `DESINCORPORACIONES` | `activo`, `usuario` (cast `Monto => decimal:2`, `Fecha => date`) |

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
| `StoreActivoRequest` | Alta de activo | `Nombre_de_activo` `min:3|max:150`; `Etiqueta_activo` `unique:ACTIVOS`; `Serial` `unique|nullable`; `Direccion_MAC` `mac_addr`; `imagen` `image|max:10240` |
| `UpdateActivoRequest` | Edición de activo | Igual que Store pero `Rule::unique(...)->ignore($id)`; `imagen` opcional |
| `GuardarCompraRequest` | Compra + factura | Campos opcionales; `imagen_factura` `file|mimes:jpg,jpeg,png,webp|max:10240` |
| `CambiarEstadoRequest` | Cambio de estado | `fk_estado` `exists:ESTADOS_ACTIVO,id`; `motivo` obligatorio `max:500` |
| `AsignarEquipoRequest` | Asignación | `fk_Personal` `exists:PERSONAL,id`; `Fecha_asignacion` date |
| `DevolverEquipoRequest` | Devolución | `Fecha_devolucion` date |
| `StoreMantenimientoRequest` | Mantenimiento | `Tipo` `in:Preventivo,Correctivo,Otro`; `evidencias.*` `mimes:jpg,jpeg,png,pdf,mp4|max:20480` |
| `StorePersonalRequest` | Alta/edición personal | `Nombre_usuario` `unique:PERSONAL` |
| `ImportarCsvRequest` | Importación | `archivo` `file|mimes:csv,txt|max:20480` |
| `ProfileUpdateRequest` | Perfil (Breeze) | email único ignorando el propio |

(Se incluyen además Form Requests para catálogos, desincorporación masiva, eliminaciones, depuración y usuarios.)

---

## 13. Interfaz de usuario (vistas)

Construida con **Blade + Tailwind CSS 3.4 + Alpine.js**. Layout principal con navegación
responsive, badge de rol, notificaciones, selección por lote y mensajes flash.

### 13.1 Vistas por dominio

| Dominio | Vistas |
|---|---|
| Dashboard | `dashboard.blade.php` (contadores por grupo + últimos registrados) |
| Activos | `index`, `create`, `edit`, `show`, `partials/form` |
| Desincorporados | `desincorporados/index` |
| Personal | `index`, `create`, `edit`, `show`, `partials/form` |
| Mantenimientos | `index`, `por-activo` |
| Historial | `index` |
| Estados | `index` |
| Importaciones | `index` (subida + historial), `show` (detalle por fila) |
| Layouts / Auth / Profile | `layouts/*`, `auth/*`, `profile/*` (Breeze) |

| Layouts / Auth / Profile | `layouts/*`, `auth/*`, `profile/*` (Breeze) |
| Catálogos | `ubicaciones`, `modelos`, `estados` (index/create/edit) |
| Reportes / Alertas / Admin | `reportes/*`, `alertas/index`, `admin/*` |

### 13.2 Funcionalidades de UI destacadas

- **Vista tabla / tarjetas**: conmutador en Activos y Personal, persistencia en `localStorage` (`vista:activos`, `vista:personal`), componente Alpine `vistaListado` + componente Blade `vista-toggle`.
- **Selección por lote**: checkboxes + acciones masivas (desincorporación de equipos, baja de personal, eliminación de importaciones/mantenimientos) con modal de confirmación.
- **Fotos y facturas**: previsualización, subida y visualización autenticada (controladores `imagen`/`imagenFactura`).
- **Búsqueda rápida**: barra en activos con soporte para lector USB/cámara.

### 13.3 Componentes Blade propios y de Breeze

- **Propios:** `status-badge` (badge por grupo de estado), `vista-toggle`, `icon`, `page-header`, `empty-state`, `seleccion/*`, `resultado-lote`, `modal`, entre otros.
- **Breeze:** `dropdown`, `nav-link`, `responsive-nav-link`, `primary-button`,
  `text-input`, `input-error`, `input-label`, etc.

**Ejemplo — `components/status-badge.blade.php`:**
[...] 

### 13.4 Estados de UI cubiertos

- **Vacío:** mensajes `@empty` / "no hay registros".
- **Error de validación:** resumen de errores en el layout + `x-input-error`.
- **Éxito:** mensajes flash (`session('success')`).
- **Responsive:** navegación de escritorio y móvil; control de acceso por rol en la UI
  (p. ej. enlaces de escritura/importación solo aparecen si `puedeEscribir()`).
- **Acceso denegado/deshabilitado:** cuentas con `activo=false` no pueden autenticarse; middleware de roles devuelve 403 cuando corresponde.

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
8. **Baja lógica**: desincorporación de activos, baja de personal, deshabilitación de
   usuarios (`activo=false`) — nunca borrado físico.
9. **Auditoría automática** de cada cambio relevante en `HISTORIAL_EVENTOS`.
10. **Descarga de evidencias siempre autenticada** (imágenes de equipo/factura y evidencias
    de mantenimientos servidas mediante descarga autenticada).
11. **Acceso a imágenes**: `Imagen_Activo` y `Imagen_Factura` se almacenan en disco `local`
    y se sirven vía `activos.imagen` / `activos.factura` con `auth`.

---

## 17. Pruebas automatizadas

- **Framework:** PHPUnit 12 con `RefreshDatabase`. Por defecto usa PostgreSQL (`Zaico_test`);
  para entornos ligeros puede forzarse SQLite en memoria mediante variables de entorno
  (`$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'`).
- **Salida:** `laravel/pao` (JSON de resultados).
- **Estado actual:** **189 tests / 625 aserciones — todos en verde.**

| Archivo | Cobertura |
|---|---|
| `ActivoCompraImagenTest` (18) | Fotos de activo/factura, subida/validación, sustitución y acceso |
| `ActivoCRUDTest` (24) | CRUD, estados, asignación/devolución, 403, eventos |
| `AdminDepuracionTest` (8) | Depuración (solo Admin) |
| `AdminUsuarioTest` (16) | Gestión de usuarios (habilitar/deshabilitar, roles) |
| `AlertaTest` (9) | Alertas de garantía/reparación prolongada |
| `BusquedaFiltrosTest` (7) | Filtros en activos/personal |
| `BusquedaRapidaTest` (6) | Búsqueda por etiqueta/serial |
| `DashboardTest` (1) | Estadísticas |
| `EliminacionRegistrosTest` (16) | Eliminación masiva/bajas |
| `EstadoCRUDTest` (10) | Catálogo de estados |
| `ImportacionTest` (10) | Dry-run/commit/idempotencia |
| `MantenimientoTest` (7) | Mantenimientos + evidencias |
| `ModeloActivoCRUDTest` (7) | Catálogo de modelos |
| `ProfileTest` (6) | Perfil (Breeze) |
| `ReporteTest` (8) | Exportación CSV |
| `DesincorporacionTest` (8) | Apartado de desincorporados: lectura, edición, reincorporación |
| `SincronizacionSnipecTest` (4) | Sincronización Snipec IT |
| `SmokePantallasTest` (1) | Render de pantallas críticas |
| `UbicacionCRUDTest` (7) | Catálogo de ubicaciones |
| `UserSecurityTest` (2) | Seguridad de usuarios |
| `VistaInventarioTest` (2) | Vista `vw_inventario` |
| `Auth/*` (19) | Login, registro, verificación, reseteo, confirmación |
| `ExampleTest` (1) + `Unit/ExampleTest` (1) | Smoke tests |

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

Más allá del MVP (fases 0–5), el plan contemplaba las siguientes fases, ya implementadas:

- [x] **Fase 6:** Exportación de reportes (CSV) desde la UI (`ReporteService`, `reportes.*`).
- [x] **Fase 7:** Búsqueda rápida por etiqueta/serial (`activos.buscar`) con lector USB y cámara.
- [x] **Fase 8:** Alertas de reparación prolongada y garantía por vencer (tabla `ALERTAS`,
  comando `inventario:alertas`, notificaciones de BD).
- [x] **Fase 9:** Vista de solo lectura `vw_inventario` para Power BI (migración).
- [x] **Fase 10:** Sincronización periódica con Snipec IT (comando `inventario:sincronizar`).
- [x] **Fase 11:** Despliegue en producción (Docker: php-fpm + nginx + supervisor) con backups.

> Verificación local contra PostgreSQL 18 real: migraciones y suite completa (189 tests /
> 625 aserciones) en verde.

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

*Documentación generada a partir del código fuente del proyecto. Última actualización: 2026-10-14.*
