# ERP App Studio Project Contract

Schema: `studio.project.v1`

Status: draft contract only

## Purpose

A Studio Project groups app, module, view, navigation, template, and planned artifact drafts before publish governance begins.

## Required Fields

| Field | Type | Notes |
|---|---|---|
| `schema_version` | string | Must be `studio.project.v1`. |
| `project.project_key` | string | Stable draft identifier. |
| `project.display_name` | string | User-facing project name. |
| `project.owner_scope` | string | `platform`, `system_app`, or `business_app`. |
| `project.status` | string | Starts as `draft`. |
| `drafts` | object | References app/module/view/navigation/template/artifact manifests. |
| `governance.lifecycle` | array | Draft lifecycle steps. |
| `governance.publish_locked` | boolean | Must be true during foundation phase. |

## Lifecycle Direction

Draft -> Validate -> Compile Plan -> Diff Preview -> Approval -> Snapshot -> Publish -> Verify

Only Draft, Validate placeholder, and Compile Plan placeholder are active in this phase.
