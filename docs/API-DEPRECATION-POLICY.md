# API Deprecation & Redirect Policy

> Historical migration policy for transitional redirects.
> Current canonical route authority is `docs/architecture/ROUTING-STANDARD.md`.
> If this document conflicts with current routing policy, follow `docs/architecture/ROUTING-STANDARD.md`.

**Last Updated:** April 16, 2026

## Overview

This document outlines the deprecation policy for outdated routing paths and provides guidance for maintaining backward compatibility during architecture migrations.

---

## Legacy Redirect Strategy

### Why Legacy Redirects Exist

System transitioned from **plugin-based architecture** to **app-native architecture**:
- Old bookmarks reference `/ops/` paths (e.g., `/ops/production-dashboard`)
- New canonical URLs use `/manufacturing/` paths (e.g., `/manufacturing/production-dashboard`)
- **302 temporary redirects** provide graceful migration

### Current Legacy Redirects (Dashboard URLs)

| Legacy Path | Canonical Path | Type | Status |
|------------|----------------|------|--------|
| `/ops/production-dashboard` | `/manufacturing/production-dashboard` | 302 | Deprecated |
| `/ops/assembly-dashboard` | `/manufacturing/assembly-dashboard` | 302 | Deprecated |
| `/ops/qc-dashboard` | `/manufacturing/qc-dashboard` | 302 | Deprecated |
| `/ops/dispatch-dashboard` | `/manufacturing/dispatch-dashboard` | 302 | Deprecated |
| `/ops/production-leader-dashboard` | `/manufacturing/production-dashboard` | 302 | Deprecated |
| `/ops/assembly-leader-dashboard` | `/manufacturing/assembly-dashboard` | 302 | Deprecated |
| `/ops/qc-leader-dashboard` | `/manufacturing/qc-dashboard` | 302 | Deprecated |
| `/ops/dispatch-leader-dashboard` | `/manufacturing/dispatch-dashboard` | 302 | Deprecated |

### Additional Legacy Redirects (Pre-Routing Migration)

| Legacy Path | Canonical Path | Type | Status |
|------------|----------------|------|--------|
| `/dispatch` | `/dispatch-entries` | 301 | Deprecated |
| `/qc` | `/qc-entries` | 301 | Deprecated |
| `/production` | `/manufacturing/production-queue` | 301 | Deprecated |
| `/manufacturing` | `/apps/manufacturing` | 301 | Deprecated |
| `/mfg` | `/apps/manufacturing` | 301 | Deprecated |
| `/assembly-plans` | `/manufacturing/assembly-plans` | 301 | Deprecated |

---

## Deprecation Timeline

### Current Phase: Transition Period (2026)
- **Status:** Legacy redirects **ACTIVE** and fully supported
- **User Impact:** None - all old bookmarks continue to work
- **Admin Action:** None required
- **SEO:** 302 redirects preserve user history and auto-updates browser bookmarks

### Planned Phase: Deprecation Notice (TBD - Est. 2027)
- **Status:** Add deprecation header and admin notice
- **Action:** Warn admins to update bookmarks
- **Timeline:** 6-12 months notice before removal

### Final Phase: Removal (TBD - Est. 2028)
- **Status:** Legacy redirect routes removed entirely
- **Impact:** Old bookmarks will return 404
- **Requires:** Full communication plan + link migration support

---

## How Browser Auto-Updates Work (302 Redirects)

```
Browser requests:  /ops/production-dashboard
↓
Server responds:   302 Found, Location: /manufacturing/production-dashboard
↓
Browser auto-follows redirect (user sees new URL in address bar)
↓
Browser updates history:  /manufacturing/production-dashboard (NEW)
↓
Bookmark auto-updates on next save
```

**Result:** Users' bookmarks automatically upgrade to canonical URLs with no action required.

---

## Canonical URLs (Preferred)

### Production Leadership Dashboards
- `/manufacturing/production-dashboard` — Production leader summary
- `/manufacturing/production-workboard` — Production execution leader board

### QC Leadership Dashboards
- `/manufacturing/qc-dashboard` — QC leader summary
- `/manufacturing/qc-workboard` — QC execution leader board

### Dispatch Leadership Dashboards
- `/manufacturing/dispatch-dashboard` — Dispatch leader summary
- `/manufacturing/dispatch-workboard` — Dispatch execution leader board

### Assembly Leadership Dashboards
- `/manufacturing/assembly-dashboard` — Assembly leader summary
- `/manufacturing/assembly-workboard` — Assembly execution leader board (redirects to queue)
- `/manufacturing/assembly-queue` — Assembly execution queue

### Operations & Workflows
- `/manufacturing/dispatch-ops` — Dispatch operations center
- `/manufacturing/qc-queue` — QC work queue
- `/manufacturing/assembly-queue` — Assembly work queue
- `/apps/manufacturing` — Manufacturing main portal

---

## Guidelines for New Development

### DO:
✅ Use canonical `/manufacturing/*` URLs in new code  
✅ Use `/apps/manufacturing` for main portal links  
✅ Update internal documentation to use canonical URLs  
✅ Create new features directly on canonical URLs  

### DON'T:
❌ Create new routes on `/ops/*` paths  
❌ Reference legacy `/ops/` URLs in new features  
❌ Rely on legacy redirects for production integrations  

---

## For API Consumers & Integrations

### If You're Integrating with Our APIs:
1. **Use canonical URLs** - `/manufacturing/*` paths are stable
2. **Expect 302 redirects** - Older paths may redirect; follow them
3. **Plan for URL changes** - Legacy paths may be removed in future versions
4. **Update your bookmarks** - Let 302 redirects auto-update your browser history

### If You're Embedding Our Links:
1. **Use canonical URLs** - Link to `/manufacturing/*` not `/ops/*`
2. **Test redirects** - Verify your integration follows 302 redirects
3. **Prepare migration plan** - Plan to update links before removal phase

---

## Request Tracking

### To Monitor Usage of Legacy Paths:
Admins can view logs to see which users/integrations still use legacy URLs:
```bash
# Example: Find all requests to legacy /ops/production-dashboard
grep "/ops/production-dashboard" app/logs/requests.log
```

**Action:** When redirect usage drops <5%, schedule removal in next major version.

---

## Questions?

- **For Admins:** See CLEANUP-REPORT.md for full consolidation details
- **For Developers:** See Architecture Documentation (TBD)
- **For Users:** Bookmarks are automatically updated; no action needed

