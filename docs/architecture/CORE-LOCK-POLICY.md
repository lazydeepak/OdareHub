# CORE LOCK POLICY

## Core Definition

Core includes:
- kernel/bootstrap
- routing
- DB base layer
- auth/session/ACL
- renderer/layout engine
- extension contracts

---

## Rule

Core is locked.

Do not modify core without explicit approval.

Core changes are exceptional and must be intentionally visible in architecture diagnostics.

Explicit diagnostic override is allowed only for approved Core work:

- `ARCHITECTURE_GATE_ALLOW_CORE=1`

Override does not waive ownership or governance laws.

---

## Forbidden

- adding app logic
- adding app routes/UI
- hardcoding app features
- shortcut patches
- absorbing Shell runtime chrome/composition ownership
- absorbing Platform governance ownership
- absorbing Studio authoring/workflow ownership
- absorbing app/module business routes/views/CSS/capability semantics

---

## Allowed (with approval)

- platform capabilities
- routing improvements
- security fixes
- extension contracts
- minimal primitive contract fixes required for platform law

---

## Escalation

If core change is needed:
- explain why app/plugin is insufficient
- propose minimal change
- request approval

Unauthorized core changes are incorrect.