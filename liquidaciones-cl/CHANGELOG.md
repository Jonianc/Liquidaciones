# Changelog

Todos los cambios importantes de este plugin se documentan en este archivo.

## [1.7.0] - 2026-03-04
### Agregado
- Carga de text domain (`liquidaciones-cl`) para habilitar traducciones del plugin.
- Centralización de enlaces rápidos en Ajustes con helper reutilizable y labels traducibles.

### Cambiado
- Mensajes de validación en frontend migrados a funciones i18n (`__`).

## [1.6.2] - 2026-03-04
### Agregado
- Sección **Rutas de acceso rápido** en Ajustes (Parámetros) con enlaces directos al frontend y admin del plugin.
- Accesos directos a: listado/nueva liquidación, empleados/listado+nuevo y períodos/listado+nuevo.

## [1.6.1] - 2026-03-04
### Corregido
- RUT: validación robusta con normalización previa (admite entrada con/sin puntos y guión) en admin y frontend.
- RUT: formateo automático persistido al guardar en formato `12.345.678-K`.
- RUT frontend/admin: autoformateo al salir del campo para mejorar UX.
- Empleados frontend: se corrige flujo para no guardar datos si el RUT es inválido.

## [1.6.0] - 2026-03-04
### Agregado
- Validación de RUT chileno (incluye dígito verificador) en formularios de empleados de frontend y guardado en admin.
- Validación de período duplicado (`YYYY-MM`) para evitar crear/guardar más de un período con el mismo mes.
- Validación de no-negativos en campos numéricos críticos de liquidaciones y UF.
- Nuevas utilidades en `CL_LIQ_Helpers` para reglas de negocio (`validate_rut`, `period_exists`, `is_negative_number_input`).

### Cambiado
- Mensajes de error en frontend para entradas inválidas (RUT, UF negativa y período duplicado).

## [1.5.0]
### Agregado
- Módulo de auditoría operativa (`CL_LIQ_Audit`) con almacenamiento centralizado de eventos y retención (máx. 200 entradas).
- Registro de auditoría para creación/actualización de empleados, períodos y liquidaciones desde admin y frontend.
- Registro de auditoría para ejecución y rollback del updater.
- Sección de visualización de auditoría en **Liquidaciones CL > Parámetros** con usuario, contexto y detalle de cambios.

## [1.4.0]
### Cambiado
- Capacidades personalizadas granulares para CPTs (`cl_empleado`, `cl_periodo`, `cl_liquidacion`).
- Migración de controles de acceso admin desde `manage_options` a capacidades propias del plugin.
- Asignación automática de nuevas capacidades al rol administrador en activación/upgrade.

## [1.3.1]
### Corregido
- Corrección de handlers admin-post para "Actualizar ahora" y "Rollback".

## [1.3.0]
### Agregado
- Auto-update mensual por WP-Cron con ventana automática de períodos y relleno de UF.
- Selector automático de períodos para formulario de liquidación.
- Parámetros y acciones de auto-update (ejecutar ahora / rollback).

## [1.2.0]
### Agregado
- Pantallas frontend de listado/creación/edición para empleados y períodos.
- Selector rápido de empleado y búsqueda/filtros en listados.

## [1.1.0]
### Agregado
- Gestión frontend por rutas y endpoint PDF.

## [1.0.0]
### Agregado
- Versión inicial (admin + cálculos + PDF).
