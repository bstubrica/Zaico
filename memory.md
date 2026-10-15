# memory.md — contexto persistente del proyecto

> Máximo 100 líneas. Antes de agregar, borra/resume lo que ya no sea relevante.

## Estado actual

- **Correcciones de auditoría aplicadas (2026-10-15)**, sin commitear (hay cambios preexistentes ajenos en el árbol: `.agents/skills/**`, `opencode.json`, `.opencode/commands/**`, `AGENTS.md`; no tocarlos/commitear).
- **Tests:** 189 tests / 625 aserciones ✅ en SQLite en memoria. Correr con:
  `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`.
  La config por defecto (`phpunit.xml`) es pgsql `Zaico_test` y **falla aquí** por falta de contraseña (`fe_sendauth: no password supplied`). SQLite soporta el índice parcial `asignaciones_activa_unica`, así que la suite corre completa.
- **MCP Postgres**: `@microsoft/postgres-mcp` en `opencode.json` → mcp.postgres. Perfil `zaico` (127.0.0.1:5432, db `Zaico`, user `postgres`, `ro`, sslmode=disable) en `~/.postgres-mcp/connections.yaml`. Falta `connection set-password zaico` + reiniciar opencode.
- **Bases**: dev local usa `DB_CONNECTION=sqlite`; pgsql 18 nativo en 5432 (servicio `postgresql-x64-18`, scram-sha-256). Laravel Boost MCP da `database-query`/`database-schema`.

## Decisiones (auditoría)

- Alcance **Todo**: higiene repo, arquitectura (Services), rendimiento, a11y, docs.
- Mantener **Tailwind v3**; se quitó `@tailwindcss/vite` de `package.json` (el CSS usa `postcss.config.js` + `tailwind.config.js`).
- Mantener **pgsql `Zaico_test`** como BD de pruebas (SQLite solo como override local).
- Mantener `database.sql` (intencional). `repomix-output.xml` (1.19 MB) se dejó de versionar (`.gitignore` + `git rm --cached`).
- Alertas reactivadas sí notifican: `wasRecentlyCreated || wasChanged('Estado')` (ALERTAS tiene `unique(Tipo, fk_activo)` no parcial → reusa fila).

## Convenciones

- Leer `memory.md` antes de cada cambio/plan; actualizarlo después; < 100 líneas (regla en `AGENTS.md`).
- Lógica en `app/Services` (inyección por constructor en controladores); Form Requests; `RuntimeException` para reglas; auditar en `HISTORIAL_EVENTOS`; sin DELETE físico; máx. 1 asignación `Activa` por activo.
- PHP: llaves siempre, property promotion, tipos explícitos, PHPDoc sobre comentarios. Correr `vendor/bin/pint --dirty --format agent`.
- Secrets: `.env` gitignored; redactar al mostrar env vars.
- Windows/PowerShell 5.1: sin `rg` (usar grep tool); sin `&&` (usar `;`).

## Convenciones de paginación

- Listados grandes paginados vía Services: `listar(array $filtros, int $tamano = 15)`. `historialAsignaciones(Activo, int $tamano = 15)`. `idsDeFiltro()` acotado a `MAXIMO_LOTE + 1`.
- Catálogos para selects resueltos en Services (`catalogoEstados`, `gruposEstado`, `categorias`, `opcionesUbicaciones`, `opcionesModelos`, `catalogoPersonal`); los basados en `pluck()` retornan `Illuminate\Support\Collection` (alias `BaseCollection`), los de `get()` retornan Eloquent `Collection`.

## Pendiente

- Fase 4 docs: `DOCUMENTACION.md`/`README.md` ya describen Tailwind 3.4 y pgsql `Zaico_test`; falta añadir nota de las correcciones de auditoría (o dejarlo en este `memory.md`).
- Verificar conexión real del MCP tras `set-password` (probar `postgres_mcp_list_databases`).
- Aclarar si la base local se llama `Zaico` o hay que crearla (`setup.ps1 -Database pgsql -PgPassword "..."`).

## Comandos útiles

- Tests (SQLite): `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php artisan test --compact`
- Pint: `vendor/bin/pint --dirty --format agent`
- Build frontend: `npm run build`
- Perfil MCP: `npx -y @microsoft/postgres-mcp connection list`
- Prod (docker): `docker compose up -d db`
