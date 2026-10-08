---
name: dominio-zaico
description: Reglas de negocio del inventario TI (Zaico) — entidades ACTIVOS/PERSONAL/ASIGNACIONES/ESTADOS_ACTIVO, máquina de estados, asignación/devolución, desincorporación sin borrado, auditoría, roles y mapeo del CSV de Snipec IT. Usar al implementar o modificar cualquier funcionalidad del dominio (activos, personal, asignaciones, mantenimientos, historial, importación).
---

# Dominio de negocio — Inventario TI (Zaico)

> Fuente de verdad completa: `plan.md` (esquema + reglas) y `database.sql` (esquema histórico). Esta skill resume lo indispensable para no re-leer el plan entero.

## Entidades principales

| Entidad | Tabla | Rol en el dominio |
|---|---|---|
| Activo (equipo) | `ACTIVOS` | Núcleo del inventario. Identificador de negocio: `Etiqueta_activo` (única). |
| Estado de catálogo | `ESTADOS_ACTIVO` | 17 estados en 5 grupos. FK `ACTIVOS.fk_estado`. |
| Personal | `PERSONAL` | Directorio de personas asignatarias (no confundir con `users`, que son los operadores del sistema). |
| Asignación | `ASIGNACIONES` | Historial; máx. 1 fila `Estado = 'Activa'` por activo. |
| Compra | `COMPRAS` | 1:1 con activo (`fk_activo` unique). |
| Mantenimiento + evidencias | `MANTENIMIENTOS` / `EVIDENCIAS_MANTENIMIENTO` | Bitácora con archivos adjuntos. |
| Auditoría | `HISTORIAL_EVENTOS` | Traza inmutable de cada operación relevante. |
| Importación | `IMPORTACIONES_LOG` / `IMPORTACIONES_DETALLE` | Log por corrida y por fila del CSV. |
| Operadores | `users` (Laravel) | Usuarios del sistema con columna `role`. |

## Máquina de estados

- `ESTADOS_ACTIVO.Nombre` = estado detallado (p.ej. `Asignado (deployed)`, `Disponible > Nuevo (deployable)`).
- `ESTADOS_ACTIVO.Grupo` ∈ {`Asignado`, `Disponible`, `Desincorporado`, `Defectuoso`, `Reparacion`} — es lo que se guarda en `ACTIVOS.Estado` (denormalizado para filtros/Power BI); `fk_estado` apunta al catálogo.
- Flags `Deployed`/`Deployable` describen capacidad de despliegue; no controlan flujos todavía.
- Catálogo sembrado con los 17 estados del reporte Snipec IT (`EstadoSeeder`, idempotente con `updateOrCreate`).
- Estados desconocidos que lleguen por CSV **se crean automáticamente** en el catálogo (grupo = primer segmento antes de `>` o `(`).

## Reglas invariantes (no negociables)

1. **Nunca `DELETE` físico** de activos ni personal. Desincorporación = cambio de estado con motivo obligatorio; baja de personal = `Estado = 'Inactivo'`.
2. **Máximo 1 asignación activa por activo.** Asignar exige: sin asignación previa `Activa`, estado ≠ `Desincorporado`, personal existente y `Activo`. La asignación pone el activo en grupo `Asignado`.
3. **Devolución** cierra la asignación (`Estado = 'Cerrada'`, `Fecha_devolucion`) y devuelve el activo a grupo `Disponible`.
4. **Toda escritura relevante genera evento** en `HISTORIAL_EVENTOS` con `fk_usuario` y `Fecha`. Tipos válidos: `Creacion`, `Modificacion`, `Asignacion`, `Devolucion`, `CambioEstado`, `Mantenimiento`, `Importacion`.
5. **Cambio de estado siempre lleva motivo** (`Descripcion` del evento).
6. `Serial`: único cuando no es `NULL`; el valor `N/A` del CSV se normaliza a `NULL`.

## Matriz de roles (`users.role` + middleware `role:`)

| Acción | Admin | SoporteTecnico | Auditor |
|---|---|---|---|
| Lectura (activos, personal, historial asignaciones) | ✅ | ✅ | ✅ |
| Escritura (CRUD, asignar, devolver, desincorporar, mantenimientos) | ✅ | ✅ | ❌ (403) |
| Importación CSV | ✅ | ✅ | ❌ |
| Traza de auditoría (`HISTORIAL_EVENTOS`) | ✅ | ❌ | ✅ |

Helpers en `User`: `isAdmin()`, `puedeEscribir()`. Ocultar botones en Blade con la misma regla del middleware (no confiar solo en la UI).

## Importación del CSV de Snipec IT

- Fuente: `custom-assets-report-*.csv` (UTF-8, `,`, comillas; 1.167 filas, 21 columnas).
- **Clave de upsert: `Etiqueta_activo`** (siempre poblada). Re-importar = `SinCambios` (idempotencia).
- `Tipo = user` + `Asignado` → crear `PERSONAL` (si no existe) + asignación activa (fecha = importación, observación "Importado desde Snipec IT").
- `Tipo = location` o vacío → el valor de `Asignado` es un área: solo `Ubicacion`, no crear personal.
- Columnas ignoradas: `Compaña`, `Responsable`, `Departamento`.
- `Modelo n.` y `Notas` se **anexan** a `Observaciones` (no sobrescribirse entre sí).
- Siempre previsualizar (dry-run) antes del commit; log por fila en `IMPORTACIONES_DETALLE` con `Datos_previos` (JSONB) al actualizar.

## Power BI

- Conexión **directa a PostgreSQL** (mismas tablas); por eso los nombres en mayúsculas de `database.sql` se preservan en migraciones y modelos.
- No diseñar API JSON masiva para Power BI; los endpoints `api/activos` son solo para tablas interactivas de la UI.
