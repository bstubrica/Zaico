# memory.md — contexto persistente del proyecto

> Máximo 100 líneas. Antes de agregar, borra/resume lo que ya no sea relevante.

## Estado actual

- **2026-10-19 — login por `username`** commiteado en `3c0f010` (tras UI de listados `94f0fb5`). Ver «Autenticación».
- **2026-10-19 — servidores, nomenclatura y selector de filas**: servidores + NICs, nomenclatura estricta (`Nomenclatura.md`) y selector de filas (15, 25, 50, 100) en `activos.index`. Tests y Vite build ✅.
- **Tests:** 263 / 887 aserciones ✅ (SQLite en memoria):
  `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`.
- **Bases:** dev local en SQLite (`database/database.sqlite`, gitignoreado); pgsql 18 nativo en 5432 (servicio `postgresql-x64-18`). Laravel Boost MCP da `database-query`/`database-schema`.

## Servidores (2026-10-19)

- Escalares en `ACTIVOS`: `IP` (45), `Sistema_Operativo`/`Memoria_RAM`/`Capacidad_Disco` (100, texto libre), `Estatus_Servidor` (enum `Encendido|Apagado|Mantenimiento`). Nullable y solo para `Categoria` en `zaico.servidores.categorias` (`servidor`,`servidores`, comparación `Str::lower(trim(...))`).
- `TARJETAS_RED` (1:N): `Numero`, `Nombre`, `Direccion_MAC`, `IP`, `fk_switch`→`ACTIVOS`, `Switch` denormalizado, `Puerto`, `Creado_el`; único `(fk_activo,Numero)`, máx. 8. Switches desde `zaico.servidores.categorias_switch` (`switch`,`switches`) con respaldo de texto libre.
- `ServidorService`: `catalogoSwitches`, `prepararTarjetas`, `sincronizarTarjetas` (`updateOrCreate` + borra ausentes + evento `Modificacion` solo si cambió), `retratoTarjetas`. `ActivoService` lo inyecta y separa `tarjetas`/`tarjetas_presente` (sin bloque no toca tarjetas).
- Reglas en `Concerns\ReglasServidor` (normaliza trim/null, descarta filas vacías, `distinct` en `tarjetas.*.Numero`, `max:8`).
- UI: `partials/servidor.blade.php` (fieldset `x-show="esServidor"` + hidden `tarjetas_presente`) y `partials/tarjeta.blade.php`. Filas Blade + `<template id="servidor-plantilla-tarjeta">` clonado por `panelServidor` (`resources/js/servidor.js`) hacia `#servidor-filas-tarjetas`; tras insertar la fila se llama `Alpine.initTree(fila)` para que arranquen los `x-search-select` clonados. El payload de `x-data` es un **objeto plano** (anidarlo rompe `@click` en silencio). `show` tiene pestaña «Servidor».
- Filtro `ip` en `activos.index`, `apiIndex` y CSV. Columnas nuevas en CSV, `vw_inventario` y nueva `vw_tarjetas_red` (migración `2026_10_19_154310`).
- Tests: `ServidorTest` (17) cubre CRUD, validación, UI, CSV y vistas.

## Nomenclatura (2026-10-19)

- `App\Enums\CategoriaActivo` = única fuente de verdad: 25 canónicos + sinónimos (`canonico()`/`normalizar()`). `Access Point`/`AP` → `Router`.
- `config('zaico.nomenclatura.estructura')`: orden `['Tipo','Fabricante','Modelo','Serial','Etiqueta']`.
- `Concerns\ReglasNomenclatura`: normaliza categoría; regla `in`; `valorEfectivo()`/`modeloEfectivo()`.
- `App\Rules\NombreActivoNomenclatura`: valida coincidencia **exacta** con `Tipo Fabricante Modelo [Serial] Etiqueta` (limpia espacios); serial obligatorio en el nombre si el equipo tiene serial; no admite texto arbitrario.
- Solo en `StoreActivoRequest`/`UpdateActivoRequest` (formularios). No en CSV ni `MODELOS_ACTIVO`. Legados protegidos en lectura.
- UI: select de categorías, botón «Generar según campos» en formulario para auto-armar nombre conforme a la regla.
- Tests: `NomenclaturaTest` (20) + suite completa (262 tests, 885 aserciones ✅).

## Convenciones de tablas

- `.table-wrap` = `max-h-[70vh] overflow-x-auto overflow-y-auto` (+ `overscroll-behavior-x: contain`, `scrollbar-gutter: stable`); `thead` sticky. Para otro alto, un `max-h` inline gana (p.ej. `importaciones/show` usa `32rem`).
- Columna fijada: `.table-col-fija` (`left:0`); con checkbox, el chk es `.table-col-chk` (`3rem`) y la identidad `.table-col-fija-2` (`left:3rem`).
- Visibilidad de columnas: esenciales siempre, secundarias `hidden xl:table-cell`, terciarias `hidden 2xl:table-cell`. **Nunca `md`/`lg`** (el sidebar aparece en `lg`).
- Ancho: `AppLayout` recibe `ancho` (default `max-w-7xl`); listados usan `ancho="max-w-screen-2xl"` (literal escaneado en `app/View/Components`). `@media print` anula `max-height`/`overflow`/`sticky`.

## Autenticación

- Login por **usuario** (`username`) + contraseña; el correo solo sirve para recuperación/verificación. Clave de error del login: `username`.
- `username`: `required|min:3|max:60|lowercase|regex:/^[a-z0-9._-]+$/|unique` (ignora el propio id); se normaliza `trim`+`Str::lower` en Form Requests y `LoginRequest`. Solo `Admin` lo cambia.
- `UserSeeder`: `admin`/`soporte`/`auditor` con `Admin123!`/`Soporte123!`/`Auditor123!`.

## Decisiones (auditoría)

- Alcance **Todo**: higiene repo, arquitectura (Services), rendimiento, a11y, docs.
- Mantener **Tailwind v3** (`postcss.config.js` + `tailwind.config.js`; sin `@tailwindcss/vite`).
- Mantener **pgsql `Zaico_test`** como BD de pruebas (SQLite como override local) y `database.sql`.
- Alertas sí notifican con `wasRecentlyCreated || wasChanged('Estado')` (`ALERTAS` reusa fila por `unique(Tipo, fk_activo)`).
- Nombre de la app: **Zaiko**.
- Nomenclatura: catálogo cerrado y estructura de nombre **solo** en captura manual; CSV/catálogos y datos legados quedan exentos.

## Convenciones

- Leer `memory.md` antes de cada cambio/plan; actualizarlo después; < 100 líneas (regla en `AGENTS.md`).
- Lógica en `app/Services` (inyección por constructor); Form Requests; `RuntimeException` para reglas; auditar en `HISTORIAL_EVENTOS`; sin DELETE físico en entidades; máx. 1 asignación `Activa` por activo.
- PHP: llaves siempre, property promotion, tipos explícitos, PHPDoc sobre comentarios. `vendor/bin/pint --dirty --format agent`.
- Secrets: `.env` gitignored; redactar al mostrar env vars.
- Windows/PowerShell 5.1: sin `rg` (usar grep tool); sin `&&` (usar `;`).

## Convenciones de acciones de fila

- Acciones de fila = `.icon-btn` / `.icon-btn-danger` / `.icon-btn-success` (en `app.css`) con `x-icon`, siempre `aria-label` + `title`; formularios POST sin `class="inline"`.
- `<th>`/`<td>` «Acciones» solo si el usuario tiene al menos una acción; texto largo con `truncate` + `title`; conservar `puedeEscribir()`, `@csrf/@method` y `onsubmit confirm()`.

## Convenciones de paginación

- Listados grandes paginados vía Services: `listar(array $filtros, int $tamano = 15)`, `historialAsignaciones(Activo, int $tamano = 15)`; `idsDeFiltro()` acotado a `MAXIMO_LOTE + 1`.
- Catálogos para selects resueltos en Services (`catalogoEstados`, `gruposEstado`, `categorias`, `opcionesUbicaciones`, `opcionesModelos`, `catalogoPersonal`).

## Desincorporaciones

- `DESINCORPORACIONES`: baja lógica con tipo/destino/monto/motivo/fecha/estado `Vigente|Anulada`; enum `TipoDesincorporacion`; `Venta` exige monto.
- Los desincorporados se ocultan de `activos.index` y viven en `desincorporados.index` (editar/reincorporar solo `puedeEscribir()`).
- Eventos: `Desincorporacion`, `Reincorporacion`, `Modificacion`. Reporte `reportes.desincorporados`.
- Carta de desincorporación: `CartaDesincorporacionService`/`Controller`; formatos `FormatoCarta` (`impresion`, `pdf`, `xlsx`, `xls`); config en `zaico.carta`.
- Personal: `fk_ubicacion` + filtros `ubicacion_id`/`sin_ubicacion`/`cargo`; datalist de cargos.
- `desincorporar` valida la baja `Vigente` en `DESINCORPORACIONES` y detecta unicidad en pgsql `23505`/SQLite-MySQL `23000`+`19/1062`; test `DesincorporacionUnicaTest`.

## Pendiente

- Verificar conexión real del MCP tras `set-password` (`postgres_mcp_list_databases`).
- Aclarar si la base local se llama `Zaico` o hay que crearla (`setup.ps1 -Database pgsql -PgPassword "..."`).
- Commit de servidores + nomenclatura (pedir confirmación al usuario).

## Comandos útiles

- Tests (SQLite): `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`
- BD local desde cero: `New-Item database\database.sqlite -Force; php artisan migrate --seed` (o `.\setup.ps1`)
- Pint: `vendor/bin/pint --dirty --format agent`. **El sandbox exige permisos completos** (PHP `is_writable()` da `false`): ejecutar con `danger-full-access`.
- Build: `npm run build` (no correr en paralelo con tests).
- `php artisan storage:link` ya creado (junction).
- Perfil MCP: `npx -y @microsoft/postgres-mcp connection list`. Prod (docker): `docker compose up -d db`.
