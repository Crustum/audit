# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-15

Initial release of `crustum/audit` (`Crustum\Audit`).

### Added

- `audit process` command with `--only`, `--except`, `--bail`, `--fix`, `--format`, and `--fail-on` options.
- Diagnostic runner with statuses (`pass`, `notice`, `warn`, `fail`, `skip`, `error`), safe fixes, fix options, reruns, and bail-on-failure.
- 23 default diagnostics across environment, composer, configuration, database, cache, queue, scheduler, session, storage, and security groups.
- Local/production environment modes that change diagnostic verdicts (`EnvironmentMode`).
- CLI, JSON, GitHub Actions, and agent-optimized output formats, with automatic agent detection.
- Programmatic API: `AuditManager` (`run`, `only`, `except`, `bail`, `fixUsing`, `observeUsing`).
- `bake diagnostic` generator with optional `--fixable` scaffolding.
- Support helpers: `Configured`, `ActiveDrivers`, and `Details`.
- Optional integrations for `cakephp/migrations`, `cakephp/queue`, and `crustum/cakephp-scheduling`.
- Manifest-based `config/audit.php` installation and bootstrap loading.
