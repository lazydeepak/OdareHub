# Style Rendering Contract

Status: Slice 2 documentation contract. No runtime renderer change, UI implementation, CSS rewrite, Shell redesign, or Core change is authorized by this document.

Runtime style rendering must consume approved contracts only. Customization Studio drafts and previews are not active runtime truth.

## Runtime Truth Boundary

Platform/System owns approved active/default style registry truth and provides resolved approved style contracts to Shell.

Shell owns style sockets and runtime style consumption. Shell consumes approved contracts through sockets, CSS variables, data attributes, chrome hooks, primitive hooks, layout hooks, state hooks, and rendering sockets.

Apps and modules own scoped view/component CSS and consume shared Shell sockets/tokens. Organization/Company Profile owns brand identity. `public/assets` is generated delivery output only.

## Preview And Rendering Methods

Future Customization Studio rendering may use these preview methods:

- Style Playground render
- real Shell preview
- app/module preview
- isolated component preview
- responsive preview
- state preview
- print/PDF preview
- dark/light preview
- accessibility preview
- runtime snapshot preview

These previews must remain preview-first and must not directly activate runtime style truth. Any activation must flow through governed Platform/System registry approval and resolved approved contracts (see [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md)).

## User Modes

Simple Mode is the default for non-technical users. Visual editing must be preview-first and inspector-driven.

Advanced Mode may expose tokens, sockets, and selectors in later slices. Code/CSS editing must not be the default.

## Forbidden Behaviors

- No Core edits.
- No direct runtime activation from Studio draft.
- No Shell reading Studio draft files.
- No hidden duplicate source of truth.
- No `public/assets` authoring truth.
- No inline style injection into random views.
- No uncontrolled global CSS.
- No app/module bypass of shared style sockets.

## Slice 2 Boundary

This document defines rendering rules only. It does not implement renderers, routes, previews, UI, CSS, or registry activation.
