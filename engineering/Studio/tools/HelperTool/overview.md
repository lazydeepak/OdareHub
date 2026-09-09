# Repository Scanner

## Purpose

Repository Scanner is the authoritative read-only repository discovery and inspection tool for OdareHub.

Its responsibility is to discover, inventory, classify, and expose repository truth across the entire platform. It provides a structured understanding of the repository without modifying source code, configuration, or runtime state.

This workspace establishes the canonical foundation upon which higher-level engineering capabilities such as Repository Doctor, Refactor Planning, Engineering Agents, and App/Module Upgrade workflows operate.

**Workspace key:** `Studio/tools/HelperTool`

## Target State

Repository Scanner provides a complete, trustworthy, and navigable representation of the repository.

Developers should be able to inspect any repository scope, file, entity, relationship, or engineering artifact from a browser without requiring direct repository access or making runtime changes.

All repository truth should originate from deterministic scanning rather than assumptions.

## Responsibilities

* Discover repository structure and inventory.
* Scan repository scopes without modifying content.
* Classify files, folders, and engineering artifacts.
* Extract repository entities (controllers, services, views, routes) and their relationships.
* Discover repository owners (apps, modules, plugins, platform, engineering workspaces) with per-owner metrics.
* Provide browser-based inspection, search, and filtering of repository contents.
* Expose repository truth for downstream engineering tools.
* Supply authoritative read-only data for Repository Doctor and future engineering agents.

## Boundaries

Repository Scanner does not:

* Modify repository contents.
* Repair, refactor, or rewrite code.
* Apply architectural recommendations.
* Perform automated upgrades.
* Generate engineering decisions.

Those responsibilities belong to downstream tools built on repository truth.

## Canonical Source Areas

* Repository root
* Applications
* Modules
* Plugins
* Platform
* Shared libraries
* Engineering workspaces
* Configuration
* Assets
* Documentation
* Tests and probes

The scanner may expand to additional repository domains as the platform evolves.

## Dependencies

* Repository filesystem
* Route and navigation registries
* Owner catalogue
* Engineering Workspace registry
* Repository classification services
* Shared repository inspection utilities

## Related Workspaces

* Repository Doctor
* App Builder
* Module Builder
* Plugin Builder
* Engineering Workspaces

## Non-goals

Repository Scanner is not intended to become:

* A source code editor
* A refactoring engine
* A repair tool
* A deployment system
* A runtime debugger

Its responsibility is to establish accurate repository truth that other engineering workflows can safely consume.
