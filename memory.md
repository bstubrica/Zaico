# memory.md — contexto persistente del proyecto

> Máximo 100 líneas. Antes de agregar, borra/resume lo que ya no sea relevante.

## Estado actual

- **Correcciones de auditoría** commiteadas y pusheadas (`886ee20`).
- **Feature "Equipos desincorporados" y "Carta de desincorporación" implementadas (2026-10-15)**, sin commitear aún (junto a cambios preexistentes ajenos en el árbol: `.agents/skills/**`, `opencode.json`, `.opencode/commands/**`, `AGENTS.md`; no tocarlos/commitear).
- **Tests:** 212 tests / 700 aserciones ✅ en SQLite en memoria. Correr con:
  `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`.
  La config por defecto (`phpunit.xml`) es pgsql `Zaico_test` y **falla aquí** por falta de contraseña (`fe_sendauth: no password supplied`). SQLite soporta el índice parcial `asignaciones_activa_unica`, así que la suite corre completa.
- **MCP Postgres**: `@microsoft/postgres-mcp` en `opencode.json` → mcp.postgres. Perfil `zaico` (127.0.0.1:5432, db `Zaico`, user `postgres`, `ro`, sslmode=disable) en `~/.postgres-mcp/connections.yaml`. Falta `connection set-password zaico` + reiniciar opencode.
- **Bases**: dev local usa `DB_CONNECTION=sqlite`; pgsql 18 nativo en 5432 (servicio `postgresql-x64-18`, scram-sha-256). Laravel Boost MCP da `database-query`/`database-schema`.
- **2026-10-19 — fix `SQLiteDatabaseDoesNotExistException`**: faltaba `database/database.sqlite` (está gitignoreado). Laravel 11+ **no** crea el archivo: hay que crearlo (`New-Item`) y luego `php artisan migrate --seed`. Verificado: `http://127.0.0.1:8000/` responde login, 27 migraciones, 17 estados, 3 usuarios.

- **2026-10-19 — acciones de fila unificadas (UI)**: en todos los listados «Ver/Editar/Baja/…» pasaron de enlaces de texto a iconos compactos con `.icon-btn` (`app.css`) + `aria-label`/`title`; columnas de texto largo con `truncate`+`title` (Nombre/Ubicación/Asignado a en activos; Nombre en personal); columna «Acciones» oculta si el usuario no tiene acciones (modelos/estados/ubicaciones). Motivo: en Equipos los botones quedaban cortados fuera de pantalla. Requiere `npm run build`.
- **2026-10-19 — scroll de tablas cómodo (UI)**: `.table-wrap` ahora tiene `max-h-[70vh]` + scroll propio, `thead` sticky y columna de identidad fija; listados con columna secundaria en `xl` y terciaria en `2xl` (nunca `md`/`lg`); contenedor ampliado a `max-w-screen-2xl` en los listados vía prop `ancho` de `AppLayout` (ver «Convenciones de tablas»). Tests: 217/714 ✅ (incluye `ComodidadTablaTest`). Requiere `npm run build`.

## Convenciones de tablas

- `.table-wrap` = `max-h-[70vh] overflow-x-auto overflow-y-auto` (+ `overscroll-behavior-x: contain`, `scrollbar-gutter: stable`); así la barra horizontal queda siempre a la vista y el `thead` sticky funciona. Para altos distintos, un `max-h` inline gana (p.ej. `importaciones/show` usa `32rem`).
- Columna fijada a la izquierda: `.table-col-fija` (izquierda del contenedor, `left:0`). Si hay checkbox de selección: el chk es `.table-col-chk` (`width: 3rem`) y la identidad es `.table-col-fija-2` (`left: 3rem`). En listados con checkbox el `th`/`td` de identidad se marca con `{{ puedeEscribir() ? 'table-col-fija-2' : 'table-col-fija' }}`.
- Regla de visibilidad de columnas en listados: esenciales siempre, secundarias `hidden xl:table-cell`, terciarias `hidden 2xl:table-cell`. **Nunca `md`/`lg`**: el sidebar de 256px aparece en `lg` y entonces el contenedor se angosta justo cuando esas clases muestran columnas.
- Ancho de contenido: `AppLayout` recibe `ancho` (default `max-w-7xl`); los listados pasan `ancho="max-w-screen-2xl"`. La clase está como literal en `AppLayout.php` → `tailwind.config.js` escanea `./app/View/Components/*.php`.
- Guarda `@media print` en `app.css`: anula `max-height`/`overflow`/`position: sticky` de tablas.

## Decisiones (auditoría)

- Alcance **Todo**: higiene repo, arquitectura (Services), rendimiento, a11y, docs.
- Mantener **Tailwind v3**; se quitó `@tailwindcss/vite` de `package.json` (el CSS usa `postcss.config.js` + `tailwind.config.js`).
- Mantener **pgsql `Zaico_test`** como BD de pruebas (SQLite solo como override local).
- Mantener `database.sql` (intencional). `repomix-output.xml` (1.19 MB) se dejó de versionar (`.gitignore` + `git rm --cached`).
- Alertas reactivadas sí notifican: `wasRecentlyCreated || wasChanged('Estado')` (ALERTAS tiene `unique(Tipo, fk_activo)` no parcial → reusa fila).
- Nombre de la aplicación actualizado a **Zaiko** (`APP_NAME`, vistas de navegación, login, layouts y defaults).

## Convenciones

- Leer `memory.md` antes de cada cambio/plan; actualizarlo después; < 100 líneas (regla en `AGENTS.md`).
- Lógica en `app/Services` (inyección por constructor en controladores); Form Requests; `RuntimeException` para reglas; auditar en `HISTORIAL_EVENTOS`; sin DELETE físico; máx. 1 asignación `Activa` por activo.
- PHP: llaves siempre, property promotion, tipos explícitos, PHPDoc sobre comentarios. Correr `vendor/bin/pint --dirty --format agent`.
- Secrets: `.env` gitignored; redactar al mostrar env vars.
- Windows/PowerShell 5.1: sin `rg` (usar grep tool); sin `&&` (usar `;`).

## Convenciones de acciones de fila

- Acciones de fila = `.icon-btn` / `.icon-btn-danger` / `.icon-btn-success` (definidas en `app.css`) con iconos del set `x-icon`, siempre con `aria-label` + `title`; formularios POST sin `class="inline"` (el `div.flex` los alinea).
- `<th>`/`<td>` «Acciones» solo si el usuario tiene al menos una acción; texto largo con `truncate` + `title`; conservar `puedeEscribir()`, `@csrf/@method` y `onsubmit confirm()`.

## Convenciones de paginación

- Listados grandes paginados vía Services: `listar(array $filtros, int $tamano = 15)`. `historialAsignaciones(Activo, int $tamano = 15)`. `idsDeFiltro()` acotado a `MAXIMO_LOTE + 1`.
- Catálogos para selects resueltos en Services (`catalogoEstados`, `gruposEstado`, `categorias`, `opcionesUbicaciones`, `opcionesModelos`, `catalogoPersonal`); los basados en `pluck()` retornan `Illuminate\Support\Collection` (alias `BaseCollection`), los de `get()` retornan Eloquent `Collection`.

## Desincorporaciones

- Nueva entidad `DESINCORPORACIONES` (baja lógica con tipo/destino/monto/motivo/fecha/estado `Vigente|Anulada`). Enum `App\Enums\TipoDesincorporacion` (`Venta, Donacion, Transferencia, Desecho, Robo, Otra`); `Venta` exige `monto` (moneda por defecto `USD`).
- Los desincorporados se **ocultan** de `activos.index` (`ActivoService::consultaListado`) y viven en `desincorporados.index` (lectura para cualquier auth; editar/reincorporar solo `puedeEscribir()`).
- Eventos de auditoría nuevos: `Desincorporacion` (baja), `Reincorporacion` (vuelve a `Disponible`, anula la baja vigente), `Modificacion` (edición de la baja).
- Reporte `reportes.desincorporados` (CSV "Exportar Excel"). Reglas compartidas en trait `Concerns\DesincorporacionRules`. `DesincorporacionService` (listado/edición) usa `BusquedaAproximada`+`PaginacionAcotada`.
- **Carta de desincorporación**: Servicio `CartaDesincorporacionService` y controlador `CartaDesincorporacionController`. Formatos vía enum `FormatoCarta` (`impresion`, `pdf`, `xlsx`, `xls`). Emisión individual (`desincorporados/{desincorporacion}/carta`) y acta por filtros (`desincorporados/carta`). Plantillas: `carta-pdf.blade.php` (DomPDF), `carta-web.blade.php` (impresión web). Hoja cálculo con PhpSpreadsheet. Config branding en `config/zaico.php` (`zaico.carta`).
- **Frontend unificado**: Etiquetas y cabeceras estandarizadas como «Tipo de desincorporación» en filtros, tabla, modal de edición y cartas. Columna «Monto» removida de la tabla de desincorporados para maximizar espacio de destino y motivo (se conserva en modal de edición, cartas y CSV).
- **Personal segmentado**: Columna `PERSONAL.fk_ubicacion` vinculada a `UBICACIONES` (nullable). Filtros en `PersonalService::consultaListado` por `ubicacion_id` (incluye `sin_ubicacion`) y `cargo`. Datalist de cargos existentes en formulario de alta/edición. Columna visible en listado, tarjeta, show y reporte CSV.
- **2026-10-19 — endurecido `ActivoService::desincorporar`**: la guarda «ya desincorporado» ahora consulta la baja `Vigente` en `DESINCORPORACIONES` (fuente autoritativa) además del grupo del catálogo, y el catch de unicidad detecta PostgreSQL `23505`, SQLite/MySQL `23000`+`19/1062` (antes solo `23505`, por eso en SQLite saltaba el `QueryException` crudo). `desincorporarLote` omite esos equipos con un solo `whereIn`. Test `DesincorporacionUnicaTest` (3 casos).

## Pendiente

- Verificar conexión real del MCP tras `set-password` (probar `postgres_mcp_list_databases`).
- Aclarar si la base local se llama `Zaico` o hay que crearla (`setup.ps1 -Database pgsql -PgPassword "..."`).

## Comandos útiles

- Tests (SQLite): `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`
- BD local desde cero: `New-Item database\database.sqlite -Force; php artisan migrate --seed` (o `.\setup.ps1`)
- Pint: `vendor/bin/pint --dirty --format agent`. **En este sandbox Pint exige permisos completos**: `is_writable()` de PHP devuelve `false` (aunque las escrituras reales funcionan), así que falla con «not writable» al escribir su temporal; ejecutarlo con `sandbox_permissions: danger-full-access`.
- `public/storage` creado con `php artisan storage:link` (junction); faltaba y las evidencias no se servían.
- Build frontend: `npm run build`
- Perfil MCP: `npx -y @microsoft/postgres-mcp connection list`
- Prod (docker): `docker compose up -d db`
