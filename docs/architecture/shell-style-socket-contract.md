# Shell Style Socket Contract

Status: Slice 2 documentation contract. No Shell UI redesign or runtime behavior change is authorized by this document.

Shell owns style sockets and runtime style consumption. Style sockets are approved runtime-facing hooks that allow Shell chrome, shared primitives, stateful surfaces, and owner-scoped app/module CSS to consume approved style contracts without reading Studio drafts.

## Shell Responsibilities

Shell may expose:

- CSS variables
- data attributes
- chrome hooks
- primitive hooks
- layout hooks
- state hooks
- rendering sockets

Shell must consume only resolved approved style contracts provided through Platform/System governance. Shell must not read Studio drafts, approve themes/styles, or own app/module scoped CSS.

## Socket Categories

Shell style socket scope includes:

- root/mode sockets
- core token sockets
- Shell chrome sockets
- layout sockets
- primitive component sockets
- navigation sockets
- data display sockets
- table/grid sockets
- form/editor sockets
- feedback sockets
- overlay sockets
- chart/visualization sockets
- diagram/graph sockets
- workflow/operation sockets
- state sockets
- motion/transform sockets
- media/asset sockets
- responsive sockets
- accessibility sockets
- print/export sockets

## Owner Boundaries

Platform/System owns approved active/default style registry truth and provides resolved approved style contracts to Shell via `ResolvedStyleConsumer` (see [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md)).

Customization Studio owns editing workflow only. It may preview or propose socket-related changes through drafts, previews, diffs, apply requests, snapshots, rollback metadata, provenance records, and handover records, but it does not own runtime socket truth.

Apps and modules own scoped CSS and consume sockets. They must not create private global design systems or bypass shared style sockets for global styling concerns.

Organization/Company Profile owns brand identity. `public/assets` owns nothing and remains generated delivery output only.

## Forbidden Behaviors

- No Shell reading Studio draft files.
- No Shell approval of active/default themes or styles.
- No Shell ownership of app/module scoped CSS.
- No Shell UI redesign in this slice.
- No uncontrolled global CSS.
- No `public/assets` authoring truth.
