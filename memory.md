# memory.md — contexto persistente del proyecto

> Máximo 100 líneas. Antes de agregar, borra/resume lo que ya no sea relevante.

## Estado actual

- **2026-10-19 — exportación completa e interpretable CSV**: exportación streaming (coma RFC 4180 + BOM UTF-8) en paridad 1:1 con `ImportacionService`. Auto-detección delimitador `,` y `;`. Mapeo completo de red, NICs, compras y servidor.
- **2026-10-19 — servidores, nomenclatura y selector de filas**: servidores + NICs, nomenclatura estricta (`Nomenclatura.md`) y selector de filas (15, 25, 50, 100) en `activos.index`. Tests y Vite build ✅.
- **2026-10-20 — script de instalación Windows**: `setup.ps1` (+ `setup.cmd`) idempotente con **PostgreSQL por defecto** (valida PHP 8.3+/Composer 2/Node 18+ y conexión PG antes de tocar nada; aborta con instrucciones si PG caído → `-Database sqlite` como alternativa). Resumen con login por `username`. Doc en README y DOCUMENTACION §5.
- **2026-10-20 — exportación multi-entidad CSV**: `importaciones/exportar?entidad=` con 8 entidades (activos, personal, asignaciones, mantenimientos, desincorporaciones, catálogos, auditoría y usuarios); auditoría/usuarios solo Admin. Selector en la UI, tests en verde.
- **2026-10-20 — rendimiento**: migración `add_performance_indexes` (índices `ASIGNACIONES.Fecha_asignacion`, `ACTIVOS.Creado_el` + GIN `pg_trgm` **solo pgsql** sobre `Nombre_de_activo`/`Serial`/`Etiqueta_activo`/`Modelo`; `ALERTAS` no: `uniq_alertas_tipo_activo` ya cubre `(Tipo, fk_activo)`). `AlertaService`: filtro `Fecha_Compra <= límite` en SQL + `lazy(500)`. Layout: total de notificaciones con `count()` (compartido) + `take(8)`. `setup.ps1 -Optimize` (`php artisan optimize`) + docs. Reglas en `AGENTS.md # Rendimiento`.
- **2026-10-21 — Reportes y KPIs**: módulo `indicadores.index`/`exportar` (`IndicadoresService`, filtros `desde/hasta` solo movimientos + `categoria/grupo` todo; 7 reportes → PDF dompdf, KPIs → CSV; gráficas SVG server-side en `components/charts/*`, en PDF como data-URI `<img>` vía `Blade::render`). Trait `Concerns\VencimientoGarantia` compartido con `AlertaService`. Índice `COMPRAS.Fecha_Compra` (ambos motores). Reglas en `AGENTS.md # Reportes y KPIs`.
- **2026-10-21 — responsive + textos**: tarjeta «Archivo» en `importaciones.show` con `col-span-2 lg:col-span-1` (desbordaba «Completado» en móvil) y `stat-card` endurecido con `break-words leading-tight` (no-op numérico). Textos de reportes unificados: REPORTES → «Inventario por categoría/ubicación», «Altas y bajas por mes»; columnas «Reparación / defecto» y «Costo total»; ventana de garantías con `config` (tarjeta + CSV + notas); hint de filtros en lenguaje llano; notas de rotación/garantías. Build Vite ✅.
- **2026-10-22 — IP general del equipo**: `ACTIVOS.IP` pasa a ser un campo general y opcional (como `Direccion_MAC`) para cualquier categoría, no solo servidores. Ver «IP del equipo».
- **2026-10-22 — PHP del equipo a 8.5.12**: `E:\php-8.5.12` (VS17 x64 TS, SHA256 verificado) + `php.ini` propio (curl, gd, intl, mbstring, pdo_sqlite/pgsql, pgsql, zip, sodium, exif; bcmath y OPcache ya integrados). PATH de usuario corregido (`...\php.exe` → directorio). Verificado: plataforma OK, 301/1062 tests ✅ y `/login` HTTP 200. **php2shell** (CVE-2026-109618/109619): 8.5.12 es la versión corregida (también 8.4.27/8.3.36/8.2.35); `php.exe` y `php-cgi.exe` locales son 8.5.12. Laragon: la carpeta `php-8.3.30-...` contiene en realidad binarios **8.5.12 NTS** (sin `php.ini` cargado: memory_limit 128M, sin extensiones).
- **2026-10-22 — Composer 2.10.3**: actualizado `C:\ProgramData\ComposerSetup\bin\composer.phar` (SHA256 oficial verificado; respaldo `composer.phar.bak-20261022`). `composer diagnose` OK y plataforma del proyecto OK con PHP 8.5.12. Esa carpeta exige administrador: la actualización se hizo con elevación UAC.
- **2026-10-22 — Docker completo**: `docker compose up -d --build` (PHP 8.5-FPM + Nginx + Supervisor, PostgreSQL 17 y scheduler). Compose **sin** `env_file: .env` (no hereda SQLite): BD vía `POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD` (`zaico`/`zaico`/`secret` por defecto). `APP_KEY` persistida en `storage/.app_key`; seeders solo si `users` está vacía (`UserSeeder` resetea claves). Backup con `postgres:17` (`docker/backup.Dockerfile`): `docker compose --profile backup run --rm backup`.
- **Tests:** 301 / 1062 aserciones ✅ (SQLite en memoria):
  `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`.
- **Bases:** dev local en SQLite (`database/database.sqlite`); pgsql 18 nativo en 5432. Laravel Boost MCP.

## Servidores (2026-10-19)

- Escalares en `ACTIVOS`: `Sistema_Operativo`/`Memoria_RAM`/`Capacidad_Disco`, `Estatus_Servidor` (`Encendido|Apagado|Mantenimiento`). Solo categoría servidor. La `IP` es **general** (ver «IP del equipo»).
- `TARJETAS_RED`: `Numero`, `Nombre`, `Direccion_MAC`, `IP`, `fk_switch`→`ACTIVOS`, `Switch`, `Puerto`; máx. 8.
- `ServidorService`: `sincronizarTarjetas`, `retratoTarjetas`. Reglas en `Concerns\ReglasServidor`.
- UI: `servidor.blade.php`, `tarjeta.blade.php`, Alpine `panelServidor` con objeto plano y `initTree(fila)`.

## IP del equipo (2026-10-22)

- `ACTIVOS.IP`: campo **general** y opcional (cualquier categoría), **no único**. Antes solo lo pintaba el bloque de servidor.
- Reglas/mensajes/normalización en `Concerns\ReglasRed` (`nullable|string|ip|max:45` → IPv4 e IPv6, IPv4 es el caso habitual), usadas por `StoreActivoRequest`/`UpdateActivoRequest`.
- UI: **un solo** `name="IP"` en `activos/partials/form.blade.php` (junto a la MAC); el bloque de servidor ya no lo pinta (duplicaba y **borraba la IP** al editar no servidores). La ficha la muestra en «Datos» de los no servidores.
- Importación CSV: `ImportacionService::ipNormalizada()` (`filter_var`); IP inválida → no se guarda (no pisa la existente) + aviso en `IMPORTACIONES_DETALLE.Mensaje`.
- Índice GIN `pg_trgm` `idx_activos_ip_trgm` sobre `ACTIVOS."IP"` (solo pgsql) por el filtro `%término%`.

## Nomenclatura (2026-10-19)

- `App\Enums\CategoriaActivo`: 25 canónicos + sinónimos. `Access Point`/`AP` → `Router`.
- `config('zaico.nomenclatura.estructura')`: orden `['Tipo','Fabricante','Modelo','Serial','Etiqueta']`.
- `App\Rules\NombreActivoNomenclatura`: valida coincidencia exacta con `Tipo Fabricante Modelo [Serial] Etiqueta`.
- Solo en `StoreActivoRequest`/`UpdateActivoRequest` (formularios). No en CSV ni legados.

## Exportación e Importación CSV (2026-10-19)

- `ExportacionCompletaService`: streaming CSV (coma RFC 4180 + BOM UTF-8) **multi-entidad** (`?entidad=`; whitelist `ETIQUETAS`): activos, personal, asignaciones, mantenimientos, desincorporaciones, catalogos, historial, usuarios. `SOLO_ADMIN = [historial, usuarios]` (403 en controlador); `usuarios` sin password.
- El CSV de `activos` no cambió: paridad 1:1 con `ImportacionService` (auto-detecta `,`/`;`); red, servidor, compras, garantía. Selector `<select>` en `importaciones.index` filtrado por `isAdmin()`.
- Botón/selector en `importaciones.index` (`route('importaciones.exportar')`), restringido a Admin y SoporteTecnico.
- Tests: `ExportacionImportableTest` + `ExportacionEntidadesTest`.

## Autenticación

- Login por **usuario** (`username`) + contraseña; correo solo para recuperación. Clave de error: `username`.
- `username`: 3-60 caracteres, minúsculas, `[a-z0-9._-]`, único. Solo Admin lo edita.
- `UserSeeder`: `admin`/`soporte`/`auditor` con sus respectivas claves `*123!`.

## Convenciones

- Leer `memory.md` antes de cambios; actualizarlo después; < 100 líneas (regla en `AGENTS.md`).
- Lógica en `app/Services` (inyección por constructor); Form Requests; `RuntimeException` para reglas; auditar en `HISTORIAL_EVENTOS`; sin DELETE físico en entidades; máx. 1 asignación `Activa` por activo.
- PHP: llaves siempre, property promotion, tipos explícitos. `vendor/bin/pint --dirty --format agent`.
- Rendimiento (detalle en `AGENTS.md # Rendimiento`): eager + `lazy(500)`, filtros en SQL, SQL pgsql condicionado a `DB::getDriverName()`, `php artisan optimize` solo en producción (`optimize:clear` en dev).
- Acciones de fila: `.icon-btn` con `x-icon` y `aria-label`/`title`. Tablas con `.table-wrap`.

## Desincorporaciones

- `DESINCORPORACIONES`: baja lógica con tipo/destino/monto/motivo/fecha/estado `Vigente|Anulada`.
- Ocultos de `activos.index`, visibles en `desincorporados.index`. Eventos y carta imprimible/PDF/Excel.
- Detección de unicidad en pgsql `23505`/SQLite-MySQL `23000`+`19/1062`.

## Comandos útiles

- Instalación Windows: `powershell -NoProfile -ExecutionPolicy Bypass -File .\setup.ps1 -Database sqlite -SkipBuild` (verde = OK; sin `-Fresh` no destructiva)
- Tests (SQLite): `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`
- BD local desde cero: `New-Item database\database.sqlite -Force; php artisan migrate --seed`
- Pint: `vendor/bin/pint --dirty --format agent`
- Build: `npm run build`
- Docker: `docker compose up -d --build`; logs `docker compose logs -f app`; shell `docker compose exec app bash`; bajar `docker compose down` (conserva volúmenes) o `docker compose down -v` (borra datos).
