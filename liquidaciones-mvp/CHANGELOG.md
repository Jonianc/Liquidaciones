# Changelog

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
