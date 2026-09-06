# Operator Realtime Deployment Guide

Complete deployment and operational guide for the Operator Realtime WebSocket server.

## Architecture

The Operator Realtime WebSocket server provides real-time KPI updates to operator browser clients.

```
Operator Browser
     │
     │ WebSocket (ws://localhost:8001)
     ▼
┌─────────────────────────┐
│ bin/operator-realtime   │  (PHP WebSocket server via Ratchet)
│ OperatorRealtimeService │
└─────────────────────────┘
     │
     ├─ OperatorRealtimeKpiProvider  (KPI extraction from adapters)
     ├─ OperatorRealtimeCacheManager (Stale-while-revalidate cache)
     └─ Manufacturing Adapters       (Coverage, QC, Dispatch, etc.)
```

**Graceful Fallback**: If WebSocket server unavailable, operator views automatically fall back to meta refresh tag (Phase 7 behavior).

## Installation

### Option 1: Supervisor (Recommended for shared hosting)

**Install Supervisor** (if needed):
```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# macOS
brew install supervisor
```

**Install operator-realtime configuration**:
```bash
sudo cp etc/supervisor/operator-realtime.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl add operator-realtime
```

**Start the server**:
```bash
sudo supervisorctl start operator-realtime
```

**Check status**:
```bash
sudo supervisorctl status operator-realtime
```

**View logs**:
```bash
tail -f /var/log/supervisor/operator-realtime.log
```

### Option 2: systemd (Recommended for modern Linux)

**Install unit file**:
```bash
sudo cp etc/systemd/operator-realtime.service /etc/systemd/system/
sudo systemctl daemon-reload
```

**Start the server**:
```bash
sudo systemctl enable operator-realtime
sudo systemctl start operator-realtime
```

**Check status**:
```bash
sudo systemctl status operator-realtime
```

**View logs**:
```bash
sudo journalctl -u operator-realtime -f
```

### Option 3: Manual (Development only)

**Start server directly**:
```bash
php bin/operator-realtime-server --port=8001 --host=127.0.0.1
```

## Configuration

### Environment Variables

Optional configuration via `/etc/sbaio/realtime.env` (for systemd) or supervisor environment:

```bash
# Server listening port (default: 8001)
OPERATOR_REALTIME_PORT=8001

# Server listening host (default: 127.0.0.1)
OPERATOR_REALTIME_HOST=127.0.0.1

# Show PHP deprecation notices from Ratchet/vendor code (default: 0)
OPERATOR_REALTIME_SHOW_DEPRECATIONS=0

# Cache backend (redis or memory, default: memory)
REALTIME_CACHE_BACKEND=memory

# Cache TTL in seconds (default: 30)
REALTIME_CACHE_TTL=30

# KPI update broadcast interval in seconds (default: 5)
REALTIME_BROADCAST_INTERVAL=5
```

### Port Configuration

- **Default**: `8001`
- **Override**: Pass `--port=PORT` to `bin/operator-realtime-server`
- **Note**: Must be accessible from operator browser (configure firewall/reverse proxy as needed)

### TLS/SSL

For production deployments:

1. **Nginx reverse proxy** (recommended):
```nginx
server {
    listen 443 ssl http2;
    server_name realtime.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_request_buffering off;
    }
}
```

2. **Browser connection** (via operator view):
   - Client library detects `useSecure` option
   - Uses `wss://` for TLS connections
   - CSRF token passed in query string

## Monitoring

### Health Checks

**Systemd**:
```bash
sudo systemctl status operator-realtime
```

**Supervisor**:
```bash
sudo supervisorctl status operator-realtime
```

**Manual check** (if running on localhost):
```bash
curl -i http://localhost:8001/
```

**WebSocket smoke test**:
```bash
scripts/runtime/operator_realtime_smoke.sh
```

### Logs

**Supervisor logs**:
```bash
tail -f /var/log/supervisor/operator-realtime.log
tail -f /var/log/supervisor/operator-realtime-error.log
```

**Systemd logs**:
```bash
# Real-time
sudo journalctl -u operator-realtime -f

# Last 100 lines
sudo journalctl -u operator-realtime -n 100

# Since specific time
sudo journalctl -u operator-realtime --since "1 hour ago"
```

### Metrics

The server logs connection counts and subscriber distribution:

```
[INFO] Operator Realtime Server listening on 127.0.0.1:8001
[INFO] Connections: 42 | Subscribers by view: dashboard=15 production=12 dispatch=15
[INFO] Broadcast: dashboard 15 clients, 3 KPI keys, 8.2ms
```

### Load Testing

To verify production readiness:

```bash
# Test concurrent connections (requires websocket-client Python library)
python3 -c "
import websocket
import threading

def client(i):
    ws = websocket.create_connection('ws://localhost:8001/operator/user_$i/dashboard?auth=token')
    ws.send('{\"type\": \"ping\"}')
    ws.close()

threads = [threading.Thread(target=client, args=(i,)) for i in range(100)]
for t in threads:
    t.start()
for t in threads:
    t.join()

print('100 concurrent connections successful')
"
```

## Troubleshooting

### Server won't start

**Check port availability**:
```bash
# Supervisor: Check logs
sudo tail -f /var/log/supervisor/operator-realtime-error.log

# Systemd: Check journal
sudo journalctl -u operator-realtime -n 50
```

**Port already in use**:
```bash
# Find process using port 8001
sudo lsof -i :8001

# Kill process or use different port
sudo kill <PID>
# OR
php bin/operator-realtime-server --port=8002
```

### Operator clients not receiving updates

1. **Verify server is running**:
   ```bash
   curl -i http://localhost:8001/
   ```

2. **Check firewall**:
   ```bash
   sudo iptables -L | grep 8001
   # Allow if needed
   sudo ufw allow 8001
   ```

3. **Check browser console** (operator view):
   - Open DevTools (F12)
   - Check WebSocket connection in Network tab
   - Look for error messages in Console

4. **Enable debug logging** (optional):
   - Set `REALTIME_DEBUG=1` in environment
   - Set `OPERATOR_REALTIME_SHOW_DEPRECATIONS=1` if you need vendor PHP 8.5 deprecation output
   - Review logs for connection/message errors

### High memory usage

1. **Check connection count**:
   - `sudo supervisorctl status` or `systemctl status operator-realtime`
   - Review logs for connection count metrics

2. **Reduce cache TTL** if needed:
   - Lower `REALTIME_CACHE_TTL` (default 30s)
   - Increases database queries but reduces memory

3. **Configure limits**:
   - Supervisor: `numprocs=1` (ensure single instance)
   - Systemd: `LimitNOFILE=65536` (file descriptor limit)

### Browser receives stale data

**Expected behavior**: Operator realtime is optional enhancement.

- Cache hit → Updates via WebSocket (real-time)
- Cache miss → Falls back to auto-refresh (Phase 7 behavior)
- Server down → Falls back to auto-refresh (graceful degradation)

**Verify cache is working**:
```bash
# Monitor cache stats in server logs
tail -f /var/log/supervisor/operator-realtime.log | grep "cache"
```

## Rollback

If issues arise, disable WebSocket updates:

**Option 1**: Stop server without removing config
```bash
sudo supervisorctl stop operator-realtime
# OR
sudo systemctl stop operator-realtime
```

Operator views automatically fall back to meta refresh (Phase 7).

**Option 2**: Remove WebSocket script reference from view
```html
<!-- Operator dashboard view -->
<script src="/assets/operator-realtime.js"></script>
<!-- Comment out this line to disable realtime updates -->
```

**Option 3**: Remove config
```bash
# Supervisor
sudo rm /etc/supervisor/conf.d/operator-realtime.conf
sudo supervisorctl reread

# Systemd
sudo systemctl disable operator-realtime
sudo rm /etc/systemd/system/operator-realtime.service
sudo systemctl daemon-reload
```

## Performance Tuning

### For high-concurrency environments (1000+ operators)

1. **Increase file descriptors**:
```bash
# Systemd
echo "LimitNOFILE=1000000" | sudo tee -a /etc/systemd/system/operator-realtime.service
sudo systemctl daemon-reload
sudo systemctl restart operator-realtime

# Supervisor: Add to [program:operator-realtime]
environment=ULIMIT_NOFILE=1000000
```

2. **Use Redis cache**:
```bash
# Set environment
export REALTIME_CACHE_BACKEND=redis

# Redis must be running
redis-server
```

3. **Scale to multiple instances** (advanced):
```bash
# Run multiple servers on different ports with load balancing
php bin/operator-realtime-server --port=8001
php bin/operator-realtime-server --port=8002
php bin/operator-realtime-server --port=8003

# Configure nginx to distribute connections
upstream realtime {
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
    server 127.0.0.1:8003;
}
```

## Security

1. **CSRF Protection**: Token validated on WebSocket connect
2. **Session Validation**: Session required to connect
3. **Rate Limiting**: Not implemented (add via nginx if needed)
4. **TLS/SSL**: Use nginx reverse proxy for encryption

## Maintenance

### Regular monitoring
```bash
# Weekly: Check logs for errors
sudo journalctl -u operator-realtime --since "7 days ago" | grep ERROR

# Monthly: Review performance metrics
sudo journalctl -u operator-realtime --since "1 month ago" | grep "Connections:"
```

### Updates

When updating the server code:

1. Stop the server
2. Deploy new code to `bin/operator-realtime-server`
3. Restart the server
4. Verify in logs that connections are accepted

**No downtime required**: Browsers automatically retry and fall back to auto-refresh during updates.

## Support

For issues or feature requests, see:
- [docs/operator-layer-phase8-realtime-updates.md](operator-layer-phase8-realtime-updates.md) - Technical specification
- [docs/runtime/operator/operator-workspace-user-guide.md](runtime/operator/operator-workspace-user-guide.md) - Operator usage
- GitHub Issues: https://github.com/lazydeepak/Susankhya/issues
