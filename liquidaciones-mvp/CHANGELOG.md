# Changelog

## [0.1.6] - 2026-02-26
### Changed
- Se endureció el flujo de generación de PDF usando `admin-post.php` con acción dedicada (`action=lqm_pdf`) para evitar depender del front (`home_url`) y mejorar el contexto de permisos en admin.
- Se mejoró el manejo de errores del endpoint PDF con mensajes guiados para nonce expirado/inválido, accesos sin permisos y documentos inexistentes, incluyendo enlaces de retorno al listado/edición.
- Se agregó logging técnico en modo `WP_DEBUG` para eventos de error del flujo PDF (sin exponer detalles al usuario final).
- Se mantuvo compatibilidad retroactiva con enlaces legacy `?lqm_pdf=...` existentes.

## [0.1.5] - 2026-02-26
### Changed
- Se implementó hardening de permisos del CPT `lqm_liquidacion` con capabilities propias + `map_meta_cap`, evitando depender de las capacidades genéricas de `post`.
- Se agregaron hooks de activación/desactivación para registrar CPT, asignar capabilities a `administrator` y refrescar reglas de reescritura.
- Se corrigió regresión de compatibilidad en `Fecha Inicio`: se revierte `lqm_inicio` a `type="text"` para preservar valores legacy no-ISO al abrir y re-guardar registros existentes.

## [0.1.4] - 2026-02-26
### Changed
- Se mejoró la UX del formulario de liquidación con validaciones en vivo en admin (obligatorios, RUT con dígito verificador, rangos de días y suma máxima de 31 días).
- Se añadieron mensajes de error accesibles por campo (`aria-live`) y enfoque automático al primer campo inválido al guardar.
- Se reforzaron atributos HTML de inputs (`required`, `min`, `max`, `step`, `id/for`) para reducir errores de captura.
- Se actualizó el estilo del metabox con estados visuales de error y comportamiento responsivo básico.

## [0.1.3] - 2026-02-26
### Changed
- Se extrajeron los estilos y scripts inline del metabox de liquidaciones a archivos estáticos en `assets/`.
- Se agregó carga condicional de assets en admin (`admin_enqueue_scripts`) solo para pantallas del CPT `lqm_liquidacion`.
- Se mantuvo el comportamiento de la tabla de no imponibles (agregar/quitar filas) desde JavaScript externo.

## [0.1.2] - 2026-02-26
### Changed
- Se robusteció el guardado de metadatos numéricos en la liquidación:
  - sanitización y normalización estricta de numéricos,
  - rangos para días (`0..31`) en trabajados/licencia/inasistencias,
  - prevención de valores negativos en montos,
  - filtrado de ítems no imponibles inválidos (sin nombre o monto no positivo).

## [0.1.1] - 2026-02-26
### Changed
- Se fortaleció la seguridad del endpoint de PDF:
  - validación de existencia del post solicitado,
  - validación estricta del `post_type` (`lqm_liquidacion`),
  - control de permisos por registro con `current_user_can('edit_post', $id)` en lugar de permiso global `edit_posts`.

## [0.1.0] - 2026-02-26
### Added
- Versión inicial MVP con CPT de liquidaciones, formulario admin y generación de PDF con FPDF.
