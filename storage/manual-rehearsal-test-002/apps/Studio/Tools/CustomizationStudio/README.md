# Customization Studio

Customization Studio is a Studio-owned governed tool area for style and experience customization workflows.

This Slice 1 structure is placeholder-only:

- It creates no runtime behavior.
- It enables no routes.
- It adds no CSS logic.
- It does not redesign Shell UI.
- It does not modify Core.

## Ownership Contract

- Customization Studio edits drafts and previews only.
- Platform/System owns approved active style registry truth.
- Shell owns style sockets and runtime consumption.
- Apps and modules own scoped CSS and consume sockets.
- Organization owns brand identity.
- `public/assets` is generated delivery output only.

Future implementation must preserve Analyze -> Changes -> Apply governance, keep runtime truth outside Studio drafts, and hand owner-scoped artifacts back to their owning app or module.

## Entry Planning

- Visual Customizer entry rules live in `Contracts/visual-customizer-entry-contract.md`.
- The Customization Studio landing route is enabled as a disabled/read-only preview skeleton.
- The Studio home card links to the Customization Studio landing page, not directly to Visual Customizer.
- The UI remains disconnected from Shell, runtime style truth, registry writes, draft saving, apply workflows, and `public/assets` generation.
