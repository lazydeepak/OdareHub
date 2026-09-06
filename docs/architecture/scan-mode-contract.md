# Scan Mode Contract

## 1. Surface Taxonomy

The Shell recognizes five distinct surface classifications:

| Surface | Owner | Behavior |
|---|---|---|
| **Topbar Surface** | Shell | Persistent app-level chrome (branding, search, notifications, avatar, scan trigger) |
| **Navigation Surface** | Shell | Wayfinding surfaces (sidebar, hamburger drawer, bottom nav, action panels) |
| **Workspace Surface** | Shell | Primary content area (dashboard, views, forms, tables) |
| **Overlay Surface** | Shell | Temporary floating popups (avatar panel, action tray, dialogs) |
| **Scan Mode** | Shell | Temporary full-screen task mode (camera capture, barcode/QR/document scanning) |

## 2. Overlay Surface vs Scan Mode

| Dimension | Overlay Surface | Scan Mode |
|---|---|---|
| Viewport coverage | Partial (floating panel, drawer, or menu) | **Full** (inset: 0) |
| Workspace visibility | Remains visible behind scrim | **Fully hidden** behind scan overlay |
| Workspace blur | Applied via shared activation contract | **Not needed** — workspace is hidden |
| Shared overlay state (`__overlayCount`) | Mandatory | **Not applicable** |
| User interaction with workspace | Possible (click-through backdrops) | **Impossible** — full viewport intercept |
| DOM lifecycle | Static (pre-rendered, class toggle) | **Dynamic** (created on open, removed on close) |
| Task model | Navigational (menu selection, preference change) | **Transactional** (scan → result → return) |
| Result delivery | None (immediate action) | **Promise-based** (caller awaits scan value) |
| Security integration | None | **Required** — camera permission, media stream |
| Resource acquisition | None | **Required** — `getUserMedia`, `BarcodeDetector` |
| Teardown | Class removal | **Required** — stop media tracks, remove DOM nodes |
| z-index | 129–500 (within surface stack) | **10,000+** (isolated above surface stack) |

## 3. Scan Mode Contract

### 3.1 Contract rules

A component classified as **Scan Mode** must satisfy these invariants:

| # | Rule | Rationale |
|---|---|---|
| 1 | Full viewport takeover (`position: fixed; inset: 0`) | User focus must be on the scan task |
| 2 | Independent z-index (10,000+) | Must not interfere with normal surface stacking; must render reliably above browser permission prompts |
| 3 | No workspace blur required | Workspace is fully hidden — blur has no user-facing effect |
| 4 | No shared overlay state (`__overlayCount`) | Scan Mode operates outside the Overlay Surface activation contract |
| 5 | Own DOM lifecycle: create on open, teardown and remove on close | Static pre-rendered elements waste resources; camera state must be reset per session |
| 6 | Promise-based result delivery: return `{ ok, value, reason }` | Caller (search, navigation, auto-fill) awaits scan completion without callback nesting |
| 7 | Media permission flow: call `getUserMedia` only after user gesture | Respects autoplay policy and user privacy |
| 8 | Resource cleanup: stop all `MediaStreamTrack` instances on close (any exit path) | Prevents camera LED remaining active, microphone leakage on mobile |
| 9 | DOM teardown: remove overlay element from `document.body` on all exit paths (cancel, success, error, timeout, manual) | Prevents orphan overlay nodes that block interaction |
| 10 | Cancel button available at all times | User must always be able to dismiss and return to workspace |
| 11 | Overlay click-outside closes (click on backdrop, not panel) | Follows platform modal-dismiss convention |
| 12 | No reliance on pre-existing DOM elements | Scan Mode must work on any surface (operator, admin, future) without HTML template dependencies |

### 3.2 Open

Triggered by user gesture (scan button in topbar, bottom nav, or other surface). The component:

1. Creates overlay and panel DOM elements
2. Appends overlay to `document.body`
3. Requests camera permission via `navigator.mediaDevices.getUserMedia`
4. Starts barcode/frame detection loop
5. Returns a Promise to the caller

### 3.3 Active

The camera feed renders in a centered panel with:

- Status text (starting → scanning → success/error)
- Cancel button (always visible)
- "Enter manually" button (alternative to scan)
- Click-outside-to-dismiss on backdrop
- Timeout protection (default 30s detection loop)

### 3.4 Close (any exit path)

On any close trigger (success, cancel, manual, timeout, error):

1. Cancel any pending animation frame
2. Stop all `MediaStreamTrack` instances
3. Remove overlay from `document.body`
4. Resolve the Promise with `{ ok, value?, reason? }`

### 3.5 API shape

```javascript
const result = await launchCameraScan();
// result.ok      → boolean
// result.value   → string (scanned value, if ok)
// result.reason  → string (cause if !ok: 'cancelled', 'manual', 'timeout', 'camera_unavailable')
```

## 4. Current Consumers

| Consumer | File | Implementation |
|---|---|---|
| **Operator Camera Scan** | `apps/Shell/Composers/OperatorSurfaceComposer.php:4342-4508` | `createOperatorLaunchCameraScan()` — triggers from topbar scan button and bottom nav scan button |
| **Admin QR Scan** | `public/views/layouts/header.php:1595-1694` | `launchCameraScan()` — triggers from admin topbar scan button |

Both implementations follow the same architectural pattern (dynamic DOM, Promise, media lifecycle) and satisfy the Scan Mode Contract with minor cosmetic differences (operator version has `backdrop-filter: blur(2px)` on overlay which is vestigial — workspace is hidden behind overlay, so the blur has no user-facing effect).

## 5. Non-Goals

Scan Mode is not:

| Not this | Because |
|---|---|
| Overlay Surface | Full viewport takeover, no workspace blur, independent lifecycle |
| Dialog | Not a blocking modal — camera feed is the primary interface |
| Drawer | Full viewport, not partial slide-out |
| Menu | Transactional scan task, not navigational selection |
| Notification panel | Scans produce a result, not passive information display |
| Workspace Surface takeover | Scan Mode is transient — workspace is restored on close, not replaced |

## 6. Future Guidance

When evaluating new camera-driven features (barcode, QR, OCR, document capture, image capture, face recognition, object detection), classify them as **Scan Mode** if:

- Full viewport takeover is required
- Camera/media stream is the primary interaction
- The workspace should be hidden during the task
- A promise-based result is delivered to the caller
- Resource cleanup (stream, tracks, DOM) is essential on all exit paths

Do NOT classify camera-driven features as Overlay Surface components. The Overlay Activation Contract (workspace blur, shared counter, contextual visibility) is designed for navigational popups, not transactional scan tasks.

## 7. References

- `apps/Shell/Composers/OperatorSurfaceComposer.php:4342-4508` — operator Camera Scan implementation
- `public/views/layouts/header.php:1595-1694` — admin QR Scan implementation
- `apps/Shell/styles/operator.css:69-80` — operator Camera Scan CSS (vestigial `backdrop-filter`)
- `docs/architecture/workspace-surface-alignment-checkpoint.md` — Overlay Surface Activation Contract
- `AGENTS.md:485-489` — Overlay Surface session summary
