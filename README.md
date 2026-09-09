# OdareHub

**OdareHub** is a modular business operations platform designed as a core engine with installable business applications.

It follows a **Core -> Apps -> Modules architecture**, with plugins used only as extension/provider mechanisms.

---

## 🚀 Vision

To provide a **workflow-first, role-driven business platform** where:

- Operations are driven by **real-time demand and execution**
- Users interact through **role-based dashboards**
- Business logic is modular and **installable as apps**
- The system evolves without monolithic constraints

---

## 🧱 Architecture

### Core Platform

The core engine provides:

- Authentication & Identity
- Access Control & Assignment
- Audit Logging
- App/Plugin Manager
- Routing & Navigation System
- Dashboard & Widget Framework (in progress)

Terminology is frozen in [ARCHITECTURE.md](ARCHITECTURE.md).

---

### Business Applications

Installed on top of the core:

#### Manufacturing (IPM)

A demand-driven manufacturing system with:

- Pre Orders + Daily Orders → Demand Engine
- Production Planning & Execution
- QC & Assembly workflows
- Dispatch operations & tracking
- Coverage & shortage analytics
- SLA / Handoff / Escalation tracking

#### SBAIO (Small Business All-in-One)

A business operations suite including:

- Staff & Attendance
- Timecards & Payroll
- Scheduling & Leave
- Sales & Expenses
- Task management

---

## 🧩 Key Concepts

### 1. App-Based System

- Apps can be:
  - Installed
  - Enabled / Disabled
  - Upgraded
  - Exported as packages

Apps own modules. Modules are not top-level apps.

---

### 2. Workflow-First Design

Operations are modeled as:

- Demand → Plan → Execute → QC → Dispatch

Each stage:
- generates workload
- triggers handoffs
- tracks SLA and ownership

---

### 3. Role-Based Dashboards

Users do not navigate menus.

They operate through:

- **My Work (`/me`)**
- Assigned dashboards
- Context-aware widgets

---

### 4. Assignment-Driven Access

Instead of static roles:

- Users are assigned:
  - dashboard types
  - operational scope (machine, part, task, etc.)
  - app/module visibility

---

## 📊 Current Status

- Core platform: ✅ functional
- Manufacturing app: ✅ active (IPM system)
- SBAIO app: ✅ active
- Dashboard system: ⚠️ in transition (widget-based assignment)
- App packaging system: ⚠️ in progress

---

## 🛠️ Tech Stack

- PHP (custom framework)
- Modular plugin architecture
- MySQL / MariaDB
- Lightweight frontend (server-rendered views)

## 🚚 Deployment Notes

- Clone the repo into `public_html`.
- Point the web root to `public_html/public`.
- If the live server cannot run Composer, commit and sync `vendor/` with the repo.
- Configure the database by either:
  - creating `storage/db_config.php` from `storage/db_config.php.example`, or
  - setting `ERP_DB_HOST`, `ERP_DB_NAME`, `ERP_DB_USER`, `ERP_DB_PASS` and optional `ERP_DB_PORT`, `ERP_DB_CHARSET`, `ERP_DB_TIMEZONE`.
- Make sure the web server can write to `storage/` and `packages/`.
- Required PHP extensions: `mysqli`, `json`, `zip`, and `gd`.

---

## 📂 Project Structure

```
/app
/Core                # Core engine (Auth, ACL, routing, system services)

/apps
/Manufacturing       # Manufacturing business app
/SBAIO               # Small Business All-in-One

/plugins
/Base
/ACL
/...                 # Extension/provider plugins only

/public
/views
/assets

/tools

# scripts, utilities, migrations

```

Architecture rules and ownership model: [ARCHITECTURE.md](ARCHITECTURE.md)

---

## 🔑 Entry Points

- `/me` → Personal Work Dashboard
- `/apps/manufacturing` → Manufacturing Portal
- `/ops/platform-admin-dashboard` → Platform Admin Control

---

## ⚙️ Development Philosophy

- Modular over monolithic
- Workflow over static data entry
- Assignment over rigid roles
- Dashboards over navigation trees

---

## 📌 Roadmap

### Short Term

- Finalize widget-based dashboard system
- Remove cockpit-style pages
- Normalize routing (`/manufacturing` vs `/apps/manufacturing`)
- Clean `/me` into a true command center
- Next pending phase: worker-perspective UI creation
  - worker-focused `/me` composition with current work first and queue next
  - role-shaped execution surfaces (worker, leader, admin, read-only, TV/display)
  - computed-scope aware worker UI (machines, parts, work/process area)
  - display-safe read-only surfaces and dynamic worker navigation from effective access

### Mid Term

- App packaging (ZIP install/export)
- Migration runner & schema sync improvements
- Plugin widget registry
- API layer for integrations

### Long Term

- Marketplace for apps/plugins
- Multi-tenant deployment support
- Advanced analytics & forecasting
- Mobile-first dashboards

---

## 🤝 Contribution

Currently under active development.

Structure and architecture are evolving toward a stable v1 platform baseline.

---

## 📜 License

(To be defined)
