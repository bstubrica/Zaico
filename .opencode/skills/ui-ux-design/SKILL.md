---
name: ui-ux-design
description: Diseño de interfaces UI/UX para la web — jerarquía visual, tipografía, color, espaciado, componentes, estados de UI, accesibilidad y responsive. Usar al crear o modificar pantallas, páginas, formularios o estilos del sistema (Blade + Tailwind CSS 4).
---

# UI/UX Design

## Proceso

1. Antes de diseñar: definir objetivo de la pantalla, usuario, y qué acción principal debe realizar.
2. Revisar pantallas existentes del proyecto para mantener consistencia (mismos colores, espaciado, componentes).
3. Diseñar primero la jerarquía (qué se ve primero), luego el estilo.

## Reglas de layout y jerarquía

- Un solo objetivo primario por pantalla; el CTA principal es visualmente dominante.
- Alineación a una retícula de 8px; espaciados en múltiplos de 4 (4/8/12/16/24/32).
- Agrupación por proximidad: contenido relacionado cerca, secciones separadas con aire.
- Fijar el ancho de contenido (~1100–1280px) y centrarlo; nunca que el texto ocupe todo el viewport.
- Formularios: etiquetas arriba del campo, campos a ancho completo en móvil, botones alineados a la derecha o al final del formulario.

## Tipografía

- Máximo 2 familias tipográficas; escala consistente: 12/14/16/20/24/32px.
- Peso 600–700 solo para encabezados y énfasis; texto corrido 400, 16px, line-height 1.5.
- Longitud de línea de texto: 60–80 caracteres.

## Color

- Paleta limitada: 1 primario, 1 acento, 3–5 neutros, semánticos (éxito/advertencia/error).
- Contraste WCAG AA mínimo: 4.5:1 en texto normal, 3:1 en texto grande y bordes de controles.
- Estados siempre visibles: hover, focus (outline, no outline:none), active, disabled, error.

## Componentes

- Un solo componente de botón con variantes: primary, secondary, ghost, danger; tamaños: sm/md.
- Tablas: encabezado persistente, filas con hover, formato para números/fechas, acción por fila como icono o texto secundario.
- Formularios: validación inline en tiempo real + resumen de errores arriba; el error explica cómo corregirlo, no solo "inválido".
- Iconos: un solo set, tamaño 16/20, siempre con `aria-label` si son botón.

## Implementación (Blade + Tailwind 4)

- Estilos con clases utilitarias Tailwind 4 en vistas Blade; CSS personalizado solo en `resources/css/app.css` si es necesario (sin `@apply` masivo).
- Componentes Blade reutilizables en `resources/views/components/` para elementos repetidos (botones, tarjetas, tablas, badges de estado).
- Alpine.js para interactividad ligera (dropdowns, modales, tabs); nada de JavaScript imperative pesado.

## Estados de UI (obligatorios en toda pantalla)

- **Loading**: skeleton o spinner; nunca dejar la pantalla vacía mientras carga.
- **Empty**: mensaje + acción sugerida ("No hay equipos. + Registrar equipo").
- **Error**: mensaje humano + botón reintentar; nunca mostrar stack traces al usuario.
- **Success**: toast/confirmación no bloqueante.

## Accesibilidad

- HTML semántico (`<form>`, `<button>`, `<table>`, `<label for>`), no `div` con click.
- Navegación completa por teclado con focus visible; `aria-invalid`/`aria-describedby` en errores.
- Texto alternativo en imágenes; contenido legible al 200% zoom.

## Responsive

- Mobile-first; breakpoints: 640 / 768 / 1024 / 1280.
- En móvil: nada de hover-dependiente, tablas pueden scrollear horizontal o apilarse, menú colapsado.
- Probar siempre a 375px y 1440px antes de dar una pantalla por terminada.

## Verificación

- Renderizar la pantalla en el navegador y comparar contra estas reglas antes de declararla completa.
- Si el diseño es un mock/plan en un archivo `.pen`, usar el flujo de pencil (load-page → screenshot) para comparar contra la implementación.
