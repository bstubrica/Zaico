---
name: buenas-practicas
description: Buenas prácticas de programación aplicadas a este repo — diseño, manejo de errores, seguridad, rendimiento y criterios de "terminado". Usar en cualquier tarea de implementación o refactorización.
---

# Buenas prácticas de programación

## Antes de escribir código

- Leer la fuente de verdad del repo: `AGENTS.md` (convenciones duras) y la tarea correspondiente de `plan.md`.
- Si el cambio toca varios archivos, definir primero la estrategia (datos → lógica → HTTP → vista) y respetar el orden de capas.
- Repetir un patrón existente del código base vale más que introducir una librería/nuevo patrón sin necesidad.

## Diseño de código

- Funciones pequeñas con una responsabilidad; nombres que expliquen intención (`asignarEquipo`, no `procesar2`).
- Reglas de negocio en servicios (`app/Services`), no en controladores ni en Blade. El controlador solo: validar (Form Request), orquestar el servicio, devolver vista/redirect.
- Form Requests para toda validación de entrada (nada de `$request->all()` ni validación ad-hoc en el controlador).
- Tipos de dominio > primitivos sueltos cuando el concepto se repite (ej. estados, motivos).
- Evitar duplicación copiando-pegando entre servicios: extraer método compartido cuando se repita 2+ veces con la misma semántica (p.ej. `HistorialService::registrar`).
- Sin dependencias nuevas salvo aprobación; ya están elegidas: Laravel 13, Breeze (Blade), Eloquent + PostgreSQL, Tailwind 4, Alpine, PHPUnit, Pint.

## Manejo de errores

- Casos esperados → resultado explícito o respuesta con error de validación (`withErrors`) / `abort(404|403)`, siempre con mensaje humano en español.
- Excepciones solo para fallos inesperados o violaciones de regla de negocio (`RuntimeException` capturada en el controlador); loggear con `Log::` incluyendo contexto (id de entidad, operación), nunca datos sensibles.
- Sin `catch`/`report()` vacíos; si se captura, se loggea y se decide (reintentar / propagar / responder).
- Nunca exponer stack traces, cadenas de conexión ni SQL al cliente (`APP_DEBUG=false` en producción).

## Seguridad

- Sin secretos ni contraseñas en el código fuente ni en la BD (solo `.env`, que no se commitea).
- Toda escritura protegida por rol (`middleware('role:...')`); toda operación auditable registra usuario y fecha (`HISTORIAL_EVENTOS`, `fk_usuario`).
- Validar y sanear entradas en el borde (Form Requests); Blade escapa por defecto contra XSS; Eloquent parametriza contra inyección SQL.
- Archivos de evidencia: validar tipo/tamaño en el servidor, servir solo autenticados (`Storage::download` bajo `auth`).

## Rendimiento

- Listados: eager loading (`with([...])`) + paginación (`paginate()`); nunca `get()` de tablas completas sin límite en datos grandes.
- Sin consultas N+1: relaciones resueltas con `with()` o proyección explícita.
- Operaciones multi-tabla dentro de `DB::transaction`.
- Índices en migraciones para campos de búsqueda frecuente.

## Criterio de "terminado"

1. `composer test` (o `php artisan test`) → en verde (ver skill `testing-php`).
2. `vendor/bin/pint --test` → sin cambios pendientes de estilo.
3. Flujo probado de punta a punta (navegador o test feature) para el camino feliz **y** un caso de error.
4. Estados de UI cubiertos (loading/empty/error/success) si la pantalla es nueva (ver skill `ui-ux-design`).
5. Sin archivos huérfanos, TODOs abandonados ni código comentado masivamente.
6. Reglas del inventario respetadas (ver skill `dominio-zaico`): no borrar activos, roles, auditoría en `HISTORIAL_EVENTOS`, máx. 1 asignación activa.
