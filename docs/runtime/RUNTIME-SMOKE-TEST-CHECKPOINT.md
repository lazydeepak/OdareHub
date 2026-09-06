# Runtime Smoke Test Checkpoint

## Date

- 2026-05-23

## Environment

- Runtime: local PHP built-in server
- Command used: `/opt/homebrew/bin/php -S 127.0.0.1:8000 -t public public/index.php`
- Observation mode: mixed unauthenticated curl GET checks and authenticated browser checks

### Authentication Observations

- Unauthenticated requests to protected routes redirect to `/login` (expected front-controller auth guard behavior).
- Authenticated requests render protected route content where route handlers exist.

## Route Matrix

| Route | Observation | Classification |
|---|---|---|
| `/login` | GET reachable and renders login page. | pass |
| `/setup` | GET reachable in smoke checks. | pass |
| `/me` | GET reachable in smoke checks. | pass |
| `/u/lazydeepak` | Authenticated browser route renders operator surface. | pass |
| `/admin/lazydeepak` | Authenticated browser route renders admin surface. | pass |
| `/ops` | Bare `/ops` has no exact route registration; authenticated GET returns 404. | expected compatibility shape |
| `/ops/dashboard` | Route is registered and renders successfully when authenticated; unauthenticated requests redirect to `/login`. | pass |
| `/apps/studio` | GET reachable and renders in smoke checks. | pass |
| `/apps/manufacturing` | GET reachable and renders in smoke checks. | pass |
| `/u/kpi-feed` | Route exists and returns JSON when authenticated; unauthenticated request is redirected by auth guard before handler execution. | optional/non-fatal polling behavior |

## Classification Reference

- `pass`: route behavior matches expected runtime smoke outcome.
- `expected auth redirect`: unauthenticated request is redirected to `/login` by auth guard.
- `expected compatibility shape`: known compatibility namespace behavior and not a blocker for smoke acceptance.
- `optional/non-fatal polling behavior`: background polling failure or auth-sensitive poll endpoint behavior does not block page render.
- `investigate later`: non-blocking observation to track without immediate runtime changes.

## Known Non-Blocking Findings

- Bare `/ops` is unresolved (exact route not registered), while `/ops/dashboard` is registered and functional.
- `/u/kpi-feed` is auth/session sensitive by design for unauthenticated access checks.
- `HEAD /login` returns 404 because router method table handles GET/POST only, while `GET /login` works.

## Constraints Applied During This Checkpoint

- Documentation-only checkpoint.
- No runtime code edits.
- No fix proposals included in this report.

## Next Safe Work

- Functional module sanity checks.
- Login/session/manual UI walkthrough.
- Optional later `/ops` root redirect only if explicitly approved.
- Optional later HEAD handling only if explicitly approved.
