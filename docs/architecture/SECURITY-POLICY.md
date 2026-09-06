# SECURITY POLICY

## Rules

- All routes require server-side authorization
- No UI-only protection
- No hidden endpoints
- No debug routes in production
- No bypass flags

---

## Data Safety

- validate inputs
- escape outputs
- no data leakage

---

## Lifecycle

Removing app must remove access surface