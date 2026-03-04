# Changelog

All notable changes to this plugin are documented in this file.

## [1.5.0] - 2026-03-04
### Added
- Operational audit module (`CL_LIQ_Audit`) with centralized event storage and retention (max. 200 entries).
- Audit logging for create/update actions on empleados, períodos and liquidaciones from both admin and frontend flows.
- Audit logging for updater execution and rollback events.
- Audit viewer section in **Liquidaciones CL > Parámetros** with user, context and changes detail.

## [1.4.0]
### Changed
- Added granular custom capabilities for CPTs (`cl_empleado`, `cl_periodo`, `cl_liquidacion`).
- Migrated admin access checks from `manage_options` to plugin capabilities.
- Auto-assigned new capabilities to administrator role on activation/upgrade.

## [1.3.1]
### Fixed
- Fixed admin-post handlers for "Actualizar ahora" and "Rollback".

## [1.3.0]
### Added
- Monthly auto-update via WP-Cron with automatic period window and UF fill.
- Automatic period selector window for liquidación form.
- Auto-update settings and actions (run now / rollback).

## [1.2.0]
### Added
- Frontend list/create/edit screens for empleados and períodos.
- Quick employee selector and search/filters in lists.

## [1.1.0]
### Added
- Frontend management routes and PDF endpoint.

## [1.0.0]
### Added
- Initial release (admin + calculations + PDF).
