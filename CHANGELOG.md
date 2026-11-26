# Changelog

## [Unreleased](https://github.com/data-health-php/data-health-mcp/compare/v0.1.0...HEAD)

## v0.1.0 - 2026-11-26

Initial public release for PHP 8.3+, Laravel 12.41.1+/13, Laravel MCP ^1.3, and Data Health ^0.1.1.

### Added

- List finding types and filter and paginate finding records through MCP.
- Read current explanations, descriptions, and authorized actions with `get_finding`.
- Verify or resolve individual active occurrences through the application's existing logic, with distinct outcomes for persistent problems and unsuccessful corrections.
- Enforce Laravel policies and support shared tenant scopes across lists, totals, detail lookups, and mutations.
- Register a local stdio server and support explicitly registered authenticated HTTP endpoints.
- Omit raw context by default, log operational errors without exposing internals, and accurately advertise tool side effects.
- Test the PHP/Laravel/dependency support matrix with GitHub Actions.
