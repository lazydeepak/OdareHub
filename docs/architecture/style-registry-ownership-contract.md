# Style Registry Ownership Contract

Status: Slice 2 documentation contract. No registry implementation, runtime behavior, route, UI, CSS rewrite, Shell redesign, or Core change is authorized by this document.

Platform/System owns the approved active/default style registry truth. Customization Studio may propose and preview changes, but Studio drafts are not active runtime truth.

## Platform/System Registry Ownership

Platform/System owns:

- approved active/default style registry truth
- approved style version metadata
- active/default style resolution
- deletion/default safety
- registry governance
- resolved approved style contracts provided to Shell

Future owner placeholder home (no runtime behavior in this slice):

- apps/Platform/StyleRegistry/README.md
- apps/Platform/StyleRegistry/Contracts/
- apps/Platform/StyleRegistry/Services/
- apps/Platform/StyleRegistry/Resources/
- apps/Platform/StyleRegistry/Diagnostics/

Platform/System must not treat Studio drafts or `public/assets` as runtime truth.

## Studio Workflow Boundary

Customization Studio owns editing workflow only.

It may create drafts, previews, diffs, apply requests, snapshots, rollback metadata, provenance records, and handover records. It must not own runtime style truth and must not directly become the active runtime styling source.

## Consumer Boundaries

Shell owns style sockets and runtime style consumption. Shell must consume resolved approved style contracts from Platform/System via `ResolvedStyleConsumer` (see [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md)) and must not read Studio drafts or approve themes/styles.

Apps and modules own their own scoped view/component CSS. They must consume shared Shell style sockets/tokens, avoid private global design systems, and define local style extensions only under owner-scoped rules.

Organization/Company Profile owns brand identity such as company name, logo, favicon, address, and tax/company identity. Customization Studio may preview branding, but it must not own brand truth.

`public/assets` owns nothing. It is generated delivery output only and must not be treated as authoring source, registry source, or governance source.

## Forbidden Behaviors

- No Core edits.
- No direct runtime activation from Studio draft.
- No Shell reading Studio draft files.
- No hidden duplicate source of truth.
- No `public/assets` authoring truth.
- No inline style injection into random views.
- No uncontrolled global CSS.
- No app/module bypass of shared style sockets.

## Future Enforcement

Slice 3 should introduce read-only diagnostic enforcement. It should report contract locations and boundary health before any mutating or approval workflow exists.
