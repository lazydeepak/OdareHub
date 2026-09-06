# Repository Scanner - Rules

## Working Rules

- Repository Scanner is a read-only engineering tool.
- Repository truth must always be derived from deterministic repository evidence.
- Repository Scanner discovers truth but never creates, modifies, or repairs repository contents.
- Every repository scope must be independently scannable.
- Scans must support progressive expansion from broad repository views to detailed investigation.
- Large repositories should be explored through focused scan scopes rather than producing one monolithic report.
- Scan results must remain reproducible for the same repository state.

## Safety Rules

- Every reported finding must be traceable to one or more repository artifacts.
- Heuristics may assist discovery but must never be presented as confirmed repository truth.
- Unknown or ambiguous findings must remain classified as unknown until sufficient evidence exists.
- File inspection must remain read-only.
- Repository Scanner must never modify repository contents, apply repairs, refactor source code, generate architectural changes, perform repository upgrades, or execute engineering actions on behalf of other tools.

## Validation Rules

- Every discoverable repository artifact should be inspectable.
- Entity inspection must reference its originating repository artifacts.
- Repository relationships should remain traceable to their source evidence.
- Repository Scanner should progressively discover repository structure, files and folders, file classifications, repository scopes, owners, registered surfaces, Engineering Workspaces, entities, relationships, and repository metadata.

## Change Rules

- Coverage should expand through new scan capabilities without changing the existing repository truth model.
- New scanner capabilities should extend the repository truth model rather than replacing it.
- All future scan modes, inspection views, entity types, and repository domains should integrate into a unified repository knowledge model.

## Escalation

- Repository Scanner is the canonical repository truth provider for downstream engineering tools.
- Repository Doctor, Refactor Planner, Owner Upgrade workflows, App Builder, Module Builder, and future engineering agents must consume repository truth rather than independently rediscovering repository state whenever practical.
- If a requested capability requires diagnosis, repair, refactoring, upgrade, or execution, route that responsibility to the appropriate downstream tool rather than adding it to Repository Scanner.
