# Phase 8: Operator Workspace Real-Time Updates

**Status**: In Planning  
**Target**: Production-ready WebSocket push + live caching for operator KPIs  
**Phase Priority**: Enhancement layer (non-blocking; graceful fallback to auto-refresh)

---

## Overview

Phase 8 adds **optional real-time KPI push** to operator workspace using WebSocket, with graceful fallback to Phase 7's `<meta http-equiv="refresh">` auto-refresh when WebSocket unavailable.

- **WebSocket Server**: Lightweight PHP daemon for KPI subscriptions
- **Live Cache**: Redis-backed stale-while-revalidate for KPI data
- **Client Library**: Browser-side connection + update handler
- **Backward Compatibility**: Full operator functionality without WebSocket

---

## Architecture

### High-Level Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Operator Browser (operator.php view)                             │
│  - Page loads with Phase 7 auto-refresh as baseline              │
│  - JS attempts WebSocket connection to realtime server           │
│  - On success: receives live KPI updates + disables refresh tag  │
│  - On failure: falls back to auto-refresh (silent)               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ WebSocket Server (bin/operator-realtime-server)                 │
│  - PHP CLI daemon using Ratchet (WebSocket library)              │
│  - Listens on localhost:8001 (configurable)                      │
│  - Handles operator subscriptions by view + user                 │
│  - Queries adapters on timer or on-demand                        │
│  - Pushes updates to subscribed clients                          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ Live Cache Layer (Redis)                                        │
│  - Key: `operator:kpi:{view}:{username}:{timestamp}`             │
│  - TTL: 30s (stale-while-revalidate)                            │
│  - Prevents thundering herd on adapter queries                   │
│  - Immediate return for cached data                              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ Operator Adapters (existing)                                    │
│  - CoverageAdapter, QcAdapter, DispatchAdapter, etc.             │
│  - Called via OperatorLayerWidgetService                         │
│  - Results: KPI summary + detail rows                            │
└─────────────────────────────────────────────────────────────────┘
```

### Graceful Fallback

If WebSocket connection fails or server is unavailable:
- Browser detects connection failure
- Silently reverts to auto-refresh tag behavior
- User sees periodic updates (no visual change from Phase 7)
- No console errors or broken functionality

---

## Detailed Design

### 1. WebSocket Protocol

**Connection URL** (from operator view):
```
ws://localhost:8001/operator/{username}/{view}?auth={session_token}
```

**Session Token**: Short-lived CSRF token regenerated on each operator page load

**Client → Server Messages**:
```json
{
  "type": "subscribe",
  "username": "lazy",
  "view": "dashboard",
  "preferences": {
    "refresh_interval_ms": 5000,
    "kpi_keys": ["summary", "critical_orders"]
  }
}
```

**Server → Client Messages**:
```json
{
  "type": "kpi_update",
  "timestamp": 1274372036,
  "summary": {
    "open_orders": 45,
    "critical": 3,
    "low_coverage": 8,
    "coverage_pct": 78.2
  },
  "critical_orders": [
    { "id": 1001, "part": "X-001", "due": "2026-05-21T14:00:00Z", "status": "critical" },
    ...
  ]
}
```

**Heartbeat** (server every 30s):
```json
{
  "type": "heartbeat",
  "timestamp": 1274372036
}
```

---

### 2. WebSocket Server (bin/operator-realtime-server)

**Framework**: Ratchet (PHP WebSocket library)  
**Process Manager**: Supervisor or systemd  
**Port**: 8001 (configurable via ENV)  
**Max Connections**: 500 concurrent operators

**Key Features**:
- Session validation on connect (checks CSRF token + Auth)
- Per-operator-per-view subscription tracking
- Shared cache layer (Redis) to prevent duplicate adapter calls
- Graceful shutdown + signal handling
- Logging to stdout for Supervisor/systemd

**File Structure**:
```
bin/operator-realtime-server         # Executable entry point
apps/Shell/Services/
  OperatorRealtimeService.php         # Connection + subscription logic
  OperatorRealtimeKpiProvider.php     # KPI data fetching + caching
  OperatorRealtimeCacheManager.php    # Redis cache wrapper
```

---

### 3. Live Cache with Stale-While-Revalidate

**Cache Keys**:
```
operator:kpi:{view}:{username}:{kpi_key}
- TTL: 30 seconds
- On fetch: if expired but within grace period (60s), return stale + refresh async
- On miss: call adapter, store with TTL, return result
```

**Redis Commands**:
```php
// Check cache
$cached = $redis->getex("operator:kpi:dashboard:lazy:summary", ["EX" => 30]);

// Update cache
$redis->set("operator:kpi:dashboard:lazy:summary", json_encode($data), ["EX" => 30]);

// Invalidate on manual refresh
$redis->del("operator:kpi:*:lazy:*");
```

**Stale-While-Revalidate Logic**:
```php
public function getKpiWithStale(string $view, string $username, string $kpiKey): array
{
    $key = "operator:kpi:{$view}:{$username}:{$kpiKey}";
    
    // Try cache
    $cached = $redis->getex($key, ["EX" => 30]);
    if ($cached) {
        return json_decode($cached, true);
    }
    
    // Cache miss or expired; fetch fresh
    $data = $this->fetchKpiFromAdapter($view, $username, $kpiKey);
    
    // Store with TTL
    $redis->set($key, json_encode($data), ["EX" => 30]);
    
    return $data;
}
```

---

### 4. Operator Preferences (Extended)

**New preference fields** in `operator_preferences`:
- `realtime_enabled` (boolean, default: true if WebSocket available)
- `realtime_refresh_interval_ms` (integer, default: 5000)
- `realtime_kpi_keys` (JSON array of KPI sections to push)

**Preferences UI** (new tab in `/u/{user}/preferences`):
- Toggle: "Enable real-time KPI updates"
- Slider: "Refresh interval" (1s - 30s, default 5s)
- Checkbox matrix: KPI sections (summary, critical, recent, etc.)

---

### 5. Browser Client Library

**File**: `public/assets/operator-realtime.js`

**Key Functions**:
```javascript
class OperatorRealtimeClient {
  constructor(username, view, csrfToken) {
    this.username = username;
    this.view = view;
    this.csrfToken = csrfToken;
    this.socket = null;
    this.isConnected = false;
  }

  connect() {
    const url = `ws://localhost:8001/operator/${this.username}/${this.view}?auth=${this.csrfToken}`;
    this.socket = new WebSocket(url);
    
    this.socket.onopen = () => this.onOpen();
    this.socket.onmessage = (e) => this.onMessage(e);
    this.socket.onerror = () => this.onError();
    this.socket.onclose = () => this.onClose();
  }

  onMessage(event) {
    const data = JSON.parse(event.data);
    
    if (data.type === 'kpi_update') {
      this.updateKpiDisplay(data.summary, data.critical_orders);
      this.disableAutoRefresh();
    }
  }

  updateKpiDisplay(summary, rows) {
    // Update DOM elements with IDs like #kpi-open-orders, #kpi-critical, etc.
    // Animate updates with fade-in transition
  }

  disableAutoRefresh() {
    // Remove <meta http-equiv="refresh"> tag
    const tag = document.querySelector('meta[http-equiv="refresh"]');
    if (tag) tag.remove();
  }

  onError() {
    console.debug('WebSocket error; falling back to auto-refresh');
    this.isConnected = false;
    // Page continues with auto-refresh (no-op)
  }
}
```

**Integration in operator view**:
```php
<script>
  const realtimeClient = new OperatorRealtimeClient(
    '<?php echo htmlspecialchars($data['username']); ?>',
    'dashboard',
    '<?php echo htmlspecialchars($csrfToken); ?>'
  );
  realtimeClient.connect();
</script>
```

---

## Implementation Phases

### Phase 8A: WebSocket Infrastructure (Week 1)
- [x] Plan architecture and protocol (this doc)
- [ ] Install Ratchet via Composer
- [ ] Create `OperatorRealtimeService` stub
- [ ] Create bin/operator-realtime-server skeleton
- [ ] Test Supervisor/systemd configuration

### Phase 8B: Cache Layer (Week 1-2)
- [ ] Create `OperatorRealtimeCacheManager` with Redis wrapper
- [ ] Implement stale-while-revalidate logic
- [ ] Add unit tests for cache behavior
- [ ] Add cache invalidation on manual refresh

### Phase 8C: KPI Push (Week 2)
- [ ] Implement `OperatorRealtimeKpiProvider`
- [ ] Wire adapters into KPI provider
- [ ] Add subscription tracking
- [ ] Implement server broadcast logic

### Phase 8D: Client & UI (Week 2-3)
- [ ] Create `public/assets/operator-realtime.js`
- [ ] Add preferences UI in preferences.php
- [ ] Wire preferences into client config
- [ ] Add CSRF token generation for WebSocket auth

### Phase 8E: Integration & Testing (Week 3)
- [ ] Integration tests for WebSocket lifecycle
- [ ] Fallback behavior tests (WebSocket unavailable)
- [ ] Load tests (500 concurrent connections)
- [ ] Manual smoke testing across all operator views

### Phase 8F: Deployment & Documentation (Week 3-4)
- [ ] Production deployment guide (systemd unit)
- [ ] Monitoring + alerting (connection count, latency)
- [ ] Operator documentation (preferences, troubleshooting)
- [ ] Commit and push Phase 8 complete

---

## Backward Compatibility

**If WebSocket server is DOWN**:
- Operator pages load normally with Phase 7 auto-refresh
- No console errors
- Periodic updates continue via `<meta http-equiv="refresh">`
- User is unaware of WebSocket failure

**If operator has WebSocket DISABLED in preferences**:
- Client never attempts connection
- Falls back to auto-refresh immediately
- No performance penalty

**If browser DOESN'T support WebSocket**:
- Connection fails silently
- Falls back to auto-refresh
- Works on IE9+ and all modern browsers

---

## Known Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| WebSocket server crashes | Supervisor restarts; fallback to auto-refresh |
| High memory (500 connections) | Connection pooling; per-operator deduplication |
| Cache invalidation lag | Stale-while-revalidate; manual refresh button |
| CSRF token expiry | Short-lived tokens; reconnect on 401 |
| Clock skew between server/client | Heartbeat validation; server-side timestamp |

---

## Success Metrics

1. ✅ WebSocket server stable under 500 concurrent connections
2. ✅ KPI updates delivered within 1s (99th percentile)
3. ✅ Zero console errors on fallback (WebSocket unavailable)
4. ✅ Auto-refresh still works (disabled if WebSocket connected)
5. ✅ All 432 tests passing (new tests added)
6. ✅ Operator layer routes unaffected

---

## Dependencies

- **Ratchet**: WebSocket server library (Composer)
- **Redis**: Cache backend (optional; fallback to in-memory cache)
- **Supervisor** or **systemd**: Process management

---

## References

- Ratchet: https://socketo.me/
- WebSocket RFC: https://tools.ietf.org/html/rfc6455
- Stale-While-Revalidate RFC: https://tools.ietf.org/html/rfc5861

---

**Next**: Phase 8A — Install dependencies and create WebSocket infrastructure skeleton.
