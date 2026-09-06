/**
 * Operator Realtime Client Library
 * 
 * Handles WebSocket connection to operator realtime server for KPI updates.
 * Provides graceful fallback to auto-refresh when WebSocket unavailable.
 * 
 * Features:
 * - Automatic connection management
 * - KPI display updates with animation
 * - Heartbeat/keepalive
 * - Graceful degradation (fallback to auto-refresh)
 * - Error recovery with exponential backoff
 * - localStorage state persistence
 * 
 * Usage:
 *   const client = new OperatorRealtimeClient('lazy', 'dashboard', 'csrf_token_xyz');
 *   client.connect();
 */

class OperatorRealtimeClient {
  /**
   * Constructor
   * 
   * @param {string} username Operator username
   * @param {string} view View identifier (dashboard, production, etc.)
   * @param {string} csrfToken Short-lived CSRF token for WebSocket auth
   * @param {Object} options Configuration options
   */
  constructor(username, view, csrfToken, options = {}) {
    this.username = username;
    this.view = view;
    this.csrfToken = csrfToken;
    
    this.options = {
      host: 'localhost',
      port: 8001,
      useSecure: false,
      reconnectIntervalMs: 3000,
      maxReconnectAttempts: 5,
      heartbeatIntervalMs: 30000,
      kpiUpdateIntervalMs: 5000,
      animationDurationMs: 300,
      ...options,
    };

    this.socket = null;
    this.isConnected = false;
    this.isConnecting = false;
    this.reconnectAttempts = 0;
    this.lastHeartbeat = Date.now();
    this.heartbeatTimeout = null;

    // State tracking
    this._state = {
      lastKpiUpdate: null,
      preferences: {
        refresh_interval_ms: 5000,
        kpi_keys: ['summary', 'critical_orders'],
      },
    };

    // Load persisted preferences
    this.loadPreferences();
  }

  /**
   * Connect to WebSocket server
   */
  connect() {
    if (this.isConnecting || this.isConnected) {
      console.debug('[OperatorRealtime] Already connecting or connected');
      return;
    }

    this.isConnecting = true;
    const protocol = this.options.useSecure ? 'wss' : 'ws';
    const wsUrl = `${protocol}://${this.options.host}:${this.options.port}/operator/${this.username}/${this.view}?auth=${this.csrfToken}`;

    console.debug(`[OperatorRealtime] Connecting to ${wsUrl}`);

    try {
      this.socket = new WebSocket(wsUrl);
      this.socket.onopen = (e) => this.onOpen(e);
      this.socket.onmessage = (e) => this.onMessage(e);
      this.socket.onerror = (e) => this.onError(e);
      this.socket.onclose = (e) => this.onClose(e);
    } catch (error) {
      console.error('[OperatorRealtime] Connection failed:', error);
      this.onError(error);
    }
  }

  /**
   * Handle WebSocket open event
   */
  onOpen(event) {
    this.isConnected = true;
    this.isConnecting = false;
    this.reconnectAttempts = 0;

    console.info('[OperatorRealtime] Connected to server');

    // Send subscription preferences
    this.send({
      type: 'subscribe',
      username: this.username,
      view: this.view,
      preferences: this._state.preferences,
    });

    // Start heartbeat
    this.startHeartbeat();

    // Disable auto-refresh tag (we have WebSocket)
    this.disableAutoRefresh();

    // Dispatch custom event
    this.dispatchEvent('realtime-connected', {
      username: this.username,
      view: this.view,
    });
  }

  /**
   * Handle incoming WebSocket message
   */
  onMessage(event) {
    try {
      const data = JSON.parse(event.data);

      switch (data.type) {
        case 'connected':
          this.onConnected(data);
          break;

        case 'subscribed':
          this.onSubscribed(data);
          break;

        case 'kpi_update':
          this.onKpiUpdate(data);
          break;

        case 'heartbeat':
          this.onHeartbeat(data);
          break;

        case 'pong':
          this.lastHeartbeat = Date.now();
          break;

        case 'error':
          console.error('[OperatorRealtime] Server error:', data.message);
          break;

        default:
          console.debug('[OperatorRealtime] Unknown message type:', data.type);
      }
    } catch (error) {
      console.error('[OperatorRealtime] Message parsing error:', error);
    }
  }

  /**
   * Handle WebSocket error event
   */
  onError(error) {
    console.error('[OperatorRealtime] Error:', error);
    this.isConnecting = false;
  }

  /**
   * Handle WebSocket close event
   */
  onClose(event) {
    this.isConnected = false;
    this.isConnecting = false;

    if (this.heartbeatTimeout) {
      clearTimeout(this.heartbeatTimeout);
    }

    console.warn('[OperatorRealtime] Disconnected from server');

    // Dispatch custom event
    this.dispatchEvent('realtime-disconnected', {
      code: event.code,
      reason: event.reason,
    });

    // Attempt reconnect with exponential backoff
    if (this.reconnectAttempts < this.options.maxReconnectAttempts) {
      const delay = Math.min(
        this.options.reconnectIntervalMs * Math.pow(2, this.reconnectAttempts),
        60000
      );
      this.reconnectAttempts++;

      console.info(`[OperatorRealtime] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
      setTimeout(() => this.connect(), delay);
    } else {
      console.error('[OperatorRealtime] Max reconnect attempts reached; falling back to auto-refresh');
      this.enableAutoRefresh();
    }
  }

  // ========== Message Handlers ==========

  /**
   * Handle 'connected' message from server
   */
  onConnected(data) {
    console.debug('[OperatorRealtime] Connected message:', data.message);
  }

  /**
   * Handle 'subscribed' message with subscription confirmation
   */
  onSubscribed(data) {
    console.debug('[OperatorRealtime] Subscribed with preferences:', data.preferences);
    this._state.preferences = data.preferences;
  }

  /**
   * Handle 'kpi_update' message and update DOM
   */
  onKpiUpdate(data) {
    this._state.lastKpiUpdate = data.timestamp;

    // Update summary KPIs
    if (data.summary) {
      this.updateKpiDisplay('summary', data.summary);
    }

    // Update critical orders
    if (data.critical_orders) {
      this.updateCriticalOrdersDisplay(data.critical_orders);
    }

    // Dispatch custom event
    this.dispatchEvent('kpi-updated', {
      timestamp: data.timestamp,
      summary: data.summary,
    });
  }

  /**
   * Handle 'heartbeat' message from server
   */
  onHeartbeat(data) {
    this.lastHeartbeat = Date.now();
  }

  // ========== DOM Update Methods ==========

  /**
   * Update KPI summary display with animation
   */
  updateKpiDisplay(section, kpiData) {
    if (section !== 'summary') {
      return;
    }

    const kpiElements = {
      'open_orders': '#kpi-open-orders',
      'critical': '#kpi-critical',
      'low_coverage': '#kpi-low-coverage',
      'coverage_pct': '#kpi-coverage-pct',
      'due_today': '#kpi-due-today',
    };

    for (const [key, selector] of Object.entries(kpiElements)) {
      const element = document.querySelector(selector);
      if (!element || !(key in kpiData)) {
        continue;
      }

      const newValue = kpiData[key];
      const currentValue = element.textContent;

      if (currentValue !== String(newValue)) {
        this.animateValueChange(element, String(newValue));
      }
    }
  }

  /**
   * Update critical orders display table
   */
  updateCriticalOrdersDisplay(orders) {
    const container = document.querySelector('#critical-orders-tbody');
    if (!container) {
      return;
    }

    // Build new rows HTML
    let html = '';
    for (const order of orders.slice(0, 20)) {
      html += `
        <tr class="critical-order-row" data-order-id="${order.id}">
          <td>${this.escapeHtml(order.id)}</td>
          <td>${this.escapeHtml(order.part)}</td>
          <td>${this.escapeHtml(order.due)}</td>
          <td><span class="status-badge status-critical">Critical</span></td>
        </tr>
      `;
    }

    // Update with fade transition
    container.style.opacity = '0.5';
    setTimeout(() => {
      container.innerHTML = html;
      container.style.opacity = '1';
    }, this.options.animationDurationMs / 2);
  }

  /**
   * Animate value change with visual feedback
   */
  animateValueChange(element, newValue) {
    const oldValue = element.textContent;

    // Flash animation
    element.classList.remove('kpi-updated');
    void element.offsetWidth; // Trigger reflow
    element.textContent = newValue;
    element.classList.add('kpi-updated');

    setTimeout(() => {
      element.classList.remove('kpi-updated');
    }, this.options.animationDurationMs);
  }

  // ========== Control Methods ==========

  /**
   * Send message to server
   */
  send(data) {
    if (!this.isConnected) {
      console.warn('[OperatorRealtime] Not connected; message not sent');
      return;
    }

    try {
      this.socket.send(JSON.stringify(data));
    } catch (error) {
      console.error('[OperatorRealtime] Send error:', error);
    }
  }

  /**
   * Request manual refresh of KPI data
   */
  refresh() {
    this.send({ type: 'refresh' });
  }

  /**
   * Disconnect from server
   */
  disconnect() {
    if (this.socket) {
      this.socket.close();
    }
  }

  /**
   * Start heartbeat/keepalive timer
   */
  startHeartbeat() {
    this.lastHeartbeat = Date.now();

    this.heartbeatTimeout = setInterval(() => {
      const timeSinceLastHeartbeat = Date.now() - this.lastHeartbeat;

      if (timeSinceLastHeartbeat > this.options.heartbeatIntervalMs * 2) {
        console.warn('[OperatorRealtime] Heartbeat timeout; reconnecting');
        this.disconnect();
        return;
      }

      this.send({ type: 'ping' });
    }, this.options.heartbeatIntervalMs);
  }

  /**
   * Disable auto-refresh meta tag (we have WebSocket)
   */
  disableAutoRefresh() {
    const refreshTag = document.querySelector('meta[http-equiv="refresh"]');
    if (refreshTag) {
      refreshTag.remove();
      console.debug('[OperatorRealtime] Disabled meta refresh tag');
    }
  }

  /**
   * Enable auto-refresh meta tag (fallback)
   */
  enableAutoRefresh() {
    const existing = document.querySelector('meta[http-equiv="refresh"]');
    if (existing) {
      return; // Already enabled
    }

    const interval = this._state.preferences.refresh_interval_ms / 1000;
    const tag = document.createElement('meta');
    tag.httpEquiv = 'refresh';
    tag.content = String(interval);
    document.head.appendChild(tag);

    console.debug('[OperatorRealtime] Enabled meta refresh tag:', interval + 's');
  }

  // ========== Preferences ==========

  /**
   * Load preferences from localStorage
   */
  loadPreferences() {
    const key = `operator_realtime_prefs:${this.username}:${this.view}`;
    const stored = localStorage.getItem(key);

    if (stored) {
      try {
        this._state.preferences = JSON.parse(stored);
      } catch (error) {
        console.error('[OperatorRealtime] Preference load error:', error);
      }
    }
  }

  /**
   * Save preferences to localStorage
   */
  savePreferences() {
    const key = `operator_realtime_prefs:${this.username}:${this.view}`;
    localStorage.setItem(key, JSON.stringify(this._state.preferences));
  }

  /**
   * Update preferences (e.g., refresh interval)
   */
  setPreferences(prefs) {
    this._state.preferences = {
      ...this._state.preferences,
      ...prefs,
    };
    this.savePreferences();

    // Re-subscribe with new preferences
    if (this.isConnected) {
      this.send({
        type: 'subscribe',
        username: this.username,
        view: this.view,
        preferences: this._state.preferences,
      });
    }
  }

  // ========== Utility Methods ==========

  /**
   * Dispatch custom event for subscribers
   */
  dispatchEvent(eventName, detail) {
    const event = new CustomEvent(eventName, { detail });
    document.dispatchEvent(event);
  }

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  /**
   * Get connection status
   */
  getStatus() {
    return {
      connected: this.isConnected,
      connecting: this.isConnecting,
      reconnectAttempts: this.reconnectAttempts,
      lastKpiUpdate: this._state.lastKpiUpdate,
      lastHeartbeat: this.lastHeartbeat,
    };
  }
}

// Export for use in views
window.OperatorRealtimeClient = OperatorRealtimeClient;
