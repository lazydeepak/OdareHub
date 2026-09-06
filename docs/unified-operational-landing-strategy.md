# Unified Operational Landing Strategy

## Current Status

This document is historical for the pre-operator-layer landing cleanup. Current landing and experience composition policy is superseded by:

- [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md)
- the Smart Landing Page Routing section in [../AGENTS.md](../AGENTS.md)

Current canonical landing behavior:

- `platform_admin` / `app_admin` -> `/admin/{username}`
- `app_user` / operator -> `/u/{username}/dashboard`
- TV/display user -> `/displays/user/{username}` or `/displays/device/{device_id}`
- `/me` is a legacy alias that redirects to `/admin/{current_user_handle}` for platform admins

Do not use this document to reintroduce `/me` as the primary operator workspace.

## Purpose

This document defines the primary landing and page intent model so users have one clear daily home and predictable drill-down paths.

Per [docs/access-control-view-architecture.md](docs/access-control-view-architecture.md), `/me` is a composed workspace Surface, not a duplicate implementation of module workflow logic.

## Primary Landing By Account Type

- `platform_admin` -> `/admin/{username}`
- `app_admin` -> `/admin/{username}`
- `app_user` -> `/u/{username}/dashboard`
- TV/display user -> `/displays/user/{username}` or `/displays/device/{device_id}`

Rules:
- App users land in the operator workspace.
- Platform and app admins land in the admin governance layer.
- Display users land in readonly display/kiosk surfaces.
- Specialized dashboards remain available as drill-down tools.

## Surfaces (per [docs/access-control-view-architecture.md])

Examples:
- `Home (/)`: account-aware redirect entry point only.
- `My Work (/me)`: composed personal workspace and primary execution inbox entry.
- `Leader Surfaces (/ops/*-dashboard)`: oversight and KPI review for supervisors/admins.
- `Approval Inbox (/ops/approval-inbox)`: decision surface for approvals/rejections.
- `Notifications (/ops/notifications)`: signal center and escalation follow-up.
- `Handoff Board (/ops/handoff-board)`: cross-role flow control and bottleneck tracing.
- `App Home (/apps/manufacturing)`: app portal/orientation and app-level entry.
- `Cockpit (/ops/cockpit)`: supervisor-only management view.
- `Coverage/Module index pages`: specialized drill-down analytics and execution tools.
- `Admin tools (/admin/*, dashboard assignments)`: administrative control surfaces.

Notes:
- `/me` is legacy/admin compatibility, not the canonical operator workspace.

## Navigation Priority Model

Top priority for operational users:
1. My Work
2. Approval Inbox (when applicable)
3. Notifications and Handoff Board

Secondary for operational users:
1. App Home
2. Role Leader dashboards
3. Module index pages

Top priority for admins:
1. Platform/App Admin dashboard
2. Approval Inbox and Notifications
3. Admin control tools

## Sidebar IA (Final Cleanup)

Top-level grouping now follows task intent rather than legacy page history.

ERP Core groups:
1. Work
2. Approve / Review
3. Investigate / Track
4. Manage Access

Manufacturing groups:
1. Workboards
2. Work Queues
3. Reference / Planning

Admin groups:
1. Platform / System

Notes:
- `/me` is the single primary operational workspace destination in navigation.
- Approval Inbox and Notifications are explicit review surfaces.
- Handoff Board, Audit Explorer, Cockpit, and Operations Overview are positioned as investigate/oversight surfaces.
- Work queues are grouped separately from planning/reference links.
- Admin/system links are isolated under Platform / System and renamed for clearer intent.

## Implementation Notes

Implemented in code via:
- centralized primary landing resolver in `UserDashboardAssignmentService`
- root (`/`) and `/ops/dashboard` redirects now use primary landing policy
- header Home button now points to primary landing policy
- sidebar updated to reduce competing "main" pages:
  - My Work emphasized
  - Role Dashboard no longer shown as universal primary item
  - App Home and leader dashboards marked as secondary

## Non-negotiables Enforced

- No additional overlapping main pages introduced.
- `/me` is primary for most operational users.
- Admin account types remain intervention-focused.
- Specialized dashboards preserved as drill-down surfaces.
- Existing backend engines are reused without redesign.
