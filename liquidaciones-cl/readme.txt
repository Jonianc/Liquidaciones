=== Liquidaciones CL ===
Contributors: rocketsolutions
Tags: liquidaciones, sueldo, chile, afp, fonasa, isapre, impuesto unico
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Genera liquidaciones de sueldo (Chile) en WordPress: empleados, períodos y liquidaciones con cálculos automáticos y PDF. Incluye una pantalla de gestión en frontend por URL (sin depender del theme).

== Flujo ==
1) Liquidaciones CL > Empleados > Agregar empleado
2) Liquidaciones CL > Períodos > Agregar período (YYYY-MM) e ingresar UF del mes
3) Liquidaciones CL > Liquidaciones > Agregar liquidación
4) Guardar y usar el botón "Ver PDF"

== Frontend (sin theme) ==
Rutas (requiere login + capacidad manage_cl_liquidaciones):
- /liquidaciones-cl/ (listado)
- /liquidaciones-cl/nueva/ (crear)
- /liquidaciones-cl/editar/<id>/ (editar)
- /liquidaciones-cl/pdf/<id>/?_wpnonce=... (PDF)
- /liquidaciones-cl/empleados/ (empleados)
- /liquidaciones-cl/empleados/nuevo/ (crear empleado)
- /liquidaciones-cl/empleados/editar/<id>/ (editar empleado)
- /liquidaciones-cl/periodos/ (períodos)
- /liquidaciones-cl/periodos/nuevo/ (crear período)
- /liquidaciones-cl/periodos/editar/<id>/ (editar período)

Nota: si instalaste/actualizaste y devuelve 404, entra a Ajustes > Enlaces permanentes y guarda (o reactiva el plugin) para refrescar rewrite rules.

== Changelog ==
= 1.7.0 =
* i18n: carga de text domain del plugin en bootstrap.
* i18n/DX: centralización de enlaces rápidos en Ajustes mediante helper y labels traducibles.
* i18n: internacionalización de mensajes de validación en frontend (RUT, período y negativos).

= 1.6.2 =
* Ajustes: nueva sección "Rutas de acceso rápido" en Parámetros con accesos directos a frontend y pantallas admin.
* UX: enlaces rápidos para listado/nueva liquidación, empleados/períodos (listado y nuevo).

= 1.6.1 =
* Fix RUT: validación consistente de RUT en admin/frontend usando normalización previa.
* Fix RUT: formateo automático al guardar en formato 12.345.678-K.
* UX RUT: autoformateo del campo RUT al perder foco (admin y frontend empleados).
* Fix frontend empleados: si el RUT es inválido, se evita guardar y se muestra error sin redirección.

= 1.6.0 =
* Validación: RUT chileno (con dígito verificador) en guardado de empleados (frontend y admin).
* Validación: bloqueo de períodos duplicados (YYYY-MM) en creación/edición de períodos (frontend y admin).
* Validación: bloqueo de valores negativos en campos numéricos críticos de liquidación y UF.
* Interno: nuevas utilidades en helpers para reglas de negocio (RUT, período duplicado y negativos).

= 1.5.0 =
* Auditoría operativa: registro de creación/actualización de empleados, períodos y liquidaciones (admin y frontend), incluyendo usuario, contexto y cambios.
* Auditoría operativa: registro de ejecución y rollback del updater en el historial de auditoría.
* Parámetros: nueva sección "Auditoría operativa" con últimos eventos y detalle de cambios.
* Interno: nuevo módulo `CL_LIQ_Audit` para centralizar snapshots, diffs y retención del log (máx. 200 eventos).

= 1.4.0 =
* Seguridad/roles: capabilities personalizadas por CPT (empleados, períodos y liquidaciones).
* Seguridad: menúes y handlers principales migrados a capacidades del plugin (manage_cl_liquidaciones + caps por entidad), evitando dependencia directa de manage_options.
* Migración: en activación/upgrade se asignan automáticamente las nuevas capabilities al rol administrador.

= 1.3.1 =
* Fix: handlers admin-post para "Actualizar ahora" y "Rollback" (evita fatal error).

= 1.3.0 =
* Auto-update: WP-Cron mensual (gate por mes), creación de períodos en ventana automática, relleno de UF (copiar último o HTTP JSON), copia de tabla impuesto del último mes si falta.
* Selector de períodos automático en formulario de liquidación (ventana configurable).
* Parámetros: sección Auto-update + botones Actualizar ahora / Rollback.


= 1.2.0 =
* Frontend: listado + crear/editar empleados y períodos.
* Frontend: selector rápido de empleado y búsqueda/filtros en listados.

= 1.1.0 =
* Gestión de liquidaciones en frontend por URL (sin theme) + endpoint PDF.

= 1.0.0 =
* Versión inicial (admin + cálculos + PDF).

== Cálculos incluidos (v1) ==
- Imponibles: sueldo base, gratificación (legal o manual), horas extra, bonos, comisiones, otros.
- No imponibles: colación, movilización, viáticos, otros, asignación familiar.
- Descuentos: AFP (10% + comisión), Salud (7% Fonasa o plan Isapre), AFC trabajador (según tipo contrato), Impuesto Único (tabla SII mensual configurable), y otros descuentos.

== Parámetros ==
En Liquidaciones CL > Parámetros puedes ajustar:
- Topes imponibles UF (AFP/Salud y AFC)
- Comisiones AFP
- Tasas AFC trabajador por tipo de contrato
- Asignación familiar (JSON)
- Tabla Impuesto Único (JSON por período YYYY-MM)

== Importante ==
Revisa siempre el resultado antes de emitir una liquidación a un trabajador. La normativa y valores cambian con el tiempo; por eso el plugin incluye parámetros editables.
